<?php
/**
 * City Situation Map — the operational map of Kampala Capital City.
 * Layers: affected sites, markets with space, relocation links, placements,
 * division boundaries. Full-screen, filters, and click-through to records.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f    = facts();
$vac  = $f['vacancy'];

$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$band     = (string) ($_GET['band'] ?? '');
$layerSet = (string) ($_GET['layers'] ?? 'zones,markets,links,placements');

/* ---------------- Summary figures for the header ---------------- */
$divName = 'All five divisions';
if ($division !== '') {
    $d = Database::one('SELECT name, code FROM divisions WHERE code = ?', [$division]);
    if ($d) {
        $divName = $d['name'] . ' Division';
    } else {
        $division = '';
    }
}

$zones  = scoredZones($division);
$markets = Database::all(
    "SELECT m.id, m.name, m.code, m.category, m.lat, m.lng, m.capacity,
            m.registered_vendors, m.vacant_spaces, m.data_basis, m.status,
            d.name AS division_name
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE m.vacant_spaces > 0"
      . ($division !== '' ? ' AND d.code = ?' : '')
      . ' ORDER BY m.vacant_spaces DESC',
    $division !== '' ? [$division] : []
);

if ($band !== '') {
    $zones = array_values(array_filter($zones, static fn($z) => strtolower($z['band']) === strtolower($band)));
}

$mapStats = [
    'zones'      => count($zones),
    'vendors'    => array_sum(array_column($zones, 'estimated_vendors')),
    'markets'    => count($markets),
    'vacant'     => array_sum(array_column($markets, 'vacant_spaces')),
    'public'     => array_values(array_filter($markets, static fn($m) => $m['category'] === 'public')),
    'private'    => array_values(array_filter($markets, static fn($m) => $m['category'] === 'private')),
];

$linksSql = "SELECT a.id, a.reference, a.total_score, a.status, a.distance_km,
                    z.name AS zone_name, z.lat AS zone_lat, z.lng AS zone_lng,
                    m.name AS market_name, m.lat AS market_lat, m.lng AS market_lng,
                    v.full_name AS vendor_name, v.business_category
               FROM allocations a
               JOIN vendors v ON v.id = a.vendor_id
               LEFT JOIN trade_zones z ON z.id = v.current_zone_id
               JOIN markets m ON m.id = a.market_id
              WHERE a.rank = 1 AND z.id IS NOT NULL";
$linkParams = [];
if ($division !== '') {
    $linkParams[] = $division;
    $linksSql .= ' AND v.division_id = (SELECT id FROM divisions WHERE code = ?)';
}
$linksSql .= ' ORDER BY a.total_score DESC LIMIT 150';
$links = Database::all($linksSql, $linkParams);

$placementsSql = "SELECT v.full_name, v.business_category, m.name AS market_name, m.lat, m.lng
                    FROM allocations a
                    JOIN vendors v ON v.id = a.vendor_id
                    JOIN markets m ON m.id = a.market_id
                   WHERE a.status IN ('accepted','occupied')";
$placementParams = [];
if ($division !== '') {
    $placementParams[] = $division;
    $placementsSql .= ' AND v.division_id = (SELECT id FROM divisions WHERE code = ?)';
}
$placementsSql .= ' ORDER BY a.decided_at DESC LIMIT 200';
$placements = Database::all($placementsSql, $placementParams);

