<?php
/**
 * Dashboard — the screen each officer lands on.
 *
 * What appears here depends entirely on who is signed in: a Division Mayor
 * gets their own division and nothing else, the Executive Director gets the
 * citywide position and the approvals put to them, Internal Audit gets the
 * trail of decisions rather than the decisions themselves.
 *
 * The sections are declared by lib/RoleDashboard.php, which is also where
 * every figure is scoped. This file only renders — it holds no queries of
 * its own beyond what a section needs to draw.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f      = facts();
$dash   = RoleDashboard::build();
$scope  = RoleDashboard::scope();
$me     = Auth::user();
$remit  = Auth::remit();

/* The funnel, once. Every role gets it; nobody gets it twice. */
$funnel = [
    ['label' => 'To relocate',   'value' => (int) Database::value("SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE 1=1" . RoleDashboard::scopeSql($scope)), 'color' => '#464646'],
    ['label' => 'Offered space', 'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'offered-space'" . RoleDashboard::scopeSql($scope)), 'color' => '#EAA515'],
    ['label' => 'Relocated',     'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'relocated'" . RoleDashboard::scopeSql($scope)), 'color' => '#1B7F4D'],
    ['label' => 'Declined',      'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'declined'" . RoleDashboard::scopeSql($scope)), 'color' => '#C0261A'],
];

$stageOrder = ['surveyed', 'notified', 'offered-space', 'relocated', 'declined', 'deferred', 'complaint'];
$stageData  = [];
foreach ($stageOrder as $s) {
    $n = (int) Database::value(
        "SELECT COUNT(*) FROM vendors WHERE status = :s" . RoleDashboard::scopeSql($scope),
        ['s' => $s]
    );
    if ($n > 0) {
        $stageData[] = ['label' => humanise($s), 'value' => $n, 'color' => '#DC351D'];
    }
}

$hasDecisions = $dash['decisions'][0]['tone'] !== 'ok';

/* Recent activity across the whole programme. */
$activity = Database::all(
    "SELECT e.occurred_at, e.event_type, e.note, v.full_name, m.name AS market_name
       FROM relocation_events e
       LEFT JOIN vendors v ON v.id = e.vendor_id
       LEFT JOIN markets m ON m.id = e.market_id
      ORDER BY e.occurred_at DESC, e.id DESC LIMIT 8"
);

$pageTitle = 'Dashboard';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb"><?= e($remit['label']) ?></div>
    <h1>Dashboard</h1>
    <p><?= e($remit['blurb']) ?></p>
  </div>
  <div class="actions">
    <span class="badge <?= e($dash['scope'] === 'division' ? 'gold' : 'green') ?>">
      <?= e(match ($dash['scope']) {
        'system'      => 'Whole system',
        'citywide'    => 'All five divisions',
        'division'    => 'Own division only',
        'oversight'   => 'Oversight, no operations',
        'directorate' => 'Directorate remit',
        default       => 'Read only',
      }) ?>
    </span>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ================= WHAT THE AUTHORITY DOES ================== -->
<?php if (Auth::role() !== 'vendor'): ?>
  <?php renderActivitySlideshow(true, 2000); ?>
<?php endif; ?>

<?php if ($hasDecisions): ?>
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Waiting on you</h2>
        <p class="desc">Only the things your role can act on.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid g3">
        <?php foreach ($dash['decisions'] as $d): ?>
          <a class="criterion" href="<?= e($d['href']) ?>" style="text-decoration:none;display:block">
            <span class="cl"><?= e($d['label']) ?></span>
            <span class="cv"><span class="badge <?= e($d['tone']) ?>"><?= KCCA_icon('external', 12) ?></span></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ======================= HEADLINE FIGURES ==================== -->
<div class="grid g4 mb-2">
  <?php foreach ($dash['stats'] as $s): ?>
    <div class="stat <?= e($s['tone']) ?>">
      <div class="label"><?= e($s['label']) ?></div>
      <div class="value"><?= e($s['value']) ?></div>
      <div class="meta"><?= e($s['meta']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ============================ ACTIVITY ======================== -->
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Recent activity</h2>
      <p class="desc">The latest decisions and movements across the programme.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="audit.php">Full audit trail</a>
  </div>
  <div class="card-body">
    <?php if ($activity): ?>
      <ul class="activity">
        <?php foreach ($activity as $a): ?>
          <li>
            <time><?= e(fmtDate($a['occurred_at'], 'd M H:i')) ?></time>
            <p>
              <span class="tag"><?= e(humanise($a['event_type'])) ?></span>
              <strong><?= e($a['full_name'] ?: '—') ?></strong>
              <?php if ($a['market_name']): ?>
                → <?= e($a['market_name']) ?>
              <?php endif; ?>
              <?php if ($a['note']): ?>
                <span class="muted"> · <?= e(truncateText($a['note'], 70)) ?></span>
              <?php endif; ?>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('log', 28) ?></div>
        <h3>No activity yet</h3>
        <p>Generating a recommendation or issuing an offer starts the trail.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============================ SECTIONS ======================= -->
<?php foreach ($dash['sections'] as $section): ?>
  <?php
  $id = $section['render'];
  require __DIR__ . '/../partials/dash_' . $id . '.php';
  ?>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <div class="row">
      <div class="grow">
        <strong><?= e($f['authority']['legal_name']) ?></strong>
        <div class="xsmall muted">
          Signed in as <?= e($me['full_name'] ?? '') ?> ·
          <?= e($remit['label']) ?> ·
          <?= e($me['directorate'] ?: 'No directorate recorded') ?>
        </div>
      </div>
      <div class="right">
        <a class="btn btn-ghost btn-sm" href="https://kcca.go.ug" target="_blank" rel="noopener">
          <?= KCCA_icon('external', 13) ?> kcca.go.ug
        </a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>