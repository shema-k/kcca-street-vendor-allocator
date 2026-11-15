<?php
/**
 * Switch the language, then go back where the reader was.
 *
 * A GET rather than a POST, deliberately. It changes what the reader sees
 * and nothing else: no record is written, no permission is exercised, and a
 * refused language leaves no trace. A link is therefore safe in a bookmark,
 * in an email, and in a browser that prefetches it.
 *
 * The return path is constrained to this site. "back" is a convenience, not
 * a redirect target we take on trust — an absolute URL there would make this
 * an open redirect, which is a phishing primitive, not a feature.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$code   = (string) ($_GET['lang'] ?? '');
$wanted = Lang::set($code);

/** Only ever send the reader back to a path on this site. */
$back = (string) ($_GET['back'] ?? '');
if ($back === '' || !str_starts_with($back, '/') || str_starts_with($back, '//')) {
    $back = '/index.php';
}

if ($wanted && str_starts_with($back, '/pages/')) {
    $back = '/pages/' . basename($back);
}

/* Referrer is a better guess than the dashboard when the reader did not say
   where they came from, but only if it names this same host. */
if (!isset($_GET['back'])) {
    $ref      = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $refHost  = $ref ? (string) (parse_url($ref, PHP_URL_HOST) ?: '') : '';
    $refPath  = $ref ? (string) (parse_url($ref, PHP_URL_PATH) ?: '') : '';
    if ($refHost === (string) ($_SERVER['HTTP_HOST'] ?? '') && str_starts_with($refPath, '/')) {
        $back = $refPath;
    }
}

header('Location: ' . $back, true, 302);
exit;