/* ---------------- Map payload ---------------- */
$mapPayload = [
    'divisions' => array_map(static function (array $d): array {
        return [
            'name' => $d['name'], 'code' => $d['code'], 'mayor' => $d['mayor'],
            'town_clerk' => $d['town_clerk'],
            'area' => num((float) $d['area_sqkm']) . ' km²',
            'population' => num((int) $d['population']),
            'lat' => lnglat($d['lat']), 'lng' => lnglat($d['lng']),
            'polygon' => divisionOutline($d['code'], (float) $d['lat'], (float) $d['lng']),
            'color'   => divisionColor($d['code']),
        ];
    }, Database::all('SELECT * FROM divisions ORDER BY name')),

    'zones' => array_map(static fn(array $z): array => [
        'id' => (int) $z['id'], 'name' => $z['name'], 'code' => $z['code'],
        'division' => $z['division_name'],
        'lat' => lnglat($z['lat']), 'lng' => lnglat($z['lng']),
        'estimated_vendors' => (int) $z['estimated_vendors'],
        'score' => $z['score'], 'band' => $z['band'],
        'footpath' => (float) $z['footpath_obstruction_pct'],
        'carriage' => (float) $z['carriageway_encroachment_pct'],
        'status' => humanise($z['status']),
        'link' => 'congestion.php?zone=' . (int) $z['id'],
    ], $zones),

    'markets' => array_map(static fn(array $m): array => [
        'id' => (int) $m['id'], 'name' => $m['name'], 'code' => $m['code'],
        'category' => $m['category'],
        'lat' => lnglat($m['lat']), 'lng' => lnglat($m['lng']),
        'capacity' => (int) $m['capacity'],
        'registered_vendors' => (int) $m['registered_vendors'],
        'vacant_spaces' => (int) $m['vacant_spaces'],
        'data_basis' => $m['data_basis'],
        'status' => humanise($m['status']),
    ], $markets),

    'links' => array_map(static fn(array $a): array => [
        'label' => $a['vendor_name'] . ' → ' . $a['market_name'],
        'score' => (float) $a['total_score'],
        'status' => humanise($a['status']),
        'from' => [lnglat($a['zone_lat']), lnglat($a['zone_lng'])],
        'to'   => [lnglat($a['market_lat']), lnglat($a['market_lng'])],
        'color' => in_array($a['status'], ['accepted', 'occupied'], true) ? '#0A6339' : '#DC351D',
    ], $links),

    'placements' => array_map(static fn(array $p): array => [
        'lat' => lnglat($p['lat']), 'lng' => lnglat($p['lng']),
        'label' => $p['full_name'] . ' — ' . $p['market_name'],
    ], $placements),
];

$pageTitle = 'City Situation Map';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>City Situation Map
    </div>
    <div class="eyebrow">Kampala Capital City · Geospatial situation picture</div>
    <h1>City Situation Map</h1>
    <p>
      Affected trading sites, markets holding vacant space, and the relocation links proposed between
      them. The base map loads from OpenStreetMap; where tiles are unavailable the Authority’s own
      vector outline of Kampala Capital City is drawn instead, so the map never appears empty.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="congestion.php"><?= KCCA_icon('pulse', 15) ?> Congestion analysis</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ============================ FILTERS =========================== -->
<div class="card">
  <form class="filters" method="get">
    <?php renderDivisionFilter('division', $division); ?>

    <div class="field">
      <label for="band">Congestion band</label>
      <select name="band" id="band" data-autosubmit>
        <option value="">All bands</option>
        <?php foreach (['Critical', 'High', 'Moderate', 'Watch', 'Low'] as $b): ?>
          <option value="<?= e(strtolower($b)) ?>" <?= strtolower($band) === strtolower($b) ? 'selected' : '' ?>>
            <?= e($b) ?> (<?= count(array_filter($zones, static fn($z) => $z['band'] === $b)) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field grow">
      <label>Layers shown</label>
      <div class="chips">
        <?php
        $layers = [
            'zones'      => ['Affected sites', 'zones'],
            'markets'    => ['Markets with space', 'markets'],
            'links'      => ['Relocation links', 'links'],
            'placements' => ['Completed placements', 'placements'],
        ];
        $active = array_map('trim', explode(',', $layerSet));
        foreach ($layers as $key => [$label, $icon]):
        ?>
          <a class="chip <?= in_array($key, $active, true) ? 'on' : '' ?>"
             href="<?= e(urlWith(['layers' => in_array($key, $active, true)
                   ? implode(',', array_values(array_diff($active, [$key])))
                   : implode(',', array_merge($active, [$key])), 'page' => null])) ?>">
            <?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="city-map.php">Reset</a>
  </form>
</div>

