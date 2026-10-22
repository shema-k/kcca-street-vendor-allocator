<?php
/**
 * Cluster & Wave Planner.
 *
 * Moves whole trading communities rather than individual traders, in dated
 * waves, and shows how much congestion each wave lifts. Read-only: it is a
 * schedule to argue over. Pitches are still assigned through the AI Planner
 * and approved through the normal workflow.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('allocate');
Auth::requireScreen('waves');

@set_time_limit(180);

$params = WavePlanner::params($_GET);
$lock   = divisionLock();
$run    = isset($_GET['run']);
$plan   = $run ? WavePlanner::plan($params) : null;

/* ---------------- CSV ---------------- */
if ($plan !== null && (string) ($_GET['format'] ?? '') === 'csv') {
    Auth::log('export', 'wave_plan', null, 'Downloaded wave plan (' . count($plan['waves']) . ' waves)');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wave-plan-' . date('Ymd') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Cluster and wave plan', 'Proposal only. Nothing is allocated.']);
    fputcsv($out, ['Prepared by', (string) (Auth::user()['full_name'] ?? '')]);
    fputcsv($out, []);
    fputcsv($out, ['Wave', 'Starts', 'Cluster', 'Site', 'Moves to', 'Traders', 'Fit']);
    foreach ($plan['waves'] as $w) {
        foreach ($w['entries'] as $e) {
            fputcsv($out, [$w['n'], $w['start'], $e['cluster'], $e['zone'], $e['market'], $e['count'], $e['fit']]);
        }
    }
    fclose($out);
    exit;
}

$divisions = Database::all('SELECT code, name FROM divisions ORDER BY name');
$S = $plan['summary'] ?? null;

$pageTitle = 'Waves';
require __DIR__ . '/../layouts/header.php';

