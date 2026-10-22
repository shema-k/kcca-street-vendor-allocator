<?php
/**
 * /api/self_account.php
 *   GET  ?action=export                   download my data as JSON
 *   POST action=delete password, confirm  delete my own account
 *
 * Both act on the signed-in account only. There is no user id in the request.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireSession();

$action = (string) ($_REQUEST['action'] ?? '');

if ($action === 'export') {
    Auth::log('export', 'users', Auth::id(), 'Downloaded their own account data');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="my-kcca-account-data.json"');
    header('Cache-Control: no-store');
    echo json_encode(SelfAccount::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    requireToken();
    $result = SelfAccount::delete((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
    if (!$result['ok']) {
        jsonOut(['ok' => false, 'error' => $result['error'] ?? 'Your account could not be deleted.'], 400);
    }
    jsonOut(['ok' => true, 'message' => 'Your account has been deleted.', 'redirect' => '../auth/login.php']);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
