<?php
/**
 * Allocation Register — every offer, its score, its reasoning, and its outcome.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('allocate');

$status   = (string) ($_GET['status'] ?? '');
$division = (string) ($_GET['division'] ?? '');
$pageNo   = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 40;

$where  = ['1 = 1'];
$params = [];
if ($status !== '') { $where[] = 'a.status = :st';  $params['st'] = $status; }
if ($division !== '') { $where[] = 'd.code = :div'; $params['div'] = $division; }
$whereSql = implode(' AND ', $where);

$total = (int) Database::value(
    "SELECT COUNT(*) FROM allocations a
       JOIN vendors v ON v.id = a.vendor_id
       JOIN divisions d ON d.id = v.division_id
      WHERE $whereSql",
    $params
);
$pageCount = max(1, (int) ceil($total / $perPage));
$pageNo    = min($pageNo, $pageCount);
$offset    = ($pageNo - 1) * $perPage;

$rows = Database::all(
    "SELECT a.*, m.name AS market_name, m.category AS market_category,
            v.full_name, v.vendor_code, v.business_category, v.dependants,
            d.name AS division_name, d.code AS div_code,
            z.name AS zone_name, s.space_code
       FROM allocations a
       JOIN vendors v ON v.id = a.vendor_id
       JOIN markets m ON m.id = a.market_id
       JOIN divisions d ON d.id = v.division_id
       LEFT JOIN trade_zones z ON z.id = v.current_zone_id
       LEFT JOIN market_spaces s ON s.id = a.market_space_id
      WHERE $whereSql
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

$counts = Database::all('SELECT status, COUNT(*) AS n FROM allocations GROUP BY status');
$statusCounts = [];
foreach ($counts as $c) { $statusCounts[$c['status']] = (int) $c['n']; }

/* Score distribution — does the engine produce a spread or a wall? */
$scoreBands = [
    ['label' => '90–100', 'min' => 90, 'max' => 101],
    ['label' => '80–89',  'min' => 80, 'max' => 90],
    ['label' => '70–79',  'min' => 70, 'max' => 80],
    ['label' => '60–69',  'min' => 60, 'max' => 70],
    ['label' => '40–59',  'min' => 40, 'max' => 60],
    ['label' => 'Under 40','min' => 0,  'max' => 40],
];
$dist = [];
foreach ($scoreBands as $b) {
    $dist[] = [
        'label' => $b['label'],
        'value' => (int) Database::value(
            'SELECT COUNT(*) FROM allocations WHERE total_score >= ? AND total_score < ?',
            [$b['min'], $b['max']]
        ),
    ];
}

$acceptRate = (($statusCounts['accepted'] ?? 0) + ($statusCounts['occupied'] ?? 0))
    + ($statusCounts['declined'] ?? 0) > 0
    ? round((($statusCounts['accepted'] ?? 0) + ($statusCounts['occupied'] ?? 0))
        / ((($statusCounts['accepted'] ?? 0) + ($statusCounts['occupied'] ?? 0)) + ($statusCounts['declined'] ?? 0)) * 100, 1)
    : 0.0;

$pageTitle = 'Allocation Register';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Allocation Register
    </div>
    <div class="eyebrow">Market administration · Decision record</div>
    <h1>Allocation Register</h1>
    <p>
      Every offer made, the score that justified it, and what happened next.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="relocation.php"><?= KCCA_icon('swap', 15) ?> New match</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ========================= HEADLINE =========================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Allocations issued</div>
    <div class="value"><?= num(array_sum($statusCounts)) ?></div>
    <div class="meta">across all markets</div>
  </div>
  <div class="stat info">
    <div class="label">Awaiting decision</div>
    <div class="value"><?= num(($statusCounts['offered'] ?? 0) + ($statusCounts['proposed'] ?? 0)) ?></div>
    <div class="meta">offers live</div>
  </div>
  <div class="stat ok">
    <div class="label">Accepted</div>
    <div class="value"><?= num(($statusCounts['accepted'] ?? 0) + ($statusCounts['occupied'] ?? 0)) ?></div>
    <div class="meta"><?= pct($acceptRate) ?> acceptance rate</div>
  </div>
  <div class="stat bad">
    <div class="label">Declined</div>
    <div class="value"><?= num($statusCounts['declined'] ?? 0) ?></div>
    <div class="meta">need re-matching</div>
  </div>
  <div class="stat warn">
    <div class="label">Withdrawn</div>
    <div class="value"><?= num(($statusCounts['withdrawn'] ?? 0) + ($statusCounts['expired'] ?? 0)) ?></div>
    <div class="meta">space returned to pool</div>
  </div>
</div>

