<?php
/**
 * AI Allocation Planner.
 *
 * Places a whole batch of traders into the Authority's vacant pitches at
 * once, so that no pitch is promised twice and the group as a whole is
 * placed as well as it can be. See lib/AllocationPlanner.php for how it
 * decides; this screen is for choosing a policy, reading the result, and
 * sending it on for approval.
 *
 * Nothing on this page allocates anything. A plan becomes recommendations
 * awaiting approval, and a different officer approves them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('allocate');
Auth::requireScreen('ai-planner');

@set_time_limit(180);

$params  = AllocationPlanner::params($_GET);
$lock    = divisionLock();
$wantRun = isset($_GET['run']);
$wantCmp = isset($_GET['compare']);

$plan = $wantRun ? AllocationPlanner::plan($params) : null;
$comparison = $wantCmp ? AllocationPlanner::compare($params) : null;

/* ---------------- CSV export ----------------
   Before any output. An export is an accountable act, so it is logged. */
if ($plan !== null && (string) ($_GET['format'] ?? '') === 'csv') {
    Auth::log('export', 'allocation_plan', null,
        'Downloaded plan ' . $plan['reference'] . ' (' . count($plan['assignments']) . ' placements)');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . strtolower($plan['reference']) . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['AI allocation plan', $plan['reference']]);
    fputcsv($out, ['Policy', AllocationPlanner::MODES[$params['mode']]['label']]);
    fputcsv($out, ['Prepared by', (string) (Auth::user()['full_name'] ?? '')]);
    fputcsv($out, ['Generated', date('d F Y H:i') . ' EAT']);
    fputcsv($out, ['Status', 'Proposal only. Nothing is allocated until the recommendations are approved.']);
    fputcsv($out, []);
    fputcsv($out, ['Priority rank', 'Vendor code', 'Vendor', 'Trade', 'Current site', 'Placed at',
                   'Market division', 'Fit (this policy)', 'Fit (standing weights)', 'Distance km', 'Need priority', 'Reasoning']);
    foreach ($plan['assignments'] as $idx => $a) {
        fputcsv($out, [
            $idx + 1, $a['vendor']['code'], $a['vendor']['name'], humanise($a['vendor']['category']),
            $a['vendor']['zone'], $a['market']['name'], $a['market']['division'],
            $a['fit'], $a['neutral_fit'], $a['distance_km'], $a['vendor']['priority'],
            implode(' | ', $a['strengths']) . ($a['caution'] ? ' | Watch - ' . $a['caution'] : ''),
        ]);
    }
    fclose($out);
    exit;
}

/* ---------------- Context for the form ---------------- */
$scope = RoleDashboard::scope();
$waiting = (int) Database::value(
    "SELECT COUNT(*) FROM vendors v
      WHERE v.status IN ('surveyed','notified') AND v.current_zone_id IS NOT NULL"
    . RoleDashboard::scopeSql($scope, 'v')
);
$vacant = (int) Database::value(
    'SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE status IN (\'operational\',\'upgrading\')'
    . RoleDashboard::scopeFor('markets', $scope)
);

$divisions = Database::all('SELECT code, name FROM divisions ORDER BY name');
$zones = Database::all(
    'SELECT id, name FROM trade_zones' . ($lock !== null ? ' WHERE division_id = ' . (int) $lock['id'] : '') . ' ORDER BY name'
);

$pageTitle = 'AI Planner';
require __DIR__ . '/../layouts/header.php';

