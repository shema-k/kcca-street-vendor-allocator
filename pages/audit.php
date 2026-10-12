<?php
/**
 * Audit Trail — every state-changing action, who took it and when.
 *
 * Officers holding the audit grant see the whole trail. Any other reader
 * is pinned to their own name, which is what makes the "My audit trail"
 * link in the account menu safe to offer everyone.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

/* Officers holding the audit grant see the whole trail. Everyone else — the
   account menu offers any signed-in reader a link to their own actions — is
   pinned to their own name. Without that pinning the route could be used to
   read another officer's record simply by editing the query string. */
$fullAccess = Auth::can('audit');
$meName     = (string) (Auth::user()['full_name'] ?? '');
$action = (string) ($_GET['action'] ?? '');
$actor  = (string) ($_GET['actor'] ?? '');
$since  = (string) ($_GET['since'] ?? '');

/* Applied after the query string is read: without that order a reader
   without the grant could widen their own view by editing the URL. */
if (!$fullAccess) {
    $actor = $meName;
}

$where  = ['1 = 1'];
$params = [];
if ($action !== '') { $where[] = 'a.action = :act'; $params['act'] = $action; }
if ($actor !== '')  { $where[] = 'a.actor = :who';  $params['who'] = $actor; }
if ($since !== '')  { $where[] = 'a.created_at >= :from'; $params['from'] = $since . ' 00:00:00'; }

$events = Database::all(
    'SELECT a.*, u.role FROM audit_log a
       LEFT JOIN users u ON u.id = a.user_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY a.created_at DESC, a.id DESC LIMIT 400',
    $params
);

/* The action filter is built from the reader's own scope rather than the
   whole log. Handing a self-viewer an empty dropdown made the filter look
   broken, and an empty dropdown is the one thing a reader cannot tell from a
   page that failed to load. */
$byAction = $fullAccess
    ? Database::all('SELECT action, COUNT(*) AS n FROM audit_log GROUP BY action ORDER BY n DESC')
    : Database::all(
        'SELECT action, COUNT(*) AS n FROM audit_log WHERE actor = :me GROUP BY action ORDER BY n DESC',
        ['me' => $meName]
      );
$actors   = $fullAccess ? Database::column('SELECT DISTINCT actor FROM audit_log ORDER BY actor') : [];

/* The headline figures obey the same scope as the log below them, so a
   reader who cannot see other actors is not told how many events exist.
   The two forms differ because the second query already has a WHERE. */
$scopeWhere = $fullAccess ? '' : ' WHERE actor = :me';
$scopeAnd   = $fullAccess ? '' : ' AND actor = :me';
$scopePar   = $fullAccess ? [] : ['me' => $meName];

$total  = (int) Database::value('SELECT COUNT(*) FROM audit_log' . $scopeWhere, $scopePar);
$today  = (int) Database::value(
    'SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()' . $scopeAnd,
    $scopePar
);
$logins   = $fullAccess ? (int) Database::value("SELECT COUNT(*) FROM audit_log WHERE action IN ('login','login-failed') AND DATE(created_at) = CURDATE()") : 0;
$failures = $fullAccess ? (int) Database::value("SELECT COUNT(*) FROM audit_log WHERE action = 'login-failed'") : 0;

page_assets('charts');
$pageTitle = 'Audit Trail';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span><?= $fullAccess ? 'Audit Trail' : 'My Audit Trail' ?>
    </div>
    <div class="eyebrow"><?= $fullAccess ? 'Internal Audit · Accountability' : 'Accountability' ?></div>
    <h1><?= $fullAccess ? 'Audit Trail' : 'My Audit Trail' ?></h1>
    <p>
      <?php if ($fullAccess): ?>
        Every sign-in, failed attempt, offer issued, space accepted or withdrawn, notice served and
        register exported. Written before the action completes, so the record survives even a
        client that disconnects part-way through.
      <?php else: ?>
        The actions this account has taken, newest first. Written before the action completes,
        so the record survives even a client that disconnects part-way through.
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<div class="grid g4 mb-2">
  <div class="stat">
    <div class="label"><?= $fullAccess ? 'Total events' : 'My events' ?></div>
    <div class="value"><?= num($total) ?></div>
    <div class="meta"><?= $fullAccess ? 'recorded since inception' : 'recorded by this account' ?></div>
  </div>
  <div class="stat info">
    <div class="label">Events today</div>
    <div class="value"><?= num($today) ?></div>
    <div class="meta"><?= $fullAccess ? num($logins) . ' sign-in attempts' : 'by this account' ?></div>
  </div>
  <?php if ($fullAccess): ?>
    <div class="stat <?= $failures > 0 ? 'warn' : 'ok' ?>">
      <div class="label">Failed sign-ins</div>
      <div class="value"><?= num($failures) ?></div>
      <div class="meta">worth reviewing</div>
    </div>
    <div class="stat gold">
      <div class="label">Distinct actors</div>
      <div class="value"><?= num(count($actors)) ?></div>
      <div class="meta">named individuals</div>
    </div>
  <?php else: ?>
    <div class="stat ok">
      <div class="label">Failed sign-ins</div>
      <div class="value">0</div>
      <div class="meta">none against this account</div>
    </div>
    <div class="stat gold">
      <div class="label">Signed in as</div>
      <div class="value xsmall" style="font-size:15px"><?= e($meName) ?></div>
      <div class="meta"><?= e(humanise((string) Auth::role())) ?></div>
    </div>
  <?php endif; ?>
</div>

