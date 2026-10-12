<?php
/**
 * Performance & KPIs — the system measured against the Authority's
 * Strategic Plan FY2025/26 – FY2029/30 and its published targets.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('report');

$f   = facts();
$vac = $f['vacancy'];
$plan = $f['strategic_plan'];

$indicators = Database::all('SELECT * FROM strategic_indicators ORDER BY objective_no, id');

/* ---------------- Programme measures ---------------- */
$zonesTotal   = (int) Database::value('SELECT COUNT(*) FROM trade_zones');
$zonesCleared = (int) Database::value("SELECT COUNT(*) FROM trade_zones WHERE status = 'cleared'");
$needTotal    = (int) Database::value('SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones');
$relocated    = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'relocated'");
$offered      = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'offered-space'");
$declined     = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'declined'");

$clearanceRate = $needTotal > 0 ? round($relocated / $needTotal * 100, 1) : 0.0;
$pipelineRate  = $needTotal > 0 ? round(($offered + $relocated) / $needTotal * 100, 1) : 0.0;
$acceptRate    = ($offered + $relocated) > 0 ? round($relocated / ($offered + $relocated) * 100, 1) : 0.0;

/* Space utilisation before and after relocation */
$capacity    = (int) Database::value('SELECT COALESCE(SUM(capacity),0) FROM markets');
$registered  = (int) Database::value('SELECT COALESCE(SUM(registered_vendors),0) FROM markets');
$vacantNow   = $capacity - $registered;
$vacantStart = $capacity - $relocated;   // approximate: what would be vacant if none had moved
$utilBefore  = $capacity > 0 ? round((($vacantStart) / $capacity) * 100, 1) : 0.0;
$utilAfter   = $capacity > 0 ? round(($vacantNow / $capacity) * 100, 1) : 0.0;

/* ---------------- Monthly activity ---------------- */
$monthly = Database::all(
    "SELECT DATE_FORMAT(issued_date, '%Y-%m') AS ym,
            COUNT(*) AS actions,
            SUM(action_type = 'survey') AS surveys,
            SUM(action_type IN ('notice','ultimatum')) AS notices,
            SUM(action_type = 'eviction') AS evictions,
            COALESCE(SUM(vendors_relocated),0) AS relocated
       FROM enforcement_actions
      GROUP BY ym ORDER BY ym"
);

/* Division comparison */
$divisionPerf = Database::all(
    "SELECT d.name, d.code, d.population, d.area_sqkm,
            (SELECT COUNT(*) FROM trade_zones z WHERE z.division_id = d.id) AS zones,
            (SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones z WHERE z.division_id = d.id) AS affected,
            (SELECT COUNT(*) FROM vendors v WHERE v.division_id = d.id AND v.status = 'relocated') AS relocated,
            (SELECT COUNT(*) FROM vendors v WHERE v.division_id = d.id AND v.status = 'declined') AS declined,
            (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets m WHERE m.division_id = d.id) AS vacant
       FROM divisions d ORDER BY d.name"
);

$tradeMix = Database::all(
    "SELECT business_category, COUNT(*) AS n,
            SUM(gender = 'female') AS women,
            SUM(is_single_parent) AS single_parents,
            ROUND(AVG(monthly_turnover_ugx)) AS avg_turnover
       FROM vendors GROUP BY business_category ORDER BY n DESC"
);

/* Public-space restored, derived from the cleared zones.
   Note the column is estimated_vendors, not vendors_affected. */
$restored = Database::one(
    "SELECT COALESCE(SUM(footpath_obstruction_pct),0)  AS footpath,
            COALESCE(SUM(carriageway_encroachment_pct),0) AS carriageway,
            COALESCE(SUM(estimated_vendors),0)            AS vendors
       FROM trade_zones WHERE status = 'cleared'"
);

