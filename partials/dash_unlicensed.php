<?php
/**
 * Dashboard section: the compliance gap (Revenue Officer).
 *
 * Unlicensed vendors by division — where the gap sits, and how much of the
 * register it is. This is a compliance position, not a revenue forecast:
 * the licence fee is set for the trade, and nothing here assumes a vendor
 * can or should pay it.
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT d.name AS division_name, d.code AS div_code,
            COUNT(*) AS vendors,
            SUM(v.has_trading_licence = 0) AS unlicensed,
            COALESCE(SUM(v.monthly_turnover_ugx),0) AS turnover
       FROM vendors v
       JOIN divisions d ON d.id = v.division_id
      GROUP BY d.id
      ORDER BY unlicensed DESC"
);

$totalUnlicensed = (int) Database::value('SELECT COUNT(*) FROM vendors WHERE has_trading_licence = 0');

/* The register's own stage matters: a relocated vendor is contactable at a
   gazetted pitch; one still surveyed at an affected site may not be. */
$byStage = Database::all(
    'SELECT v.status, COUNT(*) AS n, SUM(v.has_trading_licence = 0) AS unlicensed
       FROM vendors v GROUP BY v.status ORDER BY n DESC'
);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Traders without a licence</h2>
      <p class="desc"><?= num($totalUnlicensed) ?> across the city, and where the gap sits.</p>
    </div>
  </div>
  <div class="card-body">
    <?php if ($rows): ?>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Division</th><th class="right">Vendors</th><th class="right">Unlicensed</th>
                <th style="width:130px">Share</th><th class="right">Turnover</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $n = (int) $r['vendors'];
              $u = (int) $r['unlicensed'];
              $share = $n > 0 ? round($u / $n * 100, 1) : 0.0; ?>
              <tr>
                <td><strong><?= e($r['division_name']) ?></strong><div class="xsmall muted"><?= e($r['div_code']) ?></div></td>
                <td class="right num xsmall"><?= num($n) ?></td>
                <td class="right num strong"><?= num($u) ?></td>
                <td>
                  <div class="meter">
                    <span class="track"><span class="fill <?= $share < 30 ? 'ok' : ($share < 60 ? 'warn' : 'bad') ?>"
                          style="width:<?= (float) clamp($share, 2, 100) ?>%"></span></span>
                    <span class="val"><?= pct($share, 0) ?></span>
                  </div>
                </td>
                <td class="right xsmall"><?= ugxShort((int) $r['turnover']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="divider"></div>
      <h4 class="mb-1">By register stage</h4>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 200,
          'labels' => array_map(static fn(array $r): string => humanise((string) $r['status']), $byStage),
          'series' => [
            ['name' => 'Licensed', 'color' => '#0A6339',
             'values' => array_map(static fn(array $r): int => (int) $r['n'] - (int) $r['unlicensed'], $byStage)],
            ['name' => 'No licence', 'color' => '#C0261A',
             'values' => array_map(static fn(array $r): int => (int) $r['unlicensed'], $byStage)],
          ],
          'stacked' => true,
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Licensing by register stage"></svg>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('check', 28) ?></div>
        <h3>Everybody on the register holds a licence</h3>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Licence and occupation are separate questions: a licensed trader on a pavement still occupies
    public space unlawfully.
  </div>
</div>