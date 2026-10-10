<?php
/**
 * Dashboard section: progress by division.
 *
 * Used by the Executive Director, the System Administrator and the
 * read-only viewer, where it is citywide, and by a Division Mayor, where
 * it is scoped to the one division they are accountable for. The query
 * honours $scope, so the same partial serves both without the Mayor seeing
 * four divisions they cannot act on.
 */

declare(strict_types=1);

$div = RoleDashboard::scopeSql($scope);

$rows = Database::all(
    "SELECT d.id, d.name, d.code, d.mayor, d.town_clerk, d.population,
            (SELECT COALESCE(SUM(z.estimated_vendors),0) FROM trade_zones z WHERE z.division_id = d.id" . ') AS affected,
            (SELECT COUNT(*) FROM vendors v WHERE v.division_id = d.id AND v.status = \'relocated\') AS relocated,
            (SELECT COUNT(*) FROM vendors v WHERE v.division_id = d.id) AS vendors,
            (SELECT COALESCE(SUM(m.vacant_spaces),0) FROM markets m WHERE m.division_id = d.id) AS vacant,
            (SELECT COALESCE(SUM(m.capacity),0) FROM markets m WHERE m.division_id = d.id) AS capacity,
            (SELECT COUNT(*) FROM trade_zones z WHERE z.division_id = d.id) AS sites
       FROM divisions d
      WHERE 1 = 1 ' . ($div !== '' ? ' AND d.id = ' . (int) $scope['division_id'] : '') . '
      ORDER BY d.name'
);

foreach ($rows as &$r) {
    $r['affected']  = (int) $r['affected'];
    $r['relocated'] = (int) $r['relocated'];
    $r['vendors']   = (int) $r['vendors'];
    $r['vacant']    = (int) $r['vacant'];
    $r['capacity']  = (int) $r['capacity'];
    $r['sites']     = (int) $r['sites'];
    $r['clearance'] = $r['affected'] > 0 ? round($r['relocated'] / $r['affected'] * 100, 1) : 0.0;
    $r['occupancy'] = $r['capacity'] > 0 ? round(($r['capacity'] - $r['vacant']) / $r['capacity'] * 100, 1) : 0.0;
}
unset($r);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Progress by division</h2>
      <p class="desc">
        Clearance against the number of vendors each division has to move, and the space it has to
        move them into.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="analytics.php">Full performance</a>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Division</th><th>Mayor</th><th class="right">Population</th>
              <th class="right">Sites</th><th class="right">Vendors</th>
              <th class="right">To move</th><th class="right">Moved</th>
              <th style="width:130px">Clearance</th>
              <th class="right">Vacant</th><th style="width:120px">Occupancy</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <strong><?= e($r['name']) ?></strong>
                <div class="xsmall muted"><?= e($r['code']) ?></div>
              </td>
              <td class="xsmall"><?= e($r['mayor']) ?></td>
              <td class="right num xsmall"><?= num($r['population'] ?? 0) ?></td>
              <td class="right num xsmall"><?= num($r['sites']) ?></td>
              <td class="right num xsmall"><?= num($r['vendors']) ?></td>
              <td class="right num xsmall"><?= num($r['affected']) ?></td>
              <td class="right num strong"><?= num($r['relocated']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $r['clearance'] < 25 ? 'bad' : ($r['clearance'] < 60 ? 'warn' : 'ok') ?>"
                        style="width:<?= (float) clamp($r['clearance'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($r['clearance'], 1) ?></span>
                </div>
              </td>
              <td class="right num xsmall"><?= num($r['vacant']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $r['occupancy'] < 80 ? 'warn' : 'ok' ?>"
                        style="width:<?= (float) clamp($r['occupancy'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($r['occupancy'], 0) ?></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    UBOS 2024 populations from the KCCA Strategic Plan.
  </div>
</div>
