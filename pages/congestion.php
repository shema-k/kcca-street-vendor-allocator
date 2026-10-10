<?php
/**
 * Congestion Analysis — the "most affected areas" dashboard.
 *
 * Ranks every surveyed trade zone by the congestion engine, exposes the
 * full scoring method, and lets an officer open one zone in detail:
 * obstruction profile, vendor register for that site, enforcement history
 * and the ranked markets those vendors can be moved to.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f    = facts();
$city = $f['city'];

$band     = (string) ($_GET['band'] ?? '');
$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$type     = (string) ($_GET['type'] ?? '');
$zoneId   = (int) ($_GET['zone'] ?? 0);

$zones = scoredZones($division);

if ($band !== '') {
    $zones = array_values(array_filter($zones, static fn($z) => strtolower($z['band']) === strtolower($band)));
}
if ($type !== '') {
    $zones = array_values(array_filter($zones, static fn($z) => $z['zone_type'] === $type));
}
usort($zones, static fn($a, $b) => $b['score'] <=> $a['score']);

/* ---------------- Band and type distributions ---------------- */
$allZones   = scoredZones();
$bandCounts = [];
$typeCounts = [];
foreach ($allZones as $z) {
    $bandCounts[$z['band']][] = $z;
    $typeCounts[$z['zone_type']][] = $z;
}
$bands = ['Critical', 'High', 'Moderate', 'Watch', 'Low'];
$types = ['roadside','pavement','veranda','junction','roundabout','bus-stage','open-park','entrance'];

/* ---------------- The weights, shown as part of the method ---------------- */
$w = ZONE_SCORING_WEIGHTS;
$weightRows = [
    ['obstruction',    'Physical obstruction of public space', 'Footpath taken (65%) blended with carriageway taken (35%).'],
    ['vendor_load',    'Number of vendors affected',           'Square-root compressed so a large site is prioritised without swamping everything else.'],
    ['traffic_impact', 'Traffic and emergency access',          'Higher at junctions, roundabouts and stages; higher again on a strategic corridor.'],
    ['duration',       'How long the occupation has persisted', 'Square root of months occupied. A ten-year failure must close, not postpone.'],
    ['strategic',      'Strategic corridor importance',         'Designated priority corridors score full marks.'],
    ['public_access',  'Obstruction of a public facility',      'A hospital entrance outranks a general complaint.'],
    ['complaints',     'Public complaints in 30 days',          'Volume of logged complaints.'],
];

$detail = null;
if ($zoneId > 0) {
    $detail = Database::one(
        'SELECT z.*, d.code AS div_code, d.name AS division_name, d.mayor AS division_mayor
           FROM trade_zones z JOIN divisions d ON d.id = z.division_id
          WHERE z.id = ? LIMIT 1',
        [$zoneId]
    );
}

$pageTitle = 'Congestion Analysis';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Congestion Analysis
    </div>
    <div class="eyebrow">Physical Planning · Trade order</div>
    <h1>Congestion Analysis</h1>
    <p>
      Which places in Kampala suffer most from unlawful street vending, ranked by a seven-criterion
      composite score. The score is not a judgement about the traders — it measures the harm the
      occupation causes to public space, so the order of enforcement follows the harm.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="city-map.php"><?= KCCA_icon('map', 15) ?> See on the map</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ============================ HEADLINE =========================== -->
<?php
$totalVendors = array_sum(array_column($allZones, 'estimated_vendors'));
$totalObstruction = $allZones === [] ? 0 : round(array_sum(array_map(
    static fn($z) => (float) $z['footpath_obstruction_pct'] * 0.65 + (float) $z['carriageway_encroachment_pct'] * 0.35,
    $allZones
)) / count($allZones), 1);
$strategicCount = count(array_filter($allZones, static fn($z) => (bool) $z['strategic_corridor']));
$criticalCount  = count($bandCounts['Critical'] ?? []);
?>
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Affected sites</div>
    <div class="value"><?= num(count($allZones)) ?></div>
    <div class="meta">surveyed citywide</div>
  </div>
  <div class="stat bad">
    <div class="label">Vendors affected</div>
    <div class="value"><?= num($totalVendors) ?></div>
    <div class="meta">trading unlawfully</div>
  </div>
  <div class="stat bad">
    <div class="label">Critical sites</div>
    <div class="value"><?= num($criticalCount) ?></div>
    <div class="meta">score 75 and above</div>
  </div>
  <div class="stat warn">
    <div class="label">Strategic corridors</div>
    <div class="value"><?= num($strategicCount) ?></div>
    <div class="meta">of <?= num(count($allZones)) ?> sites</div>
  </div>
  <div class="stat warn">
    <div class="label">Mean obstruction</div>
    <div class="value"><?= pct($totalObstruction, 0) ?></div>
    <div class="meta">of footpath and carriageway</div>
  </div>
