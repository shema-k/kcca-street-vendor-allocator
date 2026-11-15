<?php
/**
 * /api/alerts.php
 *
 *   GET  ?after=<id>                     this account's alerts, newest first
 *   POST action=read     [id]            mark one alert (or all) as read
 *   POST action=message  space_id, body  trader -> their market's chairman, about a stall
 *   POST action=message  vendor_id, body chairman -> one of their traders
 *
 * Every alert belongs to one account; nobody can read or change another's.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireSession();

$userId = Auth::id();
$me     = Auth::user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $after = max(0, (int) ($_GET['after'] ?? 0));
    jsonOut([
        'ok'     => true,
        'unread' => Alerts::unread($userId),
        'latest' => Alerts::latestId($userId),
        'items'  => Alerts::recent($userId, $after, $after > 0 ? 30 : 25),
        'role'   => Auth::role(),
    ]);
}

requireToken();
$action = (string) ($_POST['action'] ?? '');

switch ($action) {

    case 'read': {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        Alerts::markRead($userId, $id);
        jsonOut(['ok' => true, 'unread' => Alerts::unread($userId)]);
    }

    case 'message': {
        $text = trim((string) ($_POST['body'] ?? ''));
        $len  = mb_strlen($text);
        if ($len < 3) {
            jsonOut(['ok' => false, 'error' => 'Write a short message first.'], 422);
        }
        if ($len > 500) {
            jsonOut(['ok' => false, 'error' => 'Keep the message under 500 characters.'], 422);
        }

        /* ---- A trader writes to the chairman of the stall's market ---- */
        if (Auth::role() === 'vendor') {
            $vendorId = (int) ($me['vendor_id'] ?? 0);
            $spaceId  = (int) ($_POST['space_id'] ?? 0);
            if ($vendorId <= 0 || $spaceId <= 0) {
                jsonOut(['ok' => false, 'error' => 'Choose which stall this is about.'], 422);
            }

            /* The trader may only write about a stall they hold. */
            $holds = Database::one(
                "SELECT a.id FROM allocations a
                  WHERE a.vendor_id = ? AND a.market_space_id = ?
                    AND a.status IN ('proposed','offered','accepted','occupied') LIMIT 1",
                [$vendorId, $spaceId]
            );
            if (!$holds) {
                jsonOut(['ok' => false, 'error' => 'You can only message about a stall you hold.'], 403);
            }

            $stall    = Alerts::stall($spaceId);
            $chairman = $stall ? Alerts::chairmanOfMarket((int) $stall['market_id']) : null;
            if (!$stall || !$chairman) {
                jsonOut(['ok' => false, 'error' => 'No chairman is recorded for this market yet.'], 404);
            }

            $recipients = Alerts::usersForChairman((int) $chairman['id']);
            if (!$recipients) {
                jsonOut([
                    'ok'    => false,
                    'error' => $chairman['full_name'] . ' has no sign-in account yet. Please call '
                               . $chairman['phone'] . '.',
                ], 409);
            }

            $vendor = Database::one('SELECT full_name, phone, vendor_code FROM vendors WHERE id = ?', [$vendorId]);
            $body = $text . "\n\nFrom: " . ($vendor['full_name'] ?? 'Trader')
                  . ' (' . ($vendor['vendor_code'] ?? '') . ')'
                  . "\nPhone: " . ($vendor['phone'] ?: 'not recorded')
                  . "\nStall: " . $stall['space_code'] . ' at ' . $stall['market_name'];
            foreach ($recipients as $uid) {
                Alerts::push($uid, 'trader-message', 'info',
                    'Message about stall ' . $stall['space_code'], $body, 'my_market.php',
                    ['vendor_id' => $vendorId, 'space_id' => $spaceId]);
            }
            Auth::log('message-chairman', 'market_spaces', $spaceId, 'Trader wrote to the chairman');
            jsonOut(['ok' => true, 'message' => 'Sent to ' . $chairman['full_name'] . '.']);
        }

        /* ---- A chairman replies to one of their own traders ---- */
        if (Auth::role() === 'chairman') {
            $chairmanId = (int) ($me['chairman_id'] ?? 0);
            $vendorId   = (int) ($_POST['vendor_id'] ?? 0);
            if ($chairmanId <= 0 || $vendorId <= 0) {
                jsonOut(['ok' => false, 'error' => 'Choose which trader to write to.'], 422);
            }
            $own = Database::one(
                'SELECT id FROM vendors WHERE id = ? AND chairman_id = ? LIMIT 1', [$vendorId, $chairmanId]
            );
            if (!$own) {
                jsonOut(['ok' => false, 'error' => 'That trader is not in your market.'], 403);
            }
            $chair = Database::one('SELECT full_name, phone FROM market_chairmen WHERE id = ?', [$chairmanId]);
            $recipients = Alerts::usersForVendor($vendorId);
            if (!$recipients) {
                jsonOut(['ok' => false, 'error' => 'That trader has no sign-in account. Please phone them.'], 409);
            }
            $body = $text . "\n\nFrom: " . ($chair['full_name'] ?? 'Your chairman')
                  . "\nPhone: " . ($chair['phone'] ?? '');
            foreach ($recipients as $uid) {
                Alerts::push($uid, 'chairman-message', 'info',
                    'Message from your chairman', $body, 'my_market.php', ['vendor_id' => $vendorId]);
            }
            Auth::log('message-trader', 'vendors', $vendorId, 'Chairman wrote to a trader');
            jsonOut(['ok' => true, 'message' => 'Sent.']);
        }

        jsonOut(['ok' => false, 'error' => 'Messaging is for traders and chairmen.'], 403);
    }

    default:
        jsonOut(['ok' => false, 'error' => 'Unknown action.'], 400);
}