$pageTitle = 'Performance & KPIs';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Performance &amp; KPIs
    </div>
    <div class="eyebrow">Strategy Management · Corporate performance</div>
    <h1>Performance &amp; KPIs</h1>
    <p>
      The relocation programme measured against its own targets and against the
      <?= e($plan['title']) ?> — <?= e($plan['value']) ?>, launched under the theme
      “<?= e($plan['theme']) ?>”.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="reports.php"><?= KCCA_icon('download', 15) ?> Reports</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ======================= PROGRAMME SCORE ====================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Programme performance</h2>
      <p class="desc">Clearance rate, offer pipeline and acceptance rate.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g5 mb-2">
      <div class="stat bad">
        <div class="label">Vendors to relocate</div>
        <div class="value"><?= num($needTotal) ?></div>
        <div class="meta">across <?= num($zonesTotal) ?> affected sites</div>
      </div>
      <div class="stat ok">
        <div class="label">Clearance rate</div>
        <div class="value"><?= pct($clearanceRate) ?></div>
        <div class="meta"><?= num($relocated) ?> now in gazetted space</div>
      </div>
      <div class="stat warn">
        <div class="label">Offer pipeline</div>
        <div class="value"><?= pct($pipelineRate) ?></div>
        <div class="meta"><?= num($offered) ?> awaiting a decision</div>
      </div>
      <div class="stat info">
        <div class="label">Acceptance rate</div>
        <div class="value"><?= pct($acceptRate) ?></div>
        <div class="meta">of offers made</div>
      </div>
      <div class="stat gold">
        <div class="label">Sites cleared</div>
        <div class="value"><?= num($zonesCleared) ?></div>
        <div class="meta">of <?= num($zonesTotal) ?> surveyed sites</div>
      </div>
    </div>

    <div class="grid g2">
      <div>
        <h4 class="mb-1">Programme funnel</h4>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 168, 'rowH' => 28,
            'items' => [
              ['label' => 'Vendors surveyed', 'value' => $needTotal, 'color' => '#464646'],
              ['label' => 'Notified', 'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status <> 'surveyed'"), 'color' => '#1D6FA5'],
              ['label' => 'Space offered', 'value' => $offered, 'color' => '#EAA515'],
              ['label' => 'Relocated', 'value' => $relocated, 'color' => '#1B7F4D'],
              ['label' => 'Declined', 'value' => $declined, 'color' => '#C0261A'],
            ],
          ],
        ]) ?>' aria-label="Programme funnel"></svg>
      </div>
      <div>
        <h4 class="mb-1">Monthly enforcement activity</h4>
        <?php if ($monthly): ?>
          <svg class="chart" data-chart='<?= ejs([
            'type' => 'bar',
            'opts' => [
              'height' => 215,
              'stacked' => true,
              'labels' => array_map(static fn($r) => date('M y', strtotime($r['ym'] . '-01')), $monthly),
              'series' => [
                ['name' => 'Surveys',  'color' => '#464646', 'values' => array_map('intval', array_column($monthly, 'surveys'))],
                ['name' => 'Notices',  'color' => '#EAA515', 'values' => array_map('intval', array_column($monthly, 'notices'))],
                ['name' => 'Evictions','color' => '#DC351D', 'values' => array_map('intval', array_column($monthly, 'evictions'))],
              ],
              'yFormat' => 'num',
            ],
          ]) ?>' aria-label="Enforcement actions per month"></svg>
        <?php else: ?>
          <div class="empty"><p class="muted mb-0">No enforcement activity recorded yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ==================== STRATEGIC INDICATORS ===================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Strategic indicators</h2>
      <p class="desc">
        Progress toward the published targets of the <?= e($plan['title']) ?>. Baseline, target and
        current values are shown so progress can be read at a glance.
      </p>
    </div>
    <span class="badge green"><?= e($plan['theme']) ?></span>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th class="center">Obj</th><th>Indicator</th>
            <th class="right">Baseline</th><th class="right">Target 2030</th>
            <th class="right">Current</th><th style="width:180px">Progress</th><th>Unit</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($indicators as $ind):
              $base = is_numeric($ind['baseline']) ? (float) $ind['baseline'] : null;
              $tgt  = is_numeric($ind['target_2030']) ? (float) $ind['target_2030'] : null;
              $cur  = is_numeric($ind['current_value']) ? (float) $ind['current_value'] : null;
              $increasing = $ind['direction'] === 'increase';

              $pct = null;
              if ($base !== null && $tgt !== null && $cur !== null && $base !== $tgt) {
                  $done = $increasing ? $cur - $base : $base - $cur;
                  $need = $increasing ? $tgt - $base : $base - $tgt;
                  $pct  = $need > 0 ? max(0, min(100, $done / $need * 100)) : null;
              }
          ?>
            <tr>
              <td class="center"><span class="tag"><?= (int) $ind['objective_no'] ?></span></td>
              <td>
                <strong><?= e($ind['indicator']) ?></strong>
                <div class="xsmall muted"><?= e($ind['objective']) ?></div>
              </td>
              <td class="right num"><?= e($ind['baseline']) ?></td>
              <td class="right num strong"><?= e($ind['target_2030']) ?></td>
              <td class="right num"><?= e($ind['current_value']) ?></td>
              <td>
                <?php if ($pct !== null): ?>
                  <div class="meter">
                    <span class="track"><span class="fill <?= $pct >= 50 ? 'ok' : 'info' ?>" style="width:<?= (float) num($pct) ?>%"></span></span>
                    <span class="val"><?= pct($pct, 0) ?></span>
                  </div>
                <?php else: ?>
                  <span class="muted xsmall">Not quantifiable</span>
                <?php endif; ?>
              </td>
              <td class="xsmall muted"><?= e($ind['unit'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    Baselines and targets are published by KCCA. Current values are this
    system's own measures.
  </div>
</div>

<!-- ===================== DIVISION PERFORMANCE =================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Division performance</h2>
      <p class="desc">How each Division Urban Council is progressing, against the space inside its own boundary.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g2 mb-2">
      <div>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 220,
            'labels' => array_column($divisionPerf, 'name'),
            'series' => [
              ['name' => 'Relocated', 'color' => '#1B7F4D',
               'values' => array_map('intval', array_column($divisionPerf, 'relocated'))],
              ['name' => 'Still to relocate', 'color' => '#C0261A',
               'values' => array_map(static fn($r) => (int) $r['affected'] - (int) $r['relocated'], $divisionPerf)],
            ],
            'stacked' => true,
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Relocation progress by division"></svg>
        <div class="legend">
          <span class="item"><span class="sw" style="background:#1B7F4D"></span> Relocated</span>
          <span class="item"><span class="sw" style="background:#C0261A"></span> Still to relocate</span>
        </div>
      </div>
      <div>
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 220,
            'labels' => array_column($divisionPerf, 'name'),
            'series' => [
              ['name' => 'Vendors affected', 'color' => '#DC351D',
               'values' => array_map('intval', array_column($divisionPerf, 'affected'))],
              ['name' => 'Vacant spaces', 'color' => '#0A6339',
               'values' => array_map('intval', array_column($divisionPerf, 'vacant'))],
            ],
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Demand against space by division"></svg>
        <div class="legend">
          <span class="item"><span class="sw" style="background:#DC351D"></span> Vendors to relocate</span>
          <span class="item"><span class="sw" style="background:#0A6339"></span> Vacant spaces in the division</span>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Division</th><th class="right">Population</th><th class="right">Affected sites</th>
            <th class="right">Vendors affected</th><th class="right">Relocated</th>
            <th class="right">Declined</th><th class="right">Vacant in division</th>
            <th style="width:160px">Clearance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($divisionPerf as $d):
              $rate = (int) $d['affected'] > 0 ? round((int) $d['relocated'] / (int) $d['affected'] * 100, 1) : 0.0;
          ?>
            <tr>
              <td><a href="city-map.php?division=<?= e($d['code']) ?>"><strong><?= e($d['name']) ?></strong></a></td>
              <td class="right num"><?= num((int) $d['population']) ?></td>
              <td class="right num"><?= num((int) $d['zones']) ?></td>
              <td class="right num strong"><?= num((int) $d['affected']) ?></td>
              <td class="right num"><?= num((int) $d['relocated']) ?></td>
              <td class="right num"><?= num((int) $d['declined']) ?></td>
              <td class="right num"><?= num((int) $d['vacant']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $rate >= 50 ? 'ok' : ($rate > 0 ? 'info' : '') ?>"
                        style="width:<?= (float) clamp($rate, 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($rate, 0) ?></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ====================== PUBLIC SPACE GAIN ===================== -->
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Public space restored</h2>
        <p class="desc">What clearing the completed sites returned to the city.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid g3">
        <div class="stat ok">
          <div class="label">Footpath returned</div>
          <div class="value"><?= pct($restored['footpath'] / max(1, $zonesCleared) * 100 / 100, 0) ?></div>
          <div class="meta">mean across cleared sites</div>
        </div>
        <div class="stat ok">
          <div class="label">Carriageway returned</div>
          <div class="value"><?= pct($restored['carriageway'] / max(1, $zonesCleared), 0) ?></div>
          <div class="meta">mean across cleared sites</div>
        </div>
        <div class="stat gold">
          <div class="label">Vendors relocated</div>
          <div class="value"><?= num((int) $restored['vendors']) ?></div>
          <div class="meta">from cleared sites</div>
        </div>
      </div>

      <div class="divider"></div>

      <h4 class="mb-1">Market utilisation</h4>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 190,
          'labels' => ['Vacancy before the programme', 'Vacancy now'],
          'series' => [['name' => 'Vacant % of capacity', 'color' => '#0A6339',
                        'values' => [$utilBefore, $utilAfter]]],
          'showValues' => true,
          'yFormat' => 'pct',
        ],
      ]) ?>' aria-label="Market vacancy before and after"></svg>

      <div class="source mt-2">
        <span class="lbl derived">derived</span>
        <span>
          Vacancy before is calculated as total capacity less the vendors already relocated;
          this is a measure of the programme's effect, not a published KCCA figure.
          Total capacity across all <?= num($capacity) ?> pitches.
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Who the programme reaches</h2>
        <p class="desc">Vulnerability profile of the register, by trade.</p>
      </div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 172, 'rowH' => 25,
          'items' => array_map(static fn($r): array => [
            'label' => humanise($r['business_category']),
            'value' => (int) $r['n'],
            'color' => '#DC351D',
          ], $tradeMix),
        ],
      ]) ?>' aria-label="Vendors by trade"></svg>

      <div class="divider"></div>
      <div class="grid g3">
        <div class="stat gold">
          <div class="label">Women</div>
          <div class="value"><?= pct(array_sum(array_column($tradeMix, 'women')) / max(1, $needTotal) * 100, 0) ?></div>
          <div class="meta">of all vendors affected</div>
        </div>
        <div class="stat brand">
          <div class="label">Single parents</div>
          <div class="value"><?= num(array_sum(array_column($tradeMix, 'single_parents'))) ?></div>
          <div class="meta">highest equity weighting</div>
        </div>
        <div class="stat info">
          <div class="label">Turnover at stake</div>
          <div class="value" style="font-size:19px"><?= ugxShort((int) Database::value('SELECT COALESCE(SUM(monthly_turnover_ugx),0) FROM vendors')) ?></div>
          <div class="meta">per month, citywide</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================= THE PILLARS ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>The plan this programme serves</h2>
      <p class="desc">
        <?= e($plan['title']) ?> ·
        theme “<?= e($plan['theme']) ?>” ·
        launched <?= e(fmtDate($plan['launched'])) ?>
        together with the <?= e(implode(' and ', $plan['ancillary'])) ?>.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="https://www.kcca.go.ug/news/1017" target="_blank" rel="noopener">
      Launch statement <?= KCCA_icon('external', 12) ?>
    </a>
  </div>
  <div class="card-body">
    <div class="row mb-2">
      <?php foreach ($plan['pillars'] as $i => $p): ?>
        <span class="badge brand" style="font-size:12px;padding:4px 12px"><?= e($p) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="grid g2">
      <?php foreach ($plan['objectives'] as $i => $obj):
          $related = array_filter($indicators, static fn($ind) => (int) $ind['objective_no'] === $i + 1);
      ?>
        <div class="criterion">
          <span class="cl">Objective <?= $i + 1 ?></span>
          <span class="cv"><span class="badge muted"><?= count($related) ?> indicator<?= count($related) === 1 ? '' : 's' ?></span></span>
          <span class="cn strong"><?= e($obj) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="quote mt-2">
      <p>“This plan is not a mere policy document, but a change tool that will deliver a capital city
         of opportunity and progress.”</p>
      <footer>
        <b>His Worship Balimwezo Ronald Nsubuga</b>, Lord Mayor of Kampala City · 30 September 2025
      </footer>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';