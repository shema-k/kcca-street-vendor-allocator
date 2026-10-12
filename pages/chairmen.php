<?php
/**
 * Chairmen — who administers each market.
 *
 * One chairman per market, recorded against the market. A vendor placed
 * into a market takes on that market's chairman, and the chairman then
 * follows the vendor through the register, the allocation record and every
 * report — which is why this screen is the one place the assignment is
 * made, rather than a field on the vendor that would have to be kept in
 * step by hand.
 *
 * Officers with 'manage' maintain the register. A chairman sees the same
 * screen narrowed to their own market, and may correct their own contact
 * details and nothing else.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$mine       = chairmanLock();
$isChairman = $mine !== null;

/* Three readers, each with a different reach: a chairman sees their own
   market; a mayor sees the markets of their own division; the administrator
   and the Executive Director see all of them. A mayor is admitted by role
   rather than by 'manage' because they hold no such grant, and the division
   they are confined to is what limits them. */
$mayorLock = $isChairman ? null : divisionLock();
if (!$isChairman && $mayorLock === null) {
    Auth::requirePermission('manage');
}

$division = (string) ($_GET['division'] ?? '');
if ($mayorLock !== null && $division !== $mayorLock['code']) {
    $division = $mayorLock['code'];
}
$q = (string) ($_GET['search'] ?? '');

$divisions = $mayorLock !== null
    ? Database::column('SELECT code FROM divisions WHERE id = ? ORDER BY name', [$mayorLock['id']])
    : Database::column('SELECT code FROM divisions ORDER BY name');

if ($mayorLock !== null) {
    /* A mayor's reach is their division. The scope is applied to the query
       rather than to a copy of the results, so the figures on this page and
       the rows in the table can never disagree. */
    $rows = ChairmanService::register(
        $division !== '' ? $division : $mayorLock['code'], $q, 500
    );
    $total    = count($rows);
    $gaps     = ChairmanService::unassignedCount($mayorLock['id']);
    $assigned = $total - $gaps;
    $vendors  = (int) Database::value(
        'SELECT COUNT(*) FROM vendors v JOIN market_chairmen ch ON ch.id = v.chairman_id
          JOIN markets m ON m.id = ch.market_id
         WHERE m.division_id = ' . $mayorLock['id']
    );
    $canEdit  = true;
} elseif ($isChairman) {
    /* A chairman sees one market: their own. The list is not their business,
       and a chairman has no authority to read it. */
    $rows      = [$mine ? ChairmanService::find($mine['id']) : null];
    $rows      = array_values(array_filter($rows));
    $gaps      = 0;
    $total     = 1;
    $assigned  = (int) ($mine['status'] === 'active');
    $vendors   = (int) Database::value(
        'SELECT COUNT(*) FROM vendors WHERE chairman_id = ' . $mine['id']
    );
    $canEdit   = true;
} else {
    $rows     = ChairmanService::register($division, $q);
    $total    = count($rows);
    $gaps     = ChairmanService::unassignedCount();
    $assigned = $total - $gaps;
    $vendors  = (int) Database::value('SELECT COUNT(*) FROM vendors WHERE chairman_id IS NOT NULL');
    $canEdit  = true;
}

$pageTitle = 'Chairmen';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>
      <?= $isChairman ? 'My market' : 'Chairmen' ?>
    </div>
    <div class="eyebrow">Markets Act 2023, s.21 · Market administration</div>
    <h1><?= $isChairman ? e($mine['market_name']) : 'Chairmen' ?></h1>
    <p>
      <?php if ($isChairman): ?>
        Your record as chairman, and the traders allocated to
        <?= e($mine['market_name']) ?> who follow you for this market.
      <?php else: ?>
        A vendor placed into a market takes on that market's chairman, who then
        follows them through the register, the allocation record and every report.
      <?php endif; ?>
    </p>
  </div>
</div>

