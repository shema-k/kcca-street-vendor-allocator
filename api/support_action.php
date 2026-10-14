<?php
/**
 * POST /api/support_action.php
 *   action = issue | confirm | save-attire
 *
 * Everything the Authority or a chairman hands to a trader, and the record
 * that it arrived.
 *
 * Two actors, one endpoint, because the difference between them is who is
 * doing the handing over and not what is being handed over. An officer writes
 * issued_by; a chairman writes issued_by_chairman; exactly one of the two is
 * ever set, and the service refuses a call that sets neither or both. Keeping
 * them apart in the endpoint is what makes that guarantee hold.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');
$mine   = chairmanLock();
$isChairman = $mine !== null;

/* An officer needs 'report' to hand things over — it is the grant that says
   you speak for the Authority. A chairman needs only their own market, which
   the service checks per trader. */
if (!$isChairman) {
    if (!Auth::can('report')) {
        jsonOut(['ok' => false, 'error' => 'Your role does not permit this action.'], 403);
    }
}

try {
    switch ($action) {

        case 'issue':
            $result = TraderSupport::issue([
                'vendor_id'    => (int) ($_POST['vendor_id'] ?? 0),
                'item_id'      => (int) ($_POST['item_id'] ?? 0),
                'quantity'     => (int) ($_POST['quantity'] ?? 1),
                'size'         => (string) ($_POST['size'] ?? ''),
                'issued_on'    => (string) ($_POST['issued_on'] ?? ''),
                'follow_up_on' => (string) ($_POST['follow_up_on'] ?? ''),
                'notes'        => (string) ($_POST['notes'] ?? ''),
            ], $isChairman ? null : Auth::id(), $isChairman ? (int) $mine['id'] : null);
            break;

        case 'confirm':
            $result = TraderSupport::confirmReceipt(
                (int) ($_POST['issue_id'] ?? 0),
                $isChairman ? null : Auth::id(),
                $isChairman ? (int) $mine['id'] : null
            );
            break;

        case 'save-attire':
            /* The prescribed rule for a trade. Officers set it; a chairman
               does not, because the rule is the Authority's even where the
               chairman is the one handing the garment over. */
            if (!Auth::can('manage')) {
                jsonOut(['ok' => false, 'error' => 'Only the Authority sets the prescribed workwear.'], 403);
            }
            $trade = (string) ($_POST['trade_code'] ?? '');
            $data  = [
                'garment'        => trim((string) ($_POST['garment'] ?? '')),
                'colour'         => trim((string) ($_POST['colour'] ?? '')),
                'colour_hex'     => (string) ($_POST['colour_hex'] ?? '#DC351D'),
                'unit_price_ugx' => (int) ($_POST['unit_price_ugx'] ?? 0),
                'issued_by'      => in_array($_POST['issued_by'] ?? '', ['kcca', 'chairman'], true)
                                       ? (string) $_POST['issued_by'] : 'kcca',
                'sizes'          => (string) ($_POST['sizes'] ?? '') ?: null,
                'notes'          => (string) ($_POST['notes'] ?? '') ?: null,
            ];

            if ($trade === '' || $data['garment'] === '' || $data['colour'] === '') {
                jsonOut(['ok' => false, 'error' => 'Name the garment and its colour.'], 400);
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['colour_hex'])) {
                jsonOut(['ok' => false, 'error' => 'The colour must be a hex value like #C0261A.'], 400);
            }
            if ($data['unit_price_ugx'] < 0) {
                jsonOut(['ok' => false, 'error' => 'The price cannot be negative.'], 400);
            }

            $exists = Database::one('SELECT id FROM trade_attire WHERE trade_code = ?', [$trade]);
            if ($exists) {
                Database::update('trade_attire', $data, 'trade_code = :t', ['t' => $trade]);
            } else {
                $data['trade_code'] = $trade;
                Database::insert('trade_attire', $data);
            }
            Auth::log('save-attire', 'trade_attire', (int) ($exists['id'] ?? 0), $trade . ' → ' . $data['colour']);
            $result = ['ok' => true, 'message' => 'Workwear for ' . $trade . ' saved.'];
            break;

        default:
            jsonOut(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'That could not be recorded. Nothing was changed.'], 500);
}

jsonOut($result, $result['ok'] ? 200 : 400);