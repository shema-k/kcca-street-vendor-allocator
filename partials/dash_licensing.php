<?php
/**
 * Dashboard section: licensing position (Revenue Collection).
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT v.licence_class, COUNT(*) AS n
       FROM vendors v GROUP BY v.licence_class ORDER BY n DESC"
);
$byTrade = Database::all(
    "SELECT v.business_category, COUNT(*) AS n,
            SUM(v.has_trading_licence = 0) AS unlicensed
       FROM vendors v GROUP BY v.business_category ORDER BY n DESC LIMIT 10"
);
$total = (int) Database::value('SELECT COUNT(*) FROM vendors');
$licensed = (int) Database::value('SELECT COUNT(*) FROM vendors WHERE has_trading_licence = 1');
$turnover = (int) Database::value('SELECT COALESCE(SUM(monthly_turnover_ugx),0) FROM vendors');
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Licensing position</h2>
      <p class="desc">Licence classes across the register, and the turnover behind them.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="licensing.php">Full licensing position</a>
</div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat">
        <div class="label">Registered</div>
        <div class="value"><?= num($total) ?></div>
      </div>
      <div class="stat green">
        <div class="label">Holding a licence</div>
        <div class="value"><?= num($licensed) ?></div>
        <div class="meta"><?= $total > 0 ? pct($licensed / $total * 100, 0) : '—' ?> of the register</div>
      </div>
      <div class="stat warn">
        <div class="label">No licence</div>
        <div class="value"><?= num($total - $licensed) ?></div>
        <div class="meta">compliance gap</div>
      </div>
      <div class="stat gold">
        <div class="label">Register turnover</div>
        <div class="value" style="font-size:17px"><?= ugxShort($turnover) ?></div>
        <div class="meta">a month, as recorded</div>
      </div>
    </div>

    <div class="grid g2">
      <div>
        <h4 class="mb-1">By licence class</h4>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 140, 'rowH' => 26,
            'items' => array_map(static fn(array $r): array => [
              'label' => humanise($r['licence_class']),
              'value' => (int) $r['n'],
              'color' => $r['licence_class'] === 'unlicensed' ? '#C0261A' : '#0A6339',
            ], $rows),
          ],
        ]) ?>' aria-label="Vendors by licence class"></svg>
      </div>
      <div>
        <h4 class="mb-1">By trade</h4>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 200,
            'labels' => array_map(static fn(array $r): string => humanise($r['business_category']), $byTrade),
            'series' => [
              ['name' => 'Licensed', 'color' => '#0A6339',
               'values' => array_map(static fn(array $r): int => (int) $r['n'] - (int) $r['unlicensed'], $byTrade)],
              ['name' => 'No licence', 'color' => '#C0261A',
               'values' => array_map(static fn(array $r): int => (int) $r['unlicensed'], $byTrade)],
            ],
            'stacked' => true,
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Licensing by trade"></svg>
      </div>
    </div>
  </div>
  <div class="card-foot">
    Licence class is recorded from the vendor's own answer at survey. The Authority's trading licence
    fee bands and the eligibility test are published on kcca.go.ug.
  </div>
</div>
