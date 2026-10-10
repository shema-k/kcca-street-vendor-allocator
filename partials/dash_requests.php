<?php
/**
 * Dashboard section: stall requests (Market Administration).
 *
 * A request is a trader's own choice of pitch; it is not an allocation.
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT sr.id, sr.reference, sr.full_name, sr.phone, sr.business_category, sr.reason,
            sr.status, sr.created_at, s.space_code, m.name AS market_name
       FROM stall_requests sr
       JOIN market_spaces s ON s.id = sr.space_id
       JOIN markets m ON m.id = sr.market_id
      ORDER BY sr.status = 'pending' DESC, sr.created_at DESC LIMIT 12"
);

$counts = [];
foreach (Database::all('SELECT status, COUNT(*) AS n FROM stall_requests GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Stall requests</h2>
      <p class="desc">
        Pitches traders have chosen and asked for. A request reserves the pitch and waits; only your
        approval turns it into an offer.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="market_map.php">Open a market</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <?php foreach (['pending' => 'Pending', 'reviewing' => 'Under review', 'approved' => 'Approved',
                      'rejected' => 'Rejected'] as $k => $label): ?>
        <div class="stat <?= $k === 'pending' && ($counts[$k] ?? 0) > 0 ? 'warn' : '' ?>">
          <div class="label"><?= e($label) ?></div>
          <div class="value" style="font-size:21px"><?= num($counts[$k] ?? 0) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Reference</th><th>Pitch</th><th>Market</th><th>Trader</th>
              <th>Telephone</th><th>Why this pitch</th><th class="center">Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $q): ?>
            <tr>
              <td class="mono xsmall"><?= e($q['reference']) ?></td>
              <td class="mono xsmall"><?= e($q['space_code']) ?></td>
              <td class="xsmall"><?= e($q['market_name']) ?></td>
              <td class="xsmall"><?= e($q['full_name']) ?></td>
              <td class="xsmall"><?= e($q['phone']) ?></td>
              <td class="xsmall muted" style="max-width:200px"><?= e(truncateText($q['reason'] ?? '', 60)) ?></td>
              <td class="center"><span class="badge <?= e(statusTone($q['status'])) ?>"><?= e(humanise($q['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('store', 28) ?></div>
        <h3>No stall requests yet</h3>
        <p>Traders request a pitch from the market's pitch plan.</p>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Approving a request opens a vendor record if the trader is not on the register, then creates the
    allocation.
  </div>
</div>
