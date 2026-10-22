<?php
/**
 * Dashboard section: how a site is scored (Physical Planning).
 */

declare(strict_types=1);

$w = Settings::zoneWeights();
$what = [
    'obstruction'   => 'Footpath taken, blended with carriageway taken',
    'vendor_load'   => 'Vendors affected, square-root compressed so one large site does not swamp the rest',
    'traffic_impact'=> 'Junctions, roundabouts and stages rank above a general street',
    'duration'      => 'How long the unlawful occupation has persisted',
    'strategic'     => 'Designated priority corridors',
    'public_access' => 'A hospital entrance outranks a general complaint',
    'complaints'    => 'Volume of logged complaints in the last 30 days',
];
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>How a site is scored</h2>
      <p class="desc">
        Seven criteria, weighted. The weights below are the ones in force — they are settings, so
        retuning them needs no deployment.
      </p>
    </div>
    <?php if (Auth::can('manage') && Auth::screenAllows('settings')): ?>
      <a class="btn btn-ghost btn-sm" href="settings.php">Retune the weights</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="criteria">
      <?php foreach ($w as $key => $weight): ?>
        <div class="criterion">
          <span class="cl"><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
          <span class="cv"><span class="badge brand"><?= pct($weight * 100, 0) ?></span></span>
          <span class="cn"><?= e($what[$key] ?? '') ?></span>
          <span class="track"><span class="fill" style="width:<?= (float) round($weight * 250, 0) ?>%"></span></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="xsmall muted mt-2" style="margin-bottom:0">
      Weights total <?= pct(array_sum($w) * 100, 1) ?>. Bands: Critical 75+, High 60–74, Moderate 45–59,
      Watch 30–44, Low under 30.
    </p>
  </div>
</div>
