<?php
/**
 * The live-queue control.
 *
 * A small button that turns the poller on, and the figure the poller
 * compares against. The count comes from LiveCounts, the same definition
 * api/live_counts.php serves — two definitions would drift, and drift shows
 * as "1 new" on every poll or as nothing ever arriving, which is worse than
 * no indicator at all.
 *
 * Off by default. A page that restarts polling on its own, or reloads itself
 * under somebody reading, is a page people learn not to leave open — so the
 * reader chooses, and the choice survives the reload.
 */

declare(strict_types=1);

watchLive();
$liveTotal = LiveCounts::total();
?>
<div class="live-watch" data-live data-live-total="<?= (int) $liveTotal ?>">
  <button type="button" class="btn btn-quiet btn-sm" data-live-toggle aria-pressed="false">
    Paused
  </button>
  <span class="xsmall muted">
    <?= num($liveTotal) ?> waiting · watch for more
  </span>
</div>