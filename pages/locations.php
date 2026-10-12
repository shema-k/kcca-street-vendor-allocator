<?php
/**
 * Trading Locations — location and space management.
 *
 * The register of locations, their individual trading spaces, the footfall
 * counted at each, the rent band, capacity, and the land-use restrictions
 * that gate what may be traded there.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f        = facts();
$vac      = $f['vacancy'];
$category = (string) ($_GET['category'] ?? '');
$division = (string) ($_GET['division'] ?? '');
$view     = (string) ($_GET['view'] ?? 'locations');
$locationId = (int) ($_GET['location'] ?? 0);
$q        = (string) ($_GET['q'] ?? '');

$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}

/* ---------------- The location list ---------------- */
$where  = ['1 = 1'];
$params = [];
if ($category !== '') { $where[] = 'm.category = :cat'; $params['cat'] = $category; }
if ($division !== '') { $where[] = 'd.code = :div';  $params['div'] = $division; }
if ($q !== '') {
    [$likeSql, $likeParams] = orLike('q', ['m.name', 'm.parish', 'm.code'], $q);
    $where[] = $likeSql;
    $params += $likeParams;
}

$locations = Database::all(
    "SELECT m.*, d.name AS division_name, d.code AS div_code, d.population, d.area_sqkm,
            (SELECT COUNT(*) FROM land_use_restrictions r
              WHERE r.market_id = m.id AND r.is_active = 1) AS restriction_count,
            (SELECT COUNT(*) FROM footfall_observations o WHERE o.market_id = m.id) AS observations,
            (SELECT COALESCE(ROUND(AVG(monthly_rent_ugx)),0) FROM market_spaces s
              WHERE s.market_id = m.id AND s.status = 'vacant') AS avg_rent
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY m.vacant_spaces DESC, m.name",
    $params
);

/* Footfall per location. One pass each, cached inside the model. */
foreach ($locations as &$loc) {
    $cap = max(1, (int) $loc['capacity']);
    $loc['occupancy']  = round((int) $loc['registered_vendors'] / $cap * 100, 1);
    $loc['footfall']   = ReachModel::footfall((int) $loc['id'], $loc);
    $fac = 0;
    foreach (['water_available','power_available','toilets','waste_collection',
              'storage_rooms','cold_chain','accessible_ramps'] as $c) {
        if (!empty($loc[$c])) { $fac++; }
    }
    $loc['facilities'] = $fac;
}
unset($loc);

/* ---------------- One location in detail ---------------- */
$detail     = null;
$spaces     = [];
$restrictions = [];
$tradeMix   = [];
if ($locationId > 0) {
    $detail = Database::one(
        'SELECT m.*, d.name AS division_name, d.code AS div_code, d.population, d.area_sqkm,
                d.mayor
           FROM markets m JOIN divisions d ON d.id = m.division_id
          WHERE m.id = ? LIMIT 1',
        [$locationId]
    );

    if ($detail) {
        $spaces = Database::all(
            "SELECT s.*, v.full_name AS occupant
               FROM market_spaces s
               LEFT JOIN allocations a ON a.market_space_id = s.id AND a.status IN ('accepted','occupied')
               LEFT JOIN vendors v ON v.id = a.vendor_id
              WHERE s.market_id = ? ORDER BY s.status, s.space_code LIMIT 150",
            [$locationId]
        );

        $spaceStats = Database::all(
            "SELECT status, COUNT(*) AS n FROM market_spaces WHERE market_id = ? GROUP BY status",
            [$locationId]
        );
        $detail['space_status'] = [];
        foreach ($spaceStats as $s) {
            $detail['space_status'][$s['status']] = (int) $s['n'];
        }

        $restrictions = Database::all(
            'SELECT * FROM land_use_restrictions WHERE market_id = ? ORDER BY is_active DESC, code',
            [$locationId]
        );

        $tradeMix = Database::all(
            "SELECT mx.business_category, mx.vendor_count, c.name, c.demand_index
               FROM market_trade_mix mx
               LEFT JOIN trade_categories c ON c.code = mx.business_category
              WHERE mx.market_id = ? ORDER BY mx.vendor_count DESC",
            [$locationId]
        );

        $detail['footfall_profile'] = ReachModel::footfall($locationId, $detail);
    }
}

