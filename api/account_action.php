<?php
/**
 * POST /api/account_action.php
 *   action=reset-password   user_id, reason   -> issue a temporary password
 *   action=delete           user_id, reason   -> block sign-in, keep the trail
 *
 * Authority is checked in AccountService, not here: this endpoint is a
 * transport for a decision that has already been made by the service, so
 * the two cannot disagree.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
require_once __DIR__ . '/../lib/AccountService.php';

$action = (string) ($_POST['action'] ?? '');

if (!in_array($action, ['reset-password', 'delete'], true)) {
    jsonOut(['error' => 'Unknown action'], 400);
}

requirePerm('manage');

$id = (int) ($_POST['user_id'] ?? 0);

try {
    $result = $action === 'reset-password'
        ? AccountService::issueTemporaryPassword($id)
        : AccountService::delete($id);
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'The action could not be completed. Nothing was changed.'], 500);
}

if (!$result['ok']) {
    jsonOut(['ok' => false, 'error' => $result['error'] ?? 'That action could not be completed.'], 400);
}

jsonOut($result);