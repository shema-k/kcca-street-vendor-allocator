<?php
/**
 * Dashboard section: the relocation programme.
 *
 * The funnel every role sees — where the surveyed vendors have reached —
 * plus the stage mix behind it. Declared once here so it is not repeated
 * in each role's branch.
 *
 * @var array $funnel, $stageData, $scope
 */

declare(strict_types=1);

$total = (int) array_sum(array_column($funnel, 'value'));
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>The relocation programme</h2>
      <p class="desc">
        <?= num($total) ?> surveyed vendors. Where each has reached, and what happens next.
      </p>
    </div>
    <?php if (Auth::screenAllows('allocations')): ?>
      <a class="btn btn-ghost btn-sm" href="allocations.php">Allocation register</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <?php foreach ($funnel as $stage): ?>
        <div class="stat" style="border-left:3px solid <?= e($stage['color']) ?>">
          <div class="label"><?= e($stage['label']) ?></div>
          <div class="value"><?= num($stage['value']) ?></div>
          <div class="meta"><?= $total > 0 ? pct($stage['value'] / $total * 100, 1) . ' of the programme' : '—' ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <svg class="chart" data-chart='<?= ejs([
      'type' => 'hbar',
      'opts' => [
        'labelW' => 152, 'rowH' => 30,
        'items' => array_map(static fn(array $x): array => [
          'label' => $x['label'], 'value' => $x['value'], 'color' => $x['color'],
        ], $funnel),
      ],
    ]) ?>' aria-label="Relocation programme funnel"></svg>

    <?php if ($stageData): ?>
      <div class="divider"></div>
      <div class="grid g-2-1">
        <div>
          <h4 class="mb-1">Register stage</h4>
          <svg class="chart" data-chart='<?= ejs([
            'type' => 'bar',
            'opts' => [
              'height' => 190,
              'labels' => array_column($stageData, 'label'),
              'series' => [['name' => 'Vendors', 'color' => '#DC351D',
                            'values' => array_column($stageData, 'value')]],
              'yFormat' => 'num',
            ],
          ]) ?>' aria-label="Vendors by register stage"></svg>
        </div>
        <div>
          <h4 class="mb-1">What each stage means</h4>
          <dl class="kv">
            <dt>Surveyed</dt><dd>Counted at an affected site</dd>
            <dt>Notified</dt><dd>Notice served</dd>
            <dt>Offered space</dt><dd>A pitch is held for them</dd>
            <dt>Relocated</dt><dd>Trading in the new pitch</dd>
            <dt>Declined</dt><dd>Turned the offer down</dd>
          </dl>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    A pitch held by an open offer is no longer vacant but is not yet occupied, so it appears in
    neither the "relocated" nor the "space available" figure.
  </div>
</div>