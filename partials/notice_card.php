<?php
/**
 * One notice, as the audience for it sees it.
 *
 * Included twice from pages/notices.php and once from a trader's own screen,
 * so it has to be honest about who is looking at it: $isChairman and $canWrite
 * are set by the caller, and the card renders read, published and answered
 * controls only for the role that is actually allowed to use them.
 *
 * The three states — read, published, replied — are separate and each has its
 * own control, because they are three separate promises. "Read" is the
 * chairman's own acknowledgement. "Published" is their word that the traders
 * were told, which is the only one of the three the Authority has any
 * evidence about.
 */

declare(strict_types=1);

$isChairman = $isChairman ?? false;
$canWrite   = $canWrite   ?? false;
$n          = $notice      ?? $card ?? null;

if (!$n) {
    return;
}

$reached  = (int) ($n['reached'] ?? 1);
$read     = (int) ($n['read_count'] ?? ($n['read_at'] ? 1 : 0));
$published= (int) ($n['published_count'] ?? ($n['forwarded_at'] ? 1 : 0));
$category = Notices::CATEGORIES[$n['category'] ?? ''] ?? 'General';
$issuedBy = (string) ($n['issued_by_name'] ?? 'The Authority');
?>
<article class="card notice<?= empty($n['read_at']) && $isChairman ? ' notice-unread' : '' ?>">
  <div class="card-head">
    <h2><?= e($n['subject']) ?></h2>
    <span class="badge"><?= e($category) ?></span>
  </div>

  <div class="card-body">
    <div class="notice-meta xsmall muted">
      <code><?= e($n['reference']) ?></code>
      · from <?= e($issuedBy) ?><?= $n['issued_by_title'] ? ' (' . e($n['issued_by_title']) . ')' : '' ?>
      · <?= e(fmtDate($n['issued_at'])) ?>
      <?php if ($n['acknowledge_by']): ?>
        · reply by <?= e(fmtDate($n['acknowledge_by'])) ?>
      <?php endif; ?>
    </div>

    <p class="notice-body"><?= nl2br(e($n['body'])) ?></p>

    <?php if (!$isChairman): ?>
      <div class="notice-progress">
        <span class="chip<?= $read >= $reached ? ' on' : '' ?>"><?= num($read) ?> of <?= num($reached) ?> read</span>
        <span class="chip<?= $published >= $reached ? ' on' : ($published === 0 ? ' warn' : '') ?>">
          <?= num($published) ?> of <?= num($reached) ?> published
        </span>
      </div>
    <?php endif; ?>

    <?php if ($isChairman): ?>
      <div class="notice-state">
        <span class="chip<?= $n['read_at'] ? ' on' : '' ?>">
          <?= $n['read_at'] ? 'Read ' . e(fmtDate($n['read_at'])) : 'Not read yet' ?>
        </span>
        <span class="chip<?= $n['forwarded_at'] ? ' on' : ' warn' ?>">
          <?= $n['forwarded_at'] ? 'Published ' . e(fmtDate($n['forwarded_at'])) : 'Not published' ?>
        </span>
      </div>

      <?php if ($n['forwarded_note']): ?>
        <p class="notice-note"><b>What you told them:</b> <?= e($n['forwarded_note']) ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isChairman && empty($n['forwarded_at'])): ?>
      <div class="field">
        <label class="sr-only" for="publish-note-<?= (int) $n['id'] ?>">What you told your traders</label>
        <textarea id="publish-note-<?= (int) $n['id'] ?>" rows="2" maxlength="500"
                  placeholder="What you told them, and what they must do."></textarea>
      </div>
    <?php endif; ?>
  </div>

  <div class="card-foot right">
    <?php if ($isChairman && empty($n['read_at'])): ?>
      <button class="btn btn-sm" data-notice-action="read" data-id="<?= (int) $n['id'] ?>">
        Mark as read
      </button>
    <?php endif; ?>

    <?php if ($isChairman && empty($n['forwarded_at'])): ?>
      <button class="btn btn-primary btn-sm" data-notice-action="publish" data-id="<?= (int) $n['id'] ?>">
        Publish to my traders
      </button>
    <?php endif; ?>

    <?php if (!$isChairman && !empty($n['receipts'])): ?>
      <details class="notice-receipts">
        <summary><?= num(count($n['receipts'])) ?> chairman(s) reached — show who</summary>
        <table class="table">
          <thead><tr><th>Chairman</th><th>Market</th><th>Read</th><th>Published</th></tr></thead>
          <tbody>
          <?php foreach ($n['receipts'] as $r): ?>
            <tr>
              <td><?= e($r['full_name']) ?></td>
              <td class="xsmall"><?= e($r['market_name']) ?></td>
              <td class="xsmall nowrap"><?= $r['read_at'] ? e(fmtDate($r['read_at'])) : '<span class="muted">no</span>' ?></td>
              <td class="xsmall nowrap"><?= $r['forwarded_at'] ? e(fmtDate($r['forwarded_at'])) : '<span class="muted">no</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php endif; ?>
  </div>
</article>