<?php
/**
 * Dashboard section: enforcement operations (Legal Affairs).
 */

declare(strict_types=1);

$rows = Database::all(
    'SELECT e.id, e.reference, e.action_type, e.status, e.issued_date, e.deadline_date,
            e.completed_date, e.vendors_affected, e.vendors_relocated, e.verandas_cleared,
            e.officer_name, z.name AS zone_name
       FROM enforcement_actions e
       JOIN trade_zones z ON z.id = e.zone_id
      ORDER BY e.issued_date DESC LIMIT 12'
);

$counts = [];
foreach (Database::all('SELECT status, COUNT(*) AS n FROM enforcement_actions GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Your operations</h2>
      <p class="desc">Notices, ultimatums and clear-ups, newest first.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="enforcement.php">Full enforcement log</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <?php foreach (['planned' => 'Planned', 'in-progress' => 'In progress', 'completed' => 'Completed',
                      'escalated' => 'Escalated'] as $k => $label): ?>
        <div class="stat">
          <div class="label"><?= e($label) ?></div>
          <div class="value" style="font-size:21px"><?= num($counts[$k] ?? 0) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Reference</th><th>Site</th><th>Type</th><th class="right">Vendors</th>
              <th class="right">Moved</th><th class="center">Status</th><th>Issued</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="mono xsmall"><?= e($r['reference']) ?></td>
              <td class="xsmall"><?= e($r['zone_name']) ?></td>
              <td class="xsmall"><?= e(humanise($r['action_type'])) ?></td>
              <td class="right num xsmall"><?= num((int) $r['vendors_affected']) ?></td>
              <td class="right num xsmall"><?= num((int) $r['vendors_relocated']) ?></td>
              <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
              <td class="xsmall muted"><?= e(fmtDate($r['issued_date'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    Closing an operation records how many vendors actually have gazetted space. A site is only marked
    cleared once 90% or more do.
  </div>
</div>
