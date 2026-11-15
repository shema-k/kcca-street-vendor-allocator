<?php
/**
 * KCCA VRSP — Cluster & Wave Planner.
 *
 * The AI Planner asks "which pitch for which trader?". That is the right
 * question for an individual and the wrong unit for a clearance. A street
 * is cleared as a community: the people who sell food at one junction know
 * each other, share customers and, usually, an association. Scatter them
 * across four markets and the customers who followed them are lost, which
 * is the most common reason a relocated trader goes back to the street.
 *
 * This planner therefore moves whole trading communities, and it plans the
 * move in time.
 *
 *  1. CLUSTERS.  Traders waiting to be placed are grouped by the site they
 *     trade at and the kind of goods they sell (food, produce, goods,
 *     services). "Food sellers at Kisekka Road" is one cluster.
 *  2. DESTINATION.  Each cluster is sent, as far as space allows, to ONE
 *     market. A market scores by how well it fits the cluster's traders
 *     (the same six criteria as Matching, sampled across the cluster) and
 *     by how much of the cluster it can hold. A "togetherness" setting says
 *     how much keeping the cluster whole is worth against raw fit.
 *     A cluster larger than any market's room is split, largest piece first.
 *  3. WAVES.  Clusters are queued by urgency - how badly the site obstructs
 *     the city, and how vulnerable its traders are - and packed into dated
 *     waves no larger than the number of traders enforcement and the markets
 *     can absorb in one go.
 *  4. RELIEF.  After every wave the plan reports how much of the city's
 *     congestion has been lifted, and which sites are fully cleared.
 *
 * Read-only: it produces a schedule to be argued over, never an allocation.
 * Individual pitches are still assigned through the AI Planner and Matching,
 * and approved through the normal workflow.
 */

declare(strict_types=1);

final class WavePlanner
{
    /** What a "kind of goods" means. Every business category belongs to one. */
    public const GROUPS = [
        'food'     => ['label' => 'Food & drink',
                       'cats'  => ['food-cooking', 'beverages', 'meat-fish']],
        'produce'  => ['label' => 'Produce',
                       'cats'  => ['fresh-produce', 'crops-grains', 'livestock']],
        'goods'    => ['label' => 'General goods',
                       'cats'  => ['household-goods', 'clothing-textiles', 'handicraft', 'other']],
        'services' => ['label' => 'Services',
                       'cats'  => ['electronics-repairs', 'salon-services', 'mobile-services']],
    ];

    public const DEFAULTS = [
        'capacity' => 120,   // traders moved per wave
        'cadence'  => 7,     // days between waves
        'together' => 60,    // 0 = pure fit, 100 = keep clusters whole above all
        'floor'    => 45,    // minimum acceptable fit
    ];

    private const NEAREST = 25;      // markets considered per cluster
    private const SAMPLE  = 5;       // traders sampled to score a cluster
    private const MAX_WAVES = 52;

    /* =================================================================
     * Parameters
     * ================================================================= */

