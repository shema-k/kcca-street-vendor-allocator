<?php
/**
 * Dashboard — the screen each officer lands on.
 *
 * What appears here depends on who is signed in: a Division Mayor
 * sees their own division and nothing else; the Executive Director
 * sees the whole Authority. The figures are scoped before they are
 * fetched, so nobody sees another tier's numbers by accident.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f     = facts();
$vac   = $f['vacancy'];
$city  = $f['city'];
$me    = Auth::user();
$scope = RoleDashboard::scope();

/* ---- headline figures, scoped ---- */
$vendorCount = (int) Database::value(
    "SELECT COUNT(*) FROM vendors WHERE 1=1" . RoleDashboard::scopeSql($scope)
);
$toMove = (int) Database::value(
    "SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones z
      WHERE 1=1" . str_replace('v.', 'z.', $scope['sql'])
);
$relocated = (int) Database::value(
    "SELECT COUNT(*) FROM vendors WHERE status='relocated'" . $scope['sql']
);
$vacant = (int) Database::value(
    "SELECT COALESCE(SUM(vacant_spaces),0) FROM markets m
      WHERE 1=1" . str_replace('v.', 'm.', $scope['sql'])
);
$pending = (int) Database::value(
    "SELECT COUNT(*) FROM vendors WHERE status IN ('offered-space','notified')" . $scope['sql']
);

$clearance = $toMove > 0 ? round($relocated / $toMove * 100, 1) : 0.0;

/* ---- division progress (citywide only) ---- */
$divisions = [];
if ($scope['level'] === 'citywide') {
    foreach (Database::all(
        "SELECT d.id, d.name, d.code, d.mayor,
                (SELECT COALESCE(SUM(z.estimated_vendors),0) FROM trade_zones z WHERE z.division_id = d.id) AS affected,
                (SELECT COUNT(*) FROM vendors v WHERE v.division_id = d.id AND v.status='relocated') AS relocated,
                (SELECT COALESCE(SUM(m.vacant_spaces),0) FROM markets m WHERE m.division_id = d.id) AS vacant
           FROM divisions d ORDER BY d.name"
    ) as $d) {
        $d['clearance'] = $d['affected'] > 0 ? round($d['relocated'] / $d['affected'] * 100, 1) : 0.0;
        $divisions[] = $d;
    }
}

/* ---- recent activity ---- */
$activity = Database::all(
    "SELECT e.occurred_at, e.event_type, e.note, v.full_name, m.name AS market_name
       FROM relocation_events e
       LEFT JOIN vendors v ON v.id = e.vendor_id
       LEFT JOIN markets m ON m.id = e.market_id
      ORDER BY e.occurred_at DESC, e.id DESC LIMIT 8"
);

/* ---- trade mix ---- */
$tradeMix = Database::all(
    "SELECT business_category, COUNT(*) AS n, SUM(gender='female') AS women
       FROM vendors WHERE 1=1" . $scope['sql'] . "
      GROUP BY business_category ORDER BY n DESC LIMIT 8"
);

/* ---- top markets by vacancy ---- */
$topMarkets = Database::all(
    "SELECT m.id, m.name, m.vacant_spaces, m.capacity, d.name AS division_name
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE m.vacant_spaces > 0
      ORDER BY m.vacant_spaces DESC LIMIT 6"
);

$pageTitle = 'Dashboard';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb"><?= e($scope['label']) ?></div>
    <div class="eyebrow">Kampala Capital City Authority · Trade Order Command</div>
    <h1><?= e($scope['level'] === 'division' ? $scope['label'] . ' dashboard' : 'Executive dashboard') ?></h1>
    <p>
      <?= e($scope['level'] === 'division'
          ? 'Your division’s position: vendors to move, space available, and progress.'
          : 'The whole programme across all five divisions, at a glance.') ?>
    </p>
  </div>
  <div class="actions">
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
    <a class="btn btn-ghost" href="reports.php"><?= KCCA_icon('download', 15) ?> Reports</a>
  </div>
</div>

