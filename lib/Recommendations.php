<?php
/**
 * KCCA VRSP — Recommendation service.
 *
 * Generates ranked location recommendations from the ReachModel, and
 * handles the approve / reject workflow over them.
 *
 * Duplicate work is prevented at three levels:
 *
 *   1. A unique index on the recommendations table stops two live
 *      recommendations for the same vendor and market.
 *   2. A unique index on allocations stops two live allocations for the
 *      same vendor, and two for the same pitch.
 *   3. This service checks both again before writing, so the user gets a
 *      clear message rather than a database error.
 */

declare(strict_types=1);

final class RecommendationService
{
    /* =================================================================
     * GENERATION
     * ================================================================= */

    /**
     * Rank candidate markets for one vendor.
     *
     * @param array  $vendor   vendors row
     * @param array  $markets  candidate markets with division population
     * @param int    $limit    how many to keep
     * @return array<int,array> ranked recommendations
     */
    public static function rank(array $vendor, array $markets, int $limit = 5): array
    {
        if ($markets === []) {
            return [];
        }

        $category = self::categoryFor((string) $vendor['business_category']);
        if ($category === null) {
            return [];
        }

        /* One footfall pass per market, cached for the whole run. */
        $footCache = [];
        $results   = [];

        foreach ($markets as $m) {
            $mid = (int) $m['id'];

            if (!isset($footCache[$mid])) {
                $footCache[$mid] = ReachModel::footfall($mid, $m);
            }
            $foot = $footCache[$mid];

            $context = [
                'same_trade'         => self::sameTradeCount($mid, (string) $vendor['business_category']),
                'monthly_rent'       => self::monthlyRent($mid),
                'accessibility'      => self::accessibility($m),
                'accessibility_note' => self::accessibilityNote($m),
            ];

            $scored = ReachModel::score($m, $category, $vendor, $foot, $context);

            /* A location the Authority cannot lawfully use is not a
               recommendation; it is excluded outright. */
            if ($scored['blocked']) {
                continue;
            }

            $results[] = [
                'market'    => $m,
                'category'  => $category,
                'score'     => $scored,
                'distance'  => RelocationEngine::haversineKm(
                    RelocationEngine::vendorLat($vendor),
                    RelocationEngine::vendorLng($vendor),
                    (float) $m['lat'], (float) $m['lng']
                ),
            ];
        }

        usort($results, static fn($a, $b) => $b['score']['reach_score'] <=> $a['score']['reach_score']);

        $out = [];
        foreach (array_slice($results, 0, $limit) as $i => $r) {
            $r['rank'] = $i + 1;
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Persist a ranked set as recommendation rows.
     *
     * @return array<int,int> created recommendation ids keyed by rank
     */
    public static function persist(array $ranked, int $vendorId, int $spaceId = 0): array
    {
        $created = [];

        Database::transaction(static function () use ($ranked, $vendorId, $spaceId, &$created): void {
            /* A vendor's earlier proposals are superseded by a fresh run,
               otherwise the approvals screen fills with stale options. */
            Database::run(
                "UPDATE recommendations SET status = 'superseded'
                  WHERE vendor_id = ? AND status = 'proposed'",
                [$vendorId]
            );

            foreach ($ranked as $r) {
                $m = $r['market'];
                $s = $r['score'];

                $reference = 'REC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                $id = Database::insert('recommendations', [
                    'reference'        => $reference,
                    'vendor_id'        => $vendorId,
                    'market_id'        => (int) $m['id'],
                    'space_id'         => $spaceId > 0 ? $spaceId : null,
                    'rank'             => (int) $r['rank'],
                    'reach_score'      => $s['reach_score'],
                    'viability_score'  => $s['viability_score'],
                    'footfall_score'   => $s['footfall_score'],
                    'confidence'       => $s['confidence'],
                    'projected_customers_day' => $s['projected_customers_day'],
                    'projected_revenue_ugx'  => $s['projected_revenue_ugx'],
                    'distance_km'      => round($r['distance'], 2),
                    'model_version'    => $s['model_version'],
                    'factors_json'     => json_encode($s['factors'], JSON_UNESCAPED_UNICODE),
                    'explanation'      => $s['explanation'],
                    'warnings_json'    => json_encode($s['warnings'], JSON_UNESCAPED_UNICODE),
                    'status'           => 'proposed',
                    'expires_on'       => date('Y-m-d', strtotime('+14 days')),
                    'generated_by'     => Auth::id() ?: null,
                ]);

                $created[(int) $r['rank']] = $id;

                self::logEvent(null, $vendorId, (int) $m['id'], null, 'proposed', 'created',
                    'Recommendation ' . $reference . ' generated (rank ' . $r['rank'] . ')');
            }
        });

        return $created;
    }

    /* =================================================================
     * APPROVAL WORKFLOW
     * ================================================================= */

