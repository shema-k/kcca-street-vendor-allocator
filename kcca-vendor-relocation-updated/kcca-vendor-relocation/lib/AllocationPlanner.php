<?php
/**
 * KCCA VRSP — AI Allocation Planner.
 *
 * The relocation engine answers "which market suits this trader?" one trader
 * at a time. That is the right question for an interview and the wrong one
 * for a clearance: ten traders can each be told that Nakawa Market is their
 * best match, and Nakawa has four pitches. Handled one by one, the system
 * promises the same space repeatedly and an officer finds out at the counter.
 *
 * This planner answers the question the Authority actually has: given THIS
 * set of traders and THIS set of empty pitches, who goes where so that the
 * whole group is placed as well as it can be?
 *
 * How it decides
 * --------------
 *  1. WHO GOES FIRST.  Traders are ranked by need: how badly their current
 *     site obstructs the city (the congestion engine) and how vulnerable
 *     they are (the equity criterion). The most pressing cases enter the
 *     batch first, so a shortage of space is felt by the least urgent
 *     traders, not by whoever happened to be registered last.
 *  2. HOW WELL EACH FITS.  Every trader is scored against every market on
 *     the same six criteria the Matching screen uses, so there is one
 *     definition of "a good fit" in the system, not two.
 *  3. WHAT IS FORBIDDEN.  A market under a land-use restriction for that
 *     trade, or a fit below the floor the officer set, is not an option.
 *  4. THE BEST JOINT ASSIGNMENT.  The Hungarian method (Kuhn-Munkres) finds
 *     the assignment of traders to pitches with the highest total fit,
 *     respecting every market's capacity at once. It is an exact optimum
 *     for the scores supplied, not a heuristic: no other assignment of this
 *     batch scores higher.
 *
 * It is not a language model and does not pretend to be one. Every decision
 * decomposes into the six scores that produced it, which is what lets an
 * officer defend it to the trader, the Mayor and Internal Audit.
 *
 * Nothing here writes to the allocation tables. A plan becomes ordinary
 * recommendations (status "proposed") and goes through the same approval
 * workflow as every other recommendation.
 */

declare(strict_types=1);

final class AllocationPlanner
{
    /** Criterion order used for the score matrix. */
    private const KEYS = ['proximity', 'trade_fit', 'equity', 'viability', 'affordability', 'facilities'];

    /** Cost of leaving a trader unplaced rather than forcing a poor match. */
    private const COST_UNPLACED = 500.0;

    /** Cost of a pair that is not allowed at all. Always worse than unplaced. */
    private const COST_FORBIDDEN = 1000.0;

    public const MAX_BATCH = 200;

    /**
     * Allocation policies. Each is a set of weights over the same six
     * criteria, so changing policy changes what is valued, never what is
     * measured.
     */
    public const MODES = [
        'balanced' => [
            'tag'   => 'Standing policy',
            'label' => 'Balanced',
            'blurb' => 'The weights currently set in Settings. The Authority’s standing policy.',
            'weights' => null,
        ],
        'equity' => [
            'tag'   => 'Vulnerable first',
            'label' => 'Equity first',
            'blurb' => 'Weighs vulnerability most. Single parents, traders with disabilities and those with the least capital are settled before convenience.',
            'weights' => ['proximity' => 0.18, 'trade_fit' => 0.18, 'equity' => 0.36,
                          'viability' => 0.12, 'affordability' => 0.10, 'facilities' => 0.06],
        ],
        'proximity' => [
            'tag'   => 'Shortest move',
            'label' => 'Stay close',
            'blurb' => 'Weighs distance most. Keeps customers within reach so livelihoods survive the move.',
            'weights' => ['proximity' => 0.42, 'trade_fit' => 0.20, 'equity' => 0.14,
                          'viability' => 0.10, 'affordability' => 0.08, 'facilities' => 0.06],
        ],
        'livelihood' => [
            'tag'   => 'Best for the trade',
            'label' => 'Trade first',
            'blurb' => 'Weighs whether the pitch can actually support the trade: section, water, power and cold chain.',
            'weights' => ['proximity' => 0.14, 'trade_fit' => 0.34, 'equity' => 0.16,
                          'viability' => 0.12, 'affordability' => 0.10, 'facilities' => 0.14],
        ],
    ];

    /* =================================================================
     * Parameters
     * ================================================================= */

