<?php
/**
 * GET /api/vendor_options.php?vendor_id=42&limit=6
 *
 * Runs the relocation engine for one vendor and returns the ranked markets
 * with the full score breakdown and the reasoning behind each. Powers the
 * Relocation Engine page and the "offer space" action.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
/* Names a trader's equity profile (single parent, disability, dependants), so it
   is the matching desk's tool and not every signed-in account's. */
requirePerm('allocate');
requireScreenApi('relocation');

$vendorId = qInt('vendor_id', 0);
$limit    = qInt('limit', 5, 1, 12);
$division = q('division');

if ($vendorId <= 0) {
    jsonOut(['error' => 'A vendor id is required'], 400);
}

$vendor = vendorForMatching($vendorId);
if (!$vendor) {
    jsonOut(['error' => 'No such vendor in the register'], 404);
}

/* Candidates: markets with space, optionally narrowed to one division. */
$markets = candidateMarkets(400);
if ($division !== '') {
    $markets = array_values(array_filter(
        $markets,
        static fn(array $m) => $m['div_code'] === $division
    ));
}

$ranked = RelocationEngine::rankForVendor($vendor, $markets, $limit);

/* Space-level detail for the top candidate, so the officer can name a pitch. */
$top = $ranked[0] ?? null;
$topSpaces = [];
if ($top) {
    $topSpaces = Database::all(
        "SELECT id, space_code, section, size_sqm, monthly_rent_ugx, has_water,
                has_power, near_toilet, step_free
           FROM market_spaces
          WHERE market_id = ? AND status = 'vacant'
          ORDER BY step_free DESC, has_water DESC, near_toilet DESC, space_code
          LIMIT 8",
        [(int) $top['market']['id']]
    );
}

/* Also show the alternative pitches the vendor already holds an offer for. */
$existing = Database::all(
    "SELECT a.*, m.name AS market_name, s.space_code
       FROM allocations a
       JOIN markets m ON m.id = a.market_id
       LEFT JOIN market_spaces s ON s.id = a.market_space_id
      WHERE a.vendor_id = ?
      ORDER BY a.created_at DESC LIMIT 5",
    [$vendorId]
);

/* Division and citywide headroom, for the affordability framing. */
$context = Database::one(
    "SELECT
       (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public')  AS public_vacant,
       (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private') AS private_vacant,
       (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE division_id = ?)    AS division_vacant",
    [(int) $vendor['division_id']]
);

jsonOut([
    'vendor' => [
        'id' => (int) $vendor['id'],
        'vendor_code' => $vendor['vendor_code'],
        'full_name' => $vendor['full_name'],
        'business_category' => humanise($vendor['business_category']),
        'description' => tradeDescription($vendor['business_category']),
        'division' => $vendor['division_name'],
        'parish' => $vendor['parish'],
        'zone' => $vendor['zone_name'],
        'lat' => $vendor['current_lat'] !== null ? lnglat($vendor['current_lat']) : null,
        'lng' => $vendor['current_lng'] !== null ? lnglat($vendor['current_lng']) : null,
        'status' => humanise($vendor['status']),
        'status_tone' => statusTone($vendor['status']),
        'gender' => $vendor['gender'],
        'turnover' => ugx((int) $vendor['monthly_turnover_ugx']),
        'capital' => ugx((int) $vendor['startup_capital_ugx']),
        'household_size' => (int) $vendor['household_size'],
        'dependants' => (int) $vendor['dependants'],
        'is_person_with_disability' => (bool) $vendor['is_person_with_disability'],
        'is_single_parent' => (bool) $vendor['is_single_parent'],
        'is_youth' => (bool) $vendor['is_youth'],
        'has_trading_licence' => (bool) $vendor['has_trading_licence'],
        'licence_class' => humanise($vendor['licence_class']),
        'started_year' => (int) $vendor['started_year'],
        'years_trading' => max(0, (int) date('Y') - (int) $vendor['started_year']),
        'phone' => $vendor['phone'],
    ],
    'options' => array_map(static function (array $o): array {
        return [
            'rank'      => $o['rank'],
            'score'     => $o['score'],
            'distance'  => round($o['distance'], 2),
            'market'    => $o['market'],
            'criteria'  => $o['criteria'],
            'reasons'   => $o['reasons'],
        ];
    }, $ranked),
    'top_spaces' => $topSpaces,
    'existing' => $existing,
    'context' => $context,
    'weights' => RELOCATION_WEIGHTS,
]);