/* Footfall observations for the selected location. */
$observations = $locationId > 0 ? Database::all(
    'SELECT * FROM footfall_observations WHERE market_id = ? ORDER BY observed_on DESC, hour_band LIMIT 40',
    [$locationId]
) : [];

$categories = Database::all('SELECT * FROM trade_categories ORDER BY demand_index DESC');

/* ---------------- Footfall chart across locations ---------------- */
$footfallChart = [];
foreach ($locations as $loc) {
    if ((int) $loc['vacant_spaces'] <= 0) {
        continue;
    }
    $footfallChart[] = [
        'label'      => $loc['name'],
        'value'      => (float) $loc['footfall']['daily'],
        'counted'    => $loc['footfall']['observations'] > 0,
        'confidence' => $loc['footfall']['confidence'],
    ];
}
usort($footfallChart, static fn($a, $b) => $b['value'] <=> $a['value']);
$footfallChart = array_slice($footfallChart, 0, 12);

$pageTitle = 'Trading Locations';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Trading Locations
    </div>
    <div class="eyebrow">Location &amp; space management</div>
    <h1>Trading Locations</h1>
    <p>
      Every location: capacity, rent, individual pitches, counted footfall, land-use restrictions.
    </p>
  </div>
  <div class="actions">
    <div class="chips">
      <a class="chip <?= $view === 'locations' ? 'on' : '' ?>"
         href="<?= e(urlWith(['view' => null, 'page' => null])) ?>">Locations</a>
      <a class="chip <?= $view === 'footfall' ? 'on' : '' ?>"
         href="<?= e(urlWith(['view' => 'footfall', 'page' => null])) ?>">Footfall</a>
      <a class="chip <?= $view === 'restrictions' ? 'on' : '' ?>"
         href="<?= e(urlWith(['view' => 'restrictions', 'page' => null])) ?>">Land use</a>
    </div>
    <?php if (Auth::can('manage')): ?>
      <a class="btn btn-ghost btn-sm" href="market_edit.php?new=1">Add a location</a>
    <?php endif; ?>
  </div>
</div>

<!-- ========================== FILTERS =========================== -->
<div class="card">
  <form class="filters" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="field grow">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>"
             placeholder="Location name, parish or code…" data-search-submit>
    </div>
    <div class="field">
      <label for="category">Type</label>
      <select name="category" id="category" data-autosubmit>
        <option value="">Public and private</option>
        <option value="public"  <?= $category === 'public'  ? 'selected' : '' ?>>Public (KCCA)</option>
        <option value="private" <?= $category === 'private' ? 'selected' : '' ?>>Private</option>
      </select>
    </div>
    <?php renderDivisionFilter('division', $division); ?>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="locations.php">Reset</a>
  </form>
</div>

<!-- ======================= LOCATION DETAIL ====================== -->
<?php if ($detail):
    $cap = max(1, (int) $detail['capacity']);
    $fp  = $detail['footfall_profile'];
    $totalSpaces = array_sum($detail['space_status']);
