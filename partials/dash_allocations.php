<?php
/**
 * Dashboard section: live allocations (Market Administration).
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT a.id, a.reference, a.status, a.source, a.distance_km, a.total_score,
            a.offer_expires, v.full_name, m.name AS market_name, s.space_code
       FROM allocations a
       JOIN vendors v ON v.id = a.vendor_id
       JOIN markets m ON m.id = a.market_id
       LEFT JOIN market_spaces s ON s.id = a.market_space_id
      ORDER BY a.status = 'offered' DESC, a.created_at DESC LIMIT 12"
);

$counts = [];
foreach (Database::all('SELECT status, COUNT(*) AS n FROM allocations GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Live allocations</h2>
      <p class="desc">Offers out, accepted, and confirmed in place.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="allocations.php">Allocation register</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <?php foreach (['offered' => 'Offered', 'accepted' => 'Accepted', 'occupied' => 'Occupied',
                      'declined' => 'Declined'] as $k => $label): ?>
        <div class="stat">
          <div class="label"><?= e($label) ?></div>
          <div class="value" style="font-size:21px"><?= num($counts[$k] ?? 0) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Reference</th><th>Trader</th><th>Market</th><th>Pitch</th>
              <th class="right">Score</th><th class="center">Source</th>
              <th class="center">Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $a): ?>
            <tr>
              <td class="mono xsmall"><?= e($a['reference']) ?></td>
              <td class="xsmall"><?= e($a['full_name']) ?></td>
              <td class="xsmall"><?= e($a['market_name']) ?></td>
              <td class="mono xsmall"><?= e($a['space_code'] ?: 'on arrival') ?></td>
              <td class="right num xsmall"><?= num((float) $a['total_score'], 1) ?></td>
              <td class="center"><span class="badge <?= $a['source'] === 'model' ? 'info' : 'muted' ?>"><?= e($a['source']) ?></span></td>
              <td class="center"><span class="badge <?= e(statusTone($a['status'])) ?>"><?= e(humanise($a['status'])) ?></span></td>
              <td class="right"><a class="btn btn-ghost btn-sm" href="allocations.php">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('list', 28) ?></div>
        <h3>No allocations yet</h3>
        <p>Approve a recommendation or a stall request to create one.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
