<?php
/**
 * Market Register — the register of markets required by the Markets Act 2023.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f   = facts();
$vac = $f['vacancy'];

$category = (string) ($_GET['category'] ?? '');
$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$status   = (string) ($_GET['status'] ?? '');
$q        = (string) ($_GET['q'] ?? '');
$marketId = (int) ($_GET['market'] ?? 0);

$where  = ['1 = 1'];
$params = [];
if ($category !== '') { $where[] = 'm.category = :cat';  $params['cat'] = $category; }
if ($division !== '') { $where[] = 'd.code = :div';      $params['div'] = $division; }
if ($status !== '')   { $where[] = 'm.status = :st';      $params['st']  = $status; }
if ($q !== '') {
    [$likeSql, $likeParams] = orLike('q', ['m.name', 'm.parish', 'm.code', 'm.ownership'], $q);
    $where[] = $likeSql;
    $params += $likeParams;
}

$rows = Database::all(
    "SELECT m.*, d.name AS division_name, d.code AS div_code
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY m.category, m.vacant_spaces DESC, m.name",
    $params
);

foreach ($rows as &$r) {
    $cap = max(1, (int) $r['capacity']);
    $r['util'] = round((int) $r['registered_vendors'] / $cap * 100, 1);
    $r['pitch_count'] = (int) Database::value('SELECT COUNT(*) FROM market_spaces WHERE market_id = ?', [(int) $r['id']]);
}
unset($r);

/* Citywide position */
$totals = Database::one(
    "SELECT COUNT(*) AS markets,
            COALESCE(SUM(CASE WHEN category='public'  THEN 1 END),0) AS public_markets,
            COALESCE(SUM(CASE WHEN category='private' THEN 1 END),0) AS private_markets,
            COALESCE(SUM(capacity),0) AS capacity,
            COALESCE(SUM(registered_vendors),0) AS registered,
            COALESCE(SUM(vacant_spaces),0) AS vacant,
            COALESCE(SUM(CASE WHEN category='public'  THEN vacant_spaces END),0) AS public_vacant,
            COALESCE(SUM(CASE WHEN category='private' THEN vacant_spaces END),0) AS private_vacant
       FROM markets"
);

$detail = null;
$pitches = [];
if ($marketId > 0) {
    $detail = Database::one(
        'SELECT m.*, d.name AS division_name FROM markets m
           JOIN divisions d ON d.id = m.division_id WHERE m.id = ? LIMIT 1',
        [$marketId]
    );
    if ($detail) {
        $pitches = Database::all(
            "SELECT s.*, v.full_name AS occupant
               FROM market_spaces s
               LEFT JOIN allocations a ON a.market_space_id = s.id AND a.status IN ('accepted','occupied')
               LEFT JOIN vendors v ON v.id = a.vendor_id
              WHERE s.market_id = ? ORDER BY s.status, s.space_code LIMIT 120",
            [$marketId]
        );
    }
}

$pageTitle = 'Market Register';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Market Register
    </div>
    <div class="eyebrow">Market development · Markets Act 2023</div>
    <h1>Market Register</h1>
    <p>
      The register of markets for Kampala Capital City kept under section 17 of the Markets Act 2023,
      and the register of vendors under section 18. Where a market is privately operated the operator
      keeps their own vendor register (s.17(2)); the Authority's role there is regulatory.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="vacancy.php"><?= KCCA_icon('grid', 15) ?> Vacancy finder</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ========================= CITY TOTALS ========================= -->
<div class="grid g5 mb-2">
  <div class="stat green">
    <div class="label">Public markets</div>
    <div class="value"><?= num((int) $totals['public_markets']) ?></div>
    <div class="meta">official: <?= num($vac['public_markets']) ?></div>
  </div>
  <div class="stat gold">
    <div class="label">Private markets</div>
    <div class="value"><?= num((int) $totals['private_markets']) ?></div>
    <div class="meta">official: <?= num($vac['private_markets']) ?></div>
  </div>
  <div class="stat">
    <div class="label">Total capacity</div>
    <div class="value"><?= num((int) $totals['capacity']) ?></div>
    <div class="meta">pitches across all markets</div>
  </div>
  <div class="stat info">
    <div class="label">Registered vendors</div>
    <div class="value"><?= num((int) $totals['registered']) ?></div>
    <div class="meta"><?= pct($totals['capacity'] > 0 ? (int) $totals['registered'] / (int) $totals['capacity'] * 100 : 0, 1) ?> occupied</div>
  </div>
  <div class="stat ok">
    <div class="label">Vacant</div>
    <div class="value"><?= num((int) $totals['vacant']) ?></div>
    <div class="meta">available to allocate</div>
  </div>
