<?php
/**
 * POST /api/allocation_action.php
 *   action=offer    vendor_id, market_id, [market_space_id]  -> create offer
 *   action=accept   allocation_id                            -> vendor accepts
 *   action=decline  allocation_id, reason
 *   action=withdraw allocation_id, reason
 *   action=occupy   allocation_id                           -> confirmed in place
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');

function badRequest(string $msg, int $code = 400): never
{
    jsonOut(['error' => $msg], $code);
}

switch ($action) {

    /* ---------------- Offer a pitch to a vendor ---------------- */
    case 'offer': {
        requirePerm('allocate');

        $vendorId = (int) ($_POST['vendor_id'] ?? 0);
        $marketId = (int) ($_POST['market_id'] ?? 0);
        /* Read once, through a default. The pitch is resolved further down,
           against the market, but it is read here so that a missing field and
           an empty field behave the same. */
        $spaceRaw = trim((string) ($_POST['market_space_id'] ?? ''));
        $spaceId  = $spaceRaw !== '' ? (int) $spaceRaw : null;

        if ($vendorId <= 0 || $marketId <= 0) {
            badRequest('Choose both a vendor and a market.');
        }

        $vendor = vendorForMatching($vendorId);
        $market = Database::one('SELECT * FROM markets WHERE id = ? LIMIT 1', [$marketId]);
        if (!$vendor || !$market) {
            badRequest('That vendor or market is not in the register.', 404);
        }
        if ((int) $market['vacant_spaces'] <= 0) {
            badRequest($market['name'] . ' has no vacant space to offer.');
        }

        /* Which pitch. An offer may name one, or leave it out and let the
           system choose the market's next free pitch.

           The pitch used to be taken straight from the request and used
           without checking it was in the chosen market, and the market's
           vacancy was then decremented whatever happened. Naming a pitch
           belonging to another market therefore reserved that other market's
           pitch while taking the vacancy from this one, and naming no pitch
           at all silently reduced an advertised count with no pitch held
           against it. Both left a figure nobody could reconcile.

           So: a named pitch must belong to this market and be vacant, and a
           missing one is filled from this market's own vacancies. */
        if ($spaceId !== null) {
            $space = Database::one(
                "SELECT id, space_code, status FROM market_spaces
                  WHERE id = ? AND market_id = ? LIMIT 1",
                [$spaceId, $marketId]
            );
            if (!$space) {
                badRequest('That pitch is not in ' . $market['name'] . '. Choose a pitch from the market you are offering.');
            }
            if ($space['status'] !== 'vacant') {
                badRequest(sprintf(
                    '%s is already %s. Choose a vacant pitch.',
                    $space['space_code'], $space['status']
                ));
            }
        } else {
            $space = Database::one(
                "SELECT id, space_code, status FROM market_spaces
                  WHERE market_id = ? AND status = 'vacant'
                  ORDER BY space_code LIMIT 1",
                [$marketId]
            );
            if (!$space) {
                badRequest($market['name'] . ' has no vacant pitch to offer, though its count says otherwise. The register needs reconciling.');
            }
            $spaceId = (int) $space['id'];
        }

        /* Duplicate open offer for the same vendor? */
        $dupe = Database::one(
            "SELECT reference FROM allocations
              WHERE vendor_id = ? AND market_id = ?
                AND status IN ('proposed','offered','accepted') LIMIT 1",
            [$vendorId, $marketId]
        );
        if ($dupe) {
            badRequest('An allocation for this vendor to this market already exists (' . $dupe['reference'] . ').');
        }

        /* Re-score at the moment of offering so the record is self-justifying. */
        $criteria = RelocationEngine::criteriaFor($vendor, $market);
        $total = 0.0; $w = 0.0;
        foreach ($criteria as $c) { $total += $c['raw'] * $c['weight']; $w += $c['weight']; }
        $total = $w > 0 ? $total / $w : 0.0;

        $distance = round(RelocationEngine::haversineKm(
            (float) ($vendor['current_lat'] ?? $vendor['lat']),
            (float) ($vendor['current_lng'] ?? $vendor['lng']),
            (float) $market['lat'], (float) $market['lng']
        ), 2);

        $reference = 'ALC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $expires   = date('Y-m-d', strtotime('+14 days'));

        $rank = 1 + (int) Database::value(
            'SELECT COUNT(*) FROM allocations WHERE vendor_id = ? AND total_score > ?',
            [$vendorId, $total]
        );

        /* Reason text, generated the same way the interface shows it. */
        $ranked = $criteria;
        usort($ranked, static fn($a, $b) => ($b['raw'] * $b['weight']) <=> ($a['raw'] * $a['weight']));
        $reasons = [];
        foreach (array_slice($ranked, 0, 3) as $c) {
            $reasons[] = $c['label'] . ': ' . $c['note'];
        }
        $reasons[] = sprintf('Weighted total %.1f out of 100 across six criteria; %s km from the current pitch.',
            $total, $distance);
        $reasonText = implode("\n", $reasons);

        $allocId = Database::transaction(static function () use (
            $vendorId, $marketId, $spaceId, $total, $criteria, $distance,
            $rank, $reference, $expires, $reasonText
        ): int {
            $id = Database::insert('allocations', [
                'reference'       => $reference,
                'vendor_id'       => $vendorId,
                'market_id'       => $marketId,
                'market_space_id' => $spaceId,
                'distance_km'     => $distance,
                'total_score'     => round($total, 2),
                'score_breakdown' => json_encode($criteria, JSON_UNESCAPED_UNICODE),
                'reasoning'       => $reasonText,
                'rank'            => $rank,
                'status'          => 'offered',
                'offer_expires'   => $expires,
                'created_by'      => Auth::id(),
            ]);

            // Reserve the pitch so it cannot be offered twice. $spaceId is
            // always resolved by now, and always belongs to $marketId, so this
            // reservation and the vacancy decrement below describe the same
            // market. That was not true before: the pitch could be elsewhere,
            // or absent entirely.
            Database::run(
                "UPDATE market_spaces SET status = 'reserved' WHERE id = ? AND status = 'vacant'",
                [$spaceId]
            );

            Database::run(
                "UPDATE vendors SET status = 'offered-space' WHERE id = ? AND status IN ('surveyed','notified')",
                [$vendorId]
            );

            // Hold the pitch against the market's advertised vacant count.
            Database::run('UPDATE markets SET vacant_spaces = GREATEST(0, vacant_spaces - 1) WHERE id = ?', [$marketId]);

            return $id;
        });

        Auth::log('offer-space', 'allocations', $allocId,
            $reference . ' — ' . $vendor['full_name'] . ' → ' . $market['name'] . ' (score ' . round($total, 1) . ')');

        jsonOut([
            'ok' => true,
            'message' => 'Space offered to ' . $vendor['full_name'] . ' at ' . $market['name'] . '.',
            'reference' => $reference,
            'allocation_id' => $allocId,
            'score' => round($total, 1),
            'expires' => fmtDate($expires),
        ]);
    }

    /* ---------------- Vendor accepts ---------------- */
    case 'accept': {
        requirePerm('allocate');
        $id = (int) ($_POST['allocation_id'] ?? 0);
        $a  = Database::one(
            'SELECT a.*, m.name AS market_name, m.lat, m.lng, m.vacant_spaces,
                    v.full_name, v.current_zone_id
               FROM allocations a
               JOIN markets m ON m.id = a.market_id
               JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            badRequest('That allocation does not exist.', 404);
        }
        if (!in_array($a['status'], ['proposed', 'offered'], true)) {
            badRequest('This offer is ' . humanise($a['status']) . ' and can no longer be accepted.');
        }

        Database::transaction(static function () use ($id, $a): void {
            Database::run(
                "UPDATE allocations SET status = 'accepted', decided_at = NOW() WHERE id = ?", [$id]);
            Database::run("UPDATE vendors SET status = 'relocated' WHERE id = ?", [(int) $a['vendor_id']]);
            // Placed in this market, so its chairman becomes responsible for
            // them from here on. Written inside the transaction so the vendor
            // can never be relocated without a chairman if one exists.
            syncVendorChairman((int) $a['vendor_id'], (int) $a['market_id']);

            // The pitch leaves the vacancy pool exactly once: when the offer
            // was made. Accepting only moves reserved -> occupied and counts
            // the vendor against the market's registration.
            if ($a['market_space_id'] !== null) {
                Database::run("UPDATE market_spaces SET status = 'occupied' WHERE id = ?",
                    [(int) $a['market_space_id']]);
            }
            Database::run("UPDATE markets SET registered_vendors = registered_vendors + 1 WHERE id = ?",
                [(int) $a['market_id']]);
        });

        Auth::log('accept-allocation', 'allocations', $id, $a['reference'] . ' accepted by ' . $a['full_name']);
        jsonOut(['ok' => true, 'message' => 'Allocation accepted. ' . $a['full_name'] . ' is recorded at ' . $a['market_name'] . '.']);
    }

    /* ---------------- Vendor declines ---------------- */
    case 'decline': {
        requirePerm('allocate');
        $id     = (int) ($_POST['allocation_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $a = Database::one(
            'SELECT a.*, m.name AS market_name, v.full_name FROM allocations a
               JOIN markets m ON m.id = a.market_id JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            badRequest('That allocation does not exist.', 404);
        }
        if (!in_array($a['status'], ['proposed', 'offered'], true)) {
            badRequest('This offer is ' . humanise($a['status']) . '.');
        }

        Database::transaction(static function () use ($id, $reason, $a): void {
            Database::run(
                "UPDATE allocations SET status = 'declined', decided_at = NOW(),
                        reasoning = CONCAT(COALESCE(reasoning,''), '\nDeclined: ', ?) WHERE id = ?",
                [$reason !== '' ? $reason : 'No reason given', $id]
            );
            Database::run("UPDATE vendors SET status = 'declined' WHERE id = ?", [(int) $a['vendor_id']]);
            // They declined the space, so they are not in that market and its
            // chairman is not responsible for them.
            clearVendorChairman((int) $a['vendor_id']);

            // The pitch and the space return to the vacancy pool.
            if ($a['market_space_id'] !== null) {
                Database::run("UPDATE market_spaces SET status = 'vacant' WHERE id = ?",
                    [(int) $a['market_space_id']]);
            }
            Database::run("UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?",
                [(int) $a['market_id']]);
        });

        Auth::log('decline-allocation', 'allocations', $id, $a['reference'] . ' declined');
        jsonOut(['ok' => true, 'message' => 'Offer recorded as declined.' . ($reason ? ' Reason: ' . $reason : '')]);
    }

    /* ---------------- Withdraw ---------------- */
    case 'withdraw': {
        requirePerm('manage');
        $id     = (int) ($_POST['allocation_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $a = Database::one('SELECT * FROM allocations WHERE id = ? LIMIT 1', [$id]);
        if (!$a) {
            badRequest('That allocation does not exist.', 404);
        }
        if (in_array($a['status'], ['withdrawn', 'expired'], true)) {
            badRequest('Already ' . humanise($a['status']) . '.');
        }

        Database::transaction(static function () use ($id, $reason, $a): void {
            Database::run(
                "UPDATE allocations SET status = 'withdrawn', decided_at = NOW(),
                        reasoning = CONCAT(COALESCE(reasoning,''), '\nWithdrawn: ', ?) WHERE id = ?",
                [$reason !== '' ? $reason : 'Withdrawn by the Authority', $id]
            );
            if ($a['market_space_id'] !== null) {
                Database::run("UPDATE market_spaces SET status = 'vacant' WHERE id = ?",
                    [(int) $a['market_space_id']]);
            }
            Database::run("UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?",
                [(int) $a['market_id']]);
            Database::run("UPDATE vendors SET status = 'notified' WHERE id = ? AND status = 'offered-space'",
                [(int) $a['vendor_id']]);
            clearVendorChairman((int) $a['vendor_id']);
        });

        Auth::log('withdraw-allocation', 'allocations', $id, $a['reference'] . ' withdrawn');
        jsonOut(['ok' => true, 'message' => 'Allocation withdrawn. The space is back in the vacancy pool.']);
    }

    /* ---------------- Confirm the vendor is trading in place ---------------- */
    case 'occupy': {
        requirePerm('manage');
        $id = (int) ($_POST['allocation_id'] ?? 0);
        $a = Database::one(
            'SELECT a.*, m.name AS market_name, v.full_name FROM allocations a
               JOIN markets m ON m.id = a.market_id JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            badRequest('That allocation does not exist.', 404);
        }
        if ($a['status'] !== 'accepted') {
            badRequest('Only an accepted allocation can be confirmed as occupied.');
        }

        Database::run("UPDATE allocations SET status = 'occupied', decided_at = NOW() WHERE id = ?", [$id]);
        // Idempotent on an already-placed vendor, and it repairs a vendor
        // placed before their market had a chairman recorded.
        syncVendorChairman((int) $a['vendor_id'], (int) $a['market_id']);
        Auth::log('occupy-allocation', 'allocations', $id, $a['reference'] . ' confirmed occupied');
        jsonOut(['ok' => true, 'message' => $a['full_name'] . ' confirmed trading at ' . $a['market_name'] . '.']);
    }

    default:
        badRequest('Unknown action.');
}