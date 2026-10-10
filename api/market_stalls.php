<?php
/**
 * GET /api/market_stalls.php?market=ID[&status=][&section=&limit=]
 *
 * The individual pitches of one market, at their real coordinates. Powers
 * the stall map and the stall list beside it.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$marketId = (int) ($_GET['market'] ?? 0);

if ($marketId <= 0) {
    jsonOut(['error' => 'No market was given'], 400);
}

$market = Database::one(
    'SELECT m.*, d.name AS division_name, d.code AS div_code, d.mayor
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE m.id = ? LIMIT 1',
    [$marketId]
);

if (!$market) {
    jsonOut(['error' => 'That market is not in the register'], 404);
}

/* ---- filters ---- */
$where  = ['market_id = :id'];
$params = ['id' => $marketId];

$status  = (string) ($_GET['status'] ?? '');
$section = (string) ($_GET['section'] ?? '');

$allowed = ['vacant', 'occupied', 'reserved', 'under-maintenance'];
if ($status !== '' && in_array($status, $allowed, true)) {
    $where[] = 'status = :st';
    $params['st'] = $status;
}
if ($section !== '') {
    $where[] = 'section = :sec';
    $params['sec'] = $section;
}

$limit = min(4000, max(50, (int) ($_GET['limit'] ?? 2000)));

$stalls = Database::all(
    'SELECT id, space_code, section, lat, lng, size_sqm, status,
            monthly_rent_ugx, has_water, has_power, near_toilet, step_free
       FROM market_spaces
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY FIELD(status, "vacant", "reserved", "under-maintenance", "occupied"), space_code
      LIMIT ' . $limit,
    $params
);

/* ---- counts, always over the whole market, so the legend stays stable ---- */
$counts = [];
foreach (Database::all(
    'SELECT status, COUNT(*) AS n FROM market_spaces WHERE market_id = ? GROUP BY status',
    [$marketId]
) as $c) {
    $counts[$c['status']] = (int) $c['n'];
}

$sections = Database::column(
    'SELECT DISTINCT section FROM market_spaces WHERE market_id = ? AND section IS NOT NULL
      ORDER BY section',
    [$marketId]
);

/* An open request shades a pitch the way a live offer does. */
$requested = [];
foreach (Database::all(
    "SELECT space_id, COUNT(*) AS n FROM stall_requests
      WHERE market_id = ? AND status IN ('pending','reviewing')
      GROUP BY space_id",
    [$marketId]
) as $r) {
    $requested[(int) $r['space_id']] = (int) $r['n'];
}

foreach ($stalls as &$s) {
    $s['id']               = (int) $s['id'];
    $s['size_sqm']         = (float) $s['size_sqm'];
    $s['monthly_rent_ugx'] = (int) $s['monthly_rent_ugx'];
    $s['has_water']        = (int) $s['has_water'];
    $s['has_power']        = (int) $s['has_power'];
    $s['near_toilet']      = (int) $s['near_toilet'];
    $s['step_free']        = (int) $s['step_free'];
    $s['requested']        = (int) ($requested[(int) $s['id']] ?? 0);
    $s['lat']              = (float) $s['lat'];
    $s['lng']              = (float) $s['lng'];
}
unset($s);

jsonOut([
    'market' => [
        'id'         => (int) $market['id'],
        'code'       => $market['code'],
        'name'       => $market['name'],
        'division'   => $market['division_name'],
        'div_code'   => $market['div_code'],
        'kind'       => $market['category'] === 'public' ? 'KCCA public market' : 'Private market',
        'parish'     => $market['parish'],
        'capacity'   => (int) $market['capacity'],
        'registered' => (int) $market['registered_vendors'],
        'vacant'     => (int) $market['vacant_spaces'],
        'lat'        => (float) $market['lat'],
        'lng'        => (float) $market['lng'],
        'status'     => $market['status'],
        'opened'     => (int) $market['opened_year'],
        'transport'  => $market['transport_stand'],
        'admin'      => $market['admin_name'],
        'facilities' => [
            'water'   => (int) $market['water_available'],
            'power'   => (int) $market['power_available'],
            'toilets' => (int) $market['toilets'],
            'waste'   => (int) $market['waste_collection'],
            'storage' => (int) $market['storage_rooms'],
            'cold'    => (int) $market['cold_chain'],
            'ramps'   => (int) $market['accessible_ramps'],
        ],
    ],
    'counts'    => $counts,
    'sections'  => $sections,
    'stalls'    => $stalls,
    'truncated' => count($stalls) >= $limit,
]);