    /**
     * Approve a recommendation.
     *
     * Approval reserves the pitch and creates the allocation in one
     * transaction. If the pitch is already gone the whole thing rolls
     * back, so a stall is never promised twice.
     */
    public static function approve(int $recommendationId, int $spaceId, string $note = ''): array
    {
        $rec = Database::one(
            'SELECT r.*, v.full_name, v.current_zone_id, m.name AS market_name, m.vacant_spaces
               FROM recommendations r
               JOIN vendors v ON v.id = r.vendor_id
               JOIN markets m ON m.id = r.market_id
              WHERE r.id = ? LIMIT 1',
            [$recommendationId]
        );

        if (!$rec) {
            throw new RuntimeException('That recommendation does not exist.');
        }
        if ($rec['status'] !== 'proposed') {
            throw new RuntimeException(
                'This recommendation is ' . humanise($rec['status']) . ' and can no longer be approved.'
            );
        }

        /* Does the vendor already hold a live allocation? */
        $existing = Database::one(
            "SELECT reference FROM allocations
              WHERE vendor_id = ? AND status IN ('proposed','offered','accepted','occupied') LIMIT 1",
            [(int) $rec['vendor_id']]
        );
        if ($existing) {
            throw new RuntimeException(sprintf(
                '%s already holds a live allocation (%s). Close it before approving another.',
                $rec['full_name'], $existing['reference']
            ));
        }

        /* Is the pitch free? */
        $space = $spaceId > 0
            ? Database::one("SELECT * FROM market_spaces WHERE id = ? AND market_id = ? LIMIT 1",
                            [$spaceId, (int) $rec['market_id']])
            : null;

        if ($spaceId > 0) {
            if (!$space) {
                throw new RuntimeException('That pitch does not belong to this market.');
            }
            if ($space['status'] !== 'vacant') {
                throw new RuntimeException(sprintf(
                    'Pitch %s is already %s. Reload the market and choose another.',
                    $space['space_code'], $space['status']
                ));
            }
        }

        $reference = 'ALC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $factors   = json_decode((string) $rec['factors_json'], true) ?: [];

        $allocId = Database::transaction(static function () use (
            $rec, $spaceId, $space, $reference, $note, $factors
        ): int {
            $id = Database::insert('allocations', [
                'reference'         => $reference,
                'vendor_id'         => (int) $rec['vendor_id'],
                'market_id'         => (int) $rec['market_id'],
                'market_space_id'   => $spaceId > 0 ? $spaceId : null,
                'distance_km'       => $rec['distance_km'],
                'total_score'       => $rec['reach_score'],
                'score_breakdown'   => json_encode($factors, JSON_UNESCAPED_UNICODE),
                'reasoning'         => $rec['explanation'],
                'rank'              => (int) $rec['rank'],
                'status'            => 'offered',
                'recommendation_id' => (int) $rec['id'],
                'source'            => 'model',
                'offer_expires'     => date('Y-m-d', strtotime('+14 days')),
                'created_by'        => Auth::id() ?: null,
            ]);

            if ($spaceId > 0) {
                Database::run(
                    "UPDATE market_spaces SET status = 'reserved' WHERE id = ? AND status = 'vacant'",
                    [$spaceId]
                );
            }
            Database::run(
                'UPDATE markets SET vacant_spaces = GREATEST(0, vacant_spaces - 1) WHERE id = ?',
                [(int) $rec['market_id']]
            );

            Database::run(
                "UPDATE recommendations
                    SET status = 'allocated', decided_by = ?, decided_at = NOW(), decision_note = ?
                  WHERE id = ?",
                [Auth::id() ?: null, $note !== '' ? $note : 'Approved', (int) $rec['id']]
            );

            Database::run(
                "UPDATE vendors SET status = 'offered-space'
                  WHERE id = ? AND status IN ('surveyed','notified')",
                [(int) $rec['vendor_id']]
            );

            return $id;
        });

        self::logEvent($allocId, (int) $rec['vendor_id'], (int) $rec['market_id'], null, 'offered', 'offered',
            'Approved recommendation ' . $rec['reference'] . ' → ' . $rec['market_name']
            . ($space ? ' pitch ' . $space['space_code'] : '') . ' (' . $reference . ')');

        Auth::log('approve-recommendation', 'recommendations', $recommendationId,
            $rec['reference'] . ' approved → ' . $reference);

        return [
            'allocation_id' => $allocId,
            'reference'     => $reference,
            'message'       => sprintf(
                'Approved. %s has been offered space at %s%s.',
                $rec['full_name'], $rec['market_name'],
                $space ? ' — pitch ' . $space['space_code'] : ''
            ),
        ];
    }

    /** Reject a recommendation, with a reason the model cannot see. */
    public static function reject(int $recommendationId, string $note): array
    {
        if (trim($note) === '') {
            throw new RuntimeException('A rejection must give a reason.');
        }

        $rec = Database::one('SELECT * FROM recommendations WHERE id = ? LIMIT 1', [$recommendationId]);
        if (!$rec) {
            throw new RuntimeException('That recommendation does not exist.');
        }
        if ($rec['status'] !== 'proposed') {
            throw new RuntimeException('This recommendation is ' . humanise($rec['status']) . '.');
        }

        Database::run(
            "UPDATE recommendations
                SET status = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ?
              WHERE id = ?",
            [Auth::id() ?: null, $note, $recommendationId]
        );

        self::logEvent(null, (int) $rec['vendor_id'], (int) $rec['market_id'],
            (string) $rec['status'], 'rejected', 'rejected', $note);

        Auth::log('reject-recommendation', 'recommendations', $recommendationId,
            $rec['reference'] . ' rejected: ' . $note);

        return ['message' => 'Recommendation rejected. The reason is recorded against the vendor.'];
    }