    /**
     * Clean the request into parameters the planner can trust.
     *
     * Applied to the query string and to the POST of the submit action alike,
     * so what is displayed and what is saved are computed from the same
     * values. A Division Mayor is confined to their own division here, on
     * the server, whatever the request says.
     */
    public static function params(array $in): array
    {
        $mode = (string) ($in['mode'] ?? 'balanced');
        if (!isset(self::MODES[$mode])) {
            $mode = 'balanced';
        }

        $batch = (int) ($in['batch'] ?? 60);
        $batch = max(5, min(self::MAX_BATCH, $batch));

        $floor = (int) ($in['floor'] ?? 45);
        $floor = max(20, min(80, $floor));

        $division = (string) ($in['division'] ?? '');
        $stay     = !empty($in['stay']) && (string) $in['stay'] !== '0';
        $zone     = max(0, (int) ($in['zone'] ?? 0));

        $lock = divisionLock();
        if ($lock !== null) {
            $division = $lock['code'];
            $stay     = true;
        }

        if ($division !== '' && !Database::value('SELECT id FROM divisions WHERE code = ?', [$division])) {
            $division = '';
        }

        return [
            'mode'     => $mode,
            'batch'    => $batch,
            'floor'    => $floor,
            'division' => $division,
            'stay'     => $stay,
            'zone'     => $zone,
        ];
    }

    /** The weights a mode uses. */
    public static function weightsFor(string $mode): array
    {
        $w = self::MODES[$mode]['weights'] ?? null;
        return $w ?? Settings::relocationWeights();
    }

    /* =================================================================
     * The plan
     * ================================================================= */

