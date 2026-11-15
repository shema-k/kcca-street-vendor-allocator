<?php
/**
 * KCCA VRSP — Footfall and trade viability model.
 *
 * This is the analytical core behind the recommendation engine. It answers
 * one question for a given (trader, product, location) triple:
 *
 *     How many customers could this trader realistically reach at this
 *     location, selling these goods, and how confident are we?
 *
 * Design rules, in priority order:
 *
 *  1. Never present an estimate without a confidence figure. A number the
 *     Authority cannot qualify is a number it cannot defend.
 *  2. Every factor is returned with its own contribution, so a
 *     recommendation can be explained rather than merely asserted.
 *  3. Land-use restrictions are a gate, not a weight. A pitch the
 *     Authority cannot lawfully use for that trade is excluded outright,
 *     however good the numbers look.
 *  4. Where footfall has not been counted, the model says so and falls
 *     back to a catchment estimate, clearly marked as such.
 *
 * The catchment fallback is anchored on the UBOS 2024 populations that
 * KCCA publishes for each division, so even the estimate has a real basis.
 */

declare(strict_types=1);

final class ReachModel
{
    public const VERSION = 'v1.0';

    /**
     * Relative importance of each factor in the reach score.
     * These are policy weights: the Authority can change them in Settings.
     */
    public const WEIGHTS = [
        'footfall'          => 0.30,  // will people actually be there
        'facility_fit'      => 0.16,  // can the trade be done properly
        'category_demand'   => 0.14,  // does this product draw buyers here
        'headroom'          => 0.10,  // is there room left to trade
        'competition'       => 0.08,  // are they already served nearby
        'rent'              => 0.12,  // can the trader afford it
        'accessibility'     => 0.06,  // how easy is it to reach
        'land_use'          => 0.04,  // free of statutory restriction
    ];

    /* =================================================================
     * FOOTFALL
     * ================================================================= */