<!-- =========================== FILTERS ========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field">
      <label for="action">Action</label>
      <select name="action" id="action" data-autosubmit>
        <option value="">All actions</option>
        <?php foreach ($byAction as $a): ?>
          <option value="<?= e($a['action']) ?>" <?= $action === $a['action'] ? 'selected' : '' ?>>
            <?= e(humanise($a['action'])) ?> (<?= num((int) $a['n']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field grow">
      <label for="actor">Actor</label>
      <?php if ($fullAccess): ?>
        <select name="actor" id="actor" data-autosubmit>
          <option value="">Anyone</option>
          <?php foreach ($actors as $a): ?>
            <option value="<?= e($a) ?>" <?= $actor === $a ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="text" id="actor" value="<?= e($meName) ?>" readonly>
      <?php endif; ?>
    </div>
    <div class="field">
      <label for="since">Since</label>
      <input type="date" id="since" name="since" value="<?= e($since) ?>" data-autosubmit>
    </div>
    <a class="btn btn-ghost" href="audit.php">Reset</a>
  </form>
</div>

<div class="grid g-3-2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Event log</h2>
        <p class="desc"><?= num(count($events)) ?> event<?= count($events) === 1 ? '' : 's' ?> shown, most recent first.</p>
      </div>
    </div>
    <div class="card-body tight">
      <div class="table-wrap" style="max-height:620px;overflow-y:auto">
        <table class="data compact">
          <thead>
            <tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr>
          </thead>
          <tbody>
            <?php foreach ($events as $ev):
                $tone = match ($ev['action']) {
                    'login-failed'        => 'bad',
                    'export'             => 'warn',
                    'withdraw-allocation','escalate-enforcement', 'decline-allocation' => 'warn',
                    'login', 'logout'    => 'info',
                    default              => 'muted',
                };
            ?>
              <tr>
                <td class="xsmall nowrap"><?= e(fmtDate($ev['created_at'], 'd M H:i')) ?></td>
                <td>
                  <span class="xsmall"><?= e($ev['actor']) ?></span>
                  <?php if ($ev['role']): ?>
                    <div class="xsmall muted"><?= e(humanise($ev['role'])) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge <?= $tone ?>"><?= e(humanise($ev['action'])) ?></span></td>
                <td class="xsmall">
                  <?= $ev['entity'] ? e($ev['entity']) : '—' ?>
                  <?php if ($ev['entity_id']): ?>#<?= (int) $ev['entity_id'] ?><?php endif; ?>
                </td>
                <td class="xsmall muted" style="max-width:340px"><?= e(truncateText($ev['detail'], 120)) ?></td>
                <td class="xsmall mono muted"><?= e($ev['ip_address']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if (!$events): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('log', 32) ?></div>
        <h3>No event matches this filter</h3>
        <p>Widen the date range or clear the action filter.</p>
        <a class="btn btn-ghost" href="audit.php">Reset filters</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($fullAccess): ?>
  <div>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Activity by action</h2></div></div>
      <div class="card-body">
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'hbar',
          'opts' => [
            'labelW' => 152, 'rowH' => 26,
            'items' => array_map(static fn($a): array => [
              'label' => humanise($a['action']),
              'value' => (int) $a['n'],
              'color' => $a['action'] === 'login-failed' ? '#C0261A'
                        : ($a['action'] === 'export' ? '#EAA515' : '#0A6339'),
            ], $byAction),
          ],
        ]) ?>' aria-label="Audit events by action"></svg>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>What is recorded</h2></div></div>
      <div class="card-body">
        <div class="stack-s">
          <?php
          $recorded = [
              ['login', 'ok',                'Every successful sign-in, with the time and the address it came from.'],
              ['login-failed', 'bad',        'Failed attempts, with the username tried. Worth reviewing for credential testing.'],
              ['offer-space', 'info',        'Space offered to a named trader, with the market and the score that justified it.'],
              ['accept-allocation', 'ok',    'The trader accepted. Records which vendor, which market, which pitch.'],
              ['decline-allocation', 'warn', 'The trader declined, with the reason they gave.'],
              ['withdraw-allocation', 'warn','The Authority withdrew an offer, with the reason.'],
              ['issue-notice', 'info',       'A notice to vacate served on a site, with the deadline set.'],
              ['issue-ultimatum', 'bad',     'A formal ultimatum, with who authorised it.'],
              ['escalate-enforcement', 'bad', 'A case escalated; the site moves to litigation hold.'],
              ['export', 'warn',            'Any register downloaded, with the rows and the requesting officer.'],
          ];
          foreach ($recorded as [$a, $tone, $desc]): ?>
            <div class="criterion">
              <span class="cl"><?= e(humanise($a)) ?></span>
              <span class="cv"><span class="badge <?= $tone ?>">recorded</span></span>
              <span class="cn"><?= e($desc) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-foot">
        A failed audit write never blocks the action it describes.
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Role permissions</h2></div></div>
      <div class="card-body">
        <div class="table-wrap">
          <table class="data compact">
            <thead><tr><th>Role</th><th class="center">View</th><th class="center">Allocate</th><th class="center">Enforce</th><th class="center">Manage</th><th class="center">Audit</th></tr></thead>
            <tbody>
              <?php
              /* Drawn from Auth itself, so the table can never disagree with
                 the permissions actually being enforced. */
              $perm = [];
              foreach (Auth::roles() as $roleName) {
                  $perm[$roleName] = Auth::permissionsFor($roleName);
              }
              foreach ($perm as $role => $granted):
                  $has = static fn(string $p) => in_array('*', $granted, true) || in_array($p, $granted, true);
              ?>
                <tr>
                  <td class="xsmall"><?= e(humanise($role)) ?></td>
                  <?php foreach (['view','allocate','enforce','manage','audit'] as $p): ?>
                    <td class="center">
                      <?= $has($p)
                          ? '<span class="badge ok">&#10003;</span>'
                          : '<span class="badge muted">&#8211;</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php';