<!-- ========================= HEADLINE FIGURES ==================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Affected sites</div>
    <div class="value"><?= num($mapStats['zones']) ?></div>
    <div class="meta">in <?= e($divName) ?></div>
  </div>
  <div class="stat bad">
    <div class="label">Vendors affected</div>
    <div class="value"><?= num($mapStats['vendors']) ?></div>
    <div class="meta">need a gazetted pitch</div>
  </div>
  <div class="stat green">
    <div class="label">Markets with space</div>
    <div class="value"><?= num($mapStats['markets']) ?></div>
    <div class="meta"><?= num(count($mapStats['public'])) ?> public · <?= num(count($mapStats['private'])) ?> private</div>
  </div>
  <div class="stat gold">
    <div class="label">Vacant spaces here</div>
    <div class="value"><?= num($mapStats['vacant']) ?></div>
    <div class="meta">of <?= num($vac['total_vacant']) ?> citywide</div>
  </div>
  <div class="stat info">
    <div class="label">Relocation links</div>
    <div class="value"><?= num(count($links)) ?></div>
    <div class="meta">top-ranked proposal each</div>
  </div>
</div>

<!-- ============================ THE MAP =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2><?= e($divName) ?></h2>
      <p class="desc">Click a site or a market for its record. Drag to pan, scroll to zoom.</p>
    </div>
    <div class="tools">
      <span class="badge green" id="map-engine-badge">Loading engine…</span>
    </div>
  </div>
  <div class="card-body tight">
    <div class="map-shell">
      <div id="kccaMap" data-map-options="<?= e(json_encode([
          'tall' => true,
          'data' => $mapPayload,
      ], JSON_UNESCAPED_UNICODE)) ?>"></div>

      <div class="map-status" id="kccaMap-status">
        <span class="live offline"></span><span>Loading map…</span>
      </div>

      <div class="map-toolbar" id="kccaMap-tools">
        <button data-zoom="in" title="Zoom in" aria-label="Zoom in">+</button>
        <button data-zoom="out" title="Zoom out" aria-label="Zoom out">&minus;</button>
        <button data-zoom="reset" title="Reset view" aria-label="Reset view">&#8635;</button>
      </div>

      <div class="map-legend">
        <h5>Congestion score</h5>
        <div class="row"><span class="sw" style="background:#1B7F4D"></span> Low · under 30</div>
        <div class="row"><span class="sw" style="background:#7FBF3F"></span> Watch · 30–44</div>
        <div class="row"><span class="sw" style="background:#EAA515"></span> Moderate · 45–59</div>
        <div class="row"><span class="sw" style="background:#E8730C"></span> High · 60–74</div>
        <div class="row"><span class="sw" style="background:#C0261A"></span> Critical · 75+</div>
        <div class="divider" style="margin:8px 0"></div>
        <h5>Markets with space</h5>
        <div class="row"><span class="sw" style="background:#0A6339"></span> Public market</div>
        <div class="row"><span class="sw" style="background:#EAA515"></span> Private market</div>
        <div class="row"><span class="sw" style="background:#DC351D;border-radius:50%"></span> Relocation link</div>
        <div class="row"><span class="sw ring" style="background:#fff;border:2px solid #0A6339"></span> Placed vendor</div>
      </div>
    </div>
  </div>
  <div class="card-foot">
    Circle size is proportional to the number of vendors trading at the site.
    Base map &copy; OpenStreetMap contributors; KCCA GIS Portal at
    <a href="https://gis.kcca.go.ug/portal" target="_blank" rel="noopener">gis.kcca.go.ug</a>.
  </div>
</div>

