<?php
/**
 * POST /api/vendor_save.php
 *   action=create | update | delete
 *
 * Thin JSON endpoint over VendorService. The validation and the write
 * live in the service so the modal form and this endpoint cannot disagree
 * about what is accepted.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/VendorService.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');

if (!in_array($action, ['create', 'update', 'delete'], true)) {
    jsonOut(['error' => 'Unknown action'], 400);
}

if ($action === 'delete') {
    requirePerm('manage');
} else {
    /* A chairman registers and corrects the traders in their own market,
       which is theirs to do and nobody else's. VendorService pins the
       market to their account and refuses an update to a trader outside it,
       so opening the endpoint here grants no reach beyond that market. */
    requireSession();
    if (!Auth::can('allocate') && Auth::role() !== 'chairman') {
        requirePerm('allocate');
    }
}

$result = VendorService::save($_POST, $action);

if (!$result['ok']) {
    jsonOut([
        'ok'     => false,
        'error'  => $result['error'],
        'errors' => $result['errors'],
        /* Echoed back so the form can restore what the user typed. Only
           fields the user supplied are returned; nothing is read from
           here on the server. */
        'values' => $_POST,
    ], $result['status']);
}

unset($result['status'], $result['errors']);
jsonOut($result + ['ok' => true]);