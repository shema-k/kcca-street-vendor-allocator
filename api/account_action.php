<?php
/**
 * POST /api/account_action.php
 *   action=reset-password   user_id
 *
 * The only state-changing operation on an account that cannot be done
 * through the accounts page itself. A password is stored hashed, so it
 * cannot be shown or edited — the only thing an officer can do is replace
 * it and hand over a temporary one.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
requirePerm('manage');

$action = (string) ($_POST['action'] ?? '');

if ($action !== 'reset-password') {
    jsonOut(['error' => 'Unknown action'], 400);
}

$id  = (int) ($_POST['user_id'] ?? 0);
$row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);

if (!$row) {
    jsonOut(['error' => 'That account does not exist.'], 404);
}

/* The same rule the accounts page enforces, applied again here so a
   crafted request cannot reach past it. */
if ((int) $row['id'] === Auth::id()) {
    jsonOut(['error' => 'You cannot reset your own password through this screen.'], 403);
}
if (Auth::rank() <= Auth::rank((string) $row['role'])) {
    jsonOut(['error' => sprintf(
        'You cannot reset %s’s password — they hold the same or a higher level of authority.',
        $row['full_name']
    )], 403);
}

$temp = 'KCCA-' . strtoupper(bin2hex(random_bytes(4)));

Database::run(
    'UPDATE users SET password_hash = ? WHERE id = ?',
    [password_hash($temp, PASSWORD_DEFAULT), $id]
);

Auth::log('reset-password', 'users', $id,
    $row['username'] . ' — temporary password issued by ' . (Auth::user()['full_name'] ?? 'system'));

jsonOut([
    'ok'      => true,
    'message' => 'Give this temporary password to ' . $row['full_name']
              . ' and have them change it on first sign-in.',
    'temp'    => $temp,
]);