$S = $plan['summary'] ?? null;
?>
<style>
  .planner-steps { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; counter-reset:step; }
  .planner-step { position:relative; padding:14px 14px 14px 52px; border:1px solid var(--line); border-radius:var(--radius); background:var(--surface); }
  .planner-step::before { counter-increment:step; content:counter(step); position:absolute; left:14px; top:14px; width:28px; height:28px; border-radius:50%;
    display:grid; place-items:center; font-weight:800; font-size:13px; color:var(--accent-ink); background:var(--accent); box-shadow:0 3px 8px rgba(var(--accent-rgb),.5); }
  .planner-step b { display:block; color:var(--ink); margin-bottom:2px; }
  .planner-step span { font-size:12.5px; color:var(--ink-3); }
  .policy-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
  .policy { display:block; cursor:pointer; }
  .policy input { position:absolute; opacity:0; pointer-events:none; }
  .policy .policy-body { display:block; height:100%; padding:13px 14px; border:2px solid var(--line); border-radius:var(--radius); background:var(--surface); transition:border-color .15s, box-shadow .15s; }
  .policy:hover .policy-body { border-color:var(--ink-4); }
  .policy input:checked + .policy-body { border-color:var(--accent-deep); box-shadow:0 0 0 3px rgba(var(--accent-rgb),.40); }
  .policy input:focus-visible + .policy-body { outline:2px solid var(--accent-deep); outline-offset:2px; }
  .policy b { display:block; color:var(--ink); font-size:14px; margin-bottom:3px; }
  .policy small { display:block; color:var(--ink-3); font-size:12px; line-height:1.45; }
  .plan-why { font-size:12px; color:var(--ink-3); line-height:1.5; max-width:46ch; }
  .plan-why .moved { color:var(--warn); }
  .plan-why .watch { color:var(--bad); }
  .plan-compare { display:flex; align-items:baseline; gap:6px; }
  @media (max-width:1100px){ .planner-steps,.policy-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:640px){ .planner-steps,.policy-grid{ grid-template-columns:minmax(0,1fr); } }
</style>

<section class="page-head">
  <div class="grow">
    <div class="eyebrow">Relocation</div>
    <h1>AI Planner</h1>
    <p>Places a whole batch of traders at once, so no pitch is promised twice. It ranks traders by need, scores every
       market on six criteria, rules out restricted land, then finds the combination with the best total fit.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="relocation.php"><?= KCCA_icon('swap', 14) ?> Match one</a>
    <a class="btn btn-ghost btn-sm" href="recommendations.php?status=proposed"><?= KCCA_icon('chart', 14) ?> Proposals</a>
  </div>
</section>

<div class="flow" aria-label="How the plan is made"><span>Need</span><i>→</i><span>Fit</span><i>→</i><span>Rules</span><i>→</i><span>Best combination</span></div>

