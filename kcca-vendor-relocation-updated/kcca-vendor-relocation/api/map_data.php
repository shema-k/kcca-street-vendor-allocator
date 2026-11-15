<?php
/**
 * GET  /api/map_data.php?division=CTR&layers=zones,markets,links
 *
 * Returns everything the Kampala map needs in one payload: division
 * outlines, trade zones with their congestion score, markets with their
 * vacant count, and the relocation links that have been proposed.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireSession();

$division = q('division');
$layers   = array_filter(array_map('trim', explode(',', q('layers', 'zones,markets,links'))));
$linkLimit = qInt('links', 150, 0, 600);

$payload = ['engine' => 'kcca-vrsp', 'generated' => date('c')];

/* ---------------- Division outlines ---------------- */
$sql = "SELECT code, name, mayor, town_clerk, area_sqkm, parishes, lc1_wards,
               population, lat, lng FROM divisions";
$params = [];
if ($division !== '') {
    $sql .= " WHERE code = :div";
    $params['div'] = $division;
}
$sql .= " ORDER BY name";

$divisions = [];
foreach (Database::all($sql, $params) as $d) {
    $d['polygon']       = divisionOutline($d['code'], (float) $d['lat'], (float) $d['lng']);
    $d['area']          = num((float) $d['area_sqkm']) . ' km²';
    $d['population_txt'] = num((int) $d['population']);
    $d['color']         = divisionColor($d['code']);
    $divisions[] = $d;
}
$payload['divisions'] = $divisions;

/* ---------------- Trade zones with congestion score ---------------- */
if (in_array('zones', $layers, true)) {
    $zones = scoredZones($division);
    $payload['zones'] = array_map(static function (array $z): array {
        return [
            'id'                => (int) $z['id'],
            'code'              => $z['code'],
            'name'              => $z['name'],
            'division'          => $z['division_name'],
            'div_code'          => $z['div_code'],
            'lat'               => lnglat($z['lat']),
            'lng'               => lnglat($z['lng']),
            'estimated_vendors' => (int) $z['estimated_vendors'],
            'score'             => $z['score'],
            'band'              => $z['band'],
            'footpath'          => (float) $z['footpath_obstruction_pct'],
            'carriage'          => (float) $z['carriageway_encroachment_pct'],
            'status'            => humanise($z['status']),
            'link'              => '../pages/congestion.php?zone=' . (int) $z['id'],
        ];
    }, $zones);

    $payload['divisionScores'] = [];
    foreach ($zones as $z) {
        $c = $z['div_code'];
        if (!isset($payload['divisionScores'][$c]) || $z['score'] > $payload['divisionScores'][$c]) {
            $payload['divisionScores'][$c] = $z['score'];
        }
    }
}

/* ---------------- Markets ---------------- */
if (in_array('markets', $layers, true)) {
    $sql = "SELECT id, name, code, category, division_id, lat, lng, capacity,
                   registered_vendors, vacant_spaces, data_basis, status
              FROM markets WHERE vacant_spaces > 0";
    $params = [];
    if ($division !== '') {
        $sql .= " AND division_id = (SELECT id FROM divisions WHERE code = :div)";
        $params['div'] = $division;
    }
    $sql .= " ORDER BY vacant_spaces DESC";
    $payload['markets'] = array_map(static function (array $m): array {
        return [
            'id'                => (int) $m['id'],
            'name'              => $m['name'],
            'code'              => $m['code'],
            'category'          => $m['category'],
            'lat'               => lnglat($m['lat']),
            'lng'               => lnglat($m['lng']),
            'capacity'          => (int) $m['capacity'],
            'registered_vendors'=> (int) $m['registered_vendors'],
            'vacant_spaces'     => (int) $m['vacant_spaces'],
            'data_basis'        => $m['data_basis'],
            'status'            => humanise($m['status']),
        ];
    }, Database::all($sql, $params));
}

/* ---------------- Proposed relocation links ---------------- */
if (in_array('links', $layers, true) && $linkLimit > 0) {
    $sql = "SELECT a.id, a.reference, a.total_score, a.status,
                   z.name AS zone_name, z.lat AS zone_lat, z.lng AS zone_lng,
                   m.name AS market_name, m.lat AS market_lat, m.lng AS market_lng,
                   v.full_name AS vendor_name, v.vendor_code
              FROM allocations a
              JOIN vendors v ON v.id = a.vendor_id
              LEFT JOIN trade_zones z ON z.id = v.current_zone_id
              JOIN markets m ON m.id = a.market_id
             WHERE a.rank = 1 AND z.id IS NOT NULL";
    $params = [];
    if ($division !== '') {
        $sql .= " AND d.code = :div";
        $params['div'] = $division;
    }
    $sql .= " ORDER BY a.total_score DESC LIMIT " . $linkLimit;

    /* allocations join divisions only through the vendor */
    $sql = str_replace('AND d.code = :div', 'AND v.division_id = (SELECT id FROM divisions WHERE code = :div)', $sql);

    $payload['links'] = array_map(static fn(array $a): array => [
        'label' => $a['vendor_name'] . ' → ' . $a['market_name'],
        'score' => (float) $a['total_score'],
        'status'=> humanise($a['status']),
        'from'  => [lnglat($a['zone_lat']), lnglat($a['zone_lng'])],
        'to'    => [lnglat($a['market_lat']), lnglat($a['market_lng'])],
        'color' => $a['status'] === 'accepted' || $a['status'] === 'occupied' ? '#0A6339' : '#DC351D',
    ], Database::all($sql, $params));
}

/* ---------------- Completed placements ---------------- */
if (in_array('placements', $layers, true)) {
    $sql = "SELECT v.full_name, m.lat, m.lng, m.name
              FROM allocations a
              JOIN vendors v ON v.id = a.vendor_id
              JOIN markets m ON m.id = a.market_id
             WHERE a.status IN ('accepted','occupied')";
    $params = [];
    if ($division !== '') {
        $sql .= " AND v.division_id = (SELECT id FROM divisions WHERE code = :div)";
        $params['div'] = $division;
    }
    $sql .= " ORDER BY a.decided_at DESC LIMIT 200";
    $payload['placements'] = array_map(static fn(array $r): array => [
        'lat' => lnglat($r['lat']), 'lng' => lnglat($r['lng']),
        'label' => $r['full_name'] . ' — ' . $r['name'],
    ], Database::all($sql, $params));
}

/* ---------------- Citywide headline ---------------- */
$payload['totals'] = headlineStats();

jsonOut($payload);