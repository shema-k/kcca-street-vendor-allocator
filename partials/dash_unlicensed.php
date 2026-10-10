<?php
/**
 * Dashboard section: the compliance gap (Revenue Collection).
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT d.name AS division_name,
            COUNT(*) AS vendors,
            SUM(v.has_trading_licence = 0) AS unlicensed,
            COALESCE(SUM(v.monthly_turnover_ugx),0) AS turnover
       FROM vendors v JOIN divisions d ON d.id = v.division_id
      GROUP BY d.id ORDER BY unlicensed DESC"
);

$total = (int) Database::value('SELECT COUNT(*) FROM vendors');
$unlicensed = (int) Database::value('SELECT COUNT(*) FROM vendors WHERE has_trading_licence = 0');
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Traders without a licence</h2>
      <p class="desc">
        <?= num($unlicensed) ?> of <?= num($total) ?> registered vendors record no current trading licence.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="licensing.php">Licensing position</a>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Division</th><th class="right">Vendors</th><th class="right">Unlicensed</th>
              <th style="width:130px">Share</th><th class="right">Turnover at stake</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $n = (int) $r['vendors'];
            $u = (int) $r['unlicensed'];
            $share = $n > 0 ? round($u / $n * 100, 1) : 0.0; ?>
            <tr>
              <td><strong><?= e($r['division_name']) ?></strong></td>
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
  </div>
  <div class="card-foot">
    Turnover is what the vendor reports at survey, not an assessment. It is here to size the gap, not
    to estimate what a licence would raise.
  </div>
</div>