<!-- =========================== CONTROLS ========================== -->
<form class="card" method="get" action="ai_planner.php" id="planner-form">
  <div class="card-head">
    <div class="grow">
      <h2>Draw up a plan</h2>
      <p class="desc" style="display:block !important">
        <?= num($waiting) ?> waiting · <?= num($vacant) ?> pitches vacant<?= $lock ? ' · ' . e($lock['name']) : '' ?>
      </p>
    </div>
  </div>
  <div class="card-body">
    <div class="policy-grid mb-2" role="radiogroup" aria-label="Allocation policy">
      <?php foreach (AllocationPlanner::MODES as $key => $mode): ?>
        <label class="policy" title="<?= e($mode['blurb']) ?>">
          <input type="radio" name="mode" value="<?= e($key) ?>" <?= $params['mode'] === $key ? 'checked' : '' ?>>
          <span class="policy-body"><b><?= e($mode['label']) ?></b><small><?= e($mode['tag']) ?></small></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="grid g4">
      <div class="field">
        <label for="batch">Batch size</label>
        <input id="batch" type="number" name="batch" min="5" max="<?= (int) AllocationPlanner::MAX_BATCH ?>" value="<?= (int) $params['batch'] ?>">
        <div class="hint">Most pressing first. Up to <?= (int) AllocationPlanner::MAX_BATCH ?>.</div>
      </div>
      <div class="field">
        <label for="floor">Minimum fit</label>
        <input id="floor" type="number" name="floor" min="20" max="80" value="<?= (int) $params['floor'] ?>">
        <div class="hint">Below this, a trader is left for review, not forced into a poor pitch.</div>
      </div>
      <div class="field">
        <label for="division">Division</label>
        <?php if ($lock !== null): ?>
          <input id="division" type="text" value="<?= e($lock['name']) ?> (your division)" disabled>
        <?php else: ?>
          <select id="division" name="division">
            <option value="">All divisions</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?= e($d['code']) ?>" <?= $params['division'] === $d['code'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <div class="hint">Narrow the batch to one division.</div>
      </div>
      <div class="field">
        <label for="zone">Site</label>
        <select id="zone" name="zone">
          <option value="0">All sites</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= (int) $z['id'] ?>" <?= (int) $params['zone'] === (int) $z['id'] ? 'selected' : '' ?>><?= e($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Plan only the traders trading at one site.</div>
      </div>
    </div>

    <?php if ($lock === null): ?>
      <label class="row-tight small" style="cursor:pointer">
        <input type="checkbox" name="stay" value="1" <?= $params['stay'] ? 'checked' : '' ?>>
        Keep traders in their own division
      </label>
    <?php else: ?>
      <p class="small muted mb-0 guide-only">Your plan covers your own division only.</p>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    <div class="row">
      <button class="btn btn-primary" type="submit" name="run" value="1"><?= KCCA_icon('layers', 15) ?> Run plan</button>
      <button class="btn btn-ghost" type="submit" name="compare" value="1"
              title="Runs all four policies over the same batch. Takes a little longer.">
        <?= KCCA_icon('chart', 15) ?> Compare policies
      </button>
      <a class="btn btn-quiet" href="ai_planner.php">Reset</a>
    </div>
  </div>
</form>

<?php if ($comparison !== null): ?>
<!-- =========================== COMPARISON ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Policy comparison</h2>
      <p class="desc">
        Same batch under each policy, all measured at the standing weights.
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Policy</th><th class="right">Placed</th><th class="right">Fit</th>
            <th class="right">Distance</th><th class="right">Vulnerable placed</th>
            <th class="right">Their distance</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($comparison as $row): ?>
            <tr>
              <td class="strong"><?= e($row['label']) ?></td>
              <td class="right num"><?= num($row['placed']) ?></td>
              <td class="right num"><?= num($row['avg_neutral'], 1) ?></td>
              <td class="right num"><?= num($row['avg_distance'], 1) ?> km</td>
              <td class="right num"><?= num($row['vulnerable']) ?></td>
              <td class="right num"><?= $row['vuln_distance'] === null ? '—' : num($row['vuln_distance'], 1) . ' km' ?></td>
              <td class="right">
                <a class="btn btn-ghost btn-sm"
                   href="<?= e('ai_planner.php?' . http_build_query([
                       'mode' => $row['mode'], 'batch' => $params['batch'], 'floor' => $params['floor'],
                       'division' => $params['division'], 'zone' => $params['zone'],
                       'stay' => $params['stay'] ? 1 : 0, 'run' => 1,
                   ])) ?>">Use this</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($plan !== null): ?>
<!-- =========================== THE PLAN ========================== -->
<?php if ($S['placed'] === 0): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty">
        <div class="big"><?= KCCA_icon('alert', 30) ?></div>
        <h3>Nothing to place</h3>
        <p><?= e($S['note'] !== '' ? $S['note'] : 'No trader could be placed. Lower the minimum fit or widen the selection.') ?></p>
      </div>
    </div>
  </div>