<!-- ====================== SITE + MARKET TABLES ==================== -->
<div class="grid g-3-2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Affected trading sites</h2>
        <p class="desc">Ranked by composite congestion score.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="congestion.php">Detailed analysis</a>
    </div>
    <div class="card-body tight">
      <div class="table-wrap" style="max-height:520px;overflow-y:auto">
        <table class="data compact">
          <thead>
            <tr>
              <th>#</th><th>Site</th><th>Type</th>
              <th class="right">Vendors</th><th class="right">Footpath</th>
              <th class="right">Carriageway</th><th class="center">Score</th><th class="center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sorted = $zones;
            usort($sorted, static fn($a, $b) => $b['score'] <=> $a['score']);
            foreach ($sorted as $i => $z):
                $days = daysUntil($z['deadline_date']);
            ?>
              <tr data-focus-zone data-focus-lat="<?= e(lnglat($z['lat'])) ?>"
                  data-focus-lng="<?= e(lnglat($z['lng'])) ?>"
                  title="Click to centre the map on this site">
                <td><span class="rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span></td>
                <td>
                  <a href="congestion.php?zone=<?= (int) $z['id'] ?>"><strong><?= e($z['name']) ?></strong></a>
                  <div class="xsmall muted">
                    <?= e($z['division_name']) ?>
                    <?php if ($z['road_or_feature']): ?> · <?= e($z['road_or_feature']) ?><?php endif; ?>
                  </div>
                </td>
                <td class="xsmall"><?= e(humanise($z['zone_type'])) ?></td>
                <td class="right num strong"><?= num((int) $z['estimated_vendors']) ?></td>
                <td class="right num <?= (float) $z['footpath_obstruction_pct'] >= 70 ? 'strong' : '' ?>">
                  <?= pct($z['footpath_obstruction_pct'], 0) ?>
                </td>
                <td class="right num"><?= pct($z['carriageway_encroachment_pct'], 0) ?></td>
                <td class="center">
                  <a href="congestion.php?zone=<?= (int) $z['id'] ?>" class="badge <?= e($z['tone']) ?>">
                    <?= num($z['score'], 1) ?>
                  </a>
                </td>
                <td class="center">
                  <span class="badge <?= e(statusTone($z['status'])) ?>"><?= e(humanise($z['status'])) ?></span>
                  <?php if ($days !== null && $days >= 0 && $days <= 30): ?>
                    <div class="xsmall muted"><?= $days ?>d left</div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Markets holding space</h2>
        <p class="desc">Where the cleared vendors can go.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="vacancy.php">Vacancy finder</a>
    </div>
    <div class="card-body tight">
      <div class="table-wrap" style="max-height:520px;overflow-y:auto">
        <table class="data compact">
          <thead>
            <tr><th>Market</th><th class="right">Vacant</th><th class="right">Occupancy</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($markets, 0, 22) as $m):
                $cap = max(1, (int) $m['capacity']);
                $util = (int) $m['registered_vendors'] / $cap * 100;
            ?>
              <tr>
                <td>
                  <strong><?= e($m['name']) ?></strong>
                  <div class="xsmall muted">
                    <?= e($m['division_name']) ?> ·
                    <span class="badge <?= $m['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                      <?= e($m['category']) ?>
                    </span>
                    <?php if ($m['data_basis'] !== 'official'): ?>
                      <span class="xsmall muted">(<?= e($m['data_basis']) ?>)</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="right num strong"><?= num((int) $m['vacant_spaces']) ?></td>
                <td class="right">
                  <div class="meter">
                    <span class="track">
                      <span class="fill <?= $util < 70 ? 'warn' : 'ok' ?>" style="width:<?= (float) clamp($util, 2, 100) ?>%"></span>
                    </span>
                    <span class="val"><?= pct($util, 0) ?></span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-foot">
      <a href="https://www.kcca.go.ug/news/1078/vendors-in-city-markets-rally-street-traders-to-relocate"
         target="_blank" rel="noopener">KCCA news 1078 — the source for the citywide vacancy figures</a>
    </div>
  </div>
</div>

<?php
/* The map needs a focus helper: clicking a table row pans the map to that
   site. Page-specific, so it stays out of the shared library. */
$pageScripts = <<<'HTML'
<script>
document.querySelectorAll('[data-focus-zone]').forEach(function (row) {
  row.addEventListener('click', function (e) {
    if (e.target.closest('a')) return;
    var lat = parseFloat(row.getAttribute('data-focus-lat'));
    var lng = parseFloat(row.getAttribute('data-focus-lng'));
    if (!isNaN(lat) && !isNaN(lng) && window.KCCAMap) {
      window.KCCAMap.focus(lat, lng, 16);
    }
  });
  row.style.cursor = 'pointer';
});
</script>
HTML;

require __DIR__ . '/../layouts/footer.php';