    /**
     * Build a footfall profile for one market.
     *
     * @return array{
     *   daily:float, peak_hour_band:int, peak_day:string,
     *   observations:int, days_covered:int, bands_covered:int,
     *   confidence:float, basis:string, per_day_type:array<string,float>
     * }
     */
    public static function footfall(int $marketId, array $market): array
    {
        $rows = Database::all(
            "SELECT observed_on, hour_band, day_type, people_in, people_out,
                    traders_present, source, event_note
               FROM footfall_observations
              WHERE market_id = ?
              ORDER BY observed_on DESC
              LIMIT 400",
            [$marketId]
        );

        if ($rows === []) {
            $est = self::catchmentEstimate($market);
            $est['observations']  = 0;
            $est['days_covered']  = 0;
            $est['bands_covered'] = 0;
            $est['basis']         = 'Catchment estimate — no footfall has been counted here yet';
            $est['confidence']    = 0.20;
            return $est;
        }

        /* --- aggregate movements by day type --- */
        $byDay = [];
        $daysSeen = [];
        $bandsSeen = [];
        $sourceWeights = ['sensor' => 1.0, 'manual-count' => 0.9, 'trader-log' => 0.7, 'estimate' => 0.45];

        foreach ($rows as $r) {
            $movements = (int) $r['people_in'] + (int) $r['people_out'];
            // A band is one quarter of a day, so its movements are a quarter
            // of a day's movements.
            $dayKey = $r['observed_on'] . '|' . $r['day_type'];
            $w = $sourceWeights[$r['source']] ?? 0.6;

            $byDay[$dayKey] = ($byDay[$dayKey] ?? 0) + ($movements * $w);
            $daysSeen[$r['observed_on']] = true;
            $bandsSeen[$r['day_type'] . '-' . $r['hour_band']] = true;
        }

        /* --- daily average per day type --- */
        $dayTotals = [];
        foreach ($byDay as $key => $total) {
            [$date, $dayType] = explode('|', $key);
            $dayTotals[$dayType][$date] = ($dayTotals[$dayType][$date] ?? 0) + $total;
        }

        $perDayType = [];
        foreach ($dayTotals as $dayType => $byDate) {
            $perDayType[$dayType] = array_sum($byDate) / max(1, count($byDate));
        }

        /* --- hourly profile, to find the peak --- */
        $byBand = [];
        foreach ($rows as $r) {
            $movements = (int) $r['people_in'] + (int) $r['people_out'];
            $byBand[$r['hour_band']] = ($byBand[$r['hour_band']] ?? 0) + $movements;
        }
        $peakBand = 0;
        foreach ($byBand as $band => $total) {
            if ($total > ($byBand[$peakBand] ?? 0)) {
                $peakBand = (int) $band;
            }
        }

        $peakDay = 'weekday';
        $peakValue = -1.0;
        foreach ($perDayType as $dayType => $value) {
            if ($value > $peakValue) {
                $peakValue = $value;
                $peakDay = $dayType;
            }
        }

        /* --- trading-day weighted daily figure --- */
        // Kampala trades hardest on weekdays and Saturday; Sunday is lighter.
        $weights = ['weekday' => 5.0, 'saturday' => 1.0, 'sunday' => 0.6, 'holiday' => 0.4];
        $sum = 0.0;
        $wt  = 0.0;
        foreach ($perDayType as $dayType => $value) {
            $w = $weights[$dayType] ?? 0.5;
            $sum += $value * $w;
            $wt  += $w;
        }
        $daily = $wt > 0 ? $sum / $wt : 0.0;

        /* --- confidence ---
           Driven by how many distinct days were counted, how much of the
           weekly profile those days cover, and how recent the work is. A
           count from three days last year is not evidence.               */
        $distinctDays = count($daysSeen);
        $dayTypesSeen = count($perDayType);
        $bandCoverage = min(1.0, count($bandsSeen) / 14);   // 4 bands x 3.5 day types

        $daysScore   = min(1.0, $distinctDays / 20);
        $coverageScore = min(1.0, $dayTypesSeen / 3) * 0.6 + $bandCoverage * 0.4;

        $latest  = strtotime((string) $rows[0]['observed_on']);
        $ageDays = $latest ? (int) floor((time() - $latest) / 86400) : 9999;
        $recency = $ageDays <= 30 ? 1.0 : ($ageDays <= 120 ? 0.75 : ($ageDays <= 365 ? 0.45 : 0.20));

        $confidence = round(0.45 * $daysScore + 0.35 * $coverageScore + 0.20 * $recency, 4);
        $confidence = max(0.05, min(0.95, $confidence));

        return [
            'daily'          => round($daily, 1),
            'peak_hour_band' => $peakBand,
            'peak_day'       => $peakDay,
            'observations'   => count($rows),
            'days_covered'   => $distinctDays,
            'bands_covered'  => count($bandsSeen),
            'confidence'     => $confidence,
            'basis'          => $confidence >= 0.6
                ? 'Counted footfall'
                : ($confidence >= 0.35 ? 'Counted footfall — limited coverage' : 'Sparse counting, treated cautiously'),
            'per_day_type'   => $perDayType,
            'age_days'       => $ageDays,
        ];
    }

    /**
     * Estimate daily footfall where nothing has been counted.
     *
     * Anchored on the division population KCCA publishes, scaled by how
     * much trading the market actually does. Deliberately conservative: a
     * low-confidence estimate should not push a location up the ranking.
     */
    public static function catchmentEstimate(array $market): array
    {
        $pop = (int) ($market['population'] ?? 0);
        $capacity = max(1, (int) ($market['capacity'] ?? 1));
        $registered = (int) ($market['registered_vendors'] ?? 0);

        // Trading intensity: busy markets sit in denser commercial catchments.
        $intensity = min(2.2, 0.6 + ($registered / $capacity) * 1.1);

        // A market draws roughly 0.9–1.8 daily visits per registered
        // vendor, the rest arriving for goods rather than traders.
        $perVendor = 1.35;
        $daily = $registered * $perVendor * $intensity;

        // Larger catchments lift the base a little, with diminishing returns.
        $catchmentLift = 1.0 + min(0.35, log10(max(1000, $pop)) / 40);
        $daily *= $catchmentLift;

        return [
            'daily'          => round($daily, 1),
            'peak_hour_band' => 3,
            'peak_day'       => 'weekday',
            'per_day_type'   => [],
            'age_days'       => null,
        ];
    }

