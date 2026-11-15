<?php
/**
 * POST /api/chairman_save.php
 *   market_id, full_name, phone, email, since, status, notes
 *
 * Records the chairman of one market. A chairman's identity is what the
 * vendor register, the allocation record and every report attribute a
 * vendor to, so it is written through a service that validates the market
 * exists before the row is created — not by a direct UPDATE from the page.
 *
 * A chairman editing their own record is limited to their contact details;
 * the market they chair is not theirs to change, and the Authority is who
 * records a change of chairman.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
require_once __DIR__ . '/../lib/ChairmanService.php';

$mine = chairmanLock();
$isChairman = $mine !== null;

/* An officer with 'manage' maintains the register. A chairman may only touch
   their own row, and only the fields that describe them. */
if (!$isChairman && !Auth::can('manage')) {
    jsonOut(['ok' => false, 'error' => 'You do not have authority to record a chairman.'], 403);
}

$marketId = (int) ($_POST['market_id'] ?? 0);

try {
    $result = ChairmanService::save($marketId, [
        'full_name' => (string) ($_POST['full_name'] ?? ''),
        'phone'     => (string) ($_POST['phone'] ?? ''),
        'email'     => (string) ($_POST['email'] ?? ''),
        'since'     => (string) ($_POST['since'] ?? ''),
        'status'    => (string) ($_POST['status'] ?? 'active'),
        'notes'     => (string) ($_POST['notes'] ?? ''),
    ], $isChairman ? (int) $mine['id'] : null);
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'The record could not be saved. Nothing was changed.'], 500);
}

if (!$result['ok']) {
    jsonOut($result, 400);
}

jsonOut($result);
