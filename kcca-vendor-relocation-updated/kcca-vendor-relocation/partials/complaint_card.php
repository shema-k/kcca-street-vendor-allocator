<?php
/**
 * One complaint, and the control the reader's role is actually allowed to use.
 *
 * The chain has three hands and this card shows all three of them: the
 * trader's words, what the chairman did about it, and what the Authority
 * replied. Only one of those controls is ever live, because only one of them
 * is the reader's to pull — a chairman can forward or close, an officer can
 * reply, and a trader can do neither to somebody else's complaint.
 *
 * Closing is deliberately not the same as resolving. A chairman who handled
 * it in their own market records it as closed-without-escalation, with the
 * reason, rather than as resolved — because nothing was resolved by the
 * Authority, and telling the trader otherwise would overstate what happened.
 */

declare(strict_types=1);

$isChairman = $isChairman ?? false;
$c          = $complaint ?? null;

if (!$c) {
    return;
}

/** Which controls this reader gets. */
$canForward = $isChairman && in_array($c['status'], ['with-chairman', 'forwarded'], true);
$canClose   = $isChairman && $c['status'] === 'with-chairman';
$canReply   = !$isChairman && in_array($c['status'], ['forwarded', 'with-authority'], true);
$closed     = in_array($c['status'], ['resolved', 'declined'], true);
?>
<article class="card complaint complaint-<?= e($c['status']) ?>">
  <div class="card-head">
    <h2><?= e($c['subject']) ?></h2>
    <span class="badge <?= complaintTone($c['status']) ?>"><?= e(Complaints::STATUSES[$c['status']] ?? $c['status']) ?></span>
  </div>

  <div class="card-body">
    <div class="notice-meta xsmall muted">
      <code><?= e($c['reference']) ?></code>
      · <?= e(Complaints::CATEGORIES[$c['category'] ?? ''] ?? 'Other') ?>
      · from <?= e($c['full_name']) ?><?= $c['market_name'] ? ' (' . e($c['market_name']) . ')' : '' ?>
      · raised <?= e(fmtDate($c['created_at'])) ?>
      <?php if ((int) ($c['days_open'] ?? 0) > 0 && !$closed): ?>
        · open <?= num((int) $c['days_open']) ?> days
      <?php endif; ?>
    </div>

    <blockquote class="complaint-said"><?= nl2br(e($c['body'])) ?></blockquote>

    <ol class="chain">
      <li class="done">
        <span class="chain-when"><?= e(fmtDate($c['created_at'])) ?></span>
        <span class="chain-what"><b>Raised by <?= e($c['full_name']) ?></b>
          — sent to <?= e($c['chairman_name'] ?? 'the chairman') ?></span>
      </li>

      <?php if ($c['chairman_note']): ?>
        <li class="done">
          <span class="chain-when"><?= e(fmtDate($c['forwarded_at'] ?? $c['created_at'])) ?></span>
          <span class="chain-what"><b><?= e($c['chairman_name'] ?? 'The chairman') ?>:</b>
            <?= e($c['chairman_note']) ?></span>
        </li>
      <?php endif; ?>

      <?php if ($c['authority_reply']): ?>
        <li class="<?= $closed ? 'done' : 'now' ?>">
          <span class="chain-when"><?= e(fmtDate($c['resolved_at'] ?? $c['forwarded_at'])) ?></span>
          <span class="chain-what"><b>The Authority:</b> <?= e($c['authority_reply']) ?></span>
        </li>
      <?php elseif ($c['forwarded_at']): ?>
        <li class="now">
          <span class="chain-when"><?= e(fmtDate($c['forwarded_at'])) ?></span>
          <span class="chain-what"><b>Passed to the Authority</b> — waiting on a reply</span>
        </li>
      <?php endif; ?>

      <?php if ($c['resolution']): ?>
        <li class="done">
          <span class="chain-when"><?= e(fmtDate($c['resolved_at'])) ?></span>
          <span class="chain-what"><b>Resolved:</b> <?= e($c['resolution']) ?></span>
        </li>
      <?php endif; ?>
    </ol>

    <?php if ($canReply): ?>
      <div class="field">
        <label for="reply-<?= (int) $c['id'] ?>">Reply from the Authority</label>
        <textarea name="authority_reply" id="reply-<?= (int) $c['id'] ?>" rows="4" required
                  placeholder="The chairman reads this before the trader does."></textarea>
      </div>
      <label class="check">
        <input type="checkbox" name="close" value="1">
        <span>This closes the complaint</span>
      </label>
    <?php endif; ?>

    <?php if ($canForward || $canClose): ?>
      <div class="field">
        <label class="sr-only" for="note-<?= (int) $c['id'] ?>">Note to the trader</label>
        <textarea name="chairman_note" id="note-<?= (int) $c['id'] ?>" rows="2" maxlength="1000"
                  placeholder="What you did about it, or why you are closing it. The trader sees this."></textarea>
      </div>
    <?php endif; ?>
  </div>

  <div class="card-foot right">
    <?php if ($canClose): ?>
      <button class="btn btn-sm" data-complaint-action="decline" data-id="<?= (int) $c['id'] ?>">
        Close without escalating
      </button>
    <?php endif; ?>

    <?php if ($canForward && $c['status'] === 'with-chairman'): ?>
      <button class="btn btn-primary btn-sm" data-complaint-action="forward" data-id="<?= (int) $c['id'] ?>">
        Pass to the Authority
      </button>
    <?php endif; ?>

    <?php if ($canReply): ?>
      <button class="btn btn-primary btn-sm" data-complaint-action="reply" data-id="<?= (int) $c['id'] ?>">
        Send reply
      </button>
    <?php endif; ?>

    <?php if (!$canForward && !$canClose && !$canReply): ?>
      <span class="xsmall muted">
        <?= $closed ? 'Closed — a record, not a queue.' : 'No action is open to you on this.' ?>
      </span>
    <?php endif; ?>
  </div>
</article>