<div class="grid g4 mb-2">
  <?php if ($isChairman): ?>
    <div class="stat info">
      <div class="label">Traders allocated to you</div>
      <div class="value"><?= num($vendors) ?></div>
      <div class="meta">placed in this market</div>
    </div>
    <div class="stat green">
      <div class="label">Vacant pitches</div>
      <div class="value"><?= num($mine['vacant']) ?></div>
      <div class="meta">of <?= num($mine['capacity']) ?> capacity</div>
    </div>
    <div class="stat">
      <div class="label">Record status</div>
      <div class="value" style="font-size:19px"><?= e(humanise($mine['status'])) ?></div>
      <div class="meta">held by the Authority</div>
    </div>
    <div class="stat">
      <div class="label">Division</div>
      <div class="value" style="font-size:17px"><?= e($mine['division']) ?></div>
      <div class="meta"><?= e($mine['market_code']) ?></div>
    </div>
  <?php else: ?>
    <div class="stat info">
      <div class="label">Markets chaired</div>
      <div class="value"><?= num($assigned) ?></div>
      <div class="meta">of <?= num($total) ?> markets</div>
    </div>
    <div class="stat <?= $gaps > 0 ? 'warn' : 'ok' ?>">
      <div class="label">No chairman recorded</div>
      <div class="value"><?= num($gaps) ?></div>
      <div class="meta"><?= $gaps > 0 ? 'traders there follow nobody yet' : 'every market covered' ?></div>
    </div>
    <div class="stat green">
      <div class="label">Traders following a chairman</div>
      <div class="value"><?= num($vendors) ?></div>
      <div class="meta">placed in a chaired market</div>
    </div>
    <div class="stat">
      <div class="label">Vendors unplaced</div>
      <div class="value"><?= num((int) Database::value('SELECT COUNT(*) FROM vendors WHERE chairman_id IS NULL')) ?></div>
      <div class="meta">still in a trade zone</div>
    </div>
  <?php endif; ?>
</div>

<?php if (!$isChairman): ?>
<!-- ============================ FILTERS ========================== -->
<div class="card mb-2">
  <form class="filters" method="get">
    <div class="field">
      <label for="division">Division</label>
      <select name="division" id="division" data-autosubmit>
        <option value="">All divisions</option>
        <?php foreach ($divisions as $code): ?>
          <option value="<?= e($code) ?>" <?= $division === $code ? 'selected' : '' ?>>
            <?= e(humanise($code)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field grow">
      <label for="search">Market or chairman</label>
      <input type="search" id="search" name="search" value="<?= e($q) ?>"
             placeholder="Nakasero, Wandegeya, a name…">
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="chairmen.php">Reset</a>
  </form>
</div>
<?php endif; ?>

<!-- ============================ REGISTER ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2><?= $isChairman ? 'My record' : 'Market by market' ?></h2>
      <p class="desc">
        <?= num($total) ?> market<?= $total === 1 ? '' : 's' ?>.
        A market with no chairman recorded shows a gap rather than a guess.
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Market</th>
            <?php if (!$isChairman): ?><th>Division</th><?php endif; ?>
            <th>Chairman</th><th>Telephone</th><th>Tenure</th>
            <th class="right">Traders</th><th class="right">Vacant</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php if (!$r) continue; ?>
            <tr>
              <td>
                <strong><?= e($r['market_name']) ?></strong>
                <div class="xsmall muted"><?= e($r['market_code']) ?></div>
              </td>
              <?php if (!$isChairman): ?>
                <td class="xsmall"><?= e($r['division_name']) ?></td>
              <?php endif; ?>
              <td>
                <?php if (!empty($r['full_name'])): ?>
                  <?= e($r['full_name']) ?>
                  <?php if (($r['status'] ?? 'active') !== 'active'): ?>
                    <div><span class="badge warn"><?= e(humanise($r['status'])) ?></span></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">not recorded</span>
                <?php endif; ?>
              </td>
              <td class="xsmall"><?= e($r['phone'] ?: '—') ?></td>
              <td class="xsmall"><?= !empty($r['since']) ? e(fmtDate($r['since'], 'M Y')) : '—' ?></td>
              <td class="right num xsmall"><?= num((int) ($r['vendors_under'] ?? 0)) ?></td>
              <td class="right num xsmall"><?= num((int) ($r['vacant_spaces'] ?? 0)) ?></td>
              <td class="right nowrap">
                <?php if ($canEdit): ?>
                  <button type="button" class="btn btn-ghost btn-sm"
                          data-chairman-edit
                          data-market="<?= (int) $r['id'] ?>"
                          data-market-name="<?= e($r['market_name']) ?>"
                          data-name="<?= e((string) ($r['full_name'] ?? '')) ?>"
                          data-phone="<?= e((string) ($r['phone'] ?? '')) ?>"
                          data-email="<?= e((string) ($r['email'] ?? '')) ?>"
                          data-since="<?= e((string) ($r['since'] ?? '')) ?>"
                          data-status="<?= e((string) ($r['status'] ?? 'active')) ?>"
                          data-notes="<?= e((string) ($r['notes'] ?? '')) ?>">
                    <?= !empty($r['full_name']) ? 'Edit' : 'Record' ?>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('user', 28) ?></div>
        <h3>No markets match</h3>
        <p>Widen the division or clear the search.</p>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!$isChairman): ?>
    <div class="card-foot">
      A chairman is a person recorded against a market, not a login. Granting a
      sign-in is a separate step in <a href="users.php">Accounts</a>.
    </div>
  <?php endif; ?>
