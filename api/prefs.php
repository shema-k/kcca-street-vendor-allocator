<?php
/**
 * POST /api/prefs.php   glass=on|off  motion=on|off  guide=on|off
 *
 * Saves the signed-in account's own appearance. It can only ever change the
 * caller's own row; there is no user id in the request to tamper with.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$changed = array_values(array_intersect(array_keys($_POST), array_keys(Preferences::DEFAULTS)));
$prefs   = Preferences::save(Auth::id(), $_POST);
if ($changed !== []) {
    Auth::log('update-preferences', 'users', Auth::id(), 'Changed their own appearance: ' . implode(', ', $changed));
}

jsonOut(['ok' => true, 'prefs' => $prefs]);
