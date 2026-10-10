<?php
/**
 * Executive Overview — retired.
 *
 * The Executive Director's figures now live in the dashboard they share with
 * every other role, composed by lib/RoleDashboard.php from the sections their
 * remit owns. A second dashboard for one role was the repetition this system
 * set out to remove, so this page redirects rather than keeping a stale copy.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

header('Location: dashboard.php');
exit;