</div>

<?php if ($isChairman): ?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>The traders who follow you</h2>
      <p class="desc">Vendors placed into <?= e($mine['market_name']) ?>, newest first.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="vendors.php">Full register</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Vendor</th><th>Trade</th><th>Stage</th><th class="right">Turnover</th><th>Telephone</th></tr>
        </thead>
        <tbody>
          <?php foreach (Database::all(
              'SELECT full_name, vendor_code, business_category, status, monthly_turnover_ugx, phone
                 FROM vendors WHERE chairman_id = ? ORDER BY full_name LIMIT 40',
              [$mine['id']]
          ) as $v): ?>
            <tr>
              <td>
                <strong><?= e($v['full_name']) ?></strong>
                <div class="xsmall muted"><?= e($v['vendor_code']) ?></div>
              </td>
              <td class="xsmall"><?= e(humanise($v['business_category'])) ?></td>
              <td class="xsmall"><span class="badge <?= e(statusTone($v['status'])) ?>"><?= e(humanise($v['status'])) ?></span></td>
              <td class="right num xsmall"><?= ugxShort((int) $v['monthly_turnover_ugx']) ?></td>
              <td class="xsmall"><?= e($v['phone'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============================ EDIT DIALOG ============================ -->
<dialog class="modal" id="chairman-modal">
  <form method="dialog" class="modal-form" id="chairman-form">
    <div class="modal-head">
      <div class="grow">
        <h2 id="chairman-modal-title">Record a chairman</h2>
        <p class="desc" id="chairman-modal-market">—</p>
      </div>
      <button type="button" class="modal-x" data-modal-close aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="field">
        <label for="chairman-name">Full name</label>
        <input type="text" id="chairman-name" maxlength="140" required autocomplete="off">
      </div>
      <div class="field">
        <label for="chairman-phone">Telephone</label>
        <input type="text" id="chairman-phone" maxlength="40" required autocomplete="off"
               placeholder="0772 000 000">
      </div>
      <div class="field">
        <label for="chairman-email">Email <span class="muted">(optional)</span></label>
        <input type="email" id="chairman-email" maxlength="120" autocomplete="off">
      </div>
      <div class="grid g2">
        <div class="field">
          <label for="chairman-since">Tenure began</label>
          <input type="date" id="chairman-since">
        </div>
        <div class="field">
          <label for="chairman-status">Status</label>
          <select id="chairman-status">
            <option value="active">Active</option>
            <option value="interim">Interim</option>
            <option value="vacant">Vacant</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="chairman-notes">Notes <span class="muted">(optional)</span></label>
        <textarea id="chairman-notes" rows="2" maxlength="1000"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <span class="grow"></span>
      <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</dialog>

<script>
/* The dialog is opened by the button on each row, which carries that row's
   values so the form is filled without a second request. */
(function () {
  var modal = document.getElementById('chairman-modal');
  var form  = document.getElementById('chairman-form');
  var marketId = 0;

  document.querySelectorAll('[data-chairman-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      marketId = parseInt(btn.dataset.market, 10) || 0;
      document.getElementById('chairman-modal-title').textContent =
        (btn.textContent.trim() === 'Record' ? 'Record a chairman' : 'Edit the chairman');
      document.getElementById('chairman-modal-market').textContent = btn.dataset.marketName;
      document.getElementById('chairman-name').value  = btn.dataset.name  || '';
      document.getElementById('chairman-phone').value = btn.dataset.phone || '';
      document.getElementById('chairman-email').value = btn.dataset.email || '';
      document.getElementById('chairman-since').value = btn.dataset.since || '';
      document.getElementById('chairman-status').value = btn.dataset.status || 'active';
      document.getElementById('chairman-notes').value = btn.dataset.notes || '';
      modal.showModal();
    });
  });

  modal.querySelectorAll('[data-modal-close]').forEach(function (b) {
    b.addEventListener('click', function () { modal.close(); });
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = new FormData();
    body.append('market_id', marketId);
    body.append('full_name', document.getElementById('chairman-name').value);
    body.append('phone',     document.getElementById('chairman-phone').value);
    body.append('email',     document.getElementById('chairman-email').value);
    body.append('since',     document.getElementById('chairman-since').value);
    body.append('status',    document.getElementById('chairman-status').value);
    body.append('notes',     document.getElementById('chairman-notes').value);
    body.append('csrf',      (window.KCCA && KCCA.csrf) || '');

    fetch(KCCA.api('api/chairman_save.php'), {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.ok) {
        window.location.reload();
      } else {
        window.alert(res.error || 'The record could not be saved.');
      }
    }).catch(function () {
      window.alert('The record could not be saved. Check your connection and try again.');
    });
  });
})();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
