<?php
/**
 * Entry point. Sends the visitor to the right place.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

if (Auth::check()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;