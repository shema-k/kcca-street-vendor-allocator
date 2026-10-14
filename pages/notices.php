<?php
/**
 * Notices.
 *
 * A trader does not read the Authority's correspondence; they read what their
 * chairman tells them. So a notice written by an officer goes to the
 * chairmen, and a chairman publishes it to their market. That indirection is
 * the design, and it is why this screen counts two things separately.
 *
 * Sent is the Authority's half: notices issued, and how many chairmen they
 * reached. Published is the half that matters operationally — a notice that
 * was sent and never published reached nobody, and a chairman who has not
 * published is the only link in the chain that can still be fixed.
 *
 * Officers write. Chairmen publish. Neither can do the other's job, and the
 * endpoint behind these forms refuses both crossings, because an officer able
 * to mark a notice published would be able to assert that 8,000 traders were
 * told when they were not.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$mine = chairmanLock();
$isChairman = $mine !== null;

/* A mayor's reach is their division, as everywhere else. */
$mayorLock = $isChairman ? null : divisionLock();
if (!$isChairman && $mayorLock === null) {
    Auth::requirePermission('approve');
}

$open  = (int) ($_GET['notice'] ?? 0);
$shown = $open > 0 ? Notices::find($open) : null;

$counts = Notices::pendingCounts($mayorLock['id'] ?? null);

if ($isChairman) {
    $filter = in_array($_GET['filter'] ?? '', ['all', 'unread', 'unpublished'], true)
        ? (string) $_GET['filter'] : 'all';
    $mine_notices = Notices::forChairman((int) $mine['id'], $filter);
    $register     = [];
} else {
    $filter   = '';
    $division = $mayorLock !== null ? $mayorLock['code'] : (string) ($_GET['division'] ?? '');
    $q        = (string) ($_GET['search'] ?? '');
    $divisionId = $mayorLock !== null
        ? $mayorLock['id']
        : ($division !== '' ? (int) Database::value('SELECT id FROM divisions WHERE code = ?', [$division]) : null);
    $mine_notices = [];
    $register     = Notices::register($divisionId, $q);
}

$divisions = $mayorLock !== null
    ? Database::column('SELECT code FROM divisions WHERE id = ? ORDER BY name', [$mayorLock['id']])
    : Database::column('SELECT code FROM divisions ORDER BY name');

$canWrite = !$isChairman && Auth::can('approve');

$pageTitle = 'Notices';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p class="sub">
      <?= $isChairman
          ? 'Notices from the Authority to you. Publishing one puts it in front of the traders in your market — which is the step that means anybody was actually told.'
          : 'A notice goes to the chairmen; the chairman publishes it to their traders. Sent and published are counted separately, because a notice nobody was told is not a notice that was issued.' ?>
    </p>
  </div>
</section>

<?php if ($isChairman): ?>

  <section class="rd-stats">
    <div class="rd-stat"><span class="num"><?= num(count($mine_notices)) ?></span><span>Notices to you</span></div>
  </section>

  <div class="filters">
    <span class="chips">
      <?php foreach (['all' => 'All', 'unread' => 'Unread', 'unpublished' => 'Not yet published'] as $k => $label): ?>
        <a class="chip<?= $filter === $k ? ' on' : '' ?>" href="<?= e(urlWith(['filter' => $k])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </span>
  </div>

  <?php if ($shown): ?>
    <?php
    /* The partial reads $notice. Naming it here is what makes the card render;
       expecting it to guess is how it came to be silently absent. */
    $notice = $shown;
    require __DIR__ . '/../partials/notice_card.php';
    ?>
  <?php endif; ?>

  <section class="card">
    <div class="card-head"><h2>Notices for your market</h2></div>
    <div class="card-body">
    <?php if ($mine_notices === []): ?>
      <p class="empty">There are no notices for you at the moment.</p>
    <?php else: ?>
      <?php foreach ($mine_notices as $n): ?>
        <?php $card = $n + ['chairman_name' => $mine['full_name'], 'market_name' => $mine['market_name']]; ?>
        <?php require __DIR__ . '/../partials/notice_card.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </section>