    public static function params(array $in): array
    {
        $d = self::DEFAULTS;

        $capacity = max(20, min(600, (int) ($in['capacity'] ?? $d['capacity'])));
        $cadence  = max(3, min(21, (int) ($in['cadence'] ?? $d['cadence'])));
        $together = max(0, min(100, (int) ($in['together'] ?? $d['together'])));
        $floor    = max(20, min(80, (int) ($in['floor'] ?? $d['floor'])));

        $start = (string) ($in['start'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d', $start);
        if (!$dt || $dt->format('Y-m-d') !== $start || $start < date('Y-m-d')) {
            $start = date('Y-m-d', strtotime('next monday'));
        }

        $division = (string) ($in['division'] ?? '');
        $lock = divisionLock();
        if ($lock !== null) {
            $division = $lock['code'];
        }
        if ($division !== '' && !Database::value('SELECT id FROM divisions WHERE code = ?', [$division])) {
            $division = '';
        }

        return compact('capacity', 'cadence', 'together', 'floor', 'start', 'division');
    }

    /* =================================================================
     * The plan
     * ================================================================= */

    public static function plan(array $params): array
    {
        $t0 = microtime(true);
        $balanced = Settings::relocationWeights();

        $zones = [];
        foreach (scoredZones() as $z) {
            $zones[(int) $z['id']] = $z;
        }

        $markets = candidateMarkets(400);
        if ($params['division'] !== '' && divisionLock() !== null) {
            $markets = array_values(array_filter(
                $markets, static fn(array $m): bool => $m['div_code'] === $params['division']
            ));
        }
        $room = [];
        foreach ($markets as $j => $m) {
            $room[$j] = (int) $m['vacant_spaces'];
        }

        /* ---- 1. Clusters ------------------------------------------- */
        $clusters = self::clusters($params, $zones, $balanced, $markets);

        /* ---- 2. Destination ---------------------------------------- */
        $cand = [];
        foreach ($clusters as $c) {
            $cand[$c['id']] = $c['_cand'];
        }
        $alloc = self::allocate($clusters, $room, $cand, $params['together'] / 100);

        /* ---- 3. Waves ---------------------------------------------- */
        $waves = self::pack($alloc['moves'], (int) $params['capacity']);

        /* ---- 4. Relief --------------------------------------------- */
        $byId = [];
        foreach ($clusters as $c) {
            $byId[$c['id']] = $c;
        }
        $zoneTotal = [];       // vendors waiting, per zone
        foreach ($clusters as $c) {
            $zoneTotal[$c['zone_id']] = ($zoneTotal[$c['zone_id']] ?? 0) + $c['size'];
        }
        $weightSum = 0.0;
        foreach ($zoneTotal as $zid => $_) {
            $weightSum += (float) ($zones[$zid]['score'] ?? 40.0);
        }

        $movedByZone = [];
        $alreadyCleared = [];
        $wavesOut    = [];
        $startTs     = strtotime($params['start']);
        $placed      = 0;
        $fitWeighted = 0.0;
        $wholeCount  = 0;
        $splitInfo   = [];   // cluster id => markets used

        foreach ($alloc['moves'] as $mv) {
            $splitInfo[$mv['cluster']][$mv['market']] = true;
        }

        foreach ($waves as $k => $w) {
            $entries = [];
            foreach ($w['entries'] as $e) {
                $c = $byId[$e['cluster']];
                $m = $markets[$e['market']];
                $entries[] = [
                    'cluster' => $c['name'], 'group' => $c['group_label'], 'zone' => $c['zone_name'],
                    'zone_id' => $c['zone_id'],
                    'market'  => (string) $m['name'], 'market_division' => (string) ($m['division_name'] ?? ''),
                    'count'   => $e['count'], 'fit' => round($e['fit'], 1),
                ];
                $movedByZone[$c['zone_id']] = ($movedByZone[$c['zone_id']] ?? 0) + $e['count'];
                $placed      += $e['count'];
                $fitWeighted += $e['fit'] * $e['count'];
            }

            $relief = 0.0;
            $cleared = [];
            foreach ($zoneTotal as $zid => $tot) {
                $score = (float) ($zones[$zid]['score'] ?? 40.0);
                $share = min(1.0, ($movedByZone[$zid] ?? 0) / max(1, $tot));
                $relief += $score * $share;
                if ($share >= 1.0 && !isset($alreadyCleared[$zid])) {
                    $alreadyCleared[$zid] = true;
                    $cleared[] = (string) ($zones[$zid]['name'] ?? 'Site');
                }
            }

            $wavesOut[] = [
                'n'       => $k + 1,
                'start'   => date('Y-m-d', strtotime('+' . ($k * $params['cadence']) . ' days', $startTs)),
                'moved'   => $w['moved'],
                'entries' => $entries,
                'relief'  => $weightSum > 0 ? round($relief / $weightSum * 100, 1) : 0.0,
                'cleared' => $cleared,
            ];
        }

        /* Togetherness: the share of traders placed with their whole cluster
           in a single market. This is the number the plan is trying to protect. */
        $whole = 0; $all = 0;
        foreach ($clusters as $c) {
            $movedC = 0;
            foreach ($alloc['moves'] as $mv) {
                if ($mv['cluster'] === $c['id']) {
                    $movedC += $mv['count'];
                }
            }
            $all += $c['size'];
            if ($movedC === $c['size'] && count($splitInfo[$c['id']] ?? []) === 1) {
                $whole += $c['size'];
            }
        }

        $deferred = [];
        foreach ($alloc['deferred'] as $d) {
            $c = $byId[$d['cluster']];
            $deferred[] = [
                'cluster' => $c['name'], 'zone_id' => $c['zone_id'], 'count' => $d['count'],
                'reason'  => $d['reason'],
            ];
        }

        $clusterRows = [];
        foreach ($clusters as $c) {
            unset($c['_cand']);
            $clusterRows[] = $c;
        }

        $marketsUsed = [];
        foreach ($alloc['moves'] as $mv) {
            $marketsUsed[$mv['market']] = true;
        }
        $total = 0;
        foreach ($clusters as $c) {
            $total += $c['size'];
        }
        $lastWave = $wavesOut !== [] ? $wavesOut[count($wavesOut) - 1] : null;

        return [
            'params'   => $params,
            'clusters' => $clusterRows,
            'waves'    => $wavesOut,
            'deferred' => $deferred,
            'summary'  => [
                'clusters'     => count($clusters),
                'traders'      => $total,
                'placed'       => $placed,
                'deferred'     => $total - $placed,
                'waves'        => count($wavesOut),
                'finish'       => $lastWave ? $lastWave['start'] : null,
                'together'     => $all > 0 ? round($whole / $all * 100, 1) : 0.0,
                'avg_fit'      => $placed > 0 ? round($fitWeighted / $placed, 1) : 0.0,
                'markets_used' => count($marketsUsed),
                'relief_final' => $lastWave ? $lastWave['relief'] : 0.0,
                'capacity'     => array_sum($room),
                'ms'           => (int) round((microtime(true) - $t0) * 1000),
            ],
        ];
    }

    /* =================================================================
     * Pure logic (no database) - kept separate so it can be tested alone
     * ================================================================= */

    /**
     * Send each cluster to markets, whole where possible.
     *
     * For a cluster of size s and a market with r pitches left, the market's
     * score is  fit * (1 - t + t * min(1, r / s))  where t is togetherness.
     * At t = 0 only fit matters; at t = 1 a market that can hold the whole
     * cluster beats a better-fitting one that cannot. The best-scoring market
     * takes as many as it has room for, and what is left is offered to the next.
     *
     * @param  array $clusters list of ['id'=>int,'size'=>int,'urgency'=>float], any order
     * @param  array $room     market index => pitches left
     * @param  array $cand     cluster id => list of [market index, fit], acceptable markets only
     * @return array{moves:array,deferred:array}
     */
    public static function allocate(array $clusters, array $room, array $cand, float $t): array
    {
        usort($clusters, static function (array $a, array $b): int {
            return $b['urgency'] <=> $a['urgency'] ?: $b['size'] <=> $a['size'] ?: $a['id'] <=> $b['id'];
        });

        $moves = [];
        $deferred = [];

        foreach ($clusters as $c) {
            $left = (int) $c['size'];
            $options = $cand[$c['id']] ?? [];

            while ($left > 0) {
                $best = null; $bestScore = -1.0;
                foreach ($options as [$j, $fit]) {
                    if (($room[$j] ?? 0) <= 0) {
                        continue;
                    }
                    $share = min(1.0, $room[$j] / $left);
                    $score = $fit * (1.0 - $t + $t * $share);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = [$j, $fit];
                    }
                }
                if ($best === null) {
                    $deferred[] = [
                        'cluster' => $c['id'], 'count' => $left,
                        'reason'  => $options === []
                            ? 'No market within reach suits this trade at the minimum fit.'
                            : 'Every suitable market is full.',
                    ];
                    break;
                }
                [$j, $fit] = $best;
                $take = min($left, $room[$j]);
                $room[$j] -= $take;
                $left     -= $take;
                $moves[] = ['cluster' => $c['id'], 'market' => $j, 'count' => $take, 'fit' => (float) $fit];
            }
        }

        return ['moves' => $moves, 'deferred' => $deferred];
    }

    /**
     * Pack moves, in the order given, into waves of at most $capacity traders.
     * A move that does not fit in what is left of a wave continues in the next.
     *
     * @return array<int,array{moved:int,entries:array}>
     */
    public static function pack(array $moves, int $capacity): array
    {
        $waves = [];
        $cur = ['moved' => 0, 'entries' => []];

        foreach ($moves as $mv) {
            $remaining = (int) $mv['count'];
            while ($remaining > 0) {
                $space = $capacity - $cur['moved'];
                if ($space <= 0) {
                    $waves[] = $cur;
                    if (count($waves) >= self::MAX_WAVES) {
                        return $waves;
                    }
                    $cur = ['moved' => 0, 'entries' => []];
                    $space = $capacity;
                }
                $take = min($space, $remaining);
                $cur['entries'][] = ['cluster' => $mv['cluster'], 'market' => $mv['market'],
                                     'count' => $take, 'fit' => $mv['fit']];
                $cur['moved'] += $take;
                $remaining    -= $take;
            }
        }
        if ($cur['moved'] > 0) {
            $waves[] = $cur;
        }
        return $waves;
    }

    /* =================================================================
     * Database-facing helpers
     * ================================================================= */

    private static function clusters(array $params, array $zones, array $weights, array $markets): array
    {
        $groupSql = 'CASE';
        foreach (self::GROUPS as $key => $g) {
            $groupSql .= " WHEN v.business_category IN ('" . implode("','", $g['cats']) . "') THEN '" . $key . "'";
        }
        $groupSql .= " ELSE 'goods' END";

        $live = "NOT EXISTS (SELECT 1 FROM allocations a WHERE a.vendor_id = v.id
                   AND a.status IN ('proposed','offered','accepted','occupied'))";

        $bind = [];
        $div  = '';
        if ($params['division'] !== '') {
            $div = ' AND d.code = :div';
            $bind['div'] = $params['division'];
        }

        $rows = Database::all(
            "SELECT v.current_zone_id AS zone_id, $groupSql AS grp, COUNT(*) AS n
               FROM vendors v JOIN divisions d ON d.id = v.division_id
              WHERE v.status IN ('surveyed','notified') AND v.current_zone_id IS NOT NULL
                AND $live $div
              GROUP BY v.current_zone_id, grp",
            $bind
        );

        $out = [];
        $id = 0;
        foreach ($rows as $r) {
            $zid = (int) $r['zone_id'];
            $zone = $zones[$zid] ?? null;
            if ($zone === null) {
                continue;
            }
            $g = self::GROUPS[$r['grp']];
            $cats = "'" . implode("','", $g['cats']) . "'";

            $sample = Database::all(
                "SELECT v.*, z.lat AS current_lat, z.lng AS current_lng, z.name AS zone_name,
                        d.code AS div_code, d.name AS division_name
                   FROM vendors v
                   JOIN divisions d ON d.id = v.division_id
                   LEFT JOIN trade_zones z ON z.id = v.current_zone_id
                  WHERE v.current_zone_id = ? AND v.business_category IN ($cats)
                    AND v.status IN ('surveyed','notified') AND $live
                  ORDER BY v.id LIMIT " . self::SAMPLE,
                [$zid]
            );
            if ($sample === []) {
                continue;
            }

            /* Nearest markets to the site, then fit over the sample. */
            $near = [];
            foreach ($markets as $j => $m) {
                $near[$j] = RelocationEngine::haversineKm(
                    (float) $zone['lat'], (float) $zone['lng'], (float) $m['lat'], (float) $m['lng']
                );
            }
            asort($near);
            $near = array_slice($near, 0, self::NEAREST, true);

            $cand = [];
            $equity = 0.0;
            foreach ($near as $j => $km) {
                $m = $markets[$j];
                $fitSum = 0.0; $blocked = false;
                foreach ($sample as $v) {
                    $crit = RelocationEngine::criteriaFor($v, $m, $weights);
                    $s = 0.0; $w = 0.0;
                    foreach ($crit as $c) {
                        $s += $c['raw'] * $c['weight'];
                        $w += $c['weight'];
                        if ($c['key'] === 'equity' && $j === array_key_first($near)) {
                            $equity += $c['raw'];
                        }
                    }
                    $fitSum += $w > 0 ? $s / $w : 0.0;

                    $category = RecommendationService::categoryFor((string) $v['business_category']);
                    if ($category === null || ReachModel::restrictionFor((int) $m['id'], $category) !== null) {
                        $blocked = true;
                    }
                }
                $fit = $fitSum / count($sample);
                if (!$blocked && $fit >= $params['floor']) {
                    $cand[] = [$j, $fit];
                }
            }

            $equity = $equity / max(1, count($sample));
            $score  = (float) ($zone['score'] ?? 40.0);

            $out[] = [
                'id'          => ++$id,
                'zone_id'     => $zid,
                'zone_name'   => (string) $zone['name'],
                'division'    => (string) ($zone['division_name'] ?? ''),
                'group'       => (string) $r['grp'],
                'group_label' => $g['label'],
                'name'        => (string) $zone['name'] . ' · ' . $g['label'],
                'size'        => (int) $r['n'],
                'congestion'  => round($score, 1),
                'equity'      => round($equity, 1),
                'urgency'     => round(0.6 * $score + 0.4 * $equity, 2),
                '_cand'       => $cand,
            ];
        }

        return $out;
    }
}
