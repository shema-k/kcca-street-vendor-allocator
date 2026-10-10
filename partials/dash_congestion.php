<?php
/**
 * Dashboard section: sites by congestion score (Physical Planning, read-only).
 */

declare(strict_types=1);

$zones = array_slice(scoredZones(), 0, 10);
$bands = ['Critical' => 0, 'High' => 0, 'Moderate' => 0, 'Watch' => 0, 'Low' => 0];
foreach (scoredZones() as $z) {
    $bands[$z['band']] = ($bands[$z['band']] ?? 0) + 1;
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Sites by congestion score</h2>
      <p class="desc">
        The seven criteria, weighted and ranked. This is the clearance order.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="congestion.php">Full analysis</a>
  </div>
  <div class="card-body">
    <div class="grid g-2-1">
      <div>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 200,
            'labels' => array_map(static fn(array $z): string => truncateText($z['name'], 16), $zones),
            'series' => [['name' => 'Congestion score', 'color' => '#C0261A',
                          'values' => array_map(static fn(array $z): float => $z['score'], $zones)]],
            'showValues' => true,
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Congestion score by site"></svg>
      </div>
      <div>
        <h4 class="mb-1">Bands</h4>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 92, 'rowH' => 26,
            'items' => array_map(static fn($label, $n): array => [
              'label' => $label,
              'value' => $n,
              'color' => match ($label) {
                  'Critical' => '#C0261A', 'High' => '#EAA515',
                  'Moderate' => '#1D6FA5', 'Watch' => '#8C9199', default => '#1B7F4D',
              },
            ], array_keys($bands), array_values($bands)),
          ],
        ]) ?>' aria-label="Sites by congestion band"></svg>
      </div>
    </div>

    <div class="divider"></div>
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Site</th><th class="right">Vendors</th><th style="width:130px">Score</th>
              <th>Band</th><th>Why it ranks here</th></tr>
        </thead>
        <tbody>
          <?php foreach ($zones as $z): ?>
            <tr>
              <td><strong><?= e($z['name']) ?></strong></td>
              <td class="right num xsmall"><?= num((int) $z['estimated_vendors']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill" style="width:<?= (float) clamp($z['score'], 3, 100) ?>%"></span></span>
                  <span class="val"><?= num($z['score'], 1) ?></span>
                </div>
              </td>
              <td><span class="badge <?= e($z['tone']) ?>"><?= e($z['band']) ?></span></td>
              <td class="xsmall muted"><?= e(truncateText(implode(' ', array_slice($z['reasons'], 0, 2)), 100)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
