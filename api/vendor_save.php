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

/* A Division Mayor registers and amends traders in their own division only. */
$mayorLock = divisionLock();
if ($mayorLock !== null) {
    if ($action === 'create') {
        $postedDivision = (int) ($_POST['division_id'] ?? 0);
        if ($postedDivision === 0) {
            $_POST['division_id'] = (string) $mayorLock['id'];   // pinned, not left to be derived
        } elseif ($postedDivision !== (int) $mayorLock['id']) {
            jsonOut(['ok' => false, 'error' => 'A trader can only be registered in your own division.', 'code' => 403], 403);
        }
    } else {
        requireVendorInScope((int) ($_POST['vendor_id'] ?? 0));
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