?>
<div class="card" id="location-detail">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="locations.php">Locations</a><span class="sep">/</span><?= e($detail['division_name']) ?>
      </div>
      <h2><?= e($detail['name']) ?></h2>
      <p class="desc">
        <span class="badge <?= $detail['category'] === 'public' ? 'green' : 'gold' ?>"><?= e($detail['category']) ?> market</span>
        · <?= e($detail['code']) ?>
        · <?= e($detail['parish'] ?: '—') ?> parish
        · <?= e(humanise($detail['market_type'])) ?>
        <?php if ($detail['opened_year']): ?> · opened <?= (int) $detail['opened_year'] ?><?php endif; ?>
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($detail['status'])) ?>"><?= e(humanise($detail['status'])) ?></span>
      <a class="btn btn-quiet btn-sm" href="locations.php?view=<?= e($view) ?>">Close</a>
    </div>
  </div>

  <div class="card-body">
    <div class="grid g-2-1">
      <div>
        <div class="grid g5 mb-2">
          <div class="stat">
            <div class="label">Capacity</div>
            <div class="value"><?= num((int) $detail['capacity']) ?></div>
            <div class="meta">pitches</div>
          </div>
          <div class="stat info">
            <div class="label">Registered</div>
            <div class="value"><?= num((int) $detail['registered_vendors']) ?></div>
            <div class="meta"><?= pct((int) $detail['registered_vendors'] / $cap * 100, 1) ?> full</div>
          </div>
          <div class="stat ok">
            <div class="label">Vacant</div>
            <div class="value"><?= num((int) $detail['vacant_spaces']) ?></div>
            <div class="meta">available</div>
          </div>
          <div class="stat <?= $fp['confidence'] >= 0.5 ? 'green' : 'warn' ?>">
            <div class="label">Footfall</div>
            <div class="value" style="font-size:20px"><?= num($fp['daily']) ?></div>
            <div class="meta"><?= pct($fp['confidence'] * 100, 0) ?> confidence</div>
          </div>
          <div class="stat gold">
            <div class="label">Rent band</div>
            <div class="value" style="font-size:19px">
              <?= (int) Database::value(
                    'SELECT COALESCE(ROUND(AVG(monthly_rent_ugx)),0) FROM market_spaces WHERE market_id = ? AND status = \'vacant\'',
                    [(int) $detail['id']]) === 0 ? 'Free' : 'Charged' ?>
            </div>
            <div class="meta">on vacant pitches</div>
          </div>
        </div>

        <!-- Footfall detail -->
        <div class="card" style="margin:0 0 16px">
          <div class="card-head">
            <div class="grow">
              <h3>Footfall</h3>
              <p class="desc"><?= e($fp['basis']) ?></p>
            </div>
            <?php if (Auth::can('report')): ?>
              <button class="btn btn-primary btn-sm" data-modal="api/form.php?form=footfall&location=<?= (int) $detail['id'] ?>">
                Record a count
              </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($fp['observations'] > 0): ?>
              <div class="grid g4 mb-2">
                <div class="stat ok">
                  <div class="label">Daily movements</div>
                  <div class="value" style="font-size:20px"><?= num($fp['daily']) ?></div>
                  <div class="meta">in and out, all bands</div>
                </div>
                <div class="stat info">
                  <div class="label">Busiest period</div>
                  <div class="value" style="font-size:15px;line-height:1.4"><?= e(ReachModel::hourBandLabel($fp['peak_hour_band'])) ?></div>
                  <div class="meta">on <?= e($fp['peak_day']) ?>s</div>
                </div>
                <div class="stat">
                  <div class="label">Days counted</div>
                  <div class="value" style="font-size:20px"><?= num($fp['days_covered']) ?></div>
                  <div class="meta"><?= num($fp['observations']) ?> observations</div>
                </div>
                <div class="stat <?= $fp['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
                  <div class="label">Confidence</div>
                  <div class="value" style="font-size:20px"><?= pct($fp['confidence'] * 100, 0) ?></div>
                  <div class="meta">
                    <?= $fp['age_days'] !== null ? 'last counted ' . e(relativeDays(date('Y-m-d', strtotime('-' . (int) $fp['age_days'] . ' days')))) : 'never counted' ?>
                  </div>
                </div>
              </div>

              <h4 class="mb-1">Daily movements by day type</h4>
              <svg class="chart" data-chart='<?= ejs([
                'type' => 'bar',
                'opts' => [
                  'height' => 180,
                  'labels' => array_map('ucfirst', array_keys($fp['per_day_type'])),
                  'series' => [[
                    'name' => 'Movements',
                    'color' => '#0A6339',
                    'values' => array_map(static fn($v) => round((float) $v), array_values($fp['per_day_type'])),
                  ]],
                  'yFormat' => 'num',
                ],
              ]) ?>' aria-label="Footfall by day type"></svg>

              <div class="divider"></div>
              <h4 class="mb-1">Recent counts</h4>
              <div class="table-wrap" style="max-height:300px;overflow-y:auto">
                <table class="data compact">
                  <thead>
                    <tr><th>Date</th><th>Day</th><th>Band</th><th class="right">In</th><th class="right">Out</th><th class="right">Traders</th><th>Source</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($observations as $o): ?>
                      <tr>
                        <td class="xsmall"><?= e(fmtDate($o['observed_on'])) ?></td>
                        <td class="xsmall"><?= e(ucfirst($o['day_type'])) ?></td>
                        <td class="xsmall"><?= e(ReachModel::hourBandLabel((int) $o['hour_band'])) ?></td>
                        <td class="right num"><?= num((int) $o['people_in']) ?></td>
                        <td class="right num"><?= num((int) $o['people_out']) ?></td>
                        <td class="right num"><?= num((int) $o['traders_present']) ?></td>
                        <td class="xsmall muted"><?= e(humanise($o['source'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-warn">
                <strong>No footfall has been counted here.</strong> The figure above is an estimate from
                the division’s population and how much trading this location does, at low confidence.
                Counting footfall would raise the confidence of every recommendation made for this market.
              </div>
              <?php if (Auth::can('report')): ?>
                <button class="btn btn-primary btn-sm" data-modal="api/form.php?form=footfall&location=<?= (int) $detail['id'] ?>">
                  Record the first count
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pitch register -->
        <?php if ($detail['category'] === 'public'): ?>
        <div class="card" style="margin:0">
          <div class="card-head">
            <div class="grow">
              <h3>Individual trading spaces</h3>
              <p class="desc">
                <?= num($totalSpaces) ?> pitches held by the Authority under the Markets Act 2023.
                Showing the first <?= num(count($spaces)) ?>.
              </p>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-2">
              <?php foreach (['vacant' => 'ok', 'occupied' => 'info', 'reserved' => 'warn',
                              'under-maintenance' => 'muted'] as $st => $tone): ?>
                <span class="badge <?= $tone ?>">
                  <?= e(humanise($st)) ?>:
                  <?= num((int) ($detail['space_status'][$st] ?? 0)) ?>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="table-wrap" style="max-height:400px;overflow-y:auto">
              <table class="data compact">
                <thead>
                  <tr><th>Pitch</th><th>Section</th><th class="right">Size</th><th class="right">Rent</th><th class="center">Status</th><th>Facilities</th><th>Occupant</th><th></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($spaces as $s): ?>
                    <tr>
                      <td class="mono xsmall"><?= e($s['space_code']) ?></td>
                      <td class="xsmall"><?= e($s['section'] ?: '—') ?></td>
                      <td class="right num xsmall"><?= num((float) $s['size_sqm'], 0) ?> m²</td>
                      <td class="right xsmall"><?= (int) $s['monthly_rent_ugx'] === 0 ? '—' : ugx((int) $s['monthly_rent_ugx']) ?></td>
                      <td class="center"><span class="badge <?= e(statusTone($s['status'])) ?>"><?= e(humanise($s['status'])) ?></span></td>
                      <td class="xsmall muted">
                        <?= $s['has_water'] ? 'W' : '' ?><?= $s['has_power'] ? 'P' : '' ?><?= $s['near_toilet'] ? 'T' : '' ?><?= $s['step_free'] ? 'S' : '' ?>
                        <?php if (!$s['has_water'] && !$s['has_power'] && !$s['near_toilet'] && !$s['step_free']): ?>—<?php endif; ?>
                      </td>
                      <td class="xsmall"><?= e($s['occupant'] ?: '—') ?></td>
                      <td class="right">
                        <?php if (Auth::can('manage')): ?>
                          <button class="btn btn-ghost btn-sm"
                                  data-modal="api/form.php?form=space&space_id=<?= (int) $s['id'] ?>">Edit</button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="source">
          <span class="lbl estimated">private</span>
          <span>
            This is a privately operated market. Under the Markets Act 2023 s.17(2) the operator keeps
            their own register of vendors and pitches; the Authority’s role here is regulatory, so
            individual pitches are not held in this system.
          </span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Side column -->
      <div>
        <div class="card" style="margin:0">
          <div class="card-head">
            <div class="grow"><h3>Land use and restrictions</h3></div>
            <?php if (Auth::can('manage')): ?>
              <button class="btn btn-ghost btn-sm"
                      data-modal="api/form.php?form=restriction&market_id=<?= (int) $detail['id'] ?>">Add</button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if (!$restrictions): ?>
              <p class="muted small mb-0">
                No restriction recorded. Any lawful trade may be allocated here.
              </p>
            <?php else: ?>
              <div class="stack-s">
                <?php foreach ($restrictions as $r): ?>
                  <div class="criterion">
                    <span class="cl"><?= e($r['title']) ?></span>
                    <span class="cv">
                      <span class="badge <?= $r['is_active'] ? 'bad' : 'muted' ?>">
                        <?= $r['is_active'] ? 'Active' : 'Inactive' ?>
                      </span>
                    </span>
                    <span class="cn"><?= e($r['description']) ?></span>
                    <span class="cw">
                      <?= e($r['excluded_categories'] ?: 'Applies to every trade on the site') ?>
                      <?php if ($r['authority']): ?> · <?= e($r['authority']) ?><?php endif; ?>
                      <?php if ($r['legal_basis']): ?> · <?= e($r['legal_basis']) ?><?php endif; ?>
                    </span>
                    <?php if (Auth::can('manage')): ?>
                      <span class="cw">
                        <a href="#" data-delete-restriction="<?= (int) $r['id'] ?>">Remove</a>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-foot">
            A restriction is a gate, not a weight: the engine will not offer a location to a trader
            whose trade the restriction excludes, whatever the numbers say.
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div class="grow"><h3>Trades already here</h3></div></div>
          <div class="card-body">
            <?php if (!$tradeMix): ?>
              <p class="muted small mb-0">No trade mix recorded for this location.</p>
            <?php else: ?>
              <svg class="chart" data-chart='<?= ejs([
                'type' => 'hbar',
                'opts' => [
                  'labelW' => 148, 'rowH' => 25,
                  'items' => array_map(static fn(array $r): array => [
                    'label' => $r['name'] ?? humanise($r['business_category']),
                    'value' => (int) $r['vendor_count'],
                    'color' => (int) ($r['demand_index'] ?? 0) >= 70 ? '#C0261A' : '#0A6339',
                  ], $tradeMix),
                ],
              ]) ?>' aria-label="Trade mix at this location"></svg>
              <p class="xsmall muted mt-1" style="margin-bottom:0">
                Red bars trade where demand is indexed at 70 or more — the most contested pitches.
              </p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div class="grow"><h3>Facilities</h3></div></div>
          <div class="card-body">
            <?php
            $fac = ['water_available' => 'Water supply', 'power_available' => 'Electricity',
                    'toilets' => 'Toilets', 'waste_collection' => 'Waste collection',
                    'storage_rooms' => 'Storage rooms', 'cold_chain' => 'Cold chain',
                    'accessible_ramps' => 'Accessible ramps'];
            ?>
            <div class="stack-s">
              <?php foreach ($fac as $col => $label): ?>
                <div class="row-tight">
                  <span class="badge <?= !empty($detail[$col]) ? 'ok' : 'muted' ?>">
                    <?= !empty($detail[$col]) ? '&#10003;' : '&#8211;' ?>
                  </span>
                  <span class="<?= !empty($detail[$col]) ? 'strong' : 'muted' ?>"><?= e($label) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div class="grow"><h3>Reach for a trader here</h3></div></div>
          <div class="card-body">
            <p class="small muted">
              Choose a trade and the model will show what a trader selling it could expect at this
              location, with the factors behind the figure.
            </p>
            <?php if (Auth::can('allocate')): ?>
              <a class="btn btn-primary btn-sm btn-block"
                 href="recommendations.php?location=<?= (int) $detail['id'] ?>">
                Model reach for this location
              </a>
            <?php else: ?>
              <!-- The reach model decides where traders are placed, so it is
                   the planner's instrument. A reader who cannot place anybody
                   is shown the location and nothing offered. -->
              <p class="xsmall muted" style="margin:0">
                The reach model is used by the planners who place traders here.
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===================== LOCATIONS LIST ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Location register</h2>
      <p class="desc"><?= num(count($locations)) ?> location<?= count($locations) === 1 ? '' : 's' ?> in this view.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Location</th><th>Division</th><th>Parish</th>
            <th class="right">Capacity</th><th class="right">Registered</th><th class="right">Vacant</th>
            <th style="width:140px">Occupancy</th>
            <th class="right">Rent</th>
            <th class="right">Footfall</th><th class="center">Confidence</th>
            <th class="center">Facilities</th><th class="center">Land use</th>
            <th class="center">Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locations as $loc):
              $fp = $loc['footfall'];
          ?>
            <tr>
              <td>
                <a href="market_map.php?market=<?= (int) $loc['id'] ?>">
                  <strong><?= e($loc['name']) ?></strong>
                </a>
                <div class="xsmall muted">
                  <span class="badge <?= $loc['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                    <?= e($loc['category']) ?>
                  </span>
                  <?= e($loc['code']) ?>
                </div>
              </td>
              <td class="xsmall"><?= e($loc['division_name']) ?></td>
              <td class="xsmall"><?= e($loc['parish'] ?: '—') ?></td>
              <td class="right num"><?= num((int) $loc['capacity']) ?></td>
              <td class="right num"><?= num((int) $loc['registered_vendors']) ?></td>
              <td class="right num strong"><?= num((int) $loc['vacant_spaces']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $loc['occupancy'] < 70 ? 'warn' : 'ok' ?>"
                        style="width:<?= (float) clamp($loc['occupancy'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($loc['occupancy'], 0) ?></span>
                </div>
              </td>
              <td class="right xsmall"><?= (int) $loc['avg_rent'] === 0 ? '<span class="badge ok">Free</span>' : ugx((int) $loc['avg_rent']) ?></td>
              <td class="right num xsmall">
                <?= num($fp['daily']) ?>
                <?php if ($fp['observations'] === 0): ?>
                  <div class="xsmall muted">estimated</div>
                <?php endif; ?>
              </td>
              <td class="center">
                <span class="badge <?= $fp['confidence'] >= 0.5 ? 'ok' : ($fp['confidence'] >= 0.3 ? 'warn' : 'bad') ?>">
                  <?= pct($fp['confidence'] * 100, 0) ?>
                </span>
              </td>
              <td class="center"><span class="badge <?= $loc['facilities'] >= 5 ? 'ok' : ($loc['facilities'] >= 3 ? 'info' : 'warn') ?>"><?= $loc['facilities'] ?>/7</span></td>
              <td class="center">
                <?= (int) $loc['restriction_count'] > 0
                    ? '<span class="badge warn">' . (int) $loc['restriction_count'] . '</span>'
                    : '<span class="muted small">—</span>' ?>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($loc['status'])) ?>"><?= e(humanise($loc['status'])) ?></span></td>
              <td class="right nowrap">
                <?php if (Auth::can('manage')): ?>
                  <a class="btn btn-ghost btn-sm" href="market_edit.php?market=<?= (int) $loc['id'] ?>"
                     title="Edit this location">Edit</a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-sm"
                   href="market_map.php?market=<?= (int) $loc['id'] ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if (!$locations): ?>
    <div class="empty">
      <div class="big"><?= KCCA_icon('store', 30) ?></div>
      <h3>No location matches this filter</h3>
      <p>Clear the search or widen the division filter.</p>
      <a class="btn btn-ghost" href="locations.php">Reset filters</a>
    </div>
  <?php endif; ?>
  <div class="card-foot">
    Public market figures reconcile to the published KCCA figures. Footfall is
    counted wherever <strong>confidence</strong> is 50% or more.
  </div>