    /**
     * Build a plan. Read-only: nothing is saved.
     *
     * @param  array $params the result of params()
     * @return array         see the keys returned at the foot of this method
     */
    public static function plan(array $params): array
    {
        $t0 = microtime(true);

        $pool    = self::pool($params);
        $markets = self::markets($params);

        $capacity = 0;
        foreach ($markets as $m) {
            $capacity += (int) $m['vacant_spaces'];
        }

        if ($pool === [] || $markets === []) {
            return self::emptyPlan($params, count($pool), $capacity, $pool === []
                ? 'No trader is waiting to be placed in this selection.'
                : 'No market in this selection has a vacant pitch.');
        }

        /* The raw criterion scores do not depend on the weights, so they are
           computed once, at the standing weights, and re-weighted per policy. */
        $balanced = self::weightsFor('balanced');

        /* ---- 1. Who goes first ------------------------------------- */
        $zoneScore = [];
        foreach (scoredZones() as $z) {
            $zoneScore[(int) $z['id']] = (float) $z['score'];
        }

        $probe = $markets[0];
        foreach ($pool as $k => $v) {
            $crit = RelocationEngine::criteriaFor($v, $probe, $balanced);
            $equity = 0.0;
            foreach ($crit as $c) {
                if ($c['key'] === 'equity') {
                    $equity = (float) $c['raw'];
                }
            }
            $zs = $zoneScore[(int) ($v['current_zone_id'] ?? 0)] ?? 40.0;
            $pool[$k]['_equity']   = $equity;
            $pool[$k]['_priority'] = round(0.55 * $zs + 0.45 * $equity, 2);
        }
        usort($pool, static function (array $a, array $b): int {
            return $b['_priority'] <=> $a['_priority'] ?: $a['id'] <=> $b['id'];
        });

        $poolSize = count($pool);
        $take     = min((int) $params['batch'], $capacity, $poolSize);
        $batch    = array_slice($pool, 0, $take);
        $n        = count($batch);
        $mCount   = count($markets);

        /* ---- 2. How well each fits (criteria once, weights per mode) -- */
        $raw = [];        // $raw[i][j] = six raw scores, in KEYS order
        $dist = [];       // $dist[i][j] = km
        $blocked = [];    // $blocked[i][j] = true when land use forbids it
        $gate = [];       // [marketId][category] => bool, cached
        $unmapped = [];   // vendors whose trade has no category row

        foreach ($batch as $i => $v) {
            $category = RecommendationService::categoryFor((string) $v['business_category']);
            if ($category === null) {
                $unmapped[$i] = true;
            }

            foreach ($markets as $j => $m) {
                $crit = RelocationEngine::criteriaFor($v, $m, $balanced);
                $row  = [];
                foreach ($crit as $c) {
                    $row[$c['key']] = (float) $c['raw'];
                }
                $raw[$i][$j] = [
                    $row['proximity'] ?? 0.0, $row['trade_fit'] ?? 0.0, $row['equity'] ?? 0.0,
                    $row['viability'] ?? 0.0, $row['affordability'] ?? 0.0, $row['facilities'] ?? 0.0,
                ];
                $dist[$i][$j] = RelocationEngine::haversineKm(
                    RelocationEngine::vendorLat($v), RelocationEngine::vendorLng($v),
                    (float) $m['lat'], (float) $m['lng']
                );

                $isBlocked = false;
                if ($category !== null) {
                    $key = (int) $m['id'] . '|' . $category['code'];
                    if (!isset($gate[$key])) {
                        $gate[$key] = ReachModel::restrictionFor((int) $m['id'], $category) !== null;
                    }
                    $isBlocked = $gate[$key];
                }
                if (!empty($params['stay']) && ($m['div_code'] ?? '') !== ($v['div_code'] ?? '')) {
                    $isBlocked = true;
                }
                $blocked[$i][$j] = $isBlocked;
            }
        }

        /* ---- 3. Solve under the chosen policy ----------------------- */
        $weights = self::weightsFor((string) $params['mode']);
        $solved  = self::solve($batch, $markets, $raw, $blocked, $unmapped, $weights, (float) $params['floor']);

        /* ---- 4. The comparison that matters: one by one vs together -- */
        $greedy = self::greedy($n, $mCount, $markets, $solved['fit'], $solved['allowed']);
        $top1   = self::collisions($n, $mCount, $markets, $solved['fit'], $solved['allowed']);

        /* ---- 5. Read the solution back as something a person can act on */
        $assignments = [];
        $unplaced    = [];
        $load        = [];
        $fitSum = $distSum = $neutralSum = 0.0;

        foreach ($batch as $i => $v) {
            $j = $solved['assign'][$i] ?? null;

            if ($j === null) {
                $unplaced[] = [
                    'vendor' => self::vendorCard($v),
                    'reason' => self::unplacedReason($i, $v, $mCount, $solved, $unmapped, (int) $params['floor'], !empty($params['stay'])),
                ];
                continue;
            }

            $m      = $markets[$j];
            $crit   = RelocationEngine::criteriaFor($v, $m, $weights);
            $fit    = $solved['fit'][$i][$j];
            $neutral = self::combine($raw[$i][$j], $balanced);

            /* The trader's own best option, to say plainly when the plan
               asks them to take something other than their first choice. */
            $bestJ = null; $bestFit = -1.0;
            foreach ($markets as $jj => $mm) {
                if ($solved['allowed'][$i][$jj] && $solved['fit'][$i][$jj] > $bestFit) {
                    $bestFit = $solved['fit'][$i][$jj];
                    $bestJ = $jj;
                }
            }

            $assignments[] = [
                'vendor'      => self::vendorCard($v),
                'market'      => [
                    'id'       => (int) $m['id'],
                    'name'     => (string) $m['name'],
                    'division' => (string) ($m['division_name'] ?? ''),
                    'category' => (string) $m['category'],
                ],
                'fit'         => round($fit, 1),
                'neutral_fit' => round($neutral, 1),
                'distance_km' => round($dist[$i][$j], 1),
                'strengths'   => self::strengths($crit),
                'caution'     => self::caution($crit),
                'moved'       => $bestJ !== null && $bestJ !== $j && ($bestFit - $fit) >= 1.0,
                'best'        => $bestJ !== null ? ['name' => (string) $markets[$bestJ]['name'], 'fit' => round($bestFit, 1)] : null,
            ];

            $mid = (int) $m['id'];
            $load[$mid] = ($load[$mid] ?? 0) + 1;
            $fitSum     += $fit;
            $distSum    += $dist[$i][$j];
            $neutralSum += $neutral;
        }

        $placed = count($assignments);

        $byMarket = [];
        foreach ($markets as $m) {
            $mid = (int) $m['id'];
            $used = $load[$mid] ?? 0;
            if ($used === 0) {
                continue;
            }
            $byMarket[] = [
                'id'       => $mid,
                'name'     => (string) $m['name'],
                'division' => (string) ($m['division_name'] ?? ''),
                'vacant'   => (int) $m['vacant_spaces'],
                'assigned' => $used,
                'left'     => max(0, (int) $m['vacant_spaces'] - $used),
            ];
        }
        usort($byMarket, static fn(array $a, array $b): int => $b['assigned'] <=> $a['assigned']);

        usort($assignments, static function (array $a, array $b): int {
            return $b['vendor']['priority'] <=> $a['vendor']['priority'];
        });

        $avgFit   = $placed > 0 ? $fitSum / $placed : 0.0;
        $greedyAvg = $greedy['placed'] > 0 ? $greedy['fit'] / $greedy['placed'] : 0.0;

        $fingerprint = self::fingerprint($params, $assignments);

        return [
            'params'      => $params,
            'weights'     => $weights,
            'assignments' => $assignments,
            'unplaced'    => $unplaced,
            'by_market'   => $byMarket,
            'fingerprint' => $fingerprint,
            'reference'   => 'PLN-' . date('ymd') . '-' . strtoupper(substr($fingerprint, 0, 5)),
            'summary'     => [
                'pool'          => $poolSize,
                'capacity'      => $capacity,
                'selected'      => $n,
                'placed'        => $placed,
                'unplaced'      => count($unplaced),
                'markets_used'  => count($byMarket),
                'avg_fit'       => round($avgFit, 1),
                'avg_neutral'   => $placed > 0 ? round($neutralSum / $placed, 1) : 0.0,
                'avg_distance'  => $placed > 0 ? round($distSum / $placed, 1) : 0.0,
                'greedy_fit'    => round($greedyAvg, 1),
                'greedy_placed' => $greedy['placed'],
                'uplift'        => round($avgFit - $greedyAvg, 1),
                'collisions'    => $top1,
                'ms'            => (int) round((microtime(true) - $t0) * 1000),
                'note'          => $take < min((int) $params['batch'], $poolSize)
                    ? 'The batch was limited by the pitches available: ' . num($capacity) . ' vacant against ' . num($poolSize) . ' traders waiting.'
                    : '',
            ],
        ];
    }

