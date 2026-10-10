<?php
/**
 * Dashboard section: the audit trail (Internal Audit).
 */

declare(strict_types=1);

$rows = Database::all(
    'SELECT a.id, a.action, a.entity, a.entity_id, a.detail, a.created_at, a.actor, a.ip_address
       FROM audit_log a ORDER BY a.created_at DESC, a.id DESC LIMIT 14'
);

$byAction = Database::all(
    'SELECT action, COUNT(*) AS n FROM audit_log GROUP BY action ORDER BY n DESC LIMIT 8'
);
$actors = Database::all(
    'SELECT actor, COUNT(*) AS n FROM audit_log GROUP BY actor ORDER BY n DESC LIMIT 6'
);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Recent audit entries</h2>
      <p class="desc">Every state change: who took it, when, and from where.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="audit.php">Full audit trail</a>
  </div>
  <div class="card-body">
    <div class="grid g-2-1 mb-2">
      <div>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 168, 'rowH' => 26,
            'items' => array_map(static fn(array $r): array => [
              'label' => humanise($r['action']),
              'value' => (int) $r['n'],
              'color' => '#0A6339',
            ], $byAction),
          ],
        ]) ?>' aria-label="Audit entries by action"></svg>
      </div>
      <div>
        <h4 class="mb-1">By officer</h4>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 120, 'rowH' => 26,
            'items' => array_map(static fn(array $r): array => [
              'label' => truncateText($r['actor'], 18),
              'value' => (int) $r['n'],
              'color' => '#DC351D',
            ], $actors),
          ],
        ]) ?>' aria-label="Audit entries by officer"></svg>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>When</th><th>Action</th><th>Officer</th><th>Detail</th><th class="right">IP</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="xsmall nowrap"><?= e(fmtDate($r['created_at'], 'd M H:i')) ?></td>
              <td class="xsmall"><?= e(humanise($r['action'])) ?></td>
              <td class="xsmall"><?= e($r['actor']) ?></td>
              <td class="xsmall muted" style="max-width:340px"><?= e(truncateText($r['detail'] ?? '', 80)) ?></td>
              <td class="right xsmall muted"><?= e($r['ip_address']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    The trail is written before the action completes, so a record stands even if the client
    disconnects mid-request.
  </div>
</div>
