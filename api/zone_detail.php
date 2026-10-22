<?php
/**
 * GET /api/zone_detail.php?id=3
 * Returns the full congestion profile for one trade zone: the score
 * breakdown, the vendors affected, enforcement history and the ranked
 * markets the zone's vendors could be relocated to.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
/* Returns the traders of a zone by name, with telephone and disability status. */
requirePerm('report');
requireScreenApi('congestion');

$zoneId = qInt('id', 0);
if ($zoneId <= 0) {
    jsonOut(['error' => 'A zone id is required'], 400);
}

$zone = Database::one(
    'SELECT z.*, d.code AS div_code, d.name AS division_name, d.mayor AS division_mayor
       FROM trade_zones z JOIN divisions d ON d.id = z.division_id
      WHERE z.id = ? LIMIT 1',
    [$zoneId]
);

if (!$zone) {
    jsonOut(['error' => 'No such trade zone'], 404);
}

$score = ZoneScoringEngine::score($zone);

/* ---------------- Vendors in this zone ---------------- */
$vendors = Database::all(
    "SELECT id, vendor_code, full_name, business_category, gender, status,
            monthly_turnover_ugx, startup_capital_ugx, household_size, dependants,
            is_person_with_disability, is_single_parent, is_youth,
            has_trading_licence, licence_class, started_year, phone
       FROM vendors WHERE current_zone_id = ?
      ORDER BY full_name LIMIT 60",
    [$zoneId]
);

$vendorStats = Database::one(
    "SELECT COUNT(*) AS total,
            SUM(gender = 'female') AS women,
            SUM(is_person_with_disability) AS pwd,
            SUM(is_single_parent) AS single_parents,
            SUM(is_youth) AS youth,
            SUM(has_trading_licence) AS licensed,
            SUM(is_veteran_ex_enforcee) AS ex_enforcee,
            ROUND(AVG(monthly_turnover_ugx)) AS avg_turnover,
            SUM(dependants) AS total_dependants
       FROM vendors WHERE current_zone_id = ?",
    [$zoneId]
);

/* Category mix within the zone */
$categoryMix = Database::all(
    "SELECT business_category, COUNT(*) AS n FROM vendors
      WHERE current_zone_id = ? GROUP BY business_category ORDER BY n DESC",
    [$zoneId]
);

/* ---------------- Enforcement history ---------------- */
$enforcement = Database::all(
    'SELECT * FROM enforcement_actions WHERE zone_id = ? ORDER BY issued_date DESC, id DESC',
    [$zoneId]
);

/* ---------------- Ranked destinations for this zone ---------------- */
$markets = candidateMarkets(150);
$ranked  = [];

if ($markets !== [] && $vendors !== []) {
    // Score each candidate market against a representative sample of this
    // zone's vendors, then take the mean. Sampling keeps the response fast
    // while remaining faithful to the zone's actual trade composition.
    $sample = array_slice($vendors, 0, 25);

    foreach ($markets as $m) {
        $sum = 0.0;
        foreach ($sample as $v) {
            $v['current_lat'] = $zone['lat'];
            $v['current_lng'] = $zone['lng'];
            $c = RelocationEngine::criteriaFor($v, $m);
            $t = 0.0; $w = 0.0;
            foreach ($c as $item) { $t += $item['raw'] * $item['weight']; $w += $item['weight']; }
            $sum += $w > 0 ? $t / $w : 0.0;
        }
        $avg = count($sample) > 0 ? $sum / count($sample) : 0.0;

        $ranked[] = [
            'market' => [
                'id' => (int) $m['id'], 'name' => $m['name'], 'code' => $m['code'],
                'division' => $m['division_name'], 'category' => $m['category'],
                'vacant_spaces' => (int) $m['vacant_spaces'],
                'capacity' => (int) $m['capacity'],
                'lat' => lnglat($m['lat']), 'lng' => lnglat($m['lng']),
                'data_basis' => $m['data_basis'],
            ],
            'score'     => round($avg, 1),
            'distance'  => round(RelocationEngine::haversineKm(
                (float) $zone['lat'], (float) $zone['lng'],
                (float) $m['lat'], (float) $m['lng']
            ), 2),
        ];
    }
    usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);
    $ranked = array_slice($ranked, 0, 8);
}

/* ---------------- Official reference figures for this division ---------------- */
$divisionTotals = Database::one(
    'SELECT COUNT(*) AS markets, COALESCE(SUM(vacant_spaces),0) AS vacant
       FROM markets WHERE division_id = ?',
    [(int) $zone['division_id']]
);

$daysLeft = daysUntil($zone['deadline_date']);

jsonOut([
    'zone' => [
        'id' => (int) $zone['id'],
        'code' => $zone['code'],
        'name' => $zone['name'],
        'division' => $zone['division_name'],
        'division_mayor' => $zone['division_mayor'],
        'parish' => $zone['parish'],
        'road' => $zone['road_or_feature'],
        'type' => humanise($zone['zone_type']),
        'lat' => lnglat($zone['lat']),
        'lng' => lnglat($zone['lng']),
        'status' => humanise($zone['status']),
        'tone' => statusTone($zone['status']),
        'estimated_vendors' => (int) $zone['estimated_vendors'],
        'footpath' => (float) $zone['footpath_obstruction_pct'],
        'carriage' => (float) $zone['carriageway_encroachment_pct'],
        'months_occupied' => (int) $zone['months_occupied'],
        'complaints_30d' => (int) $zone['complaints_30d'],
        'strategic_corridor' => (bool) $zone['strategic_corridor'],
        'near_public_asset' => $zone['near_public_asset'],
        'peak_days' => $zone['peak_days'],
        'peak_hours' => $zone['peak_hours'],
        'category_mix' => $zone['category_mix'],
        'survey_date' => fmtDate($zone['survey_date']),
        'notified_date' => fmtDate($zone['notified_date']),
        'deadline_date' => fmtDate($zone['deadline_date']),
        'days_left' => $daysLeft,
        'deadline_note' => $daysLeft === null ? 'No deadline set'
            : ($daysLeft < 0 ? 'Deadline passed ' . abs($daysLeft) . ' days ago'
                             : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' remaining'),
    ],
    'score' => $score,
    'vendors' => $vendors,
    'vendor_stats' => $vendorStats,
    'category_mix' => $categoryMix,
    'enforcement' => $enforcement,
    'destinations' => $ranked,
    'division_totals' => $divisionTotals,
]);