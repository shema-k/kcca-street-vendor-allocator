<?php
/**
 * POST /api/recommendation_action.php
 *   action=generate   vendor_id [, division]
 *   action=approve    recommendation_id, market_space_id, note
 *   action=reject     recommendation_id, reason
 *   action=explain    recommendation_id
 *
 * The recommendation workflow. Approval is the point at which a model
 * output becomes a promise to a trader, so it is deliberately the most
 * heavily guarded path in the system.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');

/** Turn a domain refusal into a clear message rather than a stack trace. */
function refuse(string $message, int $code = 400): never
{
    jsonOut(['ok' => false, 'error' => $message], $code);
}

switch ($action) {

    /* ---------------- Generate ---------------- */
    case 'generate': {
        requirePerm('allocate');

        $vendorId = (int) ($_POST['vendor_id'] ?? 0);
        $vendor   = vendorForMatching($vendorId);
        if (!$vendor) {
            refuse('That vendor is not in the register.', 404);
        }

        $division = (string) ($_POST['division'] ?? '');
        $markets  = RecommendationService::candidateMarkets();
        if ($division !== '') {
            $markets = array_values(array_filter($markets, static fn($m) => $m['div_code'] === $division));
        }

        $limit   = (int) Settings::int('max_recommendations_per_vendor', 5);
        $ranked  = RecommendationService::rank($vendor, $markets, $limit);

        if ($ranked === []) {
            refuse('No location could be recommended. Either every market is full, or a land-use '
                 . 'restriction blocks this trade everywhere the Authority has space.', 409);
        }

        try {
            $created = RecommendationService::persist($ranked, $vendorId);
        } catch (Throwable $e) {
            refuse('The recommendations could not be saved: ' . $e->getMessage(), 500);
        }

        Auth::log('generate-recommendations', 'vendors', $vendorId,
            count($ranked) . ' locations ranked for ' . $vendor['full_name']);

        jsonOut([
            'ok'      => true,
            'message' => sprintf('%d location%s recommended for %s.',
                count($ranked), count($ranked) === 1 ? ' was' : 's were', $vendor['full_name']),
            'created' => count($created),
            'options' => array_map(static fn(array $r): array => [
                'rank'      => $r['rank'],
                'market_id' => (int) $r['market']['id'],
                'market'    => $r['market']['name'],
                'score'     => $r['score']['reach_score'],
                'viability' => $r['score']['viability_score'],
                'confidence'=> $r['score']['confidence'],
                'customers' => $r['score']['projected_customers_day'],
                'revenue'   => $r['score']['projected_revenue_ugx'],
                'distance'  => round($r['distance'], 2),
                'blocked'   => $r['score']['blocked'],
                'explanation' => $r['score']['explanation'],
                'warnings'  => $r['score']['warnings'],
            ], $ranked),
        ]);
    }

    /* ---------------- Approve ---------------- */
    case 'approve': {
        requirePerm('allocate');

        $recId   = (int) ($_POST['recommendation_id'] ?? 0);
        $spaceId = (int) ($_POST['market_space_id'] ?? 0);
        $note    = trim((string) ($_POST['note'] ?? ''));

        try {
            $result = RecommendationService::approve($recId, $spaceId, $note);
        } catch (RuntimeException $e) {
            /* A refusal here is a real, explainable business rule: the
               pitch has gone, the vendor already has space, and so on. */
            refuse($e->getMessage(), 409);
        } catch (Throwable $e) {
            refuse('The approval could not be completed. Nothing was changed.', 500);
        }

        jsonOut(['ok' => true] + $result);
    }

    /* ---------------- Reject ---------------- */
    case 'reject': {
        requirePerm('allocate');

        $recId  = (int) ($_POST['recommendation_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            refuse('A rejection must give a reason. The trader and Internal Audit both need it.', 422);
        }
        if (strlen($reason) > 500) {
            refuse('The reason must be 500 characters or fewer.', 422);
        }

        try {
            $result = RecommendationService::reject($recId, $reason);
        } catch (RuntimeException $e) {
            refuse($e->getMessage(), 409);
        } catch (Throwable $e) {
            refuse('The rejection could not be recorded. Nothing was changed.', 500);
        }

        jsonOut(['ok' => true] + $result);
    }

    /* ---------------- Explain one recommendation ---------------- */
    case 'explain': {
        requirePerm('view');

        $recId = (int) ($_POST['recommendation_id'] ?? 0);
        $rec = Database::one(
            'SELECT r.*, m.name AS market_name, v.full_name, v.business_category,
                    d.name AS division_name
               FROM recommendations r
               JOIN markets m ON m.id = r.market_id
               JOIN vendors v ON v.id = r.vendor_id
               JOIN divisions d ON d.id = v.division_id
              WHERE r.id = ? LIMIT 1',
            [$recId]
        );

        if (!$rec) {
            refuse('That recommendation does not exist.', 404);
        }

        jsonOut([
            'ok'      => true,
            'record'  => [
                'reference'   => $rec['reference'],
                'vendor'      => $rec['full_name'],
                'trade'       => humanise($rec['business_category']),
                'market'      => $rec['market_name'],
                'division'    => $rec['division_name'],
                'rank'        => (int) $rec['rank'],
                'status'      => humanise($rec['status']),
                'model'       => $rec['model_version'],
                'explanation' => $rec['explanation'],
            ],
            'scores'  => [
                'reach'     => (float) $rec['reach_score'],
                'viability' => (float) $rec['viability_score'],
                'footfall'  => (float) $rec['footfall_score'],
                'confidence'=> (float) $rec['confidence'],
            ],
            'projection' => [
                'customers_per_day' => (float) $rec['projected_customers_day'],
                'revenue_per_month' => (float) $rec['projected_revenue_ugx'],
                'distance_km'       => (float) $rec['distance_km'],
            ],
            'factors' => json_decode((string) $rec['factors_json'], true) ?: [],
            'weights' => ReachModel::WEIGHTS,
            'warnings'=> json_decode((string) $rec['warnings_json'], true) ?: [],
        ]);
    }

    /* ---------------- Accept / decline / withdraw an allocation ---------------- */
    case 'accept': {
        requirePerm('allocate');
        $id = (int) ($_POST['allocation_id'] ?? 0);
        $a  = Database::one(
            'SELECT a.*, m.name AS market_name, v.full_name
               FROM allocations a
               JOIN markets m ON m.id = a.market_id
               JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            refuse('That allocation does not exist.', 404);
        }
        if (!in_array($a['status'], ['proposed', 'offered'], true)) {
            refuse('This offer is ' . humanise($a['status']) . ' and can no longer be accepted.', 409);
        }

        try {
            Database::transaction(static function () use ($id, $a): void {
                Database::run("UPDATE allocations SET status = 'accepted', decided_at = NOW() WHERE id = ?", [$id]);

                if ($a['market_space_id'] !== null) {
                    Database::run("UPDATE market_spaces SET status = 'occupied' WHERE id = ?",
                        [(int) $a['market_space_id']]);
                }
                Database::run("UPDATE vendors SET status = 'relocated' WHERE id = ?", [(int) $a['vendor_id']]);
                Database::run("UPDATE markets SET registered_vendors = registered_vendors + 1 WHERE id = ?",
                    [(int) $a['market_id']]);
                if ($a['recommendation_id'] !== null) {
                    Database::run("UPDATE recommendations SET status = 'allocated' WHERE id = ?",
                        [(int) $a['recommendation_id']]);
                }
            });
        } catch (Throwable $e) {
            refuse('The acceptance could not be recorded. Nothing was changed.', 500);
        }

        RecommendationService::logEvent($id, (int) $a['vendor_id'], (int) $a['market_id'],
            (string) $a['status'], 'accepted', 'accepted', 'Offer accepted at ' . $a['market_name']);

        Auth::log('accept-allocation', 'allocations', $id, $a['reference'] . ' accepted');

        jsonOut(['ok' => true, 'message' => sprintf(
            'Accepted. %s is recorded at %s.', $a['full_name'], $a['market_name'])]);
    }

    case 'decline': {
        requirePerm('allocate');
        $id     = (int) ($_POST['allocation_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            refuse('A decline must give a reason.', 422);
        }

        $a = Database::one(
            'SELECT a.*, m.name AS market_name, v.full_name FROM allocations a
               JOIN markets m ON m.id = a.market_id JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            refuse('That allocation does not exist.', 404);
        }
        if (!in_array($a['status'], ['proposed', 'offered'], true)) {
            refuse('This offer is ' . humanise($a['status']) . '.', 409);
        }

        Database::transaction(static function () use ($id, $reason, $a): void {
            Database::run(
                "UPDATE allocations SET status = 'declined', decided_at = NOW(),
                        reasoning = CONCAT(COALESCE(reasoning,''), '\nDeclined: ', ?) WHERE id = ?",
                [$reason, $id]
            );
            Database::run("UPDATE vendors SET status = 'declined' WHERE id = ?", [(int) $a['vendor_id']]);

            /* The pitch and the space go back into the pool. */
            if ($a['market_space_id'] !== null) {
                Database::run("UPDATE market_spaces SET status = 'vacant' WHERE id = ?",
                    [(int) $a['market_space_id']]);
            }
            Database::run("UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?",
                [(int) $a['market_id']]);
        });

        RecommendationService::logEvent($id, (int) $a['vendor_id'], (int) $a['market_id'],
            (string) $a['status'], 'declined', 'declined', $reason);

        Auth::log('decline-allocation', 'allocations', $id, $a['reference'] . ' declined');

        jsonOut(['ok' => true, 'message' => 'Recorded as declined: ' . $reason]);
    }

    case 'withdraw': {
        requirePerm('manage');
        $id     = (int) ($_POST['allocation_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: 'Withdrawn by the Authority';

        $a = Database::one(
            'SELECT a.*, m.name AS market_name, v.full_name FROM allocations a
               JOIN markets m ON m.id = a.market_id JOIN vendors v ON v.id = a.vendor_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) {
            refuse('That allocation does not exist.', 404);
        }
        if (in_array($a['status'], ['withdrawn', 'expired'], true)) {
            refuse('Already ' . humanise($a['status']) . '.', 409);
        }

        Database::transaction(static function () use ($id, $reason, $a): void {
            Database::run(
                "UPDATE allocations SET status = 'withdrawn', decided_at = NOW(),
                        reasoning = CONCAT(COALESCE(reasoning,''), '\nWithdrawn: ', ?) WHERE id = ?",
                [$reason, $id]
            );
            if ($a['market_space_id'] !== null) {
                Database::run("UPDATE market_spaces SET status = 'vacant' WHERE id = ?",
                    [(int) $a['market_space_id']]);
            }
            Database::run("UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?",
                [(int) $a['market_id']]);
            Database::run(
                "UPDATE vendors SET status = 'notified' WHERE id = ? AND status = 'offered-space'",
                [(int) $a['vendor_id']]);
        });

        RecommendationService::logEvent($id, (int) $a['vendor_id'], (int) $a['market_id'],
            (string) $a['status'], 'withdrawn', 'withdrawn', $reason);

        Auth::log('withdraw-allocation', 'allocations', $id, $a['reference'] . ' withdrawn');

        jsonOut(['ok' => true, 'message' => 'Allocation withdrawn. The space is back in the vacancy pool.']);
    }

    case 'occupy': {
        requirePerm('manage');
        $id = (int) ($_POST['allocation_id'] ?? 0);
        $a  = Database::one('SELECT * FROM allocations WHERE id = ?', [$id]);
        if (!$a) {
            refuse('That allocation does not exist.', 404);
        }
        if ($a['status'] !== 'accepted') {
            refuse('Only an accepted allocation can be confirmed as occupied.', 409);
        }

        Database::run("UPDATE allocations SET status = 'occupied', decided_at = NOW() WHERE id = ?", [$id]);
        RecommendationService::logEvent($id, (int) $a['vendor_id'], (int) $a['market_id'],
            'accepted', 'occupied', 'relocated', 'Confirmed trading in place');

        Auth::log('occupy-allocation', 'allocations', $id, $a['reference']);

        jsonOut(['ok' => true, 'message' => 'Confirmed trading in place.']);
    }

    default:
        refuse('Unknown action.', 400);
}