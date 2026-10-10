<?php
/**
 * Vendor Register — the register of vendors under Markets Act 2023 s.18.
 * Searchable, filterable, with a full record view.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
/* The register carries names, national ID numbers and telephone numbers.
   A trader reaches their own record through their profile; the register as
   a whole is for the officers who administer it. */
Auth::requirePermission('report');

$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$category = (string) ($_GET['category'] ?? '');
$status   = (string) ($_GET['status'] ?? '');
$zoneId   = (int) ($_GET['zone'] ?? 0);
$q        = (string) ($_GET['search'] ?? '');
$pageNo    = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 40;
$vendorId = (int) ($_GET['id'] ?? 0);

$where  = ['1 = 1'];
$params = [];
if ($division !== '') { $where[] = 'd.code = :div';  $params['div'] = $division; }
if ($category !== '') { $where[] = 'v.business_category = :cat'; $params['cat'] = $category; }
if ($status !== '')   { $where[] = 'v.status = :st'; $params['st'] = $status; }
if ($zoneId > 0)      { $where[] = 'v.current_zone_id = :zid'; $params['zid'] = $zoneId; }
if ($q !== '') {
    [$likeSql, $likeParams] = orLike('q',
        ['v.full_name', 'v.vendor_code', 'v.phone', 'v.national_id', 'v.business_description'], $q);
    $where[] = $likeSql;
    $params += $likeParams;
}
$whereSql = implode(' AND ', $where);

$total = (int) Database::value(
    "SELECT COUNT(*) FROM vendors v JOIN divisions d ON d.id = v.division_id WHERE $whereSql",
    $params
);
$pageCount = max(1, (int) ceil($total / $perPage));
$pageNo   = min($pageNo, $pageCount);
$offset  = ($pageNo - 1) * $perPage;

$rows = Database::all(
    "SELECT v.*, d.name AS division_name, d.code AS div_code, z.name AS zone_name
       FROM vendors v
       JOIN divisions d ON d.id = v.division_id
       LEFT JOIN trade_zones z ON z.id = v.current_zone_id
      WHERE $whereSql
      ORDER BY v.full_name
      LIMIT $perPage OFFSET $offset",
    $params
);

/* ---------------- Register-wide profile ---------------- */
$profile = Database::one(
    "SELECT COUNT(*) AS total,
            SUM(gender = 'female') AS women,
            SUM(is_single_parent) AS single_parents,
            SUM(is_person_with_disability) AS pwd,
            SUM(is_youth) AS youth,
            SUM(has_trading_licence) AS licensed,
            SUM(is_veteran_ex_enforcee) AS ex_enforcee,
            SUM(dependants) AS dependants,
            ROUND(AVG(startup_capital_ugx)) AS avg_capital,
            ROUND(AVG(monthly_turnover_ugx)) AS avg_turnover,
            ROUND(AVG(monthly_turnover_ugx) * 12) AS avg_annual,
            MIN(started_year) AS oldest
       FROM vendors"
);

$byCategory = Database::all(
    "SELECT business_category, COUNT(*) AS n, SUM(gender='female') AS women
       FROM vendors GROUP BY business_category ORDER BY n DESC"
);

$statusCounts = vendorStatusCounts();
$categories = Database::column('SELECT DISTINCT business_category FROM vendors ORDER BY business_category');

/* ---------------- Single vendor record ---------------- */
$detail = null;
$detailAllocs = [];
if ($vendorId > 0) {
    $detail = Database::one(
        'SELECT v.*, d.name AS division_name, z.name AS zone_name, z.code AS zone_code,
                z.road_or_feature
           FROM vendors v
           JOIN divisions d ON d.id = v.division_id
           LEFT JOIN trade_zones z ON z.id = v.current_zone_id
          WHERE v.id = ? LIMIT 1',
        [$vendorId]
    );
    if ($detail) {
        $detailAllocs = Database::all(
            'SELECT a.*, m.name AS market_name, m.lat, m.lng, s.space_code
               FROM allocations a
               JOIN markets m ON m.id = a.market_id
               LEFT JOIN market_spaces s ON s.id = a.market_space_id
              WHERE a.vendor_id = ? ORDER BY a.created_at DESC',
            [$vendorId]
        );
    }
}

