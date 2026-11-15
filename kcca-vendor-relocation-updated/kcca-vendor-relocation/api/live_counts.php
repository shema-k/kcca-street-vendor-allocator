<?php
/**
 * GET /api/live_counts.php
 *
 * What has arrived since the reader last looked, on the screens that queue
 * work up on one person. Served from LiveCounts, the same definition the
 * pages render, so a number here always matches a row there.
 *
 * The database is real time; this is only the signal. A chairman who must
 * reload to find that a trader has asked them something is reading a report,
 * not a queue — and a queue nobody can see filling is a queue that does not
 * feel like one.
 *
 * It answers anybody signed in, with only the counts they are entitled to.
 * There is nothing to escalate here: a reader cannot ask for somebody else's
 * queue, because the scope is taken from their own account.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    jsonOut(['ok' => false, 'error' => 'Not signed in'], 401);
}

$counts = LiveCounts::for();

jsonOut([
    'ok'     => true,
    'counts' => $counts,
    'total'  => (int) array_sum($counts),
    'at'     => date('Y-m-d H:i:s'),
]);