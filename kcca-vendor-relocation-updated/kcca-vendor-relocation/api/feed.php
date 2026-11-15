<?php
/**
 * GET /api/feed.php?after=<audit id>
 *
 * What colleagues changed since the reader last looked. Staff roles only.
 * With after=0 it returns the latest dozen, for the first paint of the bell.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireSession();

if (!ChangeFeed::allowed()) {
    jsonOut(['ok' => false, 'error' => 'This view is not part of your role', 'code' => 403], 403);
}

$after = max(0, (int) ($_GET['after'] ?? 0));

jsonOut([
    'ok'     => true,
    'events' => ChangeFeed::events($after, $after > 0 ? 30 : 12, Auth::can('audit')),
    'latest' => ChangeFeed::latestId(),
]);