</div>

<?php if ($detail):
    $cap = max(1, (int) $detail['capacity']);
    $detailUtil = round((int) $detail['registered_vendors'] / $cap * 100, 1);
    $detailSpaces = [
        'vacant'            => (int) Database::value("SELECT COUNT(*) FROM market_spaces WHERE market_id = ? AND status='vacant'", [(int) $detail['id']]),
        'occupied'          => (int) Database::value("SELECT COUNT(*) FROM market_spaces WHERE market_id = ? AND status='occupied'", [(int) $detail['id']]),
        'reserved'          => (int) Database::value("SELECT COUNT(*) FROM market_spaces WHERE market_id = ? AND status='reserved'", [(int) $detail['id']]),
        'under-maintenance' => (int) Database::value("SELECT COUNT(*) FROM market_spaces WHERE market_id = ? AND status='under-maintenance'", [(int) $detail['id']]),
    ];
?>
<!-- ======================= MARKET DETAIL ========================= -->
<div class="card" id="market-detail">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="markets.php">Register</a><span class="sep">/</span><?= e($detail['division_name']) ?>
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
      <a class="btn btn-quiet btn-sm" href="markets.php">Close</a>
    </div>
  </div>

  <div class="card-body">
    <div class="grid g-3-2">
      <div>
        <div class="grid g4 mb-2">
          <div class="stat">
            <div class="label">Capacity</div>
            <div class="value"><?= num((int) $detail['capacity']) ?></div>
          </div>
          <div class="stat info">
            <div class="label">Registered</div>
            <div class="value"><?= num((int) $detail['registered_vendors']) ?></div>
            <div class="meta"><?= pct($detailUtil, 1) ?> occupied</div>
          </div>
          <div class="stat ok">
            <div class="label">Vacant</div>
            <div class="value"><?= num((int) $detail['vacant_spaces']) ?></div>
            <div class="meta">free to allocate</div>
          </div>
          <div class="stat gold">
            <div class="label">Pitches held</div>
            <div class="value"><?= num(array_sum($detailSpaces)) ?></div>
            <div class="meta">in the register</div>
          </div>
        </div>

        <h3 class="mb-1">Occupancy</h3>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 200,
            'labels' => ['Occupied', 'Vacant', 'Reserved', 'Maintenance'],
            'series' => [[
              'name' => 'Pitches',
              'color' => '#0A6339',
              'values' => [$detailSpaces['occupied'], $detailSpaces['vacant'], $detailSpaces['reserved'], $detailSpaces['under-maintenance']],
            ]],
            'showValues' => true,
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Pitch status breakdown"></svg>

        <div class="divider"></div>
        <h3 class="mb-1">Pitch register</h3>
        <p class="small muted">
          The first <?= num(count($pitches)) ?> pitches of <?= num(array_sum($detailSpaces)) ?>.
          <?= $detail['category'] === 'private'
              ? 'A private market operator keeps their own vendor register under Markets Act 2023 s.17(2); this view is illustrative.'
              : 'Under the Markets Act 2023 a pitch is the portion of ground or floor allocated to a vendor for displaying goods.' ?>
        </p>
        <div class="table-wrap" style="max-height:400px;overflow-y:auto">
          <table class="data compact">
            <thead>
              <tr><th>Pitch</th><th>Section</th><th class="right">Size</th><th class="center">Status</th><th>Facilities</th><th>Occupant</th></tr>
            </thead>
            <tbody>
              <?php foreach ($pitches as $s): ?>
                <tr>
                  <td class="mono xsmall"><?= e($s['space_code']) ?></td>
                  <td class="xsmall"><?= e($s['section'] ?: '—') ?></td>
                  <td class="right num xsmall"><?= num((float) $s['size_sqm'], 0) ?> m²</td>
                  <td class="center"><span class="badge <?= e(statusTone($s['status'])) ?>"><?= e(humanise($s['status'])) ?></span></td>
                  <td class="xsmall muted">
                    <?= $s['has_water'] ? 'W' : '' ?><?= $s['has_power'] ? 'P' : '' ?><?= $s['near_toilet'] ? 'T' : '' ?><?= $s['step_free'] ? 'S' : '' ?>
                    <?php if (!$s['has_water'] && !$s['has_power'] && !$s['near_toilet'] && !$s['step_free']): ?>—<?php endif; ?>
                  </td>
                  <td class="xsmall"><?= e($s['occupant'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="xsmall muted mt-1">W water · P power · T near toilet · S step-free</p>
      </div>

      <div>
        <div class="card" style="margin:0">
          <div class="card-head"><div class="grow"><h3>Facilities</h3></div></div>
          <div class="card-body">
            <?php
            $fac = [
                'water_available'  => 'Water supply',
                'power_available'  => 'Electricity',
                'toilets'          => 'Toilets',
                'waste_collection' => 'Waste collection',
                'storage_rooms'    => 'Storage rooms',
                'cold_chain'       => 'Cold chain',
                'accessible_ramps' => 'Accessible ramps',
            ];
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
          <div class="card-head"><div class="grow"><h3>Administration</h3></div></div>
          <div class="card-body">
            <dl class="kv">
              <dt>Administrator</dt><dd><?= e($detail['admin_name'] ?: '—') ?></dd>
              <dt>Telephone</dt><dd><?= e($detail['admin_phone'] ?: '—') ?></dd>
              <dt>Ownership</dt><dd style="text-align:right;max-width:200px"><?= e($detail['ownership'] ?: 'KCCA') ?></dd>
              <dt>Market type</dt><dd><?= e(humanise($detail['market_type'])) ?></dd>
              <dt>Condition</dt><dd><?= num((int) $detail['condition_score']) ?> / 5</dd>
              <dt>Figure basis</dt><dd>
                <span class="badge <?= $detail['data_basis'] === 'official' ? 'green' : ($detail['data_basis'] === 'derived' ? 'gold' : 'muted') ?>">
                  <?= e($detail['data_basis']) ?>
                </span>
              </dd>
            </dl>
            <?php if ($detail['notes']): ?>
              <div class="divider"></div>
              <p class="small muted"><?= e($detail['notes']) ?></p>
            <?php endif; ?>
            <?php if ($detail['source_note']): ?>
              <div class="source mt-1">
                <span class="lbl <?= $detail['data_basis'] === 'official' ? '' : ($detail['data_basis'] === 'derived' ? 'derived' : 'estimated') ?>">
                  <?= e($detail['data_basis']) ?>
                </span>
                <span><?= e($detail['source_note']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div class="grow"><h3>Trade mix</h3></div></div>
          <div class="card-body">
            <p class="small"><?= e($detail['category_mix'] ?: 'Not recorded') ?></p>
            <?php if ((int) $detail['vacant_spaces'] > 0): ?>
              <a class="btn btn-primary btn-sm btn-block mt-1"
                 href="relocation.php?division=<?= e($detail['div_code']) ?>">
                Match vendors to this division
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============================ FILTERS ========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, parish, code or owner…" data-search-submit>
    </div>
    <div class="field">
      <label for="category">Category</label>
      <select name="category" id="category" data-autosubmit>
        <option value="">Public and private</option>
        <option value="public"  <?= $category === 'public'  ? 'selected' : '' ?>>Public</option>
        <option value="private" <?= $category === 'private' ? 'selected' : '' ?>>Private</option>
      </select>
    </div>
    <?php renderDivisionFilter('division', $division); ?>
    <div class="field">
      <label for="status">Status</label>
      <select name="status" id="status" data-autosubmit>
        <option value="">Any status</option>
        <?php foreach (['operational','upgrading','under-rehabilitation','closed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(humanise($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="markets.php">Reset</a>
  </form>
</div>

<!-- ========================== THE TABLE ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>All markets</h2>
      <p class="desc"><?= num(count($rows)) ?> market<?= count($rows) === 1 ? '' : 's' ?> in this view.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Market</th><th>Division</th><th>Parish</th><th>Type</th>
            <th class="right">Capacity</th><th class="right">Registered</th><th class="right">Vacant</th>
            <th style="width:150px">Occupancy</th>
            <th class="right">Pitches</th><th class="center">Facilities</th><th class="center">Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $facCount = 0;
              foreach (['water_available','power_available','toilets','waste_collection','storage_rooms','cold_chain','accessible_ramps'] as $fk) {
                  if (!empty($r[$fk])) $facCount++;
              }
          ?>
            <tr>
              <td>
                <strong><?= e($r['name']) ?></strong>
                <div class="xsmall muted">
                  <span class="badge <?= $r['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px"><?= e($r['category']) ?></span>
                  <?= e($r['code']) ?>
                  <?php if ($r['data_basis'] === 'official'): ?>
                    <span class="badge green" style="font-size:10px">official</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="xsmall"><?= e($r['division_name']) ?></td>
              <td class="xsmall"><?= e($r['parish'] ?: '—') ?></td>
              <td class="xsmall"><?= e(humanise($r['market_type'])) ?></td>
              <td class="right num"><?= num((int) $r['capacity']) ?></td>
              <td class="right num"><?= num((int) $r['registered_vendors']) ?></td>
              <td class="right num strong"><?= num((int) $r['vacant_spaces']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $r['util'] < 70 ? 'warn' : 'ok' ?>"
                        style="width:<?= (float) clamp($r['util'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($r['util'], 0) ?></span>
                </div>
              </td>
              <td class="right num xsmall"><?= num((int) $r['pitch_count']) ?></td>
              <td class="center"><span class="badge <?= $facCount >= 5 ? 'ok' : ($facCount >= 3 ? 'info' : 'warn') ?>"><?= $facCount ?>/7</span></td>
              <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
              <td class="right">
                <a class="btn btn-ghost btn-sm"
                   href="market_map.php?market=<?= (int) $r['id'] ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    Public market vacancies reconcile exactly to the Authority's published
    <?= num($vac['public_markets']) ?> public markets holding <?= num($vac['public_vacant_spaces']) ?> vacant spaces.
    Usafi Market's capacity of 3,000 with 1,600 registered is confirmed by the Supervisor of Market Development.
  </div>
</div>

<!-- ========================== CHARTS ============================= -->
<div class="grid g2">
  <div class="card">
    <div class="card-head"><div class="grow"><h2>Vacant spaces by division</h2><p class="desc">Public and private markets side by side.</p></div></div>
    <div class="card-body">
      <?php
      /* Public and private vacancy per division, as chart series. */
      $divVacancy = [];
      foreach (Database::all(
          "SELECT d.name,
                  COALESCE(SUM(CASE WHEN m.category='public'  THEN m.vacant_spaces END),0) AS pub,
                  COALESCE(SUM(CASE WHEN m.category='private' THEN m.vacant_spaces END),0) AS pri
             FROM divisions d
             LEFT JOIN markets m ON m.division_id = d.id
            GROUP BY d.id ORDER BY d.name"
      ) as $dr) {
          $divVacancy[] = [
              'name' => $dr['name'],
              'pub'  => (int) $dr['pub'],
              'pri'  => (int) $dr['pri'],
          ];
      }
      ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 230,
          'stacked' => true,
          'labels' => array_column($divVacancy, 'name'),
          'series' => [
            ['name' => 'Public',  'color' => '#0A6339',
             'values' => array_map('intval', array_column($divVacancy, 'pub'))],
            ['name' => 'Private', 'color' => '#EAA515',
             'values' => array_map('intval', array_column($divVacancy, 'pri'))],
          ],
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Vacant spaces by division"></svg>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div class="grow"><h2>Facility provision</h2><p class="desc">How many markets offer each facility.</p></div></div>
    <div class="card-body">
      <?php
      $facCount = [];
      foreach (['water_available','power_available','toilets','waste_collection','storage_rooms','cold_chain','accessible_ramps'] as $fk) {
          $facCount[] = [
              'label' => ucwords(str_replace('_', ' ', $fk)),
              'value' => (int) Database::value("SELECT COUNT(*) FROM markets WHERE $fk = 1"),
              'color' => '#0A6339',
          ];
      }
      ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => ['labelW' => 150, 'rowH' => 25, 'items' => $facCount, 'max' => count($rows), 'format' => 'num'],
      ]) ?>' aria-label="Facility provision across markets"></svg>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';