<?php
/**
 * Dashboard section: the forecast (Planner).
 *
 * The reach model applied to every location with space, ranked. This is the
 * tool the Planner plans with: it says what a trader could realistically
 * earn at each location, and how confident that figure is.
 *
 * The confidence column is not decoration. Where footfall has not been
 * counted the model falls back to a catchment estimate, and a plan built on
 * an estimate should look different from one built on a count.
 */

declare(strict_types=1);

$markets = RecommendationService::candidateMarkets(60);

/* Score a representative trade against each location. The forecast is per
   trade, so the most common trade in the register stands in for "the
   average trader" and is stated as such. */
$dominant = (string) Database::value(
    "SELECT business_category FROM vendors GROUP BY business_category ORDER BY COUNT(*) DESC LIMIT 1"
);
$category = RecommendationService::categoryFor($dominant);

$sampleVendor = [
    'full_name'            => 'Average vendor',
    'business_category'    => $dominant,
    'monthly_turnover_ugx' => (int) Database::value('SELECT COALESCE(AVG(monthly_turnover_ugx),120000) FROM vendors'),
    'current_lat'          => 0.3166,
    'current_lng'          => 32.5833,
];

$ranked = $category === null ? [] : RecommendationService::rank($sampleVendor, $markets, 10);

$counted   = 0;
$estimated = 0;
foreach ($ranked as $r) {
    if ($r['score']['footfall_profile']['observations'] > 0) { $counted++; } else { $estimated++; }
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Forecast</h2>
      <p class="desc">
        Projected reach for the register's most common trade —
        <strong><?= e($category ? $category['name'] : 'unknown') ?></strong> — at every location
        with space. <?= num(count($markets)) ?> locations available to score.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="recommendations.php">Open the tool</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat info">
        <div class="label">Locations scored</div>
        <div class="value" style="font-size:21px"><?= num(count($ranked)) ?></div>
        <div class="meta">with space, ranked</div>
      </div>
      <div class="stat green">
        <div class="label">Backed by a count</div>
        <div class="value" style="font-size:21px"><?= num($counted) ?></div>
        <div class="meta">footfall actually counted</div>
      </div>
      <div class="stat warn">
        <div class="label">Estimated only</div>
        <div class="value" style="font-size:21px"><?= num($estimated) ?></div>
        <div class="meta">from division population</div>
      </div>
      <div class="stat gold">
        <div class="label">Top projected revenue</div>
        <div class="value" style="font-size:17px"><?= ugxShort((float) ($ranked[0]['score']['projected_revenue_ugx'] ?? 0)) ?></div>
        <div class="meta">a month, for one stall</div>
      </div>
    </div>

    <?php if ($ranked): ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 220,
          'labels' => array_map(static fn(array $r): string => truncateText($r['market']['name'], 18), $ranked),
          'series' => [[
            'name' => 'Projected customers a day',
            'color' => '#0A6339',
            'values' => array_map(static fn(array $r): float => round($r['score']['projected_customers_day'], 0), $ranked),
          ]],
          'showValues' => true,
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Projected customers a day by location"></svg>

      <div class="divider"></div>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Location</th><th class="right">People a day</th>
                <th class="right">Customers</th><th class="right">Revenue/mo</th>
                <th class="center">Confidence</th><th>What drives it</th></tr>
          </thead>
          <tbody>
            <?php foreach ($ranked as $r):
              $s = $r['score'];
              $obs = $s['footfall_profile']['observations'] > 0; ?>
              <tr>
                <td>
                  <strong><?= e($r['market']['name']) ?></strong>
                  <div class="xsmall muted"><?= e($r['market']['division_name']) ?></div>
                </td>
                <td class="right num xsmall"><?= num($s['footfall_profile']['daily']) ?></td>
                <td class="right num xsmall strong"><?= num($s['projected_customers_day'], 0) ?></td>
                <td class="right num xsmall"><?= ugxShort((float) $s['projected_revenue_ugx']) ?></td>
                <td class="center">
                  <span class="badge <?= $obs ? 'ok' : 'warn' ?>" title="<?= $obs ? 'Footfall counted here' : 'Estimated from division population' ?>">
                    <?= pct($s['confidence'] * 100, 0) ?>
                  </span>
                </td>
                <td class="xsmall muted" style="max-width:220px">
                  <?= e(truncateText($s['explanation'], 88)) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('chart', 28) ?></div>
        <h3>Nothing to forecast</h3>
        <p>No location with space can be scored for this trade.</p>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    The model is transparent, not a black box: every score decomposes into eight weighted factors,
    and the reasoning is stored with each recommendation.
  </div>
</div>