    /** Human label for a four-hour band. */
    public static function hourBandLabel(int $band): string
    {
        return match ($band) {
            0 => '00:00–04:00', 1 => '04:00–08:00', 2 => '08:00–12:00',
            3 => '12:00–16:00', 4 => '16:00–20:00', default => '20:00–24:00',
        };
    }

    /* =================================================================
     * VIABILITY
     * ================================================================= */

    /**
     * Score one market for one product category.
     *
     * @param array $market   markets row (with division population attached)
     * @param array $category trade_categories row
     * @param array $vendor   vendors row, for rent affordability
     * @param array $foot     output of footfall()
     * @return array full factor breakdown
     */
    public static function score(array $market, array $category, array $vendor, array $foot, array $context): array
    {
        $factors = [];

        /* --- 1. Footfall: will people be there? --- */
        // Total movements (in + out) across the whole day. The scale is a
        // setting, calibrated against the busiest counted Kampala markets.
        $strongDaily = max(100.0, Settings::num('reach_footfall_strong_daily', 12000));
        $footfallRaw = min(100.0, $foot['daily'] / $strongDaily * 100);
        $factors['footfall'] = [
            'label' => 'Footfall at this location',
            'raw'   => round($footfallRaw, 1),
            'note'  => sprintf('About %s people pass through a day%s. Busiest %s, on %s. %s.',
                num($foot['daily']),
                $foot['observations'] > 0 ? '' : ', estimated rather than counted',
                self::hourBandLabel($foot['peak_hour_band']),
                $foot['peak_day'],
                $foot['basis']),
        ];

        /* --- 2. Category demand --- */
        $demandRaw = (float) $category['demand_index'];
        $factors['category_demand'] = [
            'label' => 'Demand for this product in Kampala',
            'raw'   => round($demandRaw, 1),
            'note'  => sprintf('%s carries a demand index of %d out of 100 in the city%s.',
                $category['name'], (int) $category['demand_index'],
                $category['seasonality'] ? ' ' . $category['seasonality'] : ''),
        ];

        /* --- 3. Facility fit: can the trade be done properly? --- */
        $need = [];
        if ($category['requires_water'])     $need['water_available']  = 'water';
        if ($category['requires_power'])     $need['power_available']  = 'power';
        if ($category['requires_cold_chain'])$need['cold_chain']       = 'cold chain';
        if ($category['perishable'])         $need['waste_collection'] = 'waste collection';

        $have = 0;
        $missing = [];
        foreach ($need as $col => $label) {
            if (!empty($market[$col])) {
                $have++;
            } else {
                $missing[] = $label;
            }
        }
        $facilityRaw = $need === [] ? 100.0 : ($have / count($need)) * 100;
        $factors['facility_fit'] = [
            'label' => 'Facilities this product needs',
            'raw'   => round($facilityRaw, 1),
            'note'  => $need === []
                ? 'No specialist facilities required for this trade.'
                : ($missing === []
                    ? 'Every required facility is available here.'
                    : 'Missing: ' . implode(', ', $missing) . '.'),
        ];

        /* --- 4. Headroom: is there room left? --- */
        $capacity = max(1, (int) $market['capacity']);
        $occupancy = (int) $market['registered_vendors'] / $capacity * 100;
        $headroomRaw = max(0.0, 100.0 - max(0.0, $occupancy - 50) * 2.0);
        $factors['headroom'] = [
            'label' => 'Room left in the market',
            'raw'   => round($headroomRaw, 1),
            'note'  => sprintf('%s%% occupied, %s of %s pitches free.',
                number_format($occupancy, 1), num((int) $market['vacant_spaces']), num($capacity)),
        ];

        /* --- 5. Competition: already served here? --- */
        $sameTrade = (int) ($context['same_trade'] ?? 0);
        $totalTraders = max(1, (int) $market['registered_vendors']);
        $tradeShare = $sameTrade / $totalTraders;
        // A little competition is healthy — it proves there is demand.
        // Saturation is what hurts. Six in twenty trading the same thing
        // is a normal market mix; half of them is not.
        $competitionRaw = match (true) {
            $tradeShare <= 0.05 => 100.0,
            $tradeShare <= 0.30 => 100 - (($tradeShare - 0.05) / 0.25) * 35,
            $tradeShare <= 0.55 => 65 - (($tradeShare - 0.30) / 0.25) * 55,
            default             => max(0.0, 10 - ($tradeShare - 0.55) * 100),
        };
        $factors['competition'] = [
            'label' => 'Existing competition for this trade',
            'raw'   => round(max(0.0, min(100.0, $competitionRaw)), 1),
            'note'  => $sameTrade === 0
                ? sprintf('No vendor at %s is recorded as trading %s, so this stall would be the first.',
                    $market['name'], strtolower($category['name']))
                : sprintf('%d of %d vendors here trade %s (%.0f%% of the market).',
                    $sameTrade, (int) $market['registered_vendors'],
                    strtolower($category['name']), $tradeShare * 100),
        ];

        /* --- 6. Rent: can the trader afford it? --- */
        $turnover = max(1, (int) ($vendor['monthly_turnover_ugx'] ?? 100000));
        $rent     = (int) ($context['monthly_rent'] ?? 0);
        $rentPct  = $rent / $turnover * 100;
        $rentRaw  = match (true) {
            $rent === 0  => 100.0,
            $rentPct <= 3 => 95.0,
            $rentPct <= 8 => 82.0,
            $rentPct <= 15=> 60.0,
            $rentPct <= 25=> 35.0,
            default      => 15.0,
        };
        $factors['rent'] = [
            'label' => 'Rent against the trader’s turnover',
            'raw'   => round($rentRaw, 1),
            'note'  => $rent === 0
                ? 'No rent band applies — KCCA does not charge for the identified vacant spaces.'
                : sprintf('Rent band %s per month against %s turnover (%.1f%%).',
                    ugx($rent), ugx($turnover), $rentPct),
        ];

        /* --- 7. Accessibility --- */
        $accessRaw = (float) ($context['accessibility'] ?? 60);
        $factors['accessibility'] = [
            'label' => 'How easy the location is to reach',
            'raw'   => round($accessRaw, 1),
            'note'  => $context['accessibility_note'] ?? 'Based on the division’s trading density and transport links.',
        ];

        /* --- 8. Land use: free of statutory restriction --- */
        $restriction = self::restrictionFor((int) $market['id'], $category);
        $landUseRaw = $restriction === null ? 100.0 : 0.0;
        $factors['land_use'] = [
            'label' => 'Land use and trading restrictions',
            'raw'   => $landUseRaw,
            'note'  => $restriction === null
                ? 'No restriction recorded against this trade at this market.'
                : $restriction['title'] . ' — ' . $restriction['description'],
        ];

        /* --- combine --- */
        $total = 0.0;
        $weightSum = 0.0;
        foreach (self::WEIGHTS as $key => $weight) {
            $total     += $factors[$key]['raw'] * $weight;
            $weightSum += $weight;
        }
        $reachScore = $weightSum > 0 ? $total / $weightSum : 0.0;

        /* A recommendation resting on a weak count is not a strong one. */
        $confidence = (float) $foot['confidence'];
        $viability  = self::viabilityScore($factors);

        /* --- projection ---
           Customers are the footfall the trader can actually win, and the
           projection is discounted by how well the footfall was counted:
           an estimate is not evidence of a living.                     */
        $capture = self::captureRate($factors, $category);
        $customersPerDay = $foot['daily'] * $capture * $confidence;

        $spend = (float) $category['avg_spend_per_customer_ugx'];
        $tradingDays = max(1, Settings::int('reach_trading_days', 26));
        $revenuePerMonth = $customersPerDay * $spend * $tradingDays;

        /* --- warnings --- */
        $warnings = [];
        if ($restriction !== null) {
            $warnings[] = 'BLOCKED BY LAND USE: ' . $restriction['title'] . ' — ' . $restriction['description'];
        }
        if ($confidence < 0.35) {
            $warnings[] = sprintf(
                'Low confidence: footfall here rests on %d counted %s. Count footfall before offering this space.',
                $foot['observations'],
                $foot['observations'] === 1 ? 'observation' : 'observations');
        }
        if ($missing !== []) {
            $warnings[] = 'Incomplete facilities: the market lacks ' . implode(', ', $missing) . '.';
        }
        if ((int) $market['vacant_spaces'] <= 0) {
            $warnings[] = 'No vacant space is currently recorded at this market.';
        }

        return [
            'reach_score'           => round($reachScore, 2),
            'viability_score'       => round($viability, 2),
            'footfall_score'        => round($factors['footfall']['raw'], 2),
            'confidence'            => round($confidence, 4),
            'projected_customers_day'=> round($customersPerDay, 2),
            'projected_revenue_ugx' => round($revenuePerMonth, 2),
            'factors'               => $factors,
            'weights'               => self::WEIGHTS,
            'warnings'              => $warnings,
            'blocked'               => $restriction !== null,
            'explanation'           => self::explain($factors, $category, $foot, $restriction, $vendor, $revenuePerMonth),
            'footfall_profile'      => $foot,
            'model_version'         => self::VERSION,
        ];
    }

