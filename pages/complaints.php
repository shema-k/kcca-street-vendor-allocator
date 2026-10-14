<?php
/**
 * Complaints.
 *
 * A trader's complaint goes to their chairman, not to the Authority. That is
 * the intended route, not a fallback: the chairman is the person standing in
 * that market who can do something about a queue at the entrance, and routing
 * everything past them would put the Authority in the position of answering
 * questions only the market can answer. The chairman may answer it, or pass
 * it on.
 *
 * So this screen is split by who is reading it. A chairman sees what is in
 * their market and can act on it — answer, or forward. An officer sees only
 * what a chairman actually escalated, because a complaint still sitting with
 * a chairman is not an Authority matter yet, and counting it here would put a
 * number on the dashboard that nobody can act on.
 *
 * The stage a complaint is at is the honest part. Forwarded and answered are
 * different, and so are resolved and closed-without-escalation: the second
 * means somebody decided not to act and recorded why, which is exactly what a
 * trader who feels ignored needs to see accounted for.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$mine = chairmanLock();
$isChairman = $mine !== null;

$mayorLock = $isChairman ? null : divisionLock();
if (!$isChairman && $mayorLock === null && !Auth::can('report')) {
    Auth::requirePermission('report');
}

$filter = (string) ($_GET['filter'] ?? 'open');
$open   = (int) ($_GET['complaint'] ?? 0);
$shown  = $open > 0 ? Complaints::find($open) : null;

if ($isChairman) {
    $rows   = Complaints::forChairman((int) $mine['id'], $filter);
    $counts = Complaints::counts((int) $mine['id']);
    /* A chairman may only open a complaint addressed to them. Scoping the
       detail read as well as the list, so a guessed id shows nothing. */
    if ($shown && (int) ($shown['chairman_id'] ?? 0) !== (int) $mine['id']) {
        $shown = null;
    }
} else {
    $rows   = Complaints::register($mayorLock['id'] ?? null, $filter);
    $counts = Complaints::counts(null, $mayorLock['id'] ?? null);
}

$divisions = $mayorLock !== null
    ? Database::column('SELECT code FROM divisions WHERE id = ? ORDER BY name', [$mayorLock['id']])
    : Database::column('SELECT code FROM divisions ORDER BY name');

$pageTitle = 'Complaints';
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p class="sub">
      <?= $isChairman
          ? 'Your traders send complaints to you first, because you are the one in the market who can answer them. Pass one on if the Authority is who has to act.'
          : 'Complaints a chairman passed on. Those still with a chairman are not shown here — they are not an Authority matter until they are escalated.' ?>
    </p>
  </div>
</section>

<section class="rd-stats">
  <?php if ($isChairman): ?>
    <div class="rd-stat"><span class="num"><?= num($counts['open']) ?></span><span>Open with you</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['awaiting_reply']) ?></span><span>Awaiting our reply</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['resolved']) ?></span><span>Resolved</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['declined']) ?></span><span>Closed by you</span></div>
  <?php else: ?>
    <div class="rd-stat"><span class="num"><?= num($counts['awaiting_reply']) ?></span><span>Waiting on a reply</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['open']) ?></span><span>Open</span></div>
    <div class="rd-stat"><span class="num"><?= num($counts['resolved']) ?></span><span>Resolved</span></div>
  <?php endif; ?>
</section>

<?php if ($shown): ?>
  <?php
  /* The partial reads $complaint. Handing it the row under that name rather
     than expecting it to guess is the whole difference between the card
     rendering and the page quietly omitting it — which is what happened,
     and which no error would have reported. */
  $complaint = $shown;
  require __DIR__ . '/../partials/complaint_card.php';
  ?>
<?php endif; ?>

<div class="filters">
  <span class="chips">
    <?php foreach (['open' => 'Open', 'closed' => 'Closed'] as $k => $label): ?>
      <a class="chip<?= $filter === $k ? ' on' : '' ?>" href="<?= e(urlWith(['filter' => $k])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </span>

  <?php if (!$isChairman): ?>
    <label class="sr-only" for="division">Division</label>
    <form method="get" style="display:contents">
      <select name="division" id="division">
        <option value="">Every division</option>
        <?php foreach ($divisions as $code): ?>
          <?php $nm = (string) Database::value('SELECT name FROM divisions WHERE code = ?', [$code]); ?>
          <option value="<?= e($code) ?>"<?= ($_GET['division'] ?? '') === $code ? ' selected' : '' ?>><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="filter" value="<?= e($filter) ?>">
      <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
    </form>
  <?php endif; ?>
</div>

<section class="card">
  <div class="card-head">
    <h2><?= $isChairman ? 'From your traders' : 'Escalated to the Authority' ?></h2>
    <span class="xsmall muted"><?= num(count($rows)) ?></span>
  </div>
  <div class="card-body">
  <?php if ($rows === []): ?>
    <p class="empty"><?= $filter === 'open'
        ? 'Nothing open.'
        : 'Nothing closed.' ?></p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Reference</th><th>From</th><th>About</th><th>Stage</th>
          <th class="nowrap">Age</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr<?= !empty($c['awaiting_reply']) ? ' class="row-alert"' : '' ?>>
          <td><code><?= e($c['reference']) ?></code></td>
          <td>
            <?= e($c['full_name']) ?>
            <div class="xsmall muted"><?= e($c['market_name'] ?? 'Not placed') ?></div>
          </td>
          <td>
            <a href="<?= e(urlWith(['complaint' => (int) $c['id']])) ?>"><?= e($c['subject']) ?></a>
            <div class="xsmall muted"><?= e(Complaints::CATEGORIES[$c['category']] ?? 'Other') ?></div>
          </td>
          <td><span class="badge <?= complaintTone($c['status']) ?>"><?= e(Complaints::STATUSES[$c['status']] ?? $c['status']) ?></span></td>
          <td class="nowrap xsmall">
            <?= num((int) $c['days_open']) ?>d
            <?php if (!empty($c['awaiting_reply'])): ?>
              <div class="tint-red">awaiting reply</div>
            <?php endif; ?>
          </td>
          <td class="right nowrap">
            <a class="btn btn-sm" href="<?= e(urlWith(['complaint' => (int) $c['id']])) ?>">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
  </div>
</section>

<script>
document.querySelectorAll('[data-complaint-action]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var card    = btn.closest('.card');
    var payload = { action: btn.dataset.complaintAction, complaint_id: btn.dataset.id };

    if (btn.dataset.complaintAction === 'decline') {
      var note = card.querySelector('[name="chairman_note"]');
      if (note) payload.chairman_note = note.value;
    }
    if (btn.dataset.complaintAction === 'reply') {
      var reply = card.querySelector('[name="authority_reply"]');
      var close = card.querySelector('[name="close"]');
      if (reply) payload.authority_reply = reply.value;
      if (close)  payload.close = close.checked ? 1 : 0;
    }

    btn.disabled = true;
    KCCA.post('complaint_action.php', payload).then(function (res) {
      KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
      if (res.ok) { setTimeout(function () { location.reload(); }, 800); }
      else { btn.disabled = false; }
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>