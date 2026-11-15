<?php
/**
 * Enforcement & Notices — survey, notice, ultimatum, clear-up, escalation.
 * Also carries the official public statements on trade order.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('enforce');

$f    = facts();
$zone = (int) ($_GET['zone'] ?? 0);
$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');

$where  = ['1 = 1'];
$params = [];
if ($zone > 0)   { $where[] = 'e.zone_id = :zid';   $params['zid'] = $zone; }
if ($type !== '') { $where[] = 'e.action_type = :t'; $params['t'] = $type; }
if ($status !== ''){ $where[] = 'e.status = :st';    $params['st'] = $status; }

$records = Database::all(
    "SELECT e.*, z.name AS zone_name, z.code AS zone_code, z.status AS zone_status,
            d.name AS division_name, d.code AS div_code
       FROM enforcement_actions e
       JOIN trade_zones z ON z.id = e.zone_id
       JOIN divisions d ON d.id = z.division_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY e.issued_date DESC, e.id DESC LIMIT 250",
    $params
);

/* Aggregate figures for the headline tiles. */
$agg = Database::one(
    "SELECT COUNT(*) AS actions,
            SUM(action_type = 'survey') AS surveys,
            SUM(action_type IN ('notice','ultimatum')) AS notices,
            SUM(action_type = 'eviction') AS evictions,
            SUM(status = 'in-progress') AS in_progress,
            SUM(status = 'escalated') AS escalated,
            COALESCE(SUM(vendors_affected),0) AS vendors_affected,
            COALESCE(SUM(vendors_relocated),0) AS vendors_relocated,
            COALESCE(SUM(officers_deployed),0) AS officers,
            COALESCE(SUM(verandas_cleared),0) AS verandas,
            COALESCE(SUM(materials_removed_m3),0) AS materials
       FROM enforcement_actions"
);

$selectedZone = $zone > 0
    ? Database::one('SELECT z.*, d.name AS division_name FROM trade_zones z JOIN divisions d ON d.id = z.division_id WHERE z.id = ?', [$zone])
    : null;

$zoneList = Database::all(
    'SELECT z.id, z.code, z.name, z.status, z.estimated_vendors, z.deadline_date, d.name AS division_name
       FROM trade_zones z JOIN divisions d ON d.id = z.division_id
      ORDER BY z.name'
);

/* Action mix for the chart. */
$mix = Database::all(
    'SELECT action_type, COUNT(*) AS n FROM enforcement_actions GROUP BY action_type ORDER BY n DESC'
);

$pageTitle = 'Enforcement & Notices';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Enforcement &amp; Notices
    </div>
    <div class="eyebrow">Legal Affairs · Law enforcement</div>
    <h1>Enforcement &amp; Notices</h1>
    <p>
      The enforcement chain for each affected site: survey, notice, ultimatum, clear-up, escalation. Every action is attributed.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="https://www.kcca.go.ug/news" target="_blank" rel="noopener">
      KCCA news <?= KCCA_icon('external', 13) ?>
    </a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- =========================== HEADLINE ========================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Actions recorded</div>
    <div class="value"><?= num((int) $agg['actions']) ?></div>
    <div class="meta">across <?= num(count($zoneList)) ?> affected sites</div>
  </div>
  <div class="stat bad">
    <div class="label">Vendors affected</div>
    <div class="value"><?= num((int) $agg['vendors_affected']) ?></div>
    <div class="meta">touched by an action</div>
  </div>
  <div class="stat ok">
    <div class="label">Relocated</div>
    <div class="value"><?= num((int) $agg['vendors_relocated']) ?></div>
    <div class="meta">
      <?= pct((int) $agg['vendors_affected'] > 0 ? (int) $agg['vendors_relocated'] / (int) $agg['vendors_affected'] * 100 : 0) ?> of affected
    </div>
  </div>
  <div class="stat info">
    <div class="label">Officer deployments</div>
    <div class="value"><?= num((int) $agg['officers']) ?></div>
    <div class="meta"><?= num((int) $agg['verandas']) ?> verandas cleared</div>
  </div>
  <div class="stat <?= (int) $agg['escalated'] > 0 ? 'warn' : '' ?>">
    <div class="label">Escalated</div>
    <div class="value"><?= num((int) $agg['escalated']) ?></div>
    <div class="meta"><?= num((int) $agg['in_progress']) ?> in progress</div>
  </div>