    /* =================================================================
     * RELOCATION TRACKING
     * ================================================================= */

    /** Append to the relocation history. */
    public static function logEvent(
        ?int $allocationId,
        ?int $vendorId,
        ?int $marketId,
        ?string $fromStatus,
        string $toStatus,
        string $eventType,
        string $note = ''
    ): int {
        return Database::insert('relocation_events', [
            'allocation_id' => $allocationId,
            'vendor_id'     => $vendorId,
            'market_id'     => $marketId,
            'from_status'   => $fromStatus,
            'to_status'     => $toStatus,
            'event_type'    => $eventType,
            'actor'         => (string) (Auth::check() ? (Auth::user()['full_name'] ?? 'system') : 'system'),
            'note'          => $note !== '' ? $note : null,
        ]);
    }

    /**
     * Full history for one vendor, newest first, with the recommendation
     * and allocation rows joined in.
     */
    public static function historyForVendor(int $vendorId): array
    {
        return Database::all(
            "SELECT e.*, a.reference AS allocation_reference, a.rank, a.total_score,
                    m.name AS market_name
               FROM relocation_events e
               LEFT JOIN allocations a ON a.id = e.allocation_id
               LEFT JOIN markets m ON m.id = e.market_id
              WHERE e.vendor_id = ?
              ORDER BY e.occurred_at DESC, e.id DESC
              LIMIT 100",
            [$vendorId]
        );
    }

    /* =================================================================
     * HELPERS
     * ================================================================= */

    /** Map the vendors.business_category enum onto a trade_categories row. */
    public static function categoryFor(string $vendorCategory): ?array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Database::all('SELECT * FROM trade_categories') as $c) {
                $map[$c['code']] = $c;
            }
        }
        return $map[$vendorCategory] ?? null;
    }

    /**
     * How many vendors at this market already sell the same thing.
     *
     * Reads the recorded trade mix, not just accepted allocations: a
     * market that has traded clothing for twenty years has competition
     * whether or not this system placed those vendors there.
     */
    private static function sameTradeCount(int $marketId, string $businessCategory): int
    {
        static $cache = [];
        $key = $marketId . '|' . $businessCategory;
        if (!isset($cache[$key])) {
            $cache[$key] = (int) Database::value(
                'SELECT vendor_count FROM market_trade_mix
                  WHERE market_id = ? AND business_category = ? LIMIT 1',
                [$marketId, $businessCategory]
            );
        }
        return $cache[$key];
    }

    /** Rent band for the vacant pitches at this market. */
    private static function monthlyRent(int $marketId): int
    {
        static $cache = [];
        if (!isset($cache[$marketId])) {
            $cache[$marketId] = (int) Database::value(
                "SELECT COALESCE(ROUND(AVG(monthly_rent_ugx)), 0) FROM market_spaces
                  WHERE market_id = ? AND status = 'vacant'",
                [$marketId]
            );
        }
        return $cache[$marketId];
    }

    /**
     * Accessibility proxy from real published data: division population
     * density, and whether the market has a transport stage.
     */
    private static function accessibility(array $m): float
    {
        $area = max(1.0, (float) ($m['area_sqkm'] ?? 15));
        $pop  = (int) ($m['population'] ?? 100000);
        $density = $pop / $area;                 // people per km²
        $densityScore = min(100.0, ($density / 4000) * 100);

        $transport = $m['transport_stand'] ? 25.0 : 0.0;

        return max(0.0, min(100.0, $densityScore * 0.75 + $transport));
    }

    private static function accessibilityNote(array $m): string
    {
        $area = max(1.0, (float) ($m['area_sqkm'] ?? 15));
        $pop  = (int) ($m['population'] ?? 0);
        $density = (int) round($pop / $area);

        return sprintf(
            'Division density about %s people per km² (UBOS 2024 population over KCCA area)%s.',
            num($density),
            $m['transport_stand'] ? '; served by ' . $m['transport_stand'] : '; no transport stage recorded'
        );
    }

    /**
     * Candidate markets for a vendor: those with space, not blocked for
     * this trade, joined with the division population the model needs.
     */
    public static function candidateMarkets(int $limit = 300): array
    {
        return Database::all(
            "SELECT m.*, d.code AS div_code, d.name AS division_name,
                    d.population, d.area_sqkm
               FROM markets m
               JOIN divisions d ON d.id = m.division_id
              WHERE m.vacant_spaces > 0
                AND m.status IN ('operational','upgrading')
              ORDER BY m.vacant_spaces DESC
              LIMIT " . (int) $limit
        );
    }
}