<!-- =========================== FILTERS ========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label for="status">Status</label>
      <select name="status" id="status" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach (['proposed','offered','accepted','occupied','declined','expired','withdrawn'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
            <?= e(humanise($s)) ?> (<?= num($statusCounts[$s] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="division">Division</label>
      <select name="division" id="division" data-autosubmit>
        <option value="">All five</option>
        <?php foreach (Database::all('SELECT code, name FROM divisions ORDER BY name') as $d): ?>
          <option value="<?= e($d['code']) ?>" <?= $division === $d['code'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <a class="btn btn-ghost" href="allocations.php">Reset</a>
  </form>
</div>

<div class="grid g-3-2">
  <!-- =========================== TABLE =========================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Allocation records</h2>
        <p class="desc">
          <?= num($total) ?> record<?= $total === 1 ? '' : 's' ?> ·
          showing <?= num($total > 0 ? $offset + 1 : 0) ?>–<?= num(min($offset + $perPage, $total)) ?>
        </p>
      </div>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Reference</th><th>Vendor</th><th>Market</th><th>Pitch</th>
              <th class="right">Score</th><th class="right">Distance</th><th class="center">Rank</th>
              <th class="center">Status</th><th>Issued</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $a): ?>
              <tr>
                <td class="mono xsmall"><?= e($a['reference']) ?></td>
                <td>
                  <a href="vendors.php?id=<?= (int) $a['vendor_id'] ?>"><strong><?= e($a['full_name']) ?></strong></a>
                  <div class="xsmall muted">
                    <?= e($a['vendor_code']) ?> · <?= e($a['division_name']) ?>
                  </div>
                </td>
                <td class="xsmall">
                  <?= e($a['market_name']) ?>
                  <div class="xsmall muted">
                    <span class="badge <?= $a['market_category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                      <?= e($a['market_category']) ?>
                    </span>
                  </div>
                </td>
                <td class="xsmall mono"><?= e($a['space_code'] ?: '—') ?></td>
                <td class="right num strong"><?= num((float) $a['total_score'], 1) ?></td>
                <td class="right num xsmall"><?= num((float) $a['distance_km'], 1) ?> km</td>
                <td class="center"><span class="rank <?= (int) $a['rank'] === 1 ? 'top' : '' ?>"><?= (int) $a['rank'] ?></span></td>
                <td class="center"><span class="badge <?= e(statusTone($a['status'])) ?>"><?= e(humanise($a['status'])) ?></span></td>
                <td class="xsmall"><?= e(fmtDate($a['created_at'])) ?></td>
                <td class="right">
                  <?php if ($a['reasoning']): ?>
                    <button class="btn btn-ghost btn-sm" data-reasoning="<?= e($a['reasoning']) ?>"
                            data-reference="<?= e($a['reference']) ?>">Why</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($total === 0): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('list', 32) ?></div>
        <h3>No allocation matches this filter</h3>
        <p>Offers appear here as soon as an officer issues one from the
           <a href="relocation.php">relocation engine</a>.</p>
        <a class="btn btn-ghost" href="allocations.php">Reset filters</a>
      </div>
    <?php endif; ?>

    <?php if ($pageCount > 1): ?>
      <div class="card-foot">
        <div class="row">
          <span class="muted small">Page <?= num($pageNo) ?> of <?= num($pageCount) ?></span>
          <span class="right"></span>
          <?php if ($pageNo > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(urlWith(['page' => $pageNo - 1])) ?>">Previous</a>
          <?php endif; ?>
          <?php if ($pageNo < $pageCount): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(urlWith(['page' => $pageNo + 1])) ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ========================= SIDE COLUMN ======================= -->
  <div>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Score distribution</h2><p class="desc">How the engine's recommendations fall.</p></div></div>
      <div class="card-body">
        <?php
        $distTotal = array_sum(array_column($dist, 'value'));
        if ($distTotal === 0): ?>
          <p class="muted small mb-0">
            No allocations have been scored yet. The distribution appears once the first offer is
            issued from the <a href="relocation.php">relocation engine</a>.
          </p>
        <?php else: ?>
          <svg class="chart" data-chart='<?= ejs([
            'type' => 'bar',
            'opts' => [
              'height' => 200,
              'labels' => array_column($dist, 'label'),
              'series' => [['name' => 'Allocations', 'color' => '#0A6339',
                            'values' => array_column($dist, 'value')]],
              'showValues' => true,
              'yFormat' => 'num',
            ],
          ]) ?>' aria-label="Allocation score distribution"></svg>
        <?php endif; ?>
      </div>
      <div class="card-foot">
        A healthy distribution is not all at the top: it means the engine is genuinely
        discriminating between options rather than rating everything highly.
      </div>
    </div>

    <?php
$topDest = Database::all(
    "SELECT m.name, COUNT(a.id) AS n FROM allocations a
       JOIN markets m ON m.id = a.market_id
      WHERE a.status IN ('accepted','occupied') GROUP BY m.id ORDER BY n DESC LIMIT 8"
);
?>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Top destinations</h2><p class="desc">Markets absorbing the most vendors.</p></div></div>
      <div class="card-body">
        <?php if (!$topDest): ?>
          <p class="muted small mb-0">
            No vendor has accepted an offer yet, so no market has absorbed anyone. This chart fills
            as offers are accepted.
          </p>
        <?php else: ?>
          <svg class="chart" data-chart='<?= ejs([
            'type' => 'hbar',
            'opts' => [
              'labelW' => 168, 'rowH' => 26,
              'items' => array_map(static fn(array $r): array => [
                'label' => $r['name'],
                'value' => (int) $r['n'],
                'color' => '#0A6339',
              ], $topDest),
            ],
          ]) ?>' aria-label="Top destination markets"></svg>
        <?php endif; ?>
      </div>
    </div>

    <?php if (Auth::can('manage')): ?>
      <div class="card">
        <div class="card-head"><div class="grow"><h2>Manage live offers</h2></div></div>
        <div class="card-body">
          <?php
          $live = Database::all(
            "SELECT a.*, v.full_name, m.name AS market_name
               FROM allocations a
               JOIN vendors v ON v.id = a.vendor_id
               JOIN markets m ON m.id = a.market_id
              WHERE a.status IN ('offered','proposed') ORDER BY a.offer_expires LIMIT 8"
          );
          ?>
          <?php if (!$live): ?>
            <p class="muted small mb-0">No offers are currently awaiting a decision.</p>
          <?php else: ?>
            <div class="stack-s">
              <?php foreach ($live as $a): ?>
                <div class="offer">
                  <div class="grow">
                    <h4><?= e($a['full_name']) ?></h4>
                    <span class="m">
                      <?= e($a['market_name']) ?> · <?= e($a['reference']) ?>
                      <?php if ($a['offer_expires']): ?> · expires <?= e(fmtDate($a['offer_expires'])) ?><?php endif; ?>
                    </span>
                  </div>
                  <div class="row-tight">
                    <button class="btn btn-green btn-sm"
                            data-alloc-action="accept" data-id="<?= (int) $a['id'] ?>">Accept</button>
                    <button class="btn btn-ghost btn-sm"
                            data-alloc-action="decline" data-id="<?= (int) $a['id'] ?>">Decline</button>
                    <button class="btn btn-quiet btn-sm"
                            data-alloc-action="withdraw" data-id="<?= (int) $a['id'] ?>">Withdraw</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reasoning modal -->
<div class="modal" id="reasoning-modal" style="display:none">
  <div class="modal-head">
    <h3>Reasoning for <span data-modal-ref></span></h3>
    <button class="close" data-modal-close aria-label="Close">&times;</button>
  </div>
  <div class="modal-body">
    <p class="small muted">Recorded when the offer was issued. This is the explanation the officer saw.</p>
    <div class="source" style="white-space:pre-line"><span data-modal-body></span></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" data-modal-close>Close</button>
  </div>
</div>

<script>
/* Reasoning popup */
document.querySelectorAll('[data-reasoning]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var m = document.getElementById('reasoning-modal');
    m.querySelector('[data-modal-ref]').textContent = btn.getAttribute('data-reference');
    m.querySelector('[data-modal-body]').textContent = btn.getAttribute('data-reasoning');
    document.body.appendChild(m);
    m.style.display = '';
    document.body.style.overflow = 'hidden';
  });
});

/* Accept / decline / withdraw */
document.querySelectorAll('[data-alloc-action]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var act = btn.getAttribute('data-alloc-action');
    var id = btn.getAttribute('data-id');
    var payload = { action: act, allocation_id: id };
    var confirmMsg = null;

    if (act === 'decline') {
      /* A reason is recorded against the allocation and shown to the
         trader, so ask for it properly rather than with prompt() — which
         a browser that suppresses dialogs turns into a dead button. */
      KCCA.ask(
        'Decline this offer',
        'Why is the offer being declined? The reason is recorded against the allocation.',
        { button: btn, busyLabel: 'Recording…', label: 'Reason', maxLength: 500 }
      ).then(function (why) {
        if (why === null) return;
        payload.reason = why;
        KCCA.postAction('../api/allocation_action.php', payload, {
          button: btn,
          busyLabel: 'Recording…'
        });
      });
      return;
    } else if (act === 'withdraw') {
      confirmMsg = 'Withdraw this offer? The space returns to the vacancy pool.';
    } else {
      confirmMsg = 'Confirm the trader has accepted this pitch?';
    }

    KCCA.postAction('../api/allocation_action.php', payload, {
      button: btn,
      busyLabel: 'Working…',
      confirm: confirmMsg
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php';