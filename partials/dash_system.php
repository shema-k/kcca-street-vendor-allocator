<?php
/**
 * Dashboard section: system health (System Administrator).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/Backup.php';

$stats    = Backup::stats();
$backups  = Backup::recent(4);
$auditN   = (int) Database::value('SELECT COUNT(*) FROM audit_log');
$footN    = (int) Database::value('SELECT COUNT(*) FROM footfall_observations');
$settingsN = (int) Database::value('SELECT COUNT(*) FROM settings');
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>System</h2>
      <p class="desc">Backups, settings and the audit trail. All editable without a deployment.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="settings.php">Settings</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat <?= $stats['count'] > 0 ? 'ok' : 'warn' ?>">
        <div class="label">Last backup</div>
        <div class="value" style="font-size:17px"><?= $stats['last'] ? e(fmtDate($stats['last'], 'd M Y')) : 'Never' ?></div>
        <div class="meta"><?= $stats['last'] ? e(relativeDays($stats['last'])) : 'take one now' ?></div>
      </div>
      <div class="stat">
        <div class="label">Backup size</div>
        <div class="value" style="font-size:17px"><?= e($stats['size_human']) ?></div>
        <div class="meta"><?= num($stats['rows']) ?> rows</div>
      </div>
      <div class="stat info">
        <div class="label">Settings</div>
        <div class="value" style="font-size:17px"><?= num($settingsN) ?></div>
        <div class="meta">policy values</div>
      </div>
      <div class="stat gold">
        <div class="label">Audit entries</div>
        <div class="value" style="font-size:17px"><?= num($auditN) ?></div>
        <div class="meta">state changes recorded</div>
      </div>
    </div>

    <div class="row">
      <div class="grow">
        <h4 class="mb-1">What the system holds</h4>
        <dl class="kv">
          <dt>Vendors</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM vendors')) ?></dd>
          <dt>Locations</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM markets')) ?></dd>
          <dt>Trading spaces</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM market_spaces')) ?></dd>
          <dt>Footfall counts</dt><dd><?= num($footN) ?></dd>
          <dt>Recommendations</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM recommendations')) ?></dd>
          <dt>Allocations</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM allocations')) ?></dd>
          <dt>Stall requests</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM stall_requests')) ?></dd>
          <dt>Relocation events</dt><dd><?= num((int) Database::value('SELECT COUNT(*) FROM relocation_events')) ?></dd>
        </dl>
      </div>
      <div class="grow">
        <h4 class="mb-1">Recent backups</h4>
        <?php if ($backups): ?>
          <div class="table-wrap">
            <table class="data compact">
              <thead><tr><th>Reference</th><th>Taken</th><th class="right">Rows</th><th class="right">Size</th></tr></thead>
              <tbody>
                <?php foreach ($backups as $b): ?>
                  <tr>
                    <td class="mono xsmall"><?= e($b['reference']) ?></td>
                    <td class="xsmall"><?= e(fmtDate($b['created_at'], 'd M')) ?></td>
                    <td class="right num xsmall"><?= num((int) $b['row_count']) ?></td>
                    <td class="right xsmall"><?= (int) $b['bytes'] > 1048576
                        ? number_format((int) $b['bytes'] / 1048576, 1) . ' MB'
                        : number_format((int) $b['bytes'] / 1024) . ' KB' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-warn">
            No backup has been taken. <a href="settings.php">Take one now</a>.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-foot">
    A backup is a self-contained SQL file: schema first, then every core table in restore order.
  </div>
</div>
