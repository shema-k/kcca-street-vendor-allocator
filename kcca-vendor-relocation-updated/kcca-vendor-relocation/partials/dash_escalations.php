<?php
/**
 * Dashboard section: open enforcement work (Legal Affairs).
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT e.id, e.reference, e.action_type, e.status, e.deadline_date, e.issued_date,
            e.vendors_affected, e.vendors_relocated, e.outcome,
            z.name AS zone_name, z.estimated_vendors
       FROM enforcement_actions e
       JOIN trade_zones z ON z.id = e.zone_id
      WHERE e.status NOT IN ('completed','escalated')
      ORDER BY e.deadline_date ASC, e.issued_date ASC LIMIT 20"
);

$overdue = 0;
foreach ($rows as $r) {
    if ($r['deadline_date'] && strtotime((string) $r['deadline_date']) < time()) {
        $overdue++;
    }
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Open work</h2>
      <p class="desc">
        Records not yet closed off, ordered by the nearest deadline.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="enforcement.php?status=">Open the log</a>
  </div>
  <div class="card-body">
    <?php if ($overdue > 0): ?>
      <div class="alert alert-bad">
        <strong><?= num($overdue) ?> record<?= $overdue === 1 ? '' : 's' ?> past the deadline.</strong>
        Under the Markets Act 2023 an ultimatum expires once its deadline passes, which weakens the
        Authority's position if the matter is later contested.
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Reference</th><th>Site</th><th>Type</th><th>Deadline</th>
              <th class="right">Vendors</th><th class="center">Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $late = $r['deadline_date'] && strtotime((string) $r['deadline_date']) < time(); ?>
            <tr>
              <td class="mono xsmall"><?= e($r['reference']) ?></td>
              <td class="xsmall"><?= e($r['zone_name']) ?></td>
              <td class="xsmall"><?= e(humanise($r['action_type'])) ?></td>
              <td class="xsmall <?= $late ? 'strong' : '' ?>">
                <?= e(fmtDate($r['deadline_date'])) ?>
                <?php if ($late): ?><span class="badge bad">overdue</span><?php endif; ?>
              </td>
              <td class="right num xsmall"><?= num((int) $r['vendors_affected']) ?></td>
              <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
              <td class="right">
                <?php if (Auth::can('enforce')): ?>
                  <button class="btn btn-ghost btn-sm" data-complete-action="<?= (int) $r['id'] ?>"
                          data-zone="<?= (int) Database::value('SELECT id FROM trade_zones WHERE name = ?', [$r['zone_name']]) ?>">Close</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('check', 28) ?></div>
        <h3>Nothing open</h3>
        <p>Every enforcement record has been closed or escalated.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