</div>

<?php if ($selectedZone):
    $days = daysUntil($selectedZone['deadline_date']);
?>
<!-- ====================== ZONE ACTION PANEL ===================== -->
<div class="card" id="zone-actions">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="enforcement.php">Enforcement</a><span class="sep">/</span><?= e($selectedZone['division_name']) ?>
      </div>
      <h2><?= e($selectedZone['name']) ?></h2>
      <p class="desc">
        <?= e($selectedZone['road_or_feature'] ?: '—') ?> ·
        <?= num((int) $selectedZone['estimated_vendors']) ?> vendors ·
        <span class="badge <?= e(statusTone($selectedZone['status'])) ?>"><?= e(humanise($selectedZone['status'])) ?></span>
      </p>
    </div>
    <div class="tools">
      <a class="btn btn-ghost btn-sm" href="congestion.php?zone=<?= (int) $selectedZone['id'] ?>">Congestion profile</a>
      <a class="btn btn-quiet btn-sm" href="enforcement.php">Close</a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($days !== null): ?>
      <div class="alert <?= $days < 0 ? 'alert-bad' : ($days <= 14 ? 'alert-warn' : 'alert-info') ?>">
        <strong>Deadline <?= e(fmtDate($selectedZone['deadline_date'])) ?></strong> —
        <?= $days < 0 ? 'passed ' . abs($days) . ' days ago' : $days . ' days remaining' ?>.
      </div>
    <?php endif; ?>

    <?php if (Auth::can('enforce')): ?>
      <div class="grid g3">
        <div class="card" style="margin:0">
          <div class="card-head"><div class="grow"><h3>Issue a notice</h3></div></div>
          <div class="card-body">
            <form class="stack-s" data-enf-form="notify" data-zone="<?= (int) $selectedZone['id'] ?>">
              <div class="field mb-0">
                <label for="notice-days">Days allowed to vacate</label>
                <input type="number" id="notice-days" name="days" value="7" min="1" max="90">
                <span class="hint">A formal notice to vacate streets, pavements and verandas.</span>
              </div>
              <button type="submit" class="btn btn-ghost btn-block">Issue notice</button>
            </form>
          </div>
        </div>

        <div class="card" style="margin:0">
          <div class="card-head"><div class="grow"><h3>Issue an ultimatum</h3></div></div>
          <div class="card-body">
            <form class="stack-s" data-enf-form="issue-ultimatum" data-zone="<?= (int) $selectedZone['id'] ?>">
              <div class="field mb-0">
                <label for="ult-days">Days allowed to vacate</label>
                <input type="number" id="ult-days" name="days" value="14" min="1" max="90">
                <span class="hint">
                  The pattern used in February 2026: a two-week ultimatum from the Minister for
                  Kampala City, after which joint enforcement proceeds.
                </span>
              </div>
              <button type="submit" class="btn btn-primary btn-block">Issue ultimatum</button>
            </form>
          </div>
        </div>

        <div class="card" style="margin:0">
          <div class="card-head"><div class="grow"><h3>Log an operation</h3></div></div>
          <div class="card-body">
            <form class="stack-s" data-enf-form="log" data-zone="<?= (int) $selectedZone['id'] ?>">
              <div class="field mb-0">
                <label for="log-type">Action type</label>
                <select id="log-type" name="action_type">
                  <?php foreach (['eviction','relocation-support','follow-up','litigation'] as $t): ?>
                    <option value="<?= e($t) ?>"><?= e(humanise($t)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row-tight">
                <div class="field mb-0 grow">
                  <label for="log-officers">Officers</label>
                  <input type="number" id="log-officers" name="officers_deployed" value="6" min="0">
                </div>
                <div class="field mb-0 grow">
                  <label for="log-relocated">Relocated</label>
                  <input type="number" id="log-relocated" name="vendors_relocated" value="0" min="0">
                </div>
              </div>
              <div class="field mb-0">
                <label for="log-officer">Officer name and number</label>
                <input type="text" id="log-officer" name="officer_name" placeholder="e.g. Officer M. Nakato (ENF-014)">
              </div>
              <div class="field mb-0">
                <label for="log-outcome">Outcome</label>
                <textarea id="log-outcome" name="outcome" rows="2" placeholder="What happened on the ground…"></textarea>
              </div>
              <button type="submit" class="btn btn-ghost btn-block">Log operation</button>
            </form>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        Your role can view enforcement records but cannot issue notices or log operations.
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- =========================== FILTERS =========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label for="zone">Affected site</label>
      <select name="zone" id="zone" data-autosubmit>
        <option value="">All sites</option>
        <?php foreach ($zoneList as $z): ?>
          <option value="<?= (int) $z['id'] ?>" <?= $zone === (int) $z['id'] ? 'selected' : '' ?>>
            <?= e($z['name']) ?> — <?= e($z['division_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="type">Action type</label>
      <select name="type" id="type" data-autosubmit>
        <option value="">All actions</option>
        <?php foreach ($mix as $m): ?>
          <option value="<?= e($m['action_type']) ?>" <?= $type === $m['action_type'] ? 'selected' : '' ?>>
            <?= e(humanise($m['action_type'])) ?> (<?= num((int) $m['n']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select name="status" id="status" data-autosubmit>
        <option value="">Any status</option>
        <?php foreach (['planned','issued','in-progress','completed','suspended','escalated'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(humanise($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <a class="btn btn-ghost" href="enforcement.php">Reset</a>
  </form>
</div>

<!-- ======================= ENFORCEMENT LOG ====================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Enforcement log</h2>
      <p class="desc"><?= num(count($records)) ?> record<?= count($records) === 1 ? '' : 's' ?> in this view, most recent first.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Reference</th><th>Site</th><th>Division</th><th>Action</th>
            <th>Issued</th><th>Deadline</th><th class="center">Officers</th>
            <th class="right">Affected</th><th class="right">Relocated</th>
            <th class="center">Status</th><th>Officer</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r):
              $pctDone = (int) $r['vendors_affected'] > 0
                  ? round((int) $r['vendors_relocated'] / (int) $r['vendors_affected'] * 100)
                  : 0;
          ?>
            <tr>
              <td class="mono xsmall"><?= e($r['reference']) ?></td>
              <td>
                <a href="enforcement.php?zone=<?= (int) $r['zone_id'] ?>"><strong><?= e($r['zone_name']) ?></strong></a>
                <div class="xsmall muted"><?= e($r['zone_code']) ?></div>
              </td>
              <td class="xsmall"><?= e($r['division_name']) ?></td>
              <td class="xsmall"><?= e(humanise($r['action_type'])) ?></td>
              <td class="xsmall"><?= e(fmtDate($r['issued_date'])) ?></td>
              <td class="xsmall"><?= e(fmtDate($r['deadline_date'])) ?></td>
              <td class="center num xsmall"><?= num((int) $r['officers_deployed']) ?></td>
              <td class="right num xsmall"><?= num((int) $r['vendors_affected']) ?></td>
              <td class="right num xsmall">
                <?= num((int) $r['vendors_relocated']) ?>
                <?php if ((int) $r['vendors_affected'] > 0): ?>
                  <div class="meter" style="min-width:70px">
                    <span class="track"><span class="fill <?= $pctDone >= 90 ? 'ok' : ($pctDone > 0 ? 'info' : '') ?>"
                          style="width:<?= (int) clamp($pctDone, 2, 100) ?>%"></span></span>
                  </div>
                <?php endif; ?>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
              <td class="xsmall">
                <?= e($r['officer_name'] ?: '—') ?>
                <?php if ($r['officer_number']): ?>
                  <div class="xsmall muted"><?= e($r['officer_number']) ?></div>
                <?php endif; ?>
              </td>
              <td class="right">
                <?php if (Auth::can('enforce') && $r['status'] === 'in-progress'): ?>
                  <button class="btn btn-ghost btn-sm"
                          data-complete-action="<?= (int) $r['id'] ?>"
                          data-zone="<?= (int) $r['zone_id'] ?>">Close</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if (!$records): ?>
    <div class="empty">
      <div class="big"><?= KCCA_icon('shield', 32) ?></div>
      <h3>No enforcement record matches this filter</h3>
      <p>Choose a different site, action type or status.</p>
      <a class="btn btn-ghost" href="enforcement.php">Reset filters</a>
    </div>
  <?php endif; ?>
</div>

<!-- =================== OFFICIAL STATEMENTS ====================== -->
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>The enforcement position in the public record</h2>
        <p class="desc">What the Authority has stated, and when.</p>
      </div>
    </div>
    <div class="card-body">
      <ul class="timeline">
        <?php
        $past = fn(string $d) => strtotime($d) < time();
        foreach ($f['enforcement_timeline'] as $t):
        ?>
          <li class="<?= $past($t['date']) ? 'past' : '' ?>">
            <span class="when"><?= e(fmtDate($t['date'])) ?></span>
            <?= e($t['event']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="card-foot">
      Compiled from published KCCA statements. Click through for the original reporting.
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Public notices</h2>
        <p class="desc">Statements published by the Authority on trade order and market space.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="https://www.kcca.go.ug/news" target="_blank" rel="noopener">
        All news <?= KCCA_icon('external', 12) ?>
      </a>
    </div>
    <div class="card-body">
      <?php foreach (Database::all('SELECT * FROM notices ORDER BY sort_order') as $n): ?>
        <div class="criterion mb-1">
          <span class="cl"><?= e($n['headline']) ?></span>
          <span class="cv"><span class="tag"><?= e(fmtDate($n['published_on'])) ?></span></span>
          <span class="cn"><?= e($n['body']) ?></span>
          <?php if ($n['source_url']): ?>
            <span class="cn">
              <a href="<?= e($n['source_url']) ?>" target="_blank" rel="noopener">Read the original statement</a>
            </span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card-foot">
      <div class="quote" style="margin:0">
        <p>“We are determined to enforce regulations against street vending.”</p>
        <footer><b>Daniel Nuwabine</b> — Spokesperson, KCCA · 17 February 2026</footer>
      </div>
    </div>
  </div>
</div>

<script>
/* Enforcement forms post through the JSON endpoint. */
document.querySelectorAll('[data-enf-form]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var data = { zone_id: form.getAttribute('data-zone') };
    new FormData(form).forEach(function (v, k) { data[k] = v; });
    KCCA.postAction('../api/enforcement_action.php', Object.assign({ action: form.getAttribute('data-enf-form') }, data), {
      button: form.querySelector('button[type=submit]'),
      busyLabel: 'Recording…',
      confirm: form.getAttribute('data-enf-form') === 'issue-ultimatum'
        ? 'Issue a formal ultimatum? This is a legal notice and is recorded against the site.'
        : null
    });
  });
});

document.querySelectorAll('[data-complete-action]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-complete-action');
    var zone = btn.getAttribute('data-zone');
    KCCA.ask(
      'Close off this operation',
      'How many of the affected vendors have been relocated to gazetted space? '
        + 'A zone is marked cleared once 90% or more have a pitch.',
      {
        button: btn, busyLabel: 'Closing…',
        numeric: true, maxLength: 20000, rows: 1,
        label: 'Vendors relocated',
        buttonLabel: 'Close the operation',
        placeholder: '0'
      }
    ).then(function (n) {
      if (n === null) return;
      KCCA.postAction('../api/enforcement_action.php', {
        action: 'complete',
        action_id: id,
        zone_id: zone,
        vendors_relocated: n,
        verandas_cleared: 0
      }, { button: btn, busyLabel: 'Closing…' });
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php';