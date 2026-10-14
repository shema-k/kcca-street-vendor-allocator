<?php
/**
 * POST /api/complaint_action.php
 *   action = raise | forward | decline | reply
 *
 * The complaint chain, and the one place the route is enforced.
 *
 * A trader raises it and cannot choose the destination — the chairman is
 * taken from the trader, so there is no field to tamper with. The chairman
 * forwards or closes it. Only an officer replies, and only to one a chairman
 * has actually escalated: replying to a complaint still sitting with a
 * chairman would answer for a decision that has not been taken.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');
$mine   = chairmanLock();
$isChairman = $mine !== null;

/* A trader's own account is the only thing that may raise a complaint, and it
   may only raise one for itself. */
$vendorId = (int) ($_POST['vendor_id'] ?? 0);
if (Auth::role() === 'vendor') {
    $vendorId = (int) (Auth::user()['vendor_id'] ?? 0);
    if ($vendorId <= 0) {
        jsonOut(['ok' => false, 'error' => 'Your account is not linked to a trader record.'], 403);
    }
}

try {
    switch ($action) {

        case 'raise':
            /* A trader raises for themselves and only for themselves; the
               vendor id above was already forced to their own record, so
               there is nothing in the request they could have aimed
               elsewhere. An officer may raise on a trader's behalf — the
               Authority does receive complaints in person at a division
               office — but only with the grant that says they speak for it. */
            if (Auth::role() !== 'vendor' && !Auth::can('report')) {
                jsonOut(['ok' => false, 'error' => 'You may only raise a complaint for yourself.'], 403);
            }
            if ($vendorId <= 0) {
                jsonOut(['ok' => false, 'error' => 'Choose the trader raising the complaint.'], 400);
            }
            $result = Complaints::raiseFrom($vendorId, [
                'subject'  => (string) ($_POST['subject'] ?? ''),
                'body'     => (string) ($_POST['body'] ?? ''),
                'category' => (string) ($_POST['category'] ?? 'other'),
            ]);
            break;

        case 'forward':
        case 'decline':
            if (!$isChairman) {
                jsonOut(['ok' => false, 'error' => 'A complaint is answered by the chairman it was sent to.'], 403);
            }
            $result = Complaints::chairmanAct(
                (int) ($_POST['complaint_id'] ?? 0),
                (int) $mine['id'],
                [
                    'action'        => $action,
                    'chairman_note' => (string) ($_POST['chairman_note'] ?? ''),
                ]
            );
            break;

        case 'reply':
            if (!Auth::can('report')) {
                jsonOut(['ok' => false, 'error' => 'Only an officer replies on behalf of the Authority.'], 403);
            }
            $result = Complaints::reply((int) ($_POST['complaint_id'] ?? 0), [
                'authority_reply' => (string) ($_POST['authority_reply'] ?? ''),
                'resolution'      => (string) ($_POST['resolution'] ?? ''),
                'close'           => !empty($_POST['close']),
            ]);
            break;

        default:
            jsonOut(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'That could not be recorded. Nothing was changed.'], 500);
}

jsonOut($result, $result['ok'] ? 200 : 400);