<?php else: ?>

  <div class="grid g5 mb-2">
    <div class="stat green">
      <div class="label">Placed</div>
      <div class="value"><?= num($S['placed']) ?><span class="unit"> of <?= num($S['selected']) ?></span></div>
      <div class="meta"><?= num($S['markets_used']) ?> market<?= $S['markets_used'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="stat info">
      <div class="label">Average fit</div>
      <div class="value"><?= num($S['avg_fit'], 1) ?></div>
      <div class="meta">
        <?= $S['uplift'] >= 0 ? '<b>+' . num($S['uplift'], 1) . '</b>' : '<b>' . num($S['uplift'], 1) . '</b>' ?>
        vs one by one
      </div>
    </div>
    <div class="stat">
      <div class="label">Average distance</div>
      <div class="value"><?= num($S['avg_distance'], 1) ?><span class="unit"> km</span></div>
      <div class="meta">from current pitch</div>
    </div>
    <div class="stat gold">
      <div class="label">Clashes avoided</div>
      <div class="value"><?= num($S['collisions']) ?></div>
      <div class="meta">first choices already full</div>
    </div>
    <div class="stat <?= $S['unplaced'] > 0 ? 'warn' : 'ok' ?>">
      <div class="label">For review</div>
      <div class="value"><?= num($S['unplaced']) ?></div>
      <div class="meta"><?= $S['unplaced'] > 0 ? 'no acceptable pitch' : 'all placed' ?></div>
    </div>
  </div>

  <?php if ($S['note'] !== ''): ?>
    <div class="alert alert-info mb-2"><?= e($S['note']) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Plan <?= e($plan['reference']) ?> · <?= e(AllocationPlanner::MODES[$params['mode']]['label']) ?></h2>
        <p class="desc" style="display:block !important">
          By need · <?= num($S['ms']) ?> ms · proposal only, nothing allocated until a second officer approves
        </p>
      </div>
      <div class="tools">
        <a class="btn btn-ghost btn-sm" href="<?= e('ai_planner.php?' . http_build_query([
            'mode' => $params['mode'], 'batch' => $params['batch'], 'floor' => $params['floor'],
            'division' => $params['division'], 'zone' => $params['zone'],
            'stay' => $params['stay'] ? 1 : 0, 'run' => 1, 'format' => 'csv',
        ])) ?>"><?= KCCA_icon('download', 14) ?> Export CSV</a>
        <button class="btn btn-ghost btn-sm" type="button" data-print><?= KCCA_icon('print', 14) ?> Print</button>
        <button class="btn btn-green btn-sm" type="button" data-submit-plan
                data-plan="<?= e(json_encode([
                    'mode' => $params['mode'], 'batch' => $params['batch'], 'floor' => $params['floor'],
                    'division' => $params['division'], 'zone' => $params['zone'],
                    'stay' => $params['stay'] ? '1' : '0', 'fingerprint' => $plan['fingerprint'],
                ], JSON_UNESCAPED_UNICODE)) ?>"
                data-count="<?= (int) $S['placed'] ?>">
          <?= KCCA_icon('check', 14) ?> Send <?= num($S['placed']) ?> for approval
        </button>
      </div>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>#</th><th>Trader</th><th>Need</th><th>Placed at</th>
              <th>Fit</th><th class="right">Distance</th><th>Why</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($plan['assignments'] as $idx => $a): ?>
            <tr>
              <td><span class="rank <?= $idx < 3 ? 'top' : '' ?>"><?= $idx + 1 ?></span></td>
              <td>
                <a class="strong" href="vendors.php?id=<?= (int) $a['vendor']['id'] ?>"><?= e($a['vendor']['name']) ?></a>
                <div class="xsmall muted"><?= e($a['vendor']['code']) ?> · <?= e(humanise($a['vendor']['category'])) ?></div>
                <div class="xsmall muted"><?= e($a['vendor']['zone']) ?></div>
              </td>
              <td>
                <div class="meter"><div class="track"><div class="fill <?= $a['vendor']['priority'] >= 65 ? 'bad' : ($a['vendor']['priority'] >= 50 ? 'warn' : 'info') ?>"
                  style="width:<?= (int) min(100, $a['vendor']['priority']) ?>%"></div></div><span class="val"><?= num($a['vendor']['priority'], 0) ?></span></div>
              </td>
              <td>
                <strong><?= e($a['market']['name']) ?></strong>
                <div class="xsmall muted">
                  <?= e($a['market']['division']) ?> ·
                  <span class="badge <?= $a['market']['category'] === 'public' ? 'green' : 'gold' ?>"><?= e($a['market']['category']) ?></span>
                </div>
              </td>
              <td>
                <div class="meter"><div class="track"><div class="fill <?= $a['fit'] >= 70 ? 'ok' : ($a['fit'] >= 55 ? 'info' : 'warn') ?>"
                  style="width:<?= (int) min(100, $a['fit']) ?>%"></div></div><span class="val"><?= num($a['fit'], 1) ?></span></div>
              </td>
              <td class="right num"><?= num($a['distance_km'], 1) ?> km</td>
              <td class="plan-why">
                <?php foreach ($a['strengths'] as $line): ?><div><?= e($line) ?></div><?php endforeach; ?>
                <?php if ($a['caution']): ?><div class="watch">Watch — <?= e($a['caution']) ?></div><?php endif; ?>
                <?php if ($a['moved'] && $a['best']): ?>
                  <div class="moved">
                    First choice <?= e($a['best']['name']) ?> (<?= num($a['best']['fit'], 1) ?>) — released for the batch.
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid g-2-1">
    <div class="card">
      <div class="card-head">
        <div class="grow"><h2>Market load</h2></div>
      </div>
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data compact">
            <thead><tr><th>Market</th><th>Division</th><th class="right">Vacant</th><th>Used</th><th class="right">Left</th></tr></thead>
            <tbody>
              <?php foreach ($plan['by_market'] as $m): ?>
                <tr>
                  <td class="strong"><?= e($m['name']) ?></td>
                  <td><?= e($m['division']) ?></td>
                  <td class="right num"><?= num($m['vacant']) ?></td>
                  <td>
                    <?php $share = $m['vacant'] > 0 ? min(100, $m['assigned'] / $m['vacant'] * 100) : 0; ?>
                    <div class="meter"><div class="track"><div class="fill <?= $share >= 90 ? 'warn' : 'ok' ?>" style="width:<?= (int) $share ?>%"></div></div>
                      <span class="val"><?= num($m['assigned']) ?></span></div>
                  </td>
                  <td class="right num"><?= num($m['left']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="grow"><h2>For review</h2></div>
      </div>
      <div class="card-body">
        <?php if ($plan['unplaced'] === []): ?>
          <p class="small muted mb-0">Everyone has a pitch.</p>
        <?php else: ?>
          <div class="stack-s">
            <?php foreach ($plan['unplaced'] as $u): ?>
              <div>
                <strong><?= e($u['vendor']['name']) ?></strong>
                <span class="xsmall muted"> · <?= e(humanise($u['vendor']['category'])) ?></span>
                <div class="xsmall muted"><?= e($u['reason']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php endif; ?>
<?php endif; ?>

<div class="card guide-only">
  <div class="card-body small muted">
    <b>On what “AI” means here.</b> The planner is an optimiser, not a language model. It scores with the same six
    criteria as the Matching screen and finds the assignment with the highest total fit (the Hungarian method),
    so every placement can be traced to the numbers that produced it and defended to the trader, the Mayor and Internal
    Audit. Plans are proposals: they enter the same approval queue as every other recommendation, and the officer who
    submits a plan cannot be the one who approves it.
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.querySelector('[data-submit-plan]');
  if (!btn) { return; }
  btn.addEventListener('click', function () {
    var data = JSON.parse(btn.getAttribute('data-plan'));
    data.action = 'submit';
    var n = btn.getAttribute('data-count');
    KCCA.postAction(KCCA.api('api/ai_plan_action.php'), data, {
      button: btn, busyLabel: 'Saving plan…', reload: false,
      confirm: 'Send ' + n + ' placements for approval? Nothing is allocated yet. A different officer must approve each one.'
    }).then(function (res) {
      if (res && res.ok) {
        setTimeout(function () { location.href = 'recommendations.php?status=proposed'; }, 1300);
      }
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