<?php else: ?>

  <section class="rd-stats">
    <div class="rd-stat"><span class="num"><?= num(count($register)) ?></span><span>Notices issued</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['unread']) ?></span><span>Not yet read</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['unpublished']) ?></span><span>Not yet published</span></div>
    <?php if ($counts['overdue'] > 0): ?>
      <div class="rd-stat"><span class="num tint-red"><?= num($counts['overdue']) ?></span><span>Past reply-by</span></div>
    <?php endif; ?>
  </section>

  <?php if ($shown): ?>
    <?php
    /* The partial reads $notice. Naming it here is what makes the card render;
       expecting it to guess is how it came to be silently absent. */
    $notice = $shown;
    require __DIR__ . '/../partials/notice_card.php';
    ?>
  <?php endif; ?>

  <?php if ($canWrite): ?>
    <section class="card">
      <div class="card-head"><h2>Issue a notice</h2></div>
      <div class="card-body">
        <form id="notice-form">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

          <div class="grid g2">
            <div class="field">
              <label for="n-subject">Subject</label>
              <input type="text" name="subject" id="n-subject" required maxlength="200"
                     placeholder="Bring your nets before the rains">
            </div>

            <div class="field">
              <label for="n-category">Subject of</label>
              <select name="category" id="n-category">
                <?php foreach (Notices::CATEGORIES as $k => $label): ?>
                  <option value="<?= e($k) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="n-body">The notice</label>
            <textarea name="body" id="n-body" rows="5" required
              placeholder="Write it as the chairman will read it out. Say what is happening, what the traders must do, and by when."></textarea>
          </div>

          <div class="grid g3">
            <div class="field">
              <label for="n-audience">Send to</label>
              <select name="audience" id="n-audience">
                <?php foreach (Notices::AUDIENCES as $k => $label): ?>
                  <option value="<?= e($k) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field" data-when="division">
              <label for="n-division">Division</label>
              <select name="division_id" id="n-division">
                <option value="">Choose a division</option>
                <?php foreach (Database::all('SELECT id, name FROM divisions ORDER BY name') as $d): ?>
                  <option value="<?= (int) $d['id'] ?>"<?= ($mayorLock['id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>>
                    <?= e($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field" data-when="market">
              <label for="n-market">Market</label>
              <select name="market_id" id="n-market">
                <option value="">Choose a market</option>
                <?php foreach (Database::all(
                    'SELECT m.id, m.name, d.name AS dn FROM markets m JOIN divisions d ON d.id = m.division_id
                      ORDER BY d.name, m.name') as $m): ?>
                  <option value="<?= (int) $m['id'] ?>"><?= e($m['dn']) ?> — <?= e($m['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="n-by">Reply by</label>
              <input type="date" name="acknowledge_by" id="n-by">
              <span class="xsmall muted">Optional. Shows as overdue after this date.</span>
            </div>

            <div class="field">
              <label for="n-ack">Reply required</label>
              <select name="requires_ack" id="n-ack">
                <option value="1">Yes — chairmen must confirm</option>
                <option value="0">No — for information</option>
              </select>
            </div>
          </div>

          <div class="card-foot right">
            <button type="submit" class="btn btn-primary">Issue notice</button>
          </div>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <form method="get" class="filters">
    <label class="sr-only" for="division">Division</label>
    <select name="division" id="division">
      <option value="">Every division</option>
      <?php foreach ($divisions as $code): ?>
        <?php $nm = (string) Database::value('SELECT name FROM divisions WHERE code = ?', [$code]); ?>
        <option value="<?= e($code) ?>"<?= ($_GET['division'] ?? '') === $code ? ' selected' : '' ?>><?= e($nm) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="sr-only" for="search">Search</label>
    <input type="search" name="search" id="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Subject or reference">
    <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
  </form>

  <section class="card">
    <div class="card-head"><h2>Notice register</h2></div>
    <div class="card-body">
    <?php if ($register === []): ?>
      <p class="empty">No notices have been issued.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Reference</th><th>Subject</th><th>Sent to</th><th class="nowrap">Reached</th>
              <th class="nowrap">Read</th><th class="nowrap">Published</th><th>Issued</th></tr>
        </thead>
        <tbody>
        <?php foreach ($register as $n): ?>
          <tr>
            <td><code><?= e($n['reference']) ?></code></td>
            <td>
              <a href="<?= e(urlWith(['notice' => (int) $n['id']])) ?>"><?= e($n['subject']) ?></a>
              <?php if (!empty($n['overdue'])): ?>
                <div class="xsmall tint-red">past reply-by <?= e(fmtDate($n['acknowledge_by'])) ?></div>
              <?php endif; ?>
            </td>
            <td class="xsmall"><?= e(match ($n['audience']) {
                'division' => $n['division_name'] ?? 'A division',
                'market'   => $n['market_name'] ?? 'One market',
                default    => 'Every chairman',
            }) ?></td>
            <td class="nowrap"><?= num((int) $n['reached']) ?></td>
            <td class="nowrap"><?= num((int) $n['read_count']) ?></td>
            <td class="nowrap">
              <?php $pub = (int) $n['published_count']; $reach = (int) $n['reached']; ?>
              <?php if ($pub === 0 && $reach > 0): ?>
                <span class="badge warn">none</span>
              <?php else: ?>
                <?= num($pub) ?>
              <?php endif; ?>
            </td>
            <td class="xsmall nowrap">
              <?= e(fmtDate($n['issued_at'])) ?>
              <div class="muted"><?= e(shortName((string) $n['issued_by_name'])) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    </div>
  </section>

<?php endif; ?>

<script>
/* The audience selector decides which of the two narrowing fields is asked
   for. Showing both at once invites the reader to fill in the wrong one, and
   the service then refuses a notice that looked complete. */
(function () {
  var sel  = document.getElementById('n-audience');
  if (!sel) return;
  var sync = function () {
    document.querySelectorAll('[data-when]').forEach(function (f) {
      f.classList.toggle('hidden', f.dataset.when !== sel.value);
    });
  };
  sel.addEventListener('change', sync);
  sync();
})();

document.getElementById('notice-form')?.addEventListener('submit', function (ev) {
  ev.preventDefault();
  KCCA.post('notice_action.php', new FormData(ev.target)).then(function (res) {
    KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
    if (res.ok) setTimeout(function () { location.href = 'notices.php'; }, 800);
  });
});

document.querySelectorAll('[data-notice-action]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var payload = { action: btn.dataset.noticeAction, notice_id: btn.dataset.id };
    if (btn.dataset.noticeAction === 'publish') {
      /* The note belongs to the card the button is in, not to the page — a
         page can list several notices, and publishing one must not send
         another's note with it. */
      var note = btn.closest('.card').querySelector('textarea');
      if (note) payload.note = note.value;
    }
    btn.disabled = true;
    KCCA.post('notice_action.php', payload).then(function (res) {
      KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
      if (res.ok) { setTimeout(function () { location.reload(); }, 800); }
      else { btn.disabled = false; }
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>