</div>

<!-- ===================== FOOTFALL PANEL ========================= -->
<?php if ($view === 'footfall' && $footfallChart): ?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Footfall across locations with space</h2>
      <p class="desc">
        Total movements in and out per day. The model reads this directly, so a location with no
        counts is projected rather than measured — and says so.
      </p>
    </div>
  </div>
  <div class="card-body">
    <svg class="chart" data-chart='<?= ejs([
      'type' => 'hbar',
      'opts' => [
        'labelW' => 182, 'rowH' => 26,
        'format' => 'num',
        'items' => array_map(static fn(array $x): array => [
          'label' => $x['label'],
          'value' => round($x['value'], 1),
          'color' => $x['counted'] ? '#0A6339' : '#C9D6DE',
        ], $footfallChart),
      ],
    ]) ?>' aria-label="Footfall across locations"></svg>
    <div class="legend">
      <span class="item"><span class="sw" style="background:#0A6339"></span> Counted at this location</span>
      <span class="item"><span class="sw" style="background:#C9D6DE"></span> Estimated from catchment</span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ==================== LAND USE REGISTER ======================== -->
<?php if ($view === 'restrictions'):
    $allRestrictions = Database::all(
        'SELECT r.*, m.name AS market_name, d.name AS division_name
           FROM land_use_restrictions r
           JOIN markets m ON m.id = r.market_id
           JOIN divisions d ON d.id = m.division_id
          ORDER BY r.is_active DESC, m.name'
    );