</div>

<!-- ============================ FILTERS ============================ -->
<div class="card">
  <form class="filters" method="get">
    <?php renderDivisionFilter('division', $division); ?>

    <div class="field">
      <label for="type">Occupation type</label>
      <select name="type" id="type" data-autosubmit>
        <option value="">All types</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
            <?= e(humanise($t)) ?> (<?= count($typeCounts[$t] ?? []) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field grow">
      <label>Congestion band</label>
      <div class="chips">
        <a class="chip <?= $band === '' ? 'on' : '' ?>" href="<?= e(urlWith(['band' => null, 'type' => null, 'page' => null])) ?>">
          All (<?= count($allZones) ?>)
        </a>
        <?php foreach ($bands as $b):
            $n = count($bandCounts[$b] ?? []);
            $tone = ['Critical'=>'bad','High'=>'warn','Moderate'=>'info','Watch'=>'ok','Low'=>'ok'][$b];
        ?>
          <a class="chip <?= strtolower($band) === strtolower($b) ? 'on' : '' ?>"
             href="<?= e(urlWith(['band' => $band === $b ? null : strtolower($b), 'type' => null, 'page' => null])) ?>">
            <?= e($b) ?> <span class="badge <?= $tone ?>" style="font-size:10px;padding:0 5px"><?= $n ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <a class="btn btn-ghost" href="congestion.php">Reset</a>
  </form>
</div>

<?php if ($detail):
    $dScore = ZoneScoringEngine::score($detail);
    $dVendors = Database::all(
        "SELECT id, vendor_code, full_name, business_category, gender, status, dependants,
                is_person_with_disability, is_single_parent, has_trading_licence, monthly_turnover_ugx
           FROM vendors WHERE current_zone_id = ? ORDER BY full_name LIMIT 40",
        [$detail['id']]
    );
    $dEnf = Database::all('SELECT * FROM enforcement_actions WHERE zone_id = ? ORDER BY issued_date DESC, id DESC', [$detail['id']]);
    $dDays = daysUntil($detail['deadline_date']);
?>

<!-- ========================= ZONE DETAIL =========================== -->
<div class="card" id="zone-detail">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="congestion.php">Congestion</a><span class="sep">/</span><?= e($detail['division_name']) ?>
      </div>
      <h2><?= e($detail['name']) ?></h2>
      <p class="desc">
        <?= e($detail['road_or_feature'] ?: 'No road recorded') ?> ·
        <?= e(humanise($detail['zone_type'])) ?> ·
        <?= e($detail['parish']) ?> parish ·
        surveyed <?= e(fmtDate($detail['survey_date'])) ?>
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($detail['status'])) ?>"><?= e(humanise($detail['status'])) ?></span>
      <a class="btn btn-ghost btn-sm" href="city-map.php?division=<?= e($detail['div_code']) ?>">View division</a>
      <a class="btn btn-quiet btn-sm" href="congestion.php">Close</a>
    </div>
  </div>

  <div class="card-body">
    <div class="grid g-3-2">
      <div>
        <div class="grid g4 mb-2">
          <div class="stat bad">
            <div class="label">Vendors at this site</div>
            <div class="value"><?= num((int) $detail['estimated_vendors']) ?></div>
          </div>
          <div class="stat warn">
            <div class="label">Footpath taken</div>
            <div class="value"><?= pct($detail['footpath_obstruction_pct'], 0) ?></div>
          </div>
          <div class="stat warn">
            <div class="label">Carriageway taken</div>
            <div class="value"><?= pct($detail['carriageway_encroachment_pct'], 0) ?></div>
          </div>
          <div class="stat info">
            <div class="label">Occupied for</div>
            <div class="value"><?= num((int) $detail['months_occupied']) ?></div>
            <div class="meta">months</div>
          </div>
        </div>

        <h3 class="mb-1">Why this score</h3>
        <div class="criteria">
          <?php
          $totW = array_sum(ZONE_SCORING_WEIGHTS);
          usort($dScore['criteria'], static fn($a, $b) => ($b['raw'] * $b['weight']) <=> ($a['raw'] * $a['weight']));
          foreach ($dScore['criteria'] as $c):
              $tone = $c['raw'] >= 65 ? 'bad' : ($c['raw'] >= 45 ? 'warn' : ($c['raw'] >= 28 ? 'info' : 'ok'));
          ?>
            <div class="criterion">
              <span class="cl"><?= e($c['label']) ?></span>
              <span class="cv"><?= num($c['raw'], 1) ?><span class="muted small"> / 100</span></span>
              <span class="cn"><?= e($c['note']) ?></span>
              <span class="track"><span class="fill <?= $tone === 'bad' ? '' : $tone ?>"
                    style="width:<?= (float) clamp($c['raw'], 1, 100) ?>%;
                           background:<?= ['bad'=>'#C0261A','warn'=>'#E8730C','info'=>'#EAA515','ok'=>'#1B7F4D'][$tone] ?>"></span></span>
              <span class="cw">
                weight <?= pct($c['weight'] * 100, 0) ?> ·
                contributing <?= num($c['raw'] * $c['weight'] / $totW, 1) ?> points of <?= num($dScore['score'], 1) ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="divider"></div>
        <h3 class="mb-1">Conclusion</h3>
        <ul class="reasons outcome">
          <?php foreach ($dScore['reasons'] as $r): ?>
            <li><?= e($r) ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="card" style="margin:16px 0 0">
          <div class="card-head">
            <div class="grow"><h3>Enforcement history</h3></div>
            <a class="btn btn-ghost btn-sm" href="enforcement.php?zone=<?= (int) $detail['id'] ?>">Open</a>
          </div>
          <?php if (!$dEnf): ?>
            <div class="card-body"><p class="muted small mb-0">No enforcement action recorded at this site yet.</p></div>
          <?php else: ?>
            <div class="table-wrap">
              <table class="data compact">
                <thead><tr><th>Reference</th><th>Action</th><th>Issued</th><th>Deadline</th><th class="center">Status</th><th class="right">Relocated</th></tr></thead>
                <tbody>
                  <?php foreach ($dEnf as $e): ?>
                    <tr>
                      <td class="mono xsmall"><?= e($e['reference']) ?></td>
                      <td><?= e(humanise($e['action_type'])) ?></td>
                      <td class="xsmall"><?= e(fmtDate($e['issued_date'])) ?></td>
                      <td class="xsmall"><?= e(fmtDate($e['deadline_date'])) ?></td>
                      <td class="center"><span class="badge <?= e(statusTone($e['status'])) ?>"><?= e(humanise($e['status'])) ?></span></td>
                      <td class="right num"><?= num((int) $e['vendors_relocated']) ?><span class="muted">/<?= num((int) $e['vendors_affected']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right column: the deadline, the vendors, the destinations -->
      <div>
        <?php if ($dDays !== null): ?>
          <div class="alert <?= $dDays < 0 ? 'alert-bad' : ($dDays <= 14 ? 'alert-warn' : 'alert-info') ?>">
            <strong><?= $dDays < 0 ? 'Deadline passed' : 'Deadline' ?>:</strong>
            <?= e(fmtDate($detail['deadline_date'])) ?> —
            <?= $dDays < 0 ? abs($dDays) . ' days ago' : $dDays . ' days remaining' ?>.
            Notified <?= e(fmtDate($detail['notified_date'])) ?>.
          </div>
        <?php endif; ?>

        <div class="card" style="margin-top:0">
          <div class="card-head">
            <div class="grow"><h3>Trading pattern</h3></div>
          </div>
          <div class="card-body">
            <dl class="kv">
              <dt>Peak days</dt><dd><?= e($detail['peak_days'] ?: '—') ?></dd>
              <dt>Peak hours</dt><dd><?= e($detail['peak_hours'] ?: '—') ?></dd>
              <dt>Strategic corridor</dt><dd><?= $detail['strategic_corridor'] ? 'Yes' : 'No' ?></dd>
              <dt>Public facility</dt><dd><?= e($detail['near_public_asset'] ?: 'None affected') ?></dd>
              <dt>Complaints (30d)</dt><dd><?= num((int) $detail['complaints_30d']) ?></dd>
              <dt>Trade mix</dt><dd style="text-align:right;max-width:210px"><?= e($detail['category_mix'] ?: '—') ?></dd>
            </dl>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div class="grow">
              <h3>Vendors at this site</h3>
              <p class="desc"><?= num(count($dVendors)) ?> of <?= num((int) $detail['estimated_vendors']) ?> shown</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="vendors.php?zone=<?= (int) $detail['id'] ?>">Full register</a>
          </div>
          <div class="table-wrap" style="max-height:340px;overflow-y:auto">
            <table class="data compact">
              <thead><tr><th>Vendor</th><th>Trade</th><th class="center">Status</th></tr></thead>
              <tbody>
                <?php foreach ($dVendors as $v): ?>
                  <tr>
                    <td>
                      <a href="relocation.php?vendor=<?= (int) $v['id'] ?>"><strong><?= e($v['full_name']) ?></strong></a>
                      <div class="xsmall muted">
                        <?= e($v['vendor_code']) ?>
                        <?php if ($v['is_single_parent']): ?> · single parent<?php endif; ?>
                        <?php if ($v['is_person_with_disability']): ?> · PWD<?php endif; ?>
                      </div>
                    </td>
                    <td class="xsmall"><?= e(humanise($v['business_category'])) ?></td>
                    <td class="center"><span class="badge <?= e(statusTone($v['status'])) ?>"><?= e(humanise($v['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($detail['near_public_asset']): ?>
          <div class="quote">
            <p>Clearance at this site directly restores access to <?= e($detail['near_public_asset']) ?>.</p>
            <footer>Recorded in the survey of <?= e(fmtDate($detail['survey_date'])) ?>.</footer>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ======================= RANKED TABLE =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Ranked sites</h2>
      <p class="desc">
        <?= count($zones) ?> of <?= count($allZones) ?> sites shown.
        Select a row to open the full congestion profile.
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>#</th><th>Site</th><th>Division</th><th>Type</th>
            <th class="right">Vendors</th>
            <th style="width:150px">Obstruction</th>
            <th class="right">Months</th>
            <th class="right">Complaints</th>
            <th class="center">Strategic</th>
            <th class="right">Score</th>
            <th class="center">Band</th>
            <th class="center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zones as $i => $z):
              $obstr = (float) $z['footpath_obstruction_pct'] * 0.65 + (float) $z['carriageway_encroachment_pct'] * 0.35;
              $days = daysUntil($z['deadline_date']);
          ?>
            <tr>
              <td><span class="rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span></td>
              <td>
                <a href="congestion.php?zone=<?= (int) $z['id'] ?><?= $division !== '' ? '&division=' . e($division) : '' ?>">
                  <strong><?= e($z['name']) ?></strong>
                </a>
                <div class="xsmall muted"><?= e($z['road_or_feature'] ?: '—') ?> · <?= e($z['parish']) ?></div>
              </td>
              <td class="xsmall"><?= e($z['division_name']) ?></td>
              <td class="xsmall"><?= e(humanise($z['zone_type'])) ?></td>
              <td class="right num strong"><?= num((int) $z['estimated_vendors']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $obstr >= 65 ? 'bad' : ($obstr >= 45 ? 'warn' : '') ?>"
                        style="width:<?= (float) clamp($obstr, 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($obstr, 0) ?></span>
                </div>
              </td>
              <td class="right num"><?= num((int) $z['months_occupied']) ?></td>
              <td class="right num"><?= num((int) $z['complaints_30d']) ?></td>
              <td class="center"><?= $z['strategic_corridor']
                    ? '<span class="badge warn">Yes</span>' : '<span class="muted small">No</span>' ?></td>
              <td class="right num strong"><?= num($z['score'], 1) ?></td>
              <td class="center"><span class="badge <?= e($z['tone']) ?>"><?= e($z['band']) ?></span></td>
              <td class="center">
                <span class="badge <?= e(statusTone($z['status'])) ?>"><?= e(humanise($z['status'])) ?></span>
                <?php if ($days !== null && $days >= 0 && $days <= 30): ?>
                  <div class="xsmall muted"><?= $days ?>d left</div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if (!$zones): ?>
    <div class="empty">
      <div class="big"><?= KCCA_icon('search', 32) ?></div>
      <h3>No sites match these filters</h3>
      <p>Clear the band, type or division filter to see the full ranking.</p>
      <a class="btn btn-ghost" href="congestion.php">Reset filters</a>
    </div>
  <?php endif; ?>
</div>

<!-- ====================== CHARTS + METHOD ========================= -->
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Where the obstruction is</h2>
        <p class="desc">Footpath and carriageway encroachment at each site, ranked.</p>
      </div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 186,
          'rowH' => 24,
          'max'   => 100,
          'format' => 'pct',
          'items' => array_map(static function (array $z): array {
              $o = (float) $z['footpath_obstruction_pct'] * 0.65 + (float) $z['carriageway_encroachment_pct'] * 0.35;
              return [
                'label' => $z['name'],
                'value' => round($o, 1),
                'color' => $o >= 65 ? '#C0261A' : ($o >= 45 ? '#E8730C' : '#1B7F4D'),
              ];
          }, array_slice($allZones, 0, 12)),
        ],
      ]) ?>' aria-label="Obstruction by site"></svg>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Composition of the harm</h2>
        <p class="desc">Vendors affected, by occupation type.</p>
      </div>
    </div>
    <div class="card-body">
      <?php
      $typeVendors = [];
      foreach ($typeCounts as $t => $list) {
          $typeVendors[] = ['label' => humanise($t), 'value' => array_sum(array_column($list, 'estimated_vendors'))];
      }
      usort($typeVendors, static fn($a, $b) => $b['value'] <=> $a['value']);
      ?>
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'donut',
        'opts' => [
          'height' => 230,
          'slices' => array_map(static fn($x, $i) => $x + ['color' => KCCACharts_placeholder_color($i)], $typeVendors, array_keys($typeVendors)),
          'centre' => num($totalVendors),
          'centreSub' => 'vendors affected',
        ],
      ]) ?>' aria-label="Vendors by occupation type"></svg>
      <div class="legend">
        <?php foreach ($typeVendors as $i => $x): ?>
          <span class="item">
            <span class="sw" style="background:<?= KCCACharts_placeholder_color($i) ?>"></span>
            <?= e($x['label']) ?> — <?= num($x['value']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ========================= THE METHOD ============================ -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>How the congestion score is calculated</h2>
      <p class="desc">
        Seven criteria, each scored 0–100, combined as a weighted average. The weights are policy
        settings held in <code>config/config.php</code> and may be adjusted by the Authority.
      </p>
    </div>
    <span class="badge green">Explainable by design</span>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Criterion</th><th style="width:130px">Weight</th><th>What it measures and why</th></tr>
        </thead>
        <tbody>
          <?php foreach ($weightRows as [$key, $label, $note]): ?>
            <tr>
              <td><strong><?= e($label) ?></strong></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill" style="width:<?= (float) round($w[$key] * 100 * 2.5, 0) ?>%"></span></span>
                  <span class="val"><?= pct($w[$key] * 100, 0) ?></span>
                </div>
              </td>
              <td class="small muted"><?= e($note) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="strong">Total</td>
            <td class="num strong"><?= pct(array_sum($w) * 100, 0) ?></td>
            <td class="small muted">
              Bands: Critical 75+, High 60–74, Moderate 45–59, Watch 30–44, Low under 30.
              Clearing a Critical site is scheduled first; clearing a Low site is proportionate to its harm.
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div class="card-foot">
    The score guides the order of work. It does not determine a vendor's fate — that is the relocation
    engine's job, and it is <a href="relocation.php">computed per vendor</a> on six separate criteria.
  </div>
</div>

<?php
/** Palette mirror of the JS chart library, used to colour the legend swatches. */
function KCCACharts_placeholder_color(int $i): string
{
    static $p = ['#DC351D','#EAA515','#0A6339','#1D6FA5','#8E44AD','#2F8F7B','#CE4759','#7A5A10','#3498DB','#16A085'];
    return $p[$i % count($p)];
}

require __DIR__ . '/../layouts/footer.php';