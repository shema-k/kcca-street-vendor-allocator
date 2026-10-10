<?php
/**
 * Dashboard section: footfall, so a trader chooses well.
 *
 * The Authority counts how many people pass a market. This shows that count
 * against each market with space, and says plainly which figures are counted
 * and which are estimates — a trader is about to base a business decision on
 * this, and is entitled to know which is which.
 */

declare(strict_types=1);

$rows = [];
foreach (Database::all(
    "SELECT m.id, m.name, m.vacant_spaces, m.category
       FROM markets m
      WHERE m.vacant_spaces > 0 AND m.category = 'public'
      ORDER BY m.vacant_spaces DESC LIMIT 12"
) as $mk) {
    $p = ReachModel::footfall((int) $mk['id'], $mk);
    $rows[] = [
        'id'         => (int) $mk['id'],
        'name'       => $mk['name'],
        'daily'      => (float) $p['daily'],
        'confidence' => (float) $p['confidence'],
        'counted'    => $p['observations'] > 0,
        'peak'       => ReachModel::hourBandLabel((int) $p['peak_hour_band']),
        'peak_day'   => (string) $p['peak_day'],
    ];
}
usort($rows, static fn($a, $b) => $b['daily'] <=> $a['daily']);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Footfall, to choose well</h2>
      <p class="desc">
        People passing each market a day. A busier market means more customers —
        but also more traders already selling the same thing.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="locations.php?view=footfall">Record footfall</a>
  </div>
  <div class="card-body">
    <?php if ($rows): ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 182, 'rowH' => 26, 'format' => 'num',
          'items' => array_map(static fn(array $x): array => [
            'label' => $x['name'],
            'value' => round($x['daily'], 1),
            /* A counted figure is solid; an estimate is pale. One colour
               says it, which a bar chart cannot. */
            'color' => $x['counted'] ? '#0A6339' : '#C9D6DE',
          ], $rows),
        ],
      ]) ?>' aria-label="Footfall at markets with space"></svg>

      <div class="legend">
        <span class="item"><span class="sw" style="background:#0A6339"></span> Counted by the Authority</span>
        <span class="item"><span class="sw" style="background:#C9D6DE"></span> Estimated from the division's population</span>
      </div>

      <div class="divider"></div>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Market</th><th class="right">People a day</th>
                <th>Busiest</th><th class="center">Confidence</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $x): ?>
              <tr>
                <td><strong><?= e($x['name']) ?></strong></td>
                <td class="right num strong"><?= num($x['daily']) ?></td>
                <td class="xsmall"><?= e($x['peak']) ?> · <?= e(ucfirst($x['peak_day'])) ?>s</td>
                <td class="center">
                  <span class="badge <?= $x['confidence'] >= 0.5 ? 'ok' : ($x['confidence'] >= 0.3 ? 'warn' : 'muted') ?>">
                    <?= pct($x['confidence'] * 100, 0) ?>
                  </span>
                </td>
                <td class="right">
                  <a class="btn btn-ghost btn-sm" href="market_map.php?market=<?= $x['id'] ?>">Choose a pitch</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('chart', 28) ?></div>
        <h3>No footfall recorded yet</h3>
        <p>No market with free space has been counted.</p>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Busy is not automatically better. If twenty traders already sell what you sell, a quieter market
    with fewer of them may pay better.
  </div>
</div>