    /**
     * Viability is the part a trader actually feels: can this be a living?
     * Reach says how many people pass; viability asks whether that is
     * enough to trade on, given competition and what the trader already
     * turns over.
     */
    private static function viabilityScore(array $factors): float
    {
        $blend = [
            $factors['category_demand']['raw']  => 0.28,
            $factors['competition']['raw']      => 0.26,
            $factors['footfall']['raw']         => 0.22,
            $factors['facility_fit']['raw']     => 0.16,
            $factors['headroom']['raw']         => 0.08,
        ];

        $total = 0.0;
        $w = 0.0;
        foreach ($blend as $value => $weight) {
            $total += $value * $weight;
            $w     += $weight;
        }

        return $w > 0 ? $total / $w : 0.0;
    }

    /**
     * What share of passing people could this trader realistically win?
     *
     * This is the step that turns footfall into customers, and it is the
     * most contestable number in the model, so it is derived from stated
     * factors rather than assumed. Movements are counted in and out, so
     * one shopper is roughly two movements: the base rate is the share of
     * *movements* that convert to a sale, which is why it is small.
     */
    private static function captureRate(array $factors, array $category): float
    {
        // A base rate, calibrated so a full-scoring stall in a strong
        // market serves roughly 150 customers a day. Configurable.
        $base = Settings::num('reach_capture_base', 0.012);

        // Products people specifically came for convert better.
        $base *= (0.65 + ((float) $category['demand_index'] / 100) * 0.70);

        // Where substitutes are plentiful, more of the passing trade walks
        // past. This is the single biggest reduction.
        $substitution = (float) $category['substitution_risk'];
        $base *= (1 - ($substitution * 0.55));

        // Heavier competition inside the market cuts conversion, but a
        // little competition is evidence of demand rather than a problem.
        $comp = $factors['competition']['raw'] / 100;
        $base *= (0.70 + ($comp * 0.30));

        // Only part of the flow is within the hours the trader keeps.
        $base *= 0.85;

        return max(0.0005, min(0.10, $base));
    }

