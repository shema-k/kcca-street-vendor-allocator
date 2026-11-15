<?php
/**
 * Dashboard section: what awaits this officer's decision.
 *
 * Recommendations put up for approval, and stall requests a trader has
 * made. Both are decisions, not information — which is why they appear
 * only for the roles that can actually take them.
 */

declare(strict_types=1);

$div = RoleDashboard::scopeSql($scope);

/* recommendations belongs to a vendor, so a Mayor's scope travels through
   the vendor's division, not the recommendation's own columns. */
$recDiv = $div !== ''
    ? ' AND r.vendor_id IN (SELECT id FROM vendors WHERE division_id = ' . (int) $scope['division_id'] . ')'
    : '';

$recs = Database::all(
    "SELECT r.id, r.reference, r.reach_score, r.confidence, r.projected_customers_day,
            r.projected_revenue_ugx, v.full_name, v.business_category, m.name AS market_name,
            d.name AS division_name
       FROM recommendations r
       JOIN vendors v ON v.id = r.vendor_id
       JOIN markets m ON m.id = r.market_id
       JOIN divisions d ON d.id = v.division_id
      WHERE r.status = 'proposed'" . $recDiv . "
      ORDER BY r.reach_score DESC LIMIT 8"
);

$reqDiv = $div !== ''
    ? ' AND sr.market_id IN (SELECT id FROM markets WHERE division_id = ' . (int) $scope['division_id'] . ')'
    : '';

$reqs = Database::all(
    "SELECT sr.id, sr.reference, sr.full_name, sr.phone, sr.business_category, sr.reason,
            s.space_code, m.name AS market_name
       FROM stall_requests sr
       JOIN market_spaces s ON s.id = sr.space_id
       JOIN markets m ON m.id = sr.market_id
      WHERE sr.status IN ('pending','reviewing')" . $reqDiv . "
      ORDER BY sr.created_at DESC LIMIT 8"
);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Awaiting your decision</h2>
      <p class="desc">
        Recommendations the model has produced, and pitches traders have asked for. Neither is an
        allocation until you approve it.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="recommendations.php?status=proposed">All recommendations</a>
  </div>
  <div class="card-body">
    <?php if ($recs): ?>
      <h4 class="mb-1">Recommendations to approve or reject</h4>
      <div class="table-wrap mb-2">
        <table class="data compact">
          <thead>
            <tr><th>Reference</th><th>Trader</th><th>Location</th>
                <th class="right">Reach</th><th class="center">Confidence</th>
                <th class="right">Customers/day</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($recs as $r): ?>
              <tr>
                <td class="mono xsmall"><?= e($r['reference']) ?></td>
                <td>
                  <strong><?= e($r['full_name']) ?></strong>
                  <div class="xsmall muted"><?= e(humanise($r['business_category'])) ?></div>
                </td>
                <td class="xsmall"><?= e($r['market_name']) ?></td>
                <td class="right num strong"><?= num((float) $r['reach_score'], 1) ?></td>
                <td class="center"><span class="badge <?= (float) $r['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
                    <?= pct((float) $r['confidence'] * 100, 0) ?></span></td>
                <td class="right num xsmall"><?= num((float) $r['projected_customers_day'], 0) ?></td>
                <td class="right"><a class="btn btn-ghost btn-sm"
                    href="recommendations.php?rec=<?= (int) $r['id'] ?>&status=proposed">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($reqs): ?>
      <h4 class="mb-1">Stall requests to decide</h4>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Reference</th><th>Pitch</th><th>Trader</th><th>Telephone</th>
                <th>Why this pitch</th><th class="center">Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($reqs as $q): ?>
              <tr>
                <td class="mono xsmall"><?= e($q['reference']) ?></td>
                <td class="mono xsmall"><?= e($q['space_code']) ?></td>
                <td class="xsmall"><?= e($q['full_name']) ?></td>
                <td class="xsmall"><?= e($q['phone']) ?></td>
                <td class="xsmall muted" style="max-width:220px"><?= e(truncateText($q['reason'] ?? '', 70)) ?></td>
                <td class="center"><span class="badge warn"><?= e(humanise($q['status'] ?? 'pending')) ?></span></td>
                <td class="right"><a class="btn btn-ghost btn-sm" href="market_map.php">Open the market</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!$recs && !$reqs): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('check', 28) ?></div>
        <h3>Nothing is waiting on you</h3>
        <p>Every recommendation and stall request has been decided.</p>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Approving a recommendation reserves the pitch and creates the offer. Rejecting one requires a
    reason, which is recorded and shown back to the trader.
  </div>
</div>