?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Land use and trading restrictions</h2>
      <p class="desc">
        Statutory and physical constraints on what may be traded where. These gate the
        recommendation engine outright.
      </p>
    </div>
    <?php if (Auth::can('manage')): ?>
      <a class="btn btn-primary btn-sm" href="locations.php">Add a restriction</a>
    <?php endif; ?>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Code</th><th>Restriction</th><th>Location</th><th>Division</th>
              <th>Blocks</th><th>Authority</th><th>Legal basis</th><th class="center">State</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allRestrictions as $r): ?>
            <tr>
              <td class="mono xsmall"><?= e($r['code']) ?></td>
              <td>
                <strong><?= e($r['title']) ?></strong>
                <div class="xsmall muted"><?= e(truncateText($r['description'], 110)) ?></div>
              </td>
              <td class="xsmall"><?= e($r['market_name']) ?></td>
              <td class="xsmall"><?= e($r['division_name']) ?></td>
              <td class="xsmall">
                <?php
                /* The column is nullable, so ``!== ''`` alone lets NULL
                   through into explode(). A restriction covering the whole
                   site is stored as NULL rather than an empty string. */
                $blocked = array_filter(array_map('trim',
                    explode(',', (string) ($r['excluded_categories'] ?? ''))));
                ?>
                <?= $blocked !== []
                    ? e(implode(', ', array_map(static fn($c) => humanise($c), $blocked)))
                    : '<span class="badge bad">Every trade</span>' ?>
              </td>
              <td class="xsmall"><?= e($r['authority'] ?: '—') ?></td>
              <td class="xsmall muted"><?= e($r['legal_basis'] ?: '—') ?></td>
              <td class="center">
                <span class="badge <?= $r['is_active'] ? 'ok' : 'muted' ?>">
                  <?= $r['is_active'] ? 'In force' : 'Lapsed' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    Private operators keep their own registers under the Markets Act 2023.
  </div>
</div>
<?php endif; ?>

<?php
/* Rendered by the footer, after kcca.js has loaded, so KCCA and KCCA.api
   are defined when this runs. */
$pageScripts = <<<'HTML'
<script>
/* Remove a land-use restriction. The link had no handler at all until this
   was audited — a control the officer can see and click that did nothing
   is worse than no control, because it looks like the system is broken. */
document.querySelectorAll('[data-delete-restriction]').forEach(function (el) {
  el.addEventListener('click', function (e) {
    e.preventDefault();
    var id = el.getAttribute('data-delete-restriction');
    KCCA.ask(
      'Remove this restriction',
      'The trade it blocks becomes available again, and the recommendation engine will stop ' +
      'excluding that location. Record why it is being lifted.',
      { label: 'Reason for removing it', buttonLabel: 'Remove restriction', placeholder: 'e.g. works finished, school relocated' }
    ).then(function (reason) {
      if (reason === null) return;
      KCCA.postAction(KCCA.api('api/location_save.php'), {
        action: 'delete-restriction', restriction_id: id
      }, {
        button: el,
        busyLabel: 'Removing…'
      });
    });
  });
});
</script>
HTML;
?>
<?php require __DIR__ . '/../layouts/footer.php';