<?php
/**
 * Dashboard section: footfall coverage (Physical Planning).
 *
 * The model's reach figure is only as good as the count behind it, so this
 * shows where counting has been done and where the figure is estimated.
 */

declare(strict_types=1);

$obs = (int) Database::value('SELECT COUNT(*) FROM footfall_observations');
$days = (int) Database::value('SELECT COUNT(DISTINCT observed_on) FROM footfall_observations');
$countedMarkets = (int) Database::value('SELECT COUNT(DISTINCT market_id) FROM footfall_observations');
$marketsWithSpace = (int) Database::value('SELECT COUNT(*) FROM markets WHERE vacant_spaces > 0');

$rows = [];
foreach (Database::all(
    "SELECT m.id, m.name, d.name AS division_name, m.vacant_spaces
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE m.vacant_spaces > 0 AND m.category = 'public'
      ORDER BY m.vacant_spaces DESC LIMIT 10"
) as $m) {
    $p = ReachModel::footfall((int) $m['id'], $m);
    $rows[] = [
        'label'      => $m['name'],
        'value'      => (float) $p['daily'],
        'counted'    => $p['observations'] > 0,
        'confidence' => $p['confidence'],
    ];
}
usort($rows, static fn($a, $b) => $b['value'] <=> $a['value']);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Footfall coverage</h2>
      <p class="desc">
        Where the Authority has counted passing trade, and where the reach model is working from an
        estimate instead.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="locations.php?view=footfall">Record footfall</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat info">
        <div class="label">Observations</div>
        <div class="value" style="font-size:21px"><?= num($obs) ?></div>
        <div class="meta">across <?= num($days) ?> days</div>
      </div>
      <div class="stat <?= $countedMarkets >= $marketsWithSpace * 0.6 ? 'ok' : 'warn' ?>">
        <div class="label">Markets counted</div>
        <div class="value" style="font-size:21px"><?= num($countedMarkets) ?></div>
        <div class="meta">of <?= num($marketsWithSpace) ?> with space</div>
      </div>
      <div class="stat">
        <div class="label">Coverage</div>
        <div class="value" style="font-size:21px"><?= $marketsWithSpace > 0 ? pct($countedMarkets / $marketsWithSpace * 100, 0) : '—' ?></div>
        <div class="meta">of markets with space</div>
      </div>
      <div class="stat gold">
        <div class="label">Restrictions</div>
        <div class="value" style="font-size:21px"><?= num((int) Database::value('SELECT COUNT(*) FROM land_use_restrictions WHERE is_active = 1')) ?></div>
        <div class="meta">gate what may be traded</div>
      </div>
    </div>

    <svg class="chart" data-chart='<?= ejs([
      'type' => 'hbar',
      'opts' => [
        'labelW' => 180, 'rowH' => 26, 'format' => 'num',
        'items' => array_map(static fn(array $x): array => [
          'label' => $x['label'],
          'value' => round($x['value'], 1),
          'color' => $x['counted'] ? '#0A6339' : '#C9D6DE',
        ], $rows),
      ],
    ]) ?>' aria-label="Footfall by market"></svg>
    <div class="legend">
      <span class="item"><span class="sw" style="background:#0A6339"></span> Counted</span>
      <span class="item"><span class="sw" style="background:#C9D6DE"></span> Estimated from catchment</span>
    </div>
  </div>
  <div class="card-foot">
    A market with no counts is projected from its division's population and the trading it does, at
    low confidence. Counting footfall raises the confidence of every recommendation for it.
  </div>
</div>
