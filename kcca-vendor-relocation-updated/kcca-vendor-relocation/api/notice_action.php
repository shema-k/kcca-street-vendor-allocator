<?php
/**
 * POST /api/notice_action.php
 *   action = send | read | publish
 *
 * A notice is written by an officer and published by a chairman, so the three
 * actions have three different authorities and this endpoint is where they
 * are told apart. An officer who could publish would be able to assert that
 * traders were told when they were not, which is the one claim in this whole
 * chain that must be made by the person who actually made it.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');
$mine   = chairmanLock();
$isChairman = $mine !== null;

try {
    switch ($action) {

        case 'send':
            /* Sending is the Authority's alone. A chairman has no audience to
               send to — they publish what the Authority has already written. */
            if (!Auth::can('approve')) {
                jsonOut(['ok' => false, 'error' => 'Only an officer may issue a notice.'], 403);
            }
            $result = Notices::send([
                'subject'        => (string) ($_POST['subject'] ?? ''),
                'body'           => (string) ($_POST['body'] ?? ''),
                'category'       => (string) ($_POST['category'] ?? 'general'),
                'audience'       => (string) ($_POST['audience'] ?? 'all'),
                'division_id'    => (int) ($_POST['division_id'] ?? 0),
                'market_id'      => (int) ($_POST['market_id'] ?? 0),
                'requires_ack'   => !empty($_POST['requires_ack']),
                'acknowledge_by' => (string) ($_POST['acknowledge_by'] ?? ''),
            ], Auth::id());
            break;

        case 'read':
        case 'publish':
            if (!$isChairman) {
                jsonOut(['ok' => false, 'error' => 'A notice is published by the chairman of its market.'], 403);
            }
            $noticeId = (int) ($_POST['notice_id'] ?? 0);
            $result = $action === 'read'
                ? Notices::markRead($noticeId, (int) $mine['id'])
                : Notices::publish($noticeId, (int) $mine['id'], (string) ($_POST['note'] ?? ''));
            break;

        default:
            jsonOut(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'That could not be recorded. Nothing was changed.'], 500);
}

jsonOut($result, $result['ok'] ? 200 : 400);