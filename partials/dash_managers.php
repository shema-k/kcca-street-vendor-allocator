<?php
/**
 * Dashboard section: your managers (Mayor).
 *
 * A Mayor does not run the work — they oversee the two managers under them.
 * This shows what Planning and Enforcement are actually doing, side by side,
 * so oversight is a glance rather than an enquiry.
 */

declare(strict_types=1);

/* Named $scopeSql, not $scope: every partial on this page shares $scope,
   and the next one to run still expects the array. */
$scopeSql = RoleDashboard::scopeSql(RoleDashboard::scope());

/* Planning: how much is planned, matched and left. */
$planned = [
    'Sites surveyed'    => (int) Database::value("SELECT COUNT(*) FROM trade_zones WHERE 1=1" . $scopeSql),
    'Vendors to move'   => (int) Database::value("SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE 1=1" . $scopeSql),
    'Matched to space'  => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status IN ('offered-space','relocated')" . $scopeSql),
    'Still to match'    => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status IN ('surveyed','notified')" . $scopeSql),
];

/* Enforcement: what is out there and what has been closed off. */
$enforced = [
    'Records opened'    => (int) Database::value("SELECT COUNT(*) FROM enforcement_actions WHERE 1=1"),
    'In progress'       => (int) Database::value("SELECT COUNT(*) FROM enforcement_actions WHERE status='in-progress'"),
    'Completed'         => (int) Database::value("SELECT COUNT(*) FROM enforcement_actions WHERE status='completed'"),
    'Escalated'         => (int) Database::value("SELECT COUNT(*) FROM enforcement_actions WHERE status='escalated'"),
];

/* Their officers. */
$managers = Database::all(
    "SELECT u.id, u.full_name, u.title, u.directorate, u.role, u.last_login_at
       FROM users u
      WHERE u.role IN ('planner','enforcement') AND u.is_active = 1
      ORDER BY FIELD(u.role,'planner','enforcement')"
);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Your managers</h2>
      <p class="desc">
        Planning decides how the relocation is done. Enforcement carries it out on the ground.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="enforcement.php">Open the enforcement log</a>
  </div>
  <div class="card-body">
    <div class="grid g2 mb-2">
      <div>
        <h4 class="mb-1">Planning</h4>
        <dl class="kv">
          <?php foreach ($planned as $label => $n): ?>
            <dt><?= e($label) ?></dt><dd><?= num($n) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
      <div>
        <h4 class="mb-1">Enforcement</h4>
        <dl class="kv">
          <?php foreach ($enforced as $label => $n): ?>
            <dt><?= e($label) ?></dt><dd><?= num($n) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>

    <div class="divider"></div>
    <h4 class="mb-1">The officers</h4>
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Officer</th><th>Position</th><th>Directorate</th>
              <th class="center">Last signed in</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($managers as $m): ?>
            <tr>
              <td><strong><?= e($m['full_name']) ?></strong></td>
              <td class="xsmall"><?= e($m['title']) ?></td>
              <td class="xsmall"><?= e($m['directorate'] ? humanise($m['directorate']) : '—') ?></td>
              <td class="center xsmall muted">
                <?= $m['last_login_at'] ? e(fmtDate($m['last_login_at'], 'd M H:i')) : 'never' ?>
              </td>
              <td class="right">
                <a class="btn btn-ghost btn-sm"
                   href="audit.php?actor=<?= urlencode((string) $m['full_name']) ?>">Their decisions</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    An officer who has never signed in is either new or has been locked out — both worth knowing.
  </div>
</div>