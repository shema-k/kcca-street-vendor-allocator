<?php
/**
 * Dashboard section: the sites in a Division Mayor's own division.
 *
 * Scoped by $scope, so a Mayor sees their division's sites and nothing
 * else — not a citywide list with their own highlighted in it.
 */

declare(strict_types=1);

/* scoredZones() takes a division code, not a scope array. Resolve the
   Mayor's own division to its code, which is the same scoping expressed the
   way that function expects. */
$myDivCode = '';
if (isset($scope['division_id'])) {
    $myDivCode = (string) Database::value(
        'SELECT code FROM divisions WHERE id = ?',
        [(int) $scope['division_id']]
    );
}

$zones = array_slice(scoredZones($myDivCode), 0, 10);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Sites in your division</h2>
      <p class="desc">
        Ranked by congestion score. This is the order the Authority clears them in.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="congestion.php">Full congestion analysis</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Site</th><th>Parish</th><th class="right">Vendors</th>
              <th style="width:120px">Congestion</th><th>Band</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($zones as $z): ?>
            <tr>
              <td>
                <strong><?= e($z['name']) ?></strong>
                <div class="xsmall muted"><?= e($z['code']) ?></div>
              </td>
              <td class="xsmall"><?= e($z['parish'] ?? '—') ?></td>
              <td class="right num xsmall"><?= num((int) $z['estimated_vendors']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill" style="width:<?= (float) clamp($z['score'], 3, 100) ?>%"></span></span>
                  <span class="val"><?= num($z['score'], 0) ?></span>
                </div>
              </td>
              <td><span class="badge <?= e($z['tone']) ?>"><?= e($z['band']) ?></span></td>
              <td class="right"><a class="btn btn-ghost btn-sm" href="congestion.php?zone=<?= (int) $z['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$zones): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('check', 28) ?></div>
        <h3>No affected sites in your division</h3>
        <p>Nothing to clear here.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