    /**
     * Run every policy over the same traders and pitches.
     *
     * The raw criteria do not depend on the weights, so a policy changes only
     * who is preferred. To compare them fairly the yardstick has to be the same
     * for all of them, which is why each result is also reported at the
     * Authority's standing (balanced) weights, and by what it did for the most
     * vulnerable traders.
     */
    public static function compare(array $params): array
    {
        $out = [];
        foreach (self::MODES as $key => $mode) {
            $p = $params;
            $p['mode'] = $key;
            $plan = self::plan($p);

            $vulnerable = 0; $vulnDist = 0.0;
            foreach ($plan['assignments'] as $a) {
                if ($a['vendor']['equity'] >= 70.0) {
                    $vulnerable++;
                    $vulnDist += $a['distance_km'];
                }
            }

            $out[] = [
                'mode'        => $key,
                'label'       => $mode['label'],
                'placed'      => $plan['summary']['placed'],
                'avg_neutral' => $plan['summary']['avg_neutral'],
                'avg_distance'=> $plan['summary']['avg_distance'],
                'vulnerable'  => $vulnerable,
                'vuln_distance' => $vulnerable > 0 ? round($vulnDist / $vulnerable, 1) : null,
            ];
        }
        return $out;
    }

    /* =================================================================
     * Saving a plan as proposals
     * ================================================================= */

    /**
     * Turn a plan into ordinary recommendations awaiting approval.
     *
     * Each assignment is re-scored by the reach model for the exact market the
     * planner chose, so what is saved carries the footfall, viability and
     * revenue projection that every other recommendation carries, and goes
     * through the same approval workflow. Nothing is allocated here.
     *
     * @return array{saved:int,failed:array<int,array{vendor:string,reason:string}>}
     */
    public static function submit(array $plan): array
    {
        $markets = [];
        foreach (RecommendationService::candidateMarkets(400) as $m) {
            $markets[(int) $m['id']] = $m;
        }

        $saved  = 0;
        $failed = [];

        foreach ($plan['assignments'] as $a) {
            $vendorId = (int) $a['vendor']['id'];
            $marketId = (int) $a['market']['id'];
            $name     = (string) $a['vendor']['name'];

            $vendor = vendorForMatching($vendorId);
            $market = $markets[$marketId] ?? null;

            if (!$vendor || !$market) {
                $failed[] = ['vendor' => $name, 'reason' => 'The trader or the market is no longer available.'];
                continue;
            }

            try {
                $ranked = RecommendationService::rank($vendor, [$market], 1);
                if ($ranked === []) {
                    $failed[] = ['vendor' => $name, 'reason' => 'The reach model excluded this market for this trade.'];
                    continue;
                }

                $ids = RecommendationService::persist($ranked, $vendorId);
                $recId = (int) ($ids[1] ?? 0);
                if ($recId > 0) {
                    $tag = sprintf('[AI plan %s · %s policy · fit %.1f] ',
                        $plan['reference'], self::MODES[$plan['params']['mode']]['label'], $a['fit']);
                    $why = $a['moved']
                        ? ' Placed here rather than at ' . ($a['best']['name'] ?? 'the first choice')
                          . ' so that the whole batch is placed as well as it can be.'
                        : '';
                    Database::run(
                        'UPDATE recommendations SET explanation = CONCAT(?, explanation, ?) WHERE id = ?',
                        [$tag, $why, $recId]
                    );
                    $saved++;
                }
            } catch (Throwable $e) {
                $failed[] = ['vendor' => $name, 'reason' => 'Could not be saved.'];
            }
        }

        return ['saved' => $saved, 'failed' => $failed];
    }