$pageTitle = 'Vendor Register';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Vendor Register
    </div>
    <div class="eyebrow">Trade order · Markets Act 2023 s.18</div>
    <h1>Vendor Register</h1>
    <p>
      Every surveyed trader, with the vulnerability factors that decide who is offered space first.
    </p>
  </div>
  <div class="actions">
    <?php if (Auth::can('allocate')): ?>
      <button class="btn btn-primary" data-modal="api/form.php?form=vendor">
        <?= KCCA_icon('plus', 15) ?> Register a vendor
      </button>
    <?php endif; ?>
    <a class="btn btn-ghost" href="relocation.php"><?= KCCA_icon('swap', 15) ?> Match to space</a>
    <a class="btn btn-ghost" href="reports.php"><?= KCCA_icon('download', 15) ?> Export</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<?php if ($detail): $years = max(0, (int) date('Y') - (int) $detail['started_year']); ?>
<!-- ======================= VENDOR RECORD ========================= -->
<div class="card" id="vendor-detail">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="vendors.php">Register</a><span class="sep">/</span><?= e($detail['vendor_code']) ?>
      </div>
      <h2><?= e($detail['full_name']) ?></h2>
      <p class="desc">
        <?= e($detail['vendor_code']) ?> ·
        <?= e(tradeDescription($detail['business_category'])) ?> ·
        <?= e($detail['division_name']) ?> Division ·
        <?= e($detail['parish'] ?: '—') ?> parish ·
        trading since <?= (int) $detail['started_year'] ?> (<?= $years ?> years)
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($detail['status'])) ?>"><?= e(humanise($detail['status'])) ?></span>
      <?php if (Auth::can('allocate')): ?>
        <button class="btn btn-ghost btn-sm"
                data-modal="api/form.php?form=vendor&amp;vendor_id=<?= (int) $detail['id'] ?>">Edit record</button>
      <?php endif; ?>
      <a class="btn btn-primary btn-sm" href="relocation.php?vendor=<?= (int) $detail['id'] ?>">Match to space</a>
      <a class="btn btn-quiet btn-sm" href="vendors.php">Close</a>
    </div>
  </div>

  <div class="card-body">
    <div class="grid g-3-2">
      <div>
        <div class="grid g4 mb-2">
          <div class="stat info">
            <div class="label">Monthly turnover</div>
            <div class="value" style="font-size:19px"><?= ugxShort((int) $detail['monthly_turnover_ugx']) ?></div>
            <div class="meta">capital <?= ugxShort((int) $detail['startup_capital_ugx']) ?></div>
          </div>
          <div class="stat">
            <div class="label">Household size</div>
            <div class="value"><?= num((int) $detail['household_size']) ?></div>
            <div class="meta"><?= num((int) $detail['dependants']) ?> dependants</div>
          </div>
          <div class="stat gold">
            <div class="label">Years in trade</div>
            <div class="value"><?= $years ?></div>
            <div class="meta">since <?= (int) $detail['started_year'] ?></div>
          </div>
          <div class="stat <?= $detail['has_trading_licence'] ? 'ok' : 'warn' ?>">
            <div class="label">Licence</div>
            <div class="value" style="font-size:19px"><?= $detail['has_trading_licence'] ? 'Held' : 'None' ?></div>
            <div class="meta"><?= e(humanise($detail['licence_class'])) ?></div>
          </div>
        </div>

        <div class="grid g2">
          <div class="card" style="margin:0">
            <div class="card-head"><div class="grow"><h3>Personal record</h3></div></div>
            <div class="card-body">
              <dl class="kv">
                <dt>National ID</dt><dd><?= e($detail['national_id'] ?: 'Not recorded') ?></dd>
                <dt>Telephone</dt><dd><?= e($detail['phone'] ?: '—') ?></dd>
                <dt>Alternative</dt><dd><?= e($detail['alternative_phone'] ?: '—') ?></dd>
                <dt>Gender</dt><dd><?= e(ucfirst($detail['gender'])) ?></dd>
                <dt>Date of birth</dt><dd><?= e(fmtDate($detail['date_of_birth'])) ?></dd>
                <dt>Licence year</dt><dd><?= $detail['licence_year'] ? (int) $detail['licence_year'] : '—' ?></dd>
                <dt>Trading days</dt><dd><?= e($detail['trading_days'] ?: '—') ?></dd>
                <dt>Association</dt><dd><?= e($detail['association'] ?: 'None') ?></dd>
              </dl>
            </div>
          </div>

          <div class="card" style="margin:0">
            <div class="card-head"><div class="grow"><h3>Current position</h3></div></div>
            <div class="card-body">
              <dl class="kv">
                <dt>Trading at</dt><dd style="text-align:right;max-width:210px"><?= e($detail['zone_name'] ?: 'No site recorded') ?></dd>
                <dt>Road or feature</dt><dd style="text-align:right;max-width:210px"><?= e($detail['road_or_feature'] ?: '—') ?></dd>
                <dt>Zone reference</dt><dd><?= e($detail['zone_code'] ?: '—') ?></dd>
                <dt>Division</dt><dd><?= e($detail['division_name']) ?></dd>
                <dt>Parish</dt><dd><?= e($detail['parish'] ?: '—') ?></dd>
                <dt>Goods</dt><dd style="text-align:right;max-width:210px"><?= e($detail['business_description'] ?: '—') ?></dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="divider"></div>
        <h3 class="mb-1">Relocation history</h3>
        <?php if (!$detailAllocs): ?>
          <p class="muted small">No allocation has been issued to this vendor yet.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data compact">
              <thead>
                <tr><th>Reference</th><th>Market</th><th>Pitch</th><th class="right">Score</th><th class="right">Distance</th><th class="center">Status</th><th>Offered</th></tr>
              </thead>
              <tbody>
                <?php foreach ($detailAllocs as $a): ?>
                  <tr>
                    <td class="mono xsmall"><?= e($a['reference']) ?></td>
                    <td><?= e($a['market_name']) ?></td>
                    <td class="xsmall"><?= e($a['space_code'] ?: '—') ?></td>
                    <td class="right num"><?= num((float) $a['total_score'], 1) ?></td>
                    <td class="right num"><?= num((float) $a['distance_km'], 1) ?> km</td>
                    <td class="center"><span class="badge <?= e(statusTone($a['status'])) ?>"><?= e(humanise($a['status'])) ?></span></td>
                    <td class="xsmall"><?= e(fmtDate($a['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($detailAllocs[0]['reasoning']): ?>
            <div class="source mt-1">
              <span class="lbl">reasoning</span>
              <span style="white-space:pre-line"><?= e($detailAllocs[0]['reasoning']) ?></span>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div>
        <div class="card" style="margin:0">
          <div class="card-head"><div class="grow"><h3>Equity factors</h3></div></div>
          <div class="card-body">
            <div class="stack-s">
              <?php
              $equity = [
                  ['is_person_with_disability', 'Person with disability', '+22 to equity score'],
                  ['is_single_parent',           'Single parent',           '+18 to equity score'],
                  ['is_veteran_ex_enforcee',     'Former enforcement agent', '+12 to equity score'],
                  ['is_youth',                   'Below 30 years',           '+8 to equity score'],
              ];
              ?>
              <?php foreach ($equity as [$col, $label, $effect]): ?>
                <div class="row-tight">
                  <span class="badge <?= !empty($detail[$col]) ? 'brand' : 'muted' ?>">
                    <?= !empty($detail[$col]) ? '&#10003;' : '&#8211;' ?>
                  </span>
                  <span class="<?= !empty($detail[$col]) ? 'strong' : 'muted' ?>"><?= e($label) ?></span>
                  <span class="right xsmall muted"><?= e($effect) ?></span>
                </div>
              <?php endforeach; ?>
              <div class="row-tight">
                <span class="badge info"><?= num((int) $detail['dependants']) ?></span>
                <span class="strong">Dependants</span>
                <span class="right xsmall muted">+<?= num(min(20, (int) $detail['dependants'] * 2.5), 1) ?> to equity score</span>
              </div>
            </div>
            <div class="divider"></div>
            <p class="small muted" style="margin:0">
              Equity is weighted at <?= pct(RELOCATION_WEIGHTS['equity'] * 100, 0) ?> of the relocation score.
              It exists so that when two traders fit the same market equally well, the one with less
              to lose is offered space first.
            </p>
          </div>
        </div>

        <?php if ($detail['phone']): ?>
          <div class="card">
            <div class="card-head"><div class="grow"><h3>Contact</h3></div></div>
            <div class="card-body">
              <p class="small mb-1"><strong><?= e($detail['phone']) ?></strong></p>
              <?php if ($detail['alternative_phone']): ?>
                <p class="small muted mb-1">Alternative: <?= e($detail['alternative_phone']) ?></p>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="tel:<?= e($detail['phone']) ?>">
                <?= KCCA_icon('phone', 13) ?> Call the trader
              </a>
              <?php if ($detail['association']): ?>
                <div class="divider"></div>
                <p class="xsmall muted" style="margin:0">
                  Member of <strong><?= e($detail['association']) ?></strong>. KCCA engages the
                  trader associations — NETA, FUTA, UNATA, KACITA, KATA, UATEA and KAAFO — on trade order.
                </p>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ====================== REGISTER PROFILE ====================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Vendors in register</div>
    <div class="value"><?= num((int) ($profile['total'] ?? 0)) ?></div>
    <div class="meta">surveyed across the city</div>
  </div>
  <div class="stat gold">
    <div class="label">Women</div>
    <div class="value"><?= num((int) ($profile['women'] ?? 0)) ?></div>
    <div class="meta"><?= pct($profile['total'] > 0 ? (int) $profile['women'] / (int) $profile['total'] * 100 : 0) ?> of the register</div>
  </div>
  <div class="stat brand">
    <div class="label">Single parents</div>
    <div class="value"><?= num((int) ($profile['single_parents'] ?? 0)) ?></div>
    <div class="meta">highest equity weighting</div>
  </div>
  <div class="stat info">
    <div class="label">Dependants</div>
    <div class="value"><?= num((int) ($profile['dependants'] ?? 0)) ?></div>
    <div class="meta">relying on these incomes</div>
  </div>
  <div class="stat ok">
    <div class="label">Average turnover</div>
    <div class="value" style="font-size:20px"><?= ugxShort((int) ($profile['avg_turnover'] ?? 0)) ?></div>
    <div class="meta">per month, per trader</div>
  </div>
</div>

<!-- ============================ FILTERS ========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label for="search">Search</label>
      <input type="search" id="search" name="search" value="<?= e($q) ?>"
             placeholder="Name, code, phone or national ID…" data-search-submit>
    </div>
    <?php renderDivisionFilter('division', $division); ?>
    <div class="field">
      <label for="category">Trade</label>
      <select name="category" id="category" data-autosubmit>
        <option value="">All trades</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e(humanise($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="status">Stage</label>
      <select name="status" id="status" data-autosubmit>
        <option value="">All stages</option>
        <?php foreach ($statusCounts as $st => $n): ?>
          <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(humanise($st)) ?> (<?= num($n) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="vendors.php">Reset</a>
  </form>
</div>

<!-- ============================ TABLE =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Register</h2>
      <p class="desc">
        <?= num($total) ?> vendor<?= $total === 1 ? '' : 's' ?> match.
        Showing <?= num($total > 0 ? $offset + 1 : 0) ?>–<?= num(min($offset + $perPage, $total)) ?>.
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Vendor</th><th>Trade</th><th>Division</th><th>Current site</th>
            <th class="right">Turnover</th><th class="center">Household</th>
            <th class="center">Licence</th><th class="center">Stage</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $v): ?>
            <tr>
              <td>
                <a href="vendors.php?id=<?= (int) $v['id'] ?>"><strong><?= e($v['full_name']) ?></strong></a>
                <div class="xsmall muted">
                  <?= e($v['vendor_code']) ?>
                  <?php if ($v['is_single_parent']): ?> · single parent<?php endif; ?>
                  <?php if ($v['is_person_with_disability']): ?> · PWD<?php endif; ?>
                  <?php if ($v['gender'] === 'female'): ?> · she/her<?php endif; ?>
                </div>
              </td>
              <td class="xsmall"><?= e(humanise($v['business_category'])) ?></td>
              <td class="xsmall"><?= e($v['division_name']) ?></td>
              <td class="xsmall"><?= e($v['zone_name'] ?: '—') ?></td>
              <td class="right num xsmall"><?= ugxShort((int) $v['monthly_turnover_ugx']) ?></td>
              <td class="center xsmall">
                <?= num((int) $v['household_size']) ?>
                <?php if ((int) $v['dependants'] > 0): ?>
                  <span class="muted">(<?= num((int) $v['dependants']) ?> dep)</span>
                <?php endif; ?>
              </td>
              <td class="center">
                <?php if ($v['has_trading_licence']): ?>
                  <span class="badge ok" title="<?= e(humanise($v['licence_class'])) ?>">Held</span>
                <?php else: ?>
                  <span class="badge warn">None</span>
                <?php endif; ?>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($v['status'])) ?>"><?= e(humanise($v['status'])) ?></span></td>
              <td class="right nowrap">
                <?php if (Auth::can('allocate')): ?>
                  <a class="btn btn-ghost btn-sm" title="Edit this record"
                     data-modal="api/form.php?form=vendor&amp;vendor_id=<?= (int) $v['id'] ?>">Edit</a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="relocation.php?vendor=<?= (int) $v['id'] ?>">Match</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty">
      <div class="big"><?= KCCA_icon('people', 32) ?></div>
      <h3>No vendor matches this filter</h3>
      <p>Clear the search box or widen the division and stage filters.</p>
      <a class="btn btn-ghost" href="vendors.php">Reset filters</a>
    </div>
  <?php endif; ?>

  <?php if ($pageCount > 1): ?>
    <div class="card-foot">
      <div class="row">
        <span class="muted small">Page <?= num($pageNo) ?> of <?= num($pageCount) ?></span>
        <span class="right"></span>
        <?php if ($pageNo > 1): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(urlWith(['page' => $pageNo - 1])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($pageNo < $pageCount): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(urlWith(['page' => $pageNo + 1])) ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- =========================== CHARTS ============================ -->
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div class="grow"><h2>Trade and gender</h2><p class="desc">Vendors by trade, with the women among them.</p></div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 250,
          'labels' => array_map(static fn($r) => humanise($r['business_category']), $byCategory),
          'series' => [
            ['name' => 'Men',    'color' => '#DC351D', 'values' => array_map(static fn($r) => (int) $r['n'] - (int) $r['women'], $byCategory)],
            ['name' => 'Women',  'color' => '#EAA515', 'values' => array_map(static fn($r) => (int) $r['women'], $byCategory)],
          ],
          'stacked' => true,
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Vendors by trade and gender"></svg>
      <div class="legend">
        <span class="item"><span class="sw" style="background:#DC351D"></span> Men</span>
        <span class="item"><span class="sw" style="background:#EAA515"></span> Women</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow"><h2>Programme stage</h2><p class="desc">Where every vendor in the register currently stands.</p></div>
    </div>
    <div class="card-body">
      <?php
      $stageOrder = ['surveyed','notified','offered-space','relocated','declined','deferred','complaint'];
      $stageTone  = ['surveyed'=>'muted','notified'=>'info','offered-space'=>'warn','relocated'=>'ok','declined'=>'bad','deferred'=>'warn','complaint'=>'bad'];
      ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 140, 'rowH' => 26,
          'items' => array_values(array_filter(array_map(static function (string $s) use ($statusCounts): ?array {
              return isset($statusCounts[$s]) ? ['label' => humanise($s), 'value' => $statusCounts[$s], 'color' => ''] : null;
          }, $stageOrder))),
        ],
      ]) ?>' aria-label="Vendors by programme stage"></svg>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';