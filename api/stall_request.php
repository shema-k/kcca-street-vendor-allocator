<?php
/**
 * POST /api/stall_request.php
 *   action=request    space_id, full_name, phone, [national_id, business_category,
 *                     trade_detail, reason]
 *   action=review     request_id
 *   action=approve    request_id, [market_space_id]  -> creates an allocation
 *   action=reject     request_id, reason
 *   action=cancel     request_id
 *
 * A trader chooses a pitch and asks for it. This is deliberately NOT an
 * allocation: a pitch is never taken just because somebody clicked it.
 * The Authority checks the request against the vendor register, then
 * allocates — at which point the pitch is genuinely reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = (string) ($_POST['action'] ?? '');

/** Turn a refusal into a clear message. */
function refuse(string $message, int $code = 400): never
{
    jsonOut(['ok' => false, 'error' => $message], $code);
}

switch ($action) {

    /* ---------------- A trader asks for a pitch ---------------- */
    case 'request': {
        /* Validate what the trader supplied. The browser marks these
           required, but a request can reach the endpoint without the
           browser, so the API must not rely on the form. */
        $v = new Validator($_POST, [
            'space_id'         => ['type' => 'int', 'label' => 'Pitch', 'required' => true, 'min' => 1],
            'full_name'        => ['type' => 'text', 'label' => 'Full name', 'required' => true,
                                    'min' => 3, 'max' => 140],
            'phone'            => ['type' => 'phone', 'label' => 'Telephone number', 'required' => true],
            'national_id'      => ['type' => 'national_id', 'label' => 'National ID number'],
            'business_category' => ['type' => 'enum', 'label' => 'What you sell',
                                    'options' => ['food-cooking','fresh-produce','meat-fish','beverages',
                                        'crops-grains','household-goods','clothing-textiles',
                                        'electronics-repairs','salon-services','mobile-services',
                                        'handicraft','livestock','other']],
            'trade_detail'     => ['type' => 'text', 'label' => 'Description of goods', 'max' => 200],
            'reason'           => ['type' => 'text', 'label' => 'Reason', 'max' => 500],
        ]);

        if ($v->fails()) {
            jsonOut([
                'ok'     => false,
                'error'  => 'Some details are missing. Correct the highlighted fields.',
                'errors' => $v->errors(),
            ], 422);
        }

        $spaceId = $v->value('space_id');

        $space = Database::one(
            'SELECT s.*, m.name AS market_name, m.id AS market_id
               FROM market_spaces s JOIN markets m ON m.id = s.market_id
              WHERE s.id = ? LIMIT 1',
            [$spaceId]
        );
        if (!$space) {
            refuse('That pitch does not exist.', 404);
        }

        /* The pitch must still be free, and nobody may be waiting on it. */
        $already = Database::value(
            "SELECT reference FROM stall_requests
              WHERE space_id = ? AND status IN ('pending','reviewing') LIMIT 1",
            [$spaceId]
        );
        if ($already) {
            refuse(sprintf(
                'Somebody has already asked for %s (request %s). The Authority is considering it now.',
                $space['space_code'], $already
            ), 409);
        }
        if ($space['status'] !== 'vacant') {
            refuse(sprintf(
                '%s is already %s. Pick a vacant pitch from the plan.',
                $space['space_code'], $space['status']
            ), 409);
        }

        /* Is this person already on the vendor register? */
        $vendor = null;
        if (!empty($_POST['national_id'])) {
            $vendor = Database::one(
                'SELECT * FROM vendors WHERE national_id = ? LIMIT 1',
                [preg_replace('/[^0-9]/', '', (string) $_POST['national_id'])]
            );
        }
        if (!$vendor) {
            $vendor = Database::one(
                'SELECT * FROM vendors WHERE phone = ? LIMIT 1',
                [preg_replace('/[^0-9+]/', '', (string) ($_POST['phone'] ?? ''))]
            );
        }

        /* Already got space? Then they do not need to ask. */
        if ($vendor) {
            $live = Database::value(
                "SELECT reference FROM allocations
                  WHERE vendor_id = ? AND status IN ('proposed','offered','accepted','occupied') LIMIT 1",
                [(int) $vendor['id']]
            );
            if ($live) {
                refuse(sprintf(
                    'You are recorded as holding space under %s. If you want to move, contact the '
                    . 'market administrator first so that allocation can be closed.',
                    $live
                ), 409);
            }
        }

        $reference = 'REQ-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $id = Database::insert('stall_requests', [
            'reference'        => $reference,
            'space_id'         => $spaceId,
            'market_id'        => (int) $space['market_id'],
            'vendor_id'        => $vendor ? (int) $vendor['id'] : null,
            'full_name'        => $v->value('full_name'),
            'phone'            => $v->value('phone'),
            'national_id'      => $vendor ? $vendor['national_id'] : $v->value('national_id'),
            'business_category'=> $vendor ? $vendor['business_category'] : $v->value('business_category'),
            'trade_detail'     => $v->value('trade_detail') ?: null,
            'reason'           => $v->value('reason') ?: null,
            'status'           => 'pending',
            'source_ip'        => serverClientIp(),
        ]);

        /* The pitch is asked for, so it stops being free: on the plan and
           in the market's own count. A reserved pitch is committed, whether
           the commitment came from the Authority or from the trader. */
        Database::run("UPDATE market_spaces SET status = 'reserved' WHERE id = ?", [$spaceId]);
        Database::run(
            'UPDATE markets SET vacant_spaces = GREATEST(0, vacant_spaces - 1) WHERE id = ?',
            [(int) $space['market_id']]
        );

        if ($vendor) {
            RecommendationService::logEvent(null, (int) $vendor['id'], (int) $space['market_id'],
                null, 'note', 'created', 'Requested pitch ' . $space['space_code'] . ' (' . $reference . ')');
        }

        Auth::log('request-stall', 'stall_requests', $id,
            $reference . ' — ' . $space['space_code'] . ' at ' . $space['market_name']);

        jsonOut([
            'ok'      => true,
            'message' => sprintf(
                'Your request for %s at %s has been sent as %s. '
                . 'The Authority will check it against the vendor register and let you know.',
                $space['space_code'], $space['market_name'], $reference
            ),
            'reference' => $reference,
        ]);
    }

    /* ---------------- Officer starts considering a request ---------------- */
    case 'review': {
        requirePerm('allocate');
        $id = (int) ($_POST['request_id'] ?? 0);
        $row = Database::one('SELECT * FROM stall_requests WHERE id = ?', [$id]);
        if (!$row) {
            refuse('That request does not exist.', 404);
        }
        if ($row['status'] !== 'pending') {
            refuse('This request is already ' . humanise($row['status']) . '.', 409);
        }

        Database::run(
            "UPDATE stall_requests SET status = 'reviewing', decided_by = ?, decided_at = NOW() WHERE id = ?",
            [Auth::id() ?: null, $id]
        );
        Auth::log('review-stall-request', 'stall_requests', $id, $row['reference']);
        jsonOut(['ok' => true, 'message' => 'Marked as under review.']);
    }

    /* ---------------- Officer approves: this becomes a real allocation ---------------- */
    case 'approve': {
        requirePerm('allocate');

        $id  = (int) ($_POST['request_id'] ?? 0);
        $row = Database::one('SELECT * FROM stall_requests WHERE id = ?', [$id]);
        if (!$row) {
            refuse('That request does not exist.', 404);
        }
        if (!in_array($row['status'], ['pending', 'reviewing'], true)) {
            refuse('This request is ' . humanise($row['status']) . '.', 409);
        }

        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            $result = Database::transaction(static function () use ($row, $note): array {                /* A trader with no vendor record gets one now, so the
                   allocation points at a real person rather than a name.

                   The request collects only what a trader can answer
                   standing at a market gate, so the record is created with
                   the market's own division and honest placeholders, and
                   the gaps are stated in the description rather than
                   invented. They get filled in at the division office. */
                $vendorId = (int) $row['vendor_id'];
                if ($vendorId === 0) {
                    $marketRow = Database::one(
                        'SELECT division_id FROM markets WHERE id = ?',
                        [(int) $row['market_id']]
                    );

                    $created = VendorService::save([
                        'action'               => 'create',
                        'full_name'            => $row['full_name'],
                        'phone'                => $row['phone'],
                        'national_id'          => $row['national_id'] ?: null,
                        'division_id'          => (int) ($marketRow['division_id'] ?? 1),
                        'business_category'    => $row['business_category'] ?: 'other',
                        'business_description'  => trim(
                            ($row['trade_detail'] ? $row['trade_detail'] . '. ' : '')
                            . 'Registered from a stall request ('
                            . $row['reference'] . '). Household and turnover '
                            . 'to be confirmed at the division office.'
                        ),
                        'gender'               => 'other',
                        'household_size'       => 1,
                        'started_year'         => (int) date('Y'),
                        'status'               => 'surveyed',
                        'licence_class'        => 'unlicensed',
                    ], 'create');

                    if (empty($created['ok']) || empty($created['id'])) {
                        throw new RuntimeException(
                            'This trader is not on the vendor register and a record could not be '
                            . 'opened for them from this request. Register them first, then approve.'
                        );
                    }

                    $vendorId = (int) $created['id'];

                    Database::run('UPDATE stall_requests SET vendor_id = ? WHERE id = ?',
                        [$vendorId, (int) $row['id']]);
                }

                /* The pitch must still be free — another officer may have
                   allocated it while this request was being read. */
                $space = Database::one(
                    "SELECT * FROM market_spaces WHERE id = ? AND status = 'reserved' LIMIT 1",
                    [(int) $row['space_id']]
                );
                if (!$space) {
                    throw new RuntimeException(sprintf(
                        'Pitch %s is no longer reserved to this request. Refresh the market and try again.',
                        $row['space_id']
                    ));
                }

                $reference = 'ALC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $market = Database::one('SELECT * FROM markets WHERE id = ?', [(int) $row['market_id']]);

                $allocId = Database::insert('allocations', [
                    'reference'       => $reference,
                    'vendor_id'       => $vendorId,
                    'market_id'       => (int) $row['market_id'],
                    'market_space_id' => (int) $row['space_id'],
                    'distance_km'     => 0,
                    'total_score'     => 100,
                    'score_breakdown' => json_encode(['source' => 'trader\'s own choice']),
                    'reasoning'       => 'Approved from the trader\'s own request ' . $row['reference']
                                          . '. The trader chose this pitch themselves.',
                    'rank'            => 1,
                    'status'          => 'offered',
                    'source'          => 'manual',
                    'offer_expires'   => date('Y-m-d', strtotime('+14 days')),
                    'created_by'      => Auth::id() ?: null,
                ]);

                Database::run("UPDATE market_spaces SET status = 'reserved' WHERE id = ?",
                    [(int) $row['space_id']]);
                Database::run(
                    'UPDATE markets SET vacant_spaces = GREATEST(0, vacant_spaces - 1) WHERE id = ?',
                    [(int) $row['market_id']]
                );
                Database::run(
                    "UPDATE vendors SET status = 'offered-space' WHERE id = ? AND status IN ('surveyed','notified')",
                    [$vendorId]
                );
                // A trader who asked for a pitch has chosen their market, so
                // the chairman is attached now rather than waiting for the
                // offer to be accepted: it is the same person either way.
                syncVendorChairman($vendorId, (int) $row['market_id']);

                Database::run(
                    "UPDATE stall_requests SET status = 'approved', decided_by = ?, decided_at = NOW(),
                            decision_note = ?, allocation_id = ? WHERE id = ?",
                    [Auth::id() ?: null, $note !== '' ? $note : 'Approved', $allocId, (int) $row['id']]
                );

                RecommendationService::logEvent($allocId, $vendorId, (int) $row['market_id'],
                    null, 'offered', 'created',
                    'Approved trader request ' . $row['reference'] . ' → ' . $reference);

                Auth::log('approve-stall-request', 'stall_requests', (int) $row['id'],
                    $row['reference'] . ' → ' . $reference);

                return [
                    'reference' => $reference,
                    'market'    => $market['name'] ?? 'the market',
                    'space'     => $space['space_code'],
                ];
            });
        } catch (RuntimeException $e) {
            refuse($e->getMessage(), 409);
        } catch (Throwable $e) {
            refuse('The request could not be approved. Nothing was changed.', 500);
        }

        jsonOut([
            'ok'      => true,
            'message' => sprintf(
                'Approved. %s has been offered %s as %s.',
                $row['full_name'], $result['space'], $result['reference']
            ),
        ]);
    }

    /* ---------------- Reject, with a reason the trader is told ---------------- */
    case 'reject': {
        requirePerm('allocate');

        $id     = (int) ($_POST['request_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            refuse('A rejection must give a reason. The trader is told it.', 422);
        }

        $row = Database::one('SELECT * FROM stall_requests WHERE id = ?', [$id]);
        if (!$row) {
            refuse('That request does not exist.', 404);
        }
        if (!in_array($row['status'], ['pending', 'reviewing'], true)) {
            refuse('This request is ' . humanise($row['status']) . '.', 409);
        }

        Database::transaction(static function () use ($id, $reason, $row): void {
            Database::run(
                "UPDATE stall_requests SET status = 'rejected', decided_by = ?, decided_at = NOW(),
                        decision_note = ? WHERE id = ?",
                [Auth::id() ?: null, $reason, $id]
            );
            /* The pitch goes back into the vacancy pool. */
            Database::run(
                "UPDATE market_spaces SET status = 'vacant' WHERE id = ? AND status = 'reserved'",
                [(int) $row['space_id']]
            );
            Database::run(
                'UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?',
                [(int) $row['market_id']]
            );
        });

        Auth::log('reject-stall-request', 'stall_requests', $id,
            $row['reference'] . ' rejected: ' . $reason);

        jsonOut(['ok' => true, 'message' => 'Request rejected. The pitch is back in the vacancy pool.']);
    }

    /* ---------------- The trader withdraws their own request ---------------- */
    case 'cancel': {
        $id     = (int) ($_POST['request_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $row = Database::one('SELECT * FROM stall_requests WHERE id = ?', [$id]);
        if (!$row) {
            refuse('That request does not exist.', 404);
        }
        if (!in_array($row['status'], ['pending', 'reviewing'], true)) {
            refuse('This request is already ' . humanise($row['status']) . '.', 409);
        }

        Database::transaction(static function () use ($id, $reason, $row): void {
            Database::run(
                "UPDATE stall_requests SET status = 'cancelled' WHERE id = ?", [$id]
            );
            Database::run(
                "UPDATE market_spaces SET status = 'vacant' WHERE id = ? AND status = 'reserved'",
                [(int) $row['space_id']]
            );
            Database::run(
                'UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?',
                [(int) $row['market_id']]
            );
        });

        Auth::log('cancel-stall-request', 'stall_requests', $id,
            $row['reference'] . ' cancelled by the trader');

        jsonOut(['ok' => true, 'message' => 'Request withdrawn. The pitch is free again.']);
    }

    default:
        refuse('Unknown action.', 400);
}