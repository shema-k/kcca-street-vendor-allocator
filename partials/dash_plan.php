<?php
/**
 * Dashboard section: performance against the Strategic Plan (Executive Director).
 */

declare(strict_types=1);

$plan = $f['strategic_plan'];

$rows = Database::all(
    "SELECT i.id, i.objective, i.indicator, i.baseline, i.target_2030, i.direction,
            i.current_value, i.unit, i.source
       FROM strategic_indicators i
      ORDER BY i.objective, i.id LIMIT 14"
);

$affected = (int) Database::value('SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones');
$moved    = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'relocated'");
$clearance = $affected > 0 ? round($moved / $affected * 100, 1) : 0.0;
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Against the Strategic Plan</h2>
      <p class="desc">
        <?= e($plan['period'] ?? 'FY2025/26–FY2029/30') ?>, valued at <?= e($plan['value'] ?? 'UGX 11.9 trillion') ?>.
        The targets this system reports against.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="analytics.php">Full performance</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat green">
        <div class="label">Programme clearance</div>
        <div class="value"><?= pct($clearance, 1) ?></div>
        <div class="meta"><?= num($moved) ?> of <?= num($affected) ?></div>
      </div>
      <div class="stat info">
        <div class="label">Gazetted space free</div>
        <div class="value"><?= num((int) Database::value('SELECT COALESCE(SUM(vacant_spaces),0) FROM markets')) ?></div>
        <div class="meta">across all markets</div>
      </div>
      <div class="stat gold">
        <div class="label">Sites still occupied</div>
        <div class="value"><?= num((int) Database::value("SELECT COUNT(*) FROM trade_zones WHERE status <> 'cleared'")) ?></div>
        <div class="meta">of <?= num((int) Database::value('SELECT COUNT(*) FROM trade_zones')) ?> surveyed</div>
      </div>
      <div class="stat warn">
        <div class="label">Unlicensed traders</div>
        <div class="value"><?= num((int) Database::value('SELECT COUNT(*) FROM vendors WHERE has_trading_licence = 0')) ?></div>
        <div class="meta">of <?= num((int) Database::value('SELECT COUNT(*) FROM vendors')) ?> registered</div>
      </div>
    </div>

    <?php if ($rows): ?>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Objective</th><th>Indicator</th><th class="right">Baseline</th>
                <th class="right">Target</th><th class="right">Now</th><th>Source</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i): ?>
              <tr>
                <td class="xsmall"><?= e(truncateText($i['objective'] ?? '', 46)) ?></td>
                <td class="xsmall"><?= e($i['indicator']) ?></td>
                <td class="right num xsmall"><?= e($i['baseline'] ?? '—') ?></td>
                <td class="right num xsmall strong"><?= e($i['target_2030'] ?? '—') ?></td>
                <td class="right num xsmall"><?= e($i['current_value'] ?? '—') ?></td>
                <td class="xsmall muted"><?= e($i['source'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Programme clearance is this system's own figure: vendors registered as relocated against the
    number surveyed at affected sites.
  </div>
</div>
