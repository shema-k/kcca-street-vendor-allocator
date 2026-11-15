<?php
/**
 * Dashboard section: licensing position (Revenue Officer).
 *
 * What the register says about trading licences, and the turnover behind
 * it. The officer's question is how much is licensed and how much is not;
 * that is answered first, and the turnover is framed as reported rather
 * than assessed, because it is what the vendor said at survey.
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT v.licence_class, COUNT(*) AS n,
            COALESCE(SUM(v.monthly_turnover_ugx),0) AS turnover
       FROM vendors v
      GROUP BY v.licence_class
      ORDER BY n DESC"
);

$byTrade = Database::all(
    "SELECT v.business_category, COUNT(*) AS n,
            SUM(v.has_trading_licence = 0) AS unlicensed
       FROM vendors v
      GROUP BY v.business_category\n      ORDER BY n DESC LIMIT 10"
);

$total     = (int) Database::value('SELECT COUNT(*) FROM vendors');
$licensed  = (int) Database::value('SELECT COUNT(*) FROM vendors WHERE has_trading_licence = 1');
$turnover  = (int) Database::value('SELECT COALESCE(SUM(monthly_turnover_ugx),0) FROM vendors');

/* The licence fee bands the Authority publishes. Used to size the position,
   not to assert what is owed. */
$fees = [
    'unlicensed'        => 0,
    'street-grade-1'    => 12000,
    'street-grade-2'    => 9000,
    'market-standard'   => 7000,
];
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Licensing position</h2>
      <p class="desc">Licence classes across the register, and the turnover behind them.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="licensing.php">Open the register</a>
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
        <div class="label">Reported turnover</div>
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
            'labelW' => 148, 'rowH' => 26,
            'items' => array_map(static fn(array $r): array => [
              'label' => humanise((string) $r['licence_class']),
              'value' => (int) $r['n'],
              'color' => $r['licence_class'] === 'unlicensed' ? '#C0261A' : '#0A6339',
            ], $rows),
          ],
        ]) ?>' aria-label="Vendors by licence class"></svg>
      </div>
      <div>
        <h4 class="mb-1">Licence fee band</h4>
        <dl class="kv">
          <?php foreach ($fees as $klass => $amount): ?>
            <dt><?= e(humanise($klass)) ?></dt>
            <dd><?= $amount === 0 ? '—' : ugx($amount) ?></dd>
          <?php endforeach; ?>
        </dl>
        <p class="xsmall muted" style="margin:8px 0 0">
          Bands are published by the Authority and reviewed from time to time.
        </p>
      </div>
    </div>
  </div>
  <div class="card-foot">
    Turnover is what the vendor reports at survey. It sizes the position; it is not an assessment,
    and nothing here is a demand for payment.
  </div>
</div>