    /* =================================================================
     * Internals
     * ================================================================= */

    /** Traders waiting to be placed, within the requested selection. */
    private static function pool(array $params): array
    {
        $where  = [
            "v.status IN ('surveyed','notified')",
            'v.current_zone_id IS NOT NULL',
            "NOT EXISTS (SELECT 1 FROM allocations a WHERE a.vendor_id = v.id
                            AND a.status IN ('proposed','offered','accepted','occupied'))",
        ];
        $bind = [];

        if ($params['division'] !== '') {
            $where[] = 'd.code = :div';
            $bind['div'] = $params['division'];
        }
        if ((int) $params['zone'] > 0) {
            $where[] = 'v.current_zone_id = :zone';
            $bind['zone'] = (int) $params['zone'];
        }

        return Database::all(
            "SELECT v.*, z.lat AS current_lat, z.lng AS current_lng, z.name AS zone_name,
                    d.code AS div_code, d.name AS division_name
               FROM vendors v
               JOIN divisions d ON d.id = v.division_id
               LEFT JOIN trade_zones z ON z.id = v.current_zone_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY v.id
              LIMIT 12000",
            $bind
        );
    }

    /** Markets with a vacant pitch, narrowed the same way as the traders. */
    private static function markets(array $params): array
    {
        $markets = candidateMarkets(400);

        if (!empty($params['stay']) && $params['division'] !== '') {
            $markets = array_values(array_filter(
                $markets,
                static fn(array $m): bool => $m['div_code'] === $params['division']
            ));
        }

        return array_values($markets);
    }

    private static function emptyPlan(array $params, int $pool, int $capacity, string $why): array
    {
        return [
            'params' => $params, 'weights' => self::weightsFor((string) $params['mode']),
            'assignments' => [], 'unplaced' => [], 'by_market' => [],
            'fingerprint' => sha1('empty'), 'reference' => 'PLN-' . date('ymd') . '-EMPTY',
            'summary' => [
                'pool' => $pool, 'capacity' => $capacity, 'selected' => 0, 'placed' => 0,
                'unplaced' => 0, 'markets_used' => 0, 'avg_fit' => 0.0, 'avg_neutral' => 0.0,
                'avg_distance' => 0.0, 'greedy_fit' => 0.0, 'greedy_placed' => 0, 'uplift' => 0.0,
                'collisions' => 0, 'ms' => 0, 'note' => $why,
            ],
        ];
    }

    /** One weighted fit, 0-100, from six raw scores in KEYS order. */
    private static function combine(array $row, array $weights): float
    {
        $sum = 0.0; $w = 0.0;
        foreach (self::KEYS as $idx => $key) {
            $wk   = (float) ($weights[$key] ?? 0.0);
            $sum += $row[$idx] * $wk;
            $w   += $wk;
        }
        return $w > 0 ? $sum / $w : 0.0;
    }

    /**
     * Find the best joint assignment.
     *
     * Columns are pitches (one per vacant space, so a market with six pitches
     * is six identical columns) followed by one "leave unplaced" column per
     * trader. Cost is 100 minus fit, so minimising cost maximises total fit.
     * A forbidden pair costs more than leaving the trader unplaced, which means
     * the solver never forces a trader into a market the Authority cannot use,
     * and a trader with no acceptable option does not waste a pitch.
     *
     * @return array{assign:array<int,?int>,fit:array,allowed:array}
     */
    private static function solve(
        array $batch, array $markets, array $raw, array $blocked, array $unmapped,
        array $weights, float $floor
    ): array {
        $n = count($batch);
        $mCount = count($markets);

        $fit = []; $allowed = []; $cost = [];
        foreach ($batch as $i => $v) {
            for ($j = 0; $j < $mCount; $j++) {
                $f = self::combine($raw[$i][$j], $weights);
                $ok = !isset($unmapped[$i]) && !$blocked[$i][$j] && $f >= $floor;
                $fit[$i][$j]     = $f;
                $allowed[$i][$j] = $ok;
                $cost[$i][$j]    = $ok ? (100.0 - $f) : self::COST_FORBIDDEN;
            }
        }

        /* One column per pitch, but never more than n for a market: no market
           can take more traders than there are in the batch. */
        $slotMarket = [];
        foreach ($markets as $j => $m) {
            $slots = min((int) $m['vacant_spaces'], $n);
            for ($s = 0; $s < $slots; $s++) {
                $slotMarket[] = $j;
            }
        }
        $real = count($slotMarket);
        $cols = $real + $n;

        /* Hungarian method, rows 1..n, columns 1..cols, with potentials. */
        $u = array_fill(0, $n + 1, 0.0);
        $v = array_fill(0, $cols + 1, 0.0);
        $p = array_fill(0, $cols + 1, 0);
        $way = array_fill(0, $cols + 1, 0);

        for ($i = 1; $i <= $n; $i++) {
            $p[0] = $i;
            $j0 = 0;
            $minv = array_fill(0, $cols + 1, INF);
            $used = array_fill(0, $cols + 1, false);

            do {
                $used[$j0] = true;
                $i0 = $p[$j0];
                $delta = INF;
                $j1 = 0;
                $ri = $i0 - 1;

                for ($j = 1; $j <= $cols; $j++) {
                    if ($used[$j]) {
                        continue;
                    }
                    $c = $j <= $real ? $cost[$ri][$slotMarket[$j - 1]] : self::COST_UNPLACED;
                    $cur = $c - $u[$i0] - $v[$j];
                    if ($cur < $minv[$j]) {
                        $minv[$j] = $cur;
                        $way[$j]  = $j0;
                    }
                    if ($minv[$j] < $delta) {
                        $delta = $minv[$j];
                        $j1 = $j;
                    }
                }

                for ($j = 0; $j <= $cols; $j++) {
                    if ($used[$j]) {
                        $u[$p[$j]] += $delta;
                        $v[$j]     -= $delta;
                    } else {
                        $minv[$j] -= $delta;
                    }
                }
                $j0 = $j1;
            } while ($p[$j0] !== 0);

            do {
                $j1 = $way[$j0];
                $p[$j0] = $p[$j1];
                $j0 = $j1;
            } while ($j0 !== 0);
        }

        $assign = array_fill(0, $n, null);
        for ($j = 1; $j <= $real; $j++) {
            if ($p[$j] !== 0) {
                $i = $p[$j] - 1;
                $mj = $slotMarket[$j - 1];
                /* A forbidden pair can only appear if there was no better
                   column left; treat it as unplaced, never as a placement. */
                if ($allowed[$i][$mj]) {
                    $assign[$i] = $mj;
                }
            }
        }

        return ['assign' => $assign, 'fit' => $fit, 'allowed' => $allowed];
    }

    /**
     * The comparison baseline: what happens when each trader, in turn, is sent
     * to their own best market that still has room. This is what the one-trader
     * Matching screen produces if its first answer is accepted every time.
     */
    private static function greedy(int $n, int $mCount, array $markets, array $fit, array $allowed): array
    {
        $room = [];
        foreach ($markets as $j => $m) {
            $room[$j] = (int) $m['vacant_spaces'];
        }

        $total = 0.0; $placed = 0;
        for ($i = 0; $i < $n; $i++) {
            $best = null; $bestFit = -1.0;
            for ($j = 0; $j < $mCount; $j++) {
                if ($allowed[$i][$j] && $room[$j] > 0 && $fit[$i][$j] > $bestFit) {
                    $bestFit = $fit[$i][$j];
                    $best = $j;
                }
            }
            if ($best !== null) {
                $room[$best]--;
                $total += $bestFit;
                $placed++;
            }
        }
        return ['fit' => $total, 'placed' => $placed];
    }

    /**
     * How many traders would be turned away from their first choice if every
     * one of them were treated separately: their best market is named by more
     * traders than it has pitches.
     */
    private static function collisions(int $n, int $mCount, array $markets, array $fit, array $allowed): int
    {
        $demand = [];
        for ($i = 0; $i < $n; $i++) {
            $best = null; $bestFit = -1.0;
            for ($j = 0; $j < $mCount; $j++) {
                if ($allowed[$i][$j] && $fit[$i][$j] > $bestFit) {
                    $bestFit = $fit[$i][$j];
                    $best = $j;
                }
            }
            if ($best !== null) {
                $demand[$best] = ($demand[$best] ?? 0) + 1;
            }
        }

        $over = 0;
        foreach ($demand as $j => $count) {
            $over += max(0, $count - (int) $markets[$j]['vacant_spaces']);
        }
        return $over;
    }

    private static function vendorCard(array $v): array
    {
        return [
            'id'       => (int) $v['id'],
            'code'     => (string) $v['vendor_code'],
            'name'     => (string) $v['full_name'],
            'category' => (string) $v['business_category'],
            'division' => (string) ($v['division_name'] ?? ''),
            'zone'     => (string) ($v['zone_name'] ?? ''),
            'priority' => (float) ($v['_priority'] ?? 0),
            'equity'   => (float) ($v['_equity'] ?? 0),
        ];
    }

    private static function unplacedReason(
        int $i, array $v, int $mCount, array $solved, array $unmapped, int $floor, bool $stay
    ): string {
        if (isset($unmapped[$i])) {
            return 'This trade is not mapped to a category the reach model understands.';
        }

        $anyAllowed = false; $best = 0.0;
        for ($j = 0; $j < $mCount; $j++) {
            $best = max($best, $solved['fit'][$i][$j]);
            if ($solved['allowed'][$i][$j]) {
                $anyAllowed = true;
            }
        }

        if (!$anyAllowed) {
            return $best < $floor
                ? sprintf('No market reaches the minimum fit of %d (the best is %.0f). Lower the floor or add suitable space.', $floor, $best)
                : 'Every market with space is closed to this trade by a land-use restriction'
                  . ($stay ? ' or lies outside the division.' : '.');
        }

        return 'Every suitable pitch is taken by a trader the plan places ahead of them. Offer them the next batch.';
    }

    /** The two things this placement does best, in the trader’s own terms. */
    private static function strengths(array $criteria): array
    {
        $labels = [
            'proximity' => 'Nearby', 'trade_fit' => 'Suits the trade', 'equity' => 'Priority need',
            'viability' => 'Room to trade', 'affordability' => 'Affordable', 'facilities' => 'Facilities',
        ];

        usort($criteria, static fn(array $a, array $b): int => ($b['raw'] * $b['weight']) <=> ($a['raw'] * $a['weight']));

        $out = [];
        foreach ($criteria as $c) {
            if ($c['raw'] >= 70 && count($out) < 2) {
                $out[] = ($labels[$c['key']] ?? $c['key']) . ': ' . $c['note'];
            }
        }
        return $out;
    }

    /** The weakest criterion, when it is weak enough that an officer should know. */
    private static function caution(array $criteria): ?string
    {
        $labels = [
            'proximity' => 'Distance', 'trade_fit' => 'Trade fit', 'equity' => 'Need',
            'viability' => 'Headroom', 'affordability' => 'Cost', 'facilities' => 'Facilities',
        ];

        $worst = null;
        foreach ($criteria as $c) {
            if ($c['key'] === 'equity') {
                continue;   // vulnerability is a property of the trader, not a weakness of the pitch
            }
            if ($worst === null || $c['raw'] < $worst['raw']) {
                $worst = $c;
            }
        }
        return ($worst !== null && $worst['raw'] < 50)
            ? ($labels[$worst['key']] ?? $worst['key']) . ': ' . $worst['note']
            : null;
    }

    /**
     * A short digest of what the plan would do. The page shows it, the submit
     * button posts it back, and the server refuses to save if the register has
     * moved on in between - an officer never approves a plan they were not
     * shown.
     */
    private static function fingerprint(array $params, array $assignments): string
    {
        $pairs = [];
        foreach ($assignments as $a) {
            $pairs[] = $a['vendor']['id'] . ':' . $a['market']['id'];
        }
        sort($pairs);
        return sha1(json_encode([$params['mode'], $params['floor']]) . '|' . implode(',', $pairs));
    }
}