<!-- ===================== HEADLINE FIGURES ==================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Vendors in register</div>
    <div class="value"><?= num($vendorCount) ?></div>
    <div class="meta">of <?= num($city['vendors_surveyed']) ?> surveyed citywide</div>
  </div>
  <div class="stat green">
    <div class="label">Relocated</div>
    <div class="value"><?= num($relocated) ?></div>
    <div class="meta"><?= pct($clearance) ?> clearance</div>
  </div>
  <div class="stat info">
    <div class="label">Space available</div>
    <div class="value"><?= num($vacant) ?></div>
    <div class="meta">pitches</div>
  </div>
  <div class="stat warn">
    <div class="label">Awaiting a decision</div>
    <div class="value"><?= num($pending) ?></div>
    <div class="meta">offers &amp; notices outstanding</div>
  </div>
  <div class="stat gold">
    <div class="label">To relocate</div>
    <div class="value"><?= num($toMove) ?></div>
    <div class="meta">vendors</div>
  </div>
</div>

<!-- ===================== DIVISION PROGRESS ==================== -->
<?php if ($divisions): ?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Progress by division</h2>
      <p class="desc">Clearance against the number of vendors each division has to move.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Division</th><th class="right">To move</th><th class="right">Moved</th>
              <th class="right">Vacant</th><th style="width:130px">Clearance</th></tr>
        </thead>
        <tbody>
          <?php foreach ($divisions as $d): ?>
            <tr>
              <td><strong><?= e($d['name']) ?></strong></td>
              <td class="right num"><?= num($d['affected']) ?></td>
              <td class="right num"><?= num($d['relocated']) ?></td>
              <td class="right num"><?= num($d['vacant']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $d['clearance'] < 25 ? 'bad' : ($d['clearance'] < 60 ? 'warn' : 'ok') ?>"
                        style="width<?= (float) clamp($d['clearance'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($d['clearance'], 0) ?></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid g-2-1">

  <!-- ===================== TOP MARKETS ==================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Markets with the most space</h2>
        <p class="desc">Where the Authority can place vendors first.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="locations.php">All locations</a>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 168, 'rowH' => 26,
          'items' => array_map(static fn(array $m): array => [
            'label' => $m['name'],
            'value' => (int) $m['vacant'],
            'color' => '#0A6339',
          ], $topMarkets),
        ],
      ]) ?>' aria-label="Vacant pitches by market"></svg>
    </div>
  </div>

  <!-- ===================== TRADE MIX ==================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>What the traders sell</h2>
        <p class="desc">The trade mix the reach model scores against demand.</p>
      </div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 200,
          'labels' => array_map(static fn(array $r): string => humanise($r['business_category']), $tradeMix),
          'series' => [
            ['name' => 'All vendors', 'color' => '#DC351D',
             'values' => array_map('intval', array_column($tradeMix, 'n'))],
            ['name' => 'Women', 'color' => '#EAA515',
             'values' => array_map('intval', array_column($tradeMix, 'women'))],
          ],
          'stacked' => false,
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Vendors by trade"></svg>
      <div class="legend">
        <span class="item"><span class="sw" style="background:#DC351D"></span> All vendors</span>
        <span class="item"><span class="sw" style="background:#EAA515"></span> Women</span>
      </div>
    </div>
  </div>
</div>

<!-- ===================== RECENT ACTIVITY ==================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Recent activity</h2>
      <p class="desc">Every state change is written to the relocation history.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="audit.php">Audit trail</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>When</th><th>Event</th><th>Vendor</th><th>Location</th><th>Note</th></tr>
        </thead>
        <tbody>
          <?php foreach ($activity as $a): ?>
            <tr>
              <td class="xsmall nowrap"><?= e(fmtDate($a['occurred_at'], 'd M H:i')) ?></td>
              <td><span class="badge <?= in_array($a['event_type'], ['declined','withdrawn','cancelled'], true) ? 'warn' : 'info' ?>">
                <?= e(humanise($a['event_type'])) ?></span></td>
              <td class="xsmall"><?= e($a['full_name'] ?: '—') ?></td>
              <td class="xsmall"><?= e($a['market_name'] ?: '—') ?></td>
              <td class="xsmall muted" style="max-width:340px"><?= e(truncateText($a['note'] ?? '', 80)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$activity): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('log', 28) ?></div>
        <h3>No activity recorded yet</h3>
        <p>Generating a recommendation or issuing an offer starts the history.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>