/* Chart geometry, computed once. */
$chart = null;
if ($plan !== null && $plan['waves'] !== []) {
    $W = 640; $H = 190; $x0 = 34; $x1 = 624; $y0 = 160; $y1 = 18;
    $n = count($plan['waves']);
    $pts = [[$x0, $y0]];
    foreach ($plan['waves'] as $i => $w) {
        $x = $n === 1 ? ($x0 + $x1) / 2 : $x0 + ($x1 - $x0) * (($i + 1) / $n);
        $y = $y0 - ($y0 - $y1) * min(100, $w['relief']) / 100;
        $pts[] = [round($x, 1), round($y, 1)];
    }
    $line = 'M' . implode(' L', array_map(static fn($p) => $p[0] . ' ' . $p[1], $pts));
    $area = $line . ' L' . $pts[count($pts) - 1][0] . ' ' . $y0 . ' L' . $x0 . ' ' . $y0 . ' Z';
    $chart = compact('W', 'H', 'x0', 'x1', 'y0', 'y1', 'pts', 'line', 'area', 'n');
}
?>
<style>
  @property --p { syntax: '<number>'; inherits: false; initial-value: 0; }
  .wv-form { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:14px; align-items:end; }
  .wv-form .field { margin:0; }
  .wv-range { width:100%; accent-color:var(--accent-deep); }
  .wv-chart svg { width:100%; height:auto; display:block; }
  .wv-chart .grid { stroke:var(--line); stroke-width:1; stroke-dasharray:3 5; }
  .wv-chart .axis { fill:var(--ink-3); font-size:10px; }
  .wv-chart .area { fill:url(#wvfill); opacity:0; animation:wvfade 1s .5s forwards; }
  .wv-chart .stroke { fill:none; stroke:var(--accent-deep); stroke-width:3.5; stroke-linecap:round; stroke-linejoin:round;
    stroke-dasharray:1; stroke-dashoffset:1; animation:wvdraw 1.6s .2s ease-out forwards; }
  .wv-chart .dot { fill:var(--surface); stroke:var(--accent-deep); stroke-width:2.5; opacity:0; animation:wvfade .4s forwards; }
  @keyframes wvdraw { to { stroke-dashoffset:0; } }
  @keyframes wvfade { to { opacity:1; } }
  .wv-timeline { position:relative; display:grid; gap:14px; padding-left:34px; }
  .wv-timeline::before { content:""; position:absolute; left:11px; top:8px; bottom:8px; width:3px; border-radius:3px;
    background:linear-gradient(180deg,#F7C401,#FFE066,#3FA776,#0A6339); opacity:.7; }
  .wave { position:relative; }
  .wave::before { content:attr(data-n); position:absolute; left:-34px; top:14px; width:25px; height:25px; border-radius:50%;
    display:grid; place-items:center; font-size:11px; font-weight:800; color:var(--accent-ink); background:var(--accent);
    box-shadow:0 0 0 4px var(--glass-strong), 0 4px 10px rgba(var(--accent-rgb),.55); }
  .wave-head { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .wave-head .when { font-weight:800; color:var(--ink); font-size:15px; }
  .wave-head .moved { color:var(--ink-3); font-size:12.5px; }
  .ring { --p:0; width:54px; height:54px; border-radius:50%; margin-left:auto; position:relative; flex:none;
    background:conic-gradient(var(--accent-deep) calc(var(--p) * 1%), var(--surface-3) 0); animation:ringin 1.2s .3s ease-out backwards; }
  .ring::after { content:""; position:absolute; inset:6px; border-radius:50%; background:var(--glass-strong); }
  .ring b { position:absolute; inset:0; display:grid; place-items:center; z-index:1; font-size:11.5px; color:var(--ink); }
  @keyframes ringin { from { --p:0; } }
  .wv-entries { display:grid; gap:6px; margin-top:10px; }
  .wv-entry { display:grid; grid-template-columns:minmax(0,1.3fr) auto minmax(0,1.2fr) auto; gap:10px; align-items:center;
    padding:8px 12px; border-radius:12px; background:color-mix(in srgb,var(--surface) 55%,transparent); border:1px solid var(--glass-edge);
    font-size:12.5px; }
  .wv-entry .arrow { color:var(--accent-deep); font-weight:800; }
  .wv-entry .n { font-weight:800; color:var(--ink); font-variant-numeric:tabular-nums; }
  .wv-cleared { margin-top:10px; }
  @media (max-width:1100px){ .wv-form{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:640px){ .wv-entry{ grid-template-columns:minmax(0,1fr); } .wv-entry .arrow{ display:none; } }
</style>

<section class="page-head">
  <div class="grow">
    <div class="eyebrow">Relocation</div>
    <h1>Cluster &amp; Wave Planner</h1>
    <p>Moves whole trading communities, not scattered individuals, so customers follow their sellers. Communities are queued by
       urgency and packed into dated waves no larger than the Authority can handle, with the congestion relief shown after each.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="ai_planner.php"><?= KCCA_icon('layers', 14) ?> AI Planner</a>
  </div>
</section>

<div class="flow" aria-label="How the schedule is made"><span>Cluster</span><i>→</i><span>Destination</span><i>→</i><span>Waves</span><i>→</i><span>Relief</span></div>

<form class="card" method="get" action="waves.php">
  <div class="card-head"><div class="grow"><h2>Plan the move</h2></div></div>
  <div class="card-body">
    <div class="wv-form">
      <div class="field">
        <label for="capacity">Traders per wave</label>
        <input id="capacity" type="number" name="capacity" min="20" max="600" value="<?= (int) $params['capacity'] ?>">
        <div class="hint">What enforcement and the markets can absorb at once.</div>
      </div>
      <div class="field">
        <label for="cadence">Days between waves</label>
        <input id="cadence" type="number" name="cadence" min="3" max="21" value="<?= (int) $params['cadence'] ?>">
      </div>
      <div class="field">
        <label for="start">First wave</label>
        <input id="start" type="date" name="start" min="<?= e(date('Y-m-d')) ?>" value="<?= e($params['start']) ?>">
      </div>
      <div class="field">
        <label for="together">Keep together: <b data-range-out="together"><?= (int) $params['together'] ?></b>%</label>
        <input class="wv-range" id="together" type="range" name="together" min="0" max="100" step="5" value="<?= (int) $params['together'] ?>">
        <div class="hint">Higher keeps a community in one market, even at some cost in fit.</div>
      </div>
      <div class="field">
        <label for="floor">Minimum fit</label>
        <input id="floor" type="number" name="floor" min="20" max="80" value="<?= (int) $params['floor'] ?>">
      </div>
      <div class="field">
        <label for="division">Division</label>
        <?php if ($lock !== null): ?>
          <input id="division" type="text" value="<?= e($lock['name']) ?>" disabled>
        <?php else: ?>
          <select id="division" name="division">
            <option value="">All divisions</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?= e($d['code']) ?>" <?= $params['division'] === $d['code'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-foot">
    <div class="row">
      <button class="btn btn-primary" type="submit" name="run" value="1"><?= KCCA_icon('clock', 15) ?> Build the schedule</button>
      <a class="btn btn-quiet" href="waves.php">Reset</a>
    </div>
  </div>
</form>

<?php if ($plan !== null): ?>
  <?php if ($S['clusters'] === 0): ?>
    <div class="card"><div class="card-body"><div class="empty">
      <div class="big"><?= KCCA_icon('alert', 30) ?></div><h3>Nothing to schedule</h3>
      <p>No trader is waiting to be placed in this selection.</p>
    </div></div></div>
  <?php else: ?>

  <div class="grid g5 mb-2">
    <div class="stat green"><div class="label">Waves</div><div class="value"><?= num($S['waves']) ?></div>
      <div class="meta"><?= $S['finish'] ? 'last starts ' . e(fmtDate($S['finish'])) : '—' ?></div></div>
    <div class="stat"><div class="label">Communities</div><div class="value"><?= num($S['clusters']) ?></div>
      <div class="meta"><?= num($S['traders']) ?> traders</div></div>
    <div class="stat gold"><div class="label">Kept together</div><div class="value"><?= num($S['together'], 0) ?><span class="unit">%</span></div>
      <div class="meta">with their whole community</div></div>
    <div class="stat info"><div class="label">Relief at the end</div><div class="value"><?= num($S['relief_final'], 0) ?><span class="unit">%</span></div>
      <div class="meta">of site congestion lifted</div></div>
    <div class="stat <?= $S['deferred'] > 0 ? 'warn' : 'ok' ?>"><div class="label">Deferred</div><div class="value"><?= num($S['deferred']) ?></div>
      <div class="meta"><?= $S['deferred'] > 0 ? 'no room or no suitable market' : 'everyone scheduled' ?></div></div>
  </div>

  <?php if ($chart !== null): ?>
  <div class="card wv-chart">
    <div class="card-head">
      <div class="grow"><h2>Congestion relief by wave</h2></div>
      <div class="tools">
        <a class="btn btn-ghost btn-sm" href="<?= e('waves.php?' . http_build_query($params + ['run' => 1, 'format' => 'csv'])) ?>"><?= KCCA_icon('download', 14) ?> Export CSV</a>
        <button class="btn btn-ghost btn-sm" type="button" data-print><?= KCCA_icon('print', 14) ?> Print</button>
      </div>
    </div>
    <div class="card-body">
      <svg viewBox="0 0 <?= $chart['W'] ?> <?= $chart['H'] ?>" role="img" aria-label="Cumulative congestion relief after each wave">
        <defs><linearGradient id="wvfill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#F7C401" stop-opacity=".45"/><stop offset="1" stop-color="#FFE066" stop-opacity="0"/></linearGradient></defs>
        <?php foreach ([0, 25, 50, 75, 100] as $g): $gy = $chart['y0'] - ($chart['y0'] - $chart['y1']) * $g / 100; ?>
          <line class="grid" x1="<?= $chart['x0'] ?>" x2="<?= $chart['x1'] ?>" y1="<?= $gy ?>" y2="<?= $gy ?>"/>
          <text class="axis" x="4" y="<?= $gy + 3 ?>"><?= $g ?>%</text>
        <?php endforeach; ?>
        <path class="area" d="<?= e($chart['area']) ?>"/>
        <path class="stroke" pathLength="1" d="<?= e($chart['line']) ?>"/>
        <?php foreach ($chart['pts'] as $i => $pt): if ($i === 0) { continue; } ?>
          <circle class="dot" style="animation-delay:<?= number_format(0.4 + $i * 0.12, 2, '.', '') ?>s" cx="<?= $pt[0] ?>" cy="<?= $pt[1] ?>" r="4.5"/>
          <?php if ($chart['n'] <= 14 || $i % 2 === 0): ?>
            <text class="axis" x="<?= $pt[0] - 6 ?>" y="<?= $chart['y0'] + 16 ?>">W<?= $i ?></text>
          <?php endif; ?>
        <?php endforeach; ?>
      </svg>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><div class="grow"><h2>The schedule</h2></div></div>
    <div class="card-body">
      <div class="wv-timeline">
        <?php foreach ($plan['waves'] as $w): ?>
          <div class="wave" data-n="<?= (int) $w['n'] ?>" data-reveal>
            <div class="wave-head">
              <div>
                <div class="when"><?= e(fmtDate($w['start'], 'D d M Y')) ?></div>
                <div class="moved"><?= num($w['moved']) ?> traders · <?= count($w['entries']) ?> move<?= count($w['entries']) === 1 ? '' : 's' ?></div>
              </div>
              <div class="ring" style="--p:<?= (float) min(100, $w['relief']) ?>"><b><?= num($w['relief'], 0) ?>%</b></div>
            </div>
            <div class="wv-entries">
              <?php foreach ($w['entries'] as $e): ?>
                <div class="wv-entry">
                  <span><strong><?= e($e['cluster']) ?></strong></span>
                  <span class="arrow">→</span>
                  <span><?= e($e['market']) ?> <span class="muted xsmall">· fit <?= num($e['fit'], 0) ?></span></span>
                  <span class="n"><?= num($e['count']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($w['cleared']): ?>
              <div class="wv-cleared"><?php foreach ($w['cleared'] as $name): ?>
                <span class="badge green">Cleared · <?= e($name) ?></span>
              <?php endforeach; ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if ($plan['deferred']): ?>
  <div class="card">
    <div class="card-head"><div class="grow"><h2>Deferred</h2></div></div>
    <div class="card-body tight"><div class="table-wrap"><table class="data compact">
      <thead><tr><th>Community</th><th class="right">Traders</th><th>Why</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($plan['deferred'] as $d): ?>
          <tr>
            <td class="strong"><?= e($d['cluster']) ?></td>
            <td class="right num"><?= num($d['count']) ?></td>
            <td class="muted"><?= e($d['reason']) ?></td>
            <td class="right"><a class="btn btn-ghost btn-sm" href="ai_planner.php?zone=<?= (int) $d['zone_id'] ?>&amp;run=1">Plan this site</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var r = document.getElementById('together');
  var out = document.querySelector('[data-range-out="together"]');
  if (r && out) { r.addEventListener('input', function () { out.textContent = r.value; }); }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