    /**
     * The active land-use restriction blocking this category, or null.
     * An empty excluded_categories list means the restriction covers the
     * whole site and therefore every trade.
     */
    public static function restrictionFor(int $marketId, array $category): ?array
    {
        $rows = Database::all(
            "SELECT * FROM land_use_restrictions
              WHERE market_id = ? AND is_active = 1
                AND (effective_to IS NULL OR effective_to >= CURDATE())
                AND effective_from <= CURDATE()",
            [$marketId]
        );

        foreach ($rows as $r) {
            $excluded = array_filter(array_map('trim', explode(',', (string) $r['excluded_categories'])));
            if ($excluded === [] || in_array($category['code'], $excluded, true)) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Plain-language explanation of a score. Written to be read aloud to
     * a trader without losing its meaning.
     */
    private static function explain(
        array $f,
        array $category,
        array $foot,
        ?array $restriction,
        array $vendor = [],
        float $revenuePerMonth = 0.0
    ): string
    {
        if ($restriction !== null) {
            return sprintf(
                'This location cannot be offered for %s. %s applies here (%s). %s',
                strtolower($category['name']),
                $restriction['title'],
                $restriction['legal_basis'] ?: 'recorded by the Authority',
                $restriction['description']
            );
        }

        /* Name the factors that actually carried the score.
           A factor sitting at 100 discriminates nothing — rent is free at
           every public market, so naming it as this location's strength
           would be true of every location and useless to the reader. So
           factors at the top of their range are set aside unless nothing
           else distinguishes this location. */
        $contributions = [];
        foreach (self::WEIGHTS as $key => $weight) {
            $contributions[$key] = [
                'label' => $f[$key]['label'],
                'raw'   => $f[$key]['raw'],
                'points' => $f[$key]['raw'] * $weight,
            ];
        }

        $discriminating = array_filter($contributions, static fn(array $c) => $c['raw'] < 98);
        if (count($discriminating) >= 2) {
            arsort($discriminating);
            $top = array_slice($discriminating, 0, 2, true);
        } else {
            arsort($contributions);
            $top = array_slice($contributions, 0, 2, true);
        }

        /* The weakest factor is always worth naming — that is the caveat. */
        arsort($contributions);
        $worst = end($contributions);
        $bestPoints = reset($top)['points'];

        $strengths = [];
        foreach ($top as $item) {
            $strengths[] = sprintf('%s (%.0f points)', strtolower($item['label']), $item['points']);
        }
        $weaknesses = [];
        if ($worst['points'] < $bestPoints * 0.6) {
            $weaknesses[] = sprintf('%s (%.0f points)', strtolower($worst['label']), $worst['points']);
        }

        $parts = [];
        $parts[] = sprintf(
            'About %s people pass through a day%s.',
            num($foot['daily']),
            $foot['observations'] > 0 ? '' : ' — this figure is estimated, not counted'
        );

        $parts[] = 'This location scores highest on ' . self::list($strengths) . '.';

        if ($weaknesses !== []) {
            $parts[] = 'It scores lowest on ' . self::list($weaknesses) . '.';
        }

        $parts[] = sprintf(
            'A %s stall here is %s to run: demand is indexed at %d out of 100 in Kampala.',
            strtolower($category['name']),
            (int) $category['demand_index'] >= 70 ? 'well placed' :
                ((int) $category['demand_index'] >= 50 ? 'workable' : 'a harder sell'),
            (int) $category['demand_index']
        );

        /* Does the projection look like the trade this trader already runs?
           If the market is projected to earn several times their current
           turnover, the honest reading is that the projection is optimistic
           — a trader can compare it against what they know of their own
           business. */
        $currentMonthly = (float) ($vendor['monthly_turnover_ugx'] ?? 0) * 1;
        $ratio = $currentMonthly > 0 ? $revenuePerMonth / $currentMonthly : 0;
        if ($ratio > 2.5) {
            $parts[] = sprintf(
                'Treat the revenue figure with care: it projects about %.1f times what this trader earns today, which is possible in a good market but is an optimistic estimate.',
                $ratio
            );
        }

        return implode(' ', $parts);
    }

    /** "a, b and c" */
    private static function list(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }
}