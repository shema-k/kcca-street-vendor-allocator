<?php
/**
 * KCCA VRSP — Multi-criteria reasoning engines.
 *
 * Two engines, both explainable. Neither returns a bare number: every
 * score comes back with the per-criterion breakdown that produced it and
 * a set of plain-English statements, because a relocation decision that
 * affects a trader's livelihood has to be justifiable to the trader, to
 * a Division Mayor, and to the Authority's Internal Audit.
 *
 *   ZoneScoringEngine    how badly does this place need to be cleared?
 *   RelocationEngine     which gazetted space suits this vendor best?
 */

declare(strict_types=1);

/* =====================================================================
 * ZONE SCORING ENGINE
 * ===================================================================== */
final class ZoneScoringEngine
{
    /**
     * Score a trade zone out of 100 for "how urgently must this be cleared
     * and how much does clearing it improve the city?".
     *
     * @param  array $z   zone row from trade_zones
     * @return array{score:float, band:string, tone:string, criteria:array<int,array>, reasons:array<int,string>}
     */
    public static function score(array $z): array
    {
        // Weights come from Settings so the Authority can retune them
        // without a deployment, falling back to the config defaults.
        $w = class_exists(Settings::class) ? Settings::zoneWeights() : ZONE_SCORING_WEIGHTS;

        /* --- 1. Physical obstruction -----------------------------------
           A footpath fully taken is a worse failure than a road taking a
           tenth of its width. We blend the two because the harm differs:
           footpath blocks pedestrians outright, carriageway blocks
           vehicles.                                                    */
        $foot   = (float) $z['footpath_obstruction_pct'];
        $carriage = (float) $z['carriageway_encroachment_pct'];
        $obstruction = min(100, ($foot * 0.65) + ($carriage * 0.35));

        /* --- 2. Vendor load -------------------------------------------
           More vendors cleared from one site is more benefit per day of
           disruption, but it is also more people to relocate well, so the
           curve is gently compressive rather than linear.            */
        $vendors = (int) $z['estimated_vendors'];
        $vendorLoad = min(100, ($vendors / 90) * 100);

        /* --- 3. Traffic impact ----------------------------------------
           Strategic corridors and bus stages / junctions block vehicles
           and emergency access, which is worse than a side street.     */
        $traffic = 35;
        if ($z['strategic_corridor']) {
            $traffic += 40;
        }
        $traffic += match ($z['zone_type']) {
            'junction', 'roundabout', 'bus-stage' => 25,
            'roadside', 'entrance'                => 15,
            default                               => 5,
        };

        /* --- 4. Duration ---------------------------------------------
           An occupation that has run for years is a governance failure
           that must be closed, not postponed. Square root keeps a 10-year
           case from completely burying a 4-year one.                   */
        $months = (int) $z['months_occupied'];
        $duration = min(100, sqrt(max(0, $months) / 36) * 100);

        /* --- 5. Strategic corridor ------------------------------------ */
        $strategic = $z['strategic_corridor'] ? 100 : 30;

        /* --- 6. Public access ----------------------------------------
           Blocking the entrance of a hospital, school or place of worship
           is treated as a priority override, not just a weighting.      */
        $asset = (string) ($z['near_public_asset'] ?? '');
        $publicAccess = 20;
        if ($asset !== '') {
            $publicAccess = match (true) {
                (bool) preg_match('/hospital|clinic|health/i', $asset)  => 100,
                (bool) preg_match('/school|university|college/i', $asset) => 85,
                (bool) preg_match('/church|mosque|temple|cathedral/i', $asset) => 70,
                (bool) preg_match('/office|city hall|court|police/i', $asset) => 65,
                default => 55,
            };
        }

        /* --- 7. Complaints ------------------------------------------- */
        $complaints = min(100, ((int) $z['complaints_30d'] / 25) * 100);

        $criteria = [
            ['key' => 'obstruction',    'label' => 'Physical obstruction of public space',
             'raw' => $obstruction, 'weight' => $w['obstruction'],
             'note' => sprintf('%s%% of footpath and %s%% of carriageway taken',
                               number_format($foot, 0), number_format($carriage, 0))],
            ['key' => 'vendor_load',    'label' => 'Number of vendors affected',
             'raw' => $vendorLoad, 'weight' => $w['vendor_load'],
             'note' => $vendors . ' vendors currently trading here'],
            ['key' => 'traffic_impact', 'label' => 'Effect on traffic and emergency access',
             'raw' => $traffic, 'weight' => $w['traffic_impact'],
             'note' => self::zoneTypeNote((string) $z['zone_type'], (bool) $z['strategic_corridor'])],
            ['key' => 'duration',       'label' => 'How long the occupation has persisted',
             'raw' => $duration, 'weight' => $w['duration'],
             'note' => $months . ' months occupied'],
            ['key' => 'strategic',      'label' => 'Strategic corridor importance',
             'raw' => $strategic, 'weight' => $w['strategic'],
             'note' => $z['strategic_corridor'] ? 'On a designated strategic corridor' : 'Local access road'],
            ['key' => 'public_access',  'label' => 'Obstruction of a public facility',
             'raw' => $publicAccess, 'weight' => $w['public_access'],
             'note' => $asset !== '' ? 'Beside ' . $asset : 'No public facility affected'],
            ['key' => 'complaints',     'label' => 'Public complaints received (30 days)',
             'raw' => $complaints, 'weight' => $w['complaints'],
             'note' => $z['complaints_30d'] . ' complaints in the last 30 days'],
        ];

        $score = self::combine($criteria);
        $totalWeight = array_sum($w);

        return [
            'score'     => round($score, 1),
            'band'      => self::band($score),
            'tone'      => riskTone($score),
            'criteria'  => $criteria,
            'reasons'   => self::zoneReasons(
                $criteria, $score, (int) $z['estimated_vendors'], (string) $z['name'], $totalWeight),
        ];
    }

    private static function combine(array $criteria): float
    {
        $total = 0.0;
        $weightSum = 0.0;
        foreach ($criteria as $c) {
            $total     += $c['raw'] * $c['weight'];
            $weightSum += $c['weight'];
        }
        return $weightSum > 0 ? $total / $weightSum : 0.0;
    }

    private static function band(float $s): string
    {
        return match (true) {
            $s >= 75 => 'Critical',
            $s >= 60 => 'High',
            $s >= 45 => 'Moderate',
            $s >= 30 => 'Watch',
            default  => 'Low',
        };
    }

    private static function zoneTypeNote(string $type, bool $strategic): string
    {
        $base = match ($type) {
            'junction'    => 'Vendor activity at a road junction',
            'roundabout'  => 'Vendor activity on a roundabout',
            'bus-stage'   => 'Vendor activity at a public transport stage',
            'roadside'    => 'Trading on the carriageway',
            'pavement'    => 'Trading on the footpath',
            'veranda'     => 'Trading on building verandas',
            'open-park'   => 'Trading in an open public park',
            'entrance'    => 'Trading at a building entrance',
            default       => 'Unclassified occupation',
        };
        return $strategic ? $base . ', on a strategic corridor' : $base;
    }

    /**
     * Plain-language reasons for a score.
     *
     * $totalWeight is passed in rather than recomputed: the weights come
     * from Settings, which score() has already read, and reading them a
     * second time here could disagree if they changed mid-request.
     *
     * @return array<int,string>
     */
    private static function zoneReasons(
        array $criteria,
        float $score,
        int $vendors,
        string $name,
        float $totalWeight
    ): array {
        $reasons = [];

        $ranked = $criteria;
        usort($ranked, static fn($a, $b) => ($b['raw'] * $b['weight']) <=> ($a['raw'] * $a['weight']));
        foreach (array_slice($ranked, 0, 3) as $c) {
            $reasons[] = sprintf('%s (%s, contributing %s points)',
                $c['label'], $c['note'], round($c['raw'] * $c['weight'] / $totalWeight, 1));
        }

        if ($vendors >= 40) {
            $reasons[] = sprintf('Clearing %s releases a significant trading footprint affecting %d vendors, who each require a gazetted alternative before removal.',
                $name, $vendors);
        }

        $band = self::band($score);
        $reasons[] = match ($band) {
            'Critical' => sprintf('Composite score %.1f places %s in the Critical band. These sites lead the enforcement schedule.', $score, $name),
            'High'     => sprintf('Composite score %.1f places %s in the High band. Schedule for the next enforcement cycle.', $score, $name),
            default    => sprintf('Composite score %.1f places %s in the %s band. Resourcing is proportionate to the harm caused.', $score, $name, $band),
        };

        return $reasons;
    }
}

/* =====================================================================
 * RELOCATION ENGINE
 * ===================================================================== */
final class RelocationEngine
{
    /**
     * Rank candidate markets for one vendor.
     *
     * @param array $vendor  vendors row
     * @param array $markets candidate markets (already filtered to those with space)
     * @param int   $limit   how many ranked options to return
     * @return array<int,array> ranked options, best first
     */
    /**
     * The vendor's own latitude.
     *
     * A vendor has no coordinates of their own; they are surveyed against
     * a trade site, and that site's position is what "where they trade now"
     * means. vendorForMatching() aliases it as current_lat, so accept that
     * name first and fall back to a plain lat/lng pair if a caller supplies
     * one.
     */
    public static function vendorLat(array $vendor): float
    {
        return (float) ($vendor['current_lat'] ?? $vendor['lat'] ?? 0.0);
    }

    public static function vendorLng(array $vendor): float
    {
        return (float) ($vendor['current_lng'] ?? $vendor['lng'] ?? 0.0);
    }

    public static function rankForVendor(array $vendor, array $markets, int $limit = 5): array
    {
        $options = [];

        foreach ($markets as $m) {
            $c   = self::criteriaFor($vendor, $m);
            $tot = 0.0;
            $wsum = 0.0;
            foreach ($c as $item) {
                $tot     += $item['raw'] * $item['weight'];
                $wsum    += $item['weight'];
            }
            $total = $wsum > 0 ? $tot / $wsum : 0.0;

            $options[] = [
                'market'     => $m,
                'score'      => round($total, 1),
                'criteria'   => $c,
                /* The vendor's own position comes from the trade zone they
                   are surveyed against (vendorForMatching aliases it as
                   current_lat/current_lng). Reading 'lat' here raises an
                   undefined-key warning and silently drops the proximity
                   criterion, which is the single most important one. */
                'distance'   => self::haversineKm(
                    (float) self::vendorLat($vendor), (float) self::vendorLng($vendor),
                    (float) $m['lat'], (float) $m['lng']
                ),
                'reasons'    => self::reasons($c, $total),
            ];
        }

        usort($options, static fn($a, $b) => $b['score'] <=> $a['score']);

        $ranked = array_slice($options, 0, $limit);

        /* Rank by index after sorting. Note that $ranked must be its own
           variable: array_slice() returns a copy, so writing through a
           reference to a slice taken inside the foreach header modifies a
           temporary that is thrown away, and every option comes back
           without a rank. */
        foreach ($ranked as $i => &$o) {
            $o['rank'] = $i + 1;
        }
        unset($o);

        return $ranked;
    }

    /**
     * Score one vendor against one market on all six criteria.
     * @return array<int,array{key:string,label:string,raw:float,weight:float,note:string}>
     */
    public static function criteriaFor(array $vendor, array $m): array
    {
        // Weights come from Settings; RELOCATION_WEIGHTS is the fallback.
        $w = class_exists(Settings::class) ? Settings::relocationWeights() : RELOCATION_WEIGHTS;

        /* --- 1. Proximity ---------------------------------------------
           A vendor who loses their customers overnight will not survive a
           relocation. 0 km scores full marks; beyond 10 km the score
           collapses, because past that distance the relocation is
           effectively a new business.                                 */
        $dist = self::haversineKm(
            (float) ($vendor['current_lat'] ?? $vendor['lat']),
            (float) ($vendor['current_lng'] ?? $vendor['lng']),
            (float) $m['lat'], (float) $m['lng']
        );
        $proximity = $dist <= 0.4 ? 100.0
            : max(0.0, 100.0 - (($dist - 0.4) / 9.6) * 100);

        /* --- 2. Trade fit ---------------------------------------------
           Food cannot be sold from a hardware pitch and fresh produce
           needs ground-level access. We match the vendor's trade against
           the sections the market actually has.                        */
        $tradeFit = self::tradeFit((string) $vendor['business_category'], (string) ($m['category_mix'] ?? ''), $m);

        /* --- 3. Equity -------------------------------------------------
           A vendor with no capital, dependants and no licence is the one
           for whom relocation most easily becomes destitution. This is
           the equity criterion.                                       */
        $equity = self::equityScore($vendor);

        /* --- 4. Viability ---------------------------------------------
           Sending vendors to a market that is 98% full simply moves the
           congestion indoors and leaves no headroom for the next
           enforcement wave. Prefer markets with room.                 */
        $cap = max(1, (int) $m['capacity']);
        $occ = (int) $m['registered_vendors'] / $cap * 100;
        $viability = max(0.0, 100.0 - max(0.0, $occ - 55) * 2.0);

        /* --- 5. Affordability ------------------------------------------
           Rent against turnover. KCCA states the identified vacant spaces
           are free; where a rent band applies we still test whether it
           is within the vendor's means.                               */
        $turnover = max(1, (int) $vendor['monthly_turnover_ugx']);
        $rent = (int) ($m['avg_rent_ugx'] ?? 0);
        $ratio = $rent / $turnover * 100;                     // rent as % of turnover
        $affordability = match (true) {
            $ratio <= 0  => 100.0,
            $ratio <= 5  => 92.0,
            $ratio <= 10 => 80.0,
            $ratio <= 20 => 55.0,
            $ratio <= 30 => 30.0,
            default      => 10.0,
        };

        /* --- 6. Facilities ---------------------------------------------
           A cook with no water at the pitch is not allocated a pitch in
           a market with no water.                                     */
        $facilities = self::facilityScore((string) $vendor['business_category'], $m);

        return [
            ['key' => 'proximity', 'label' => 'Proximity to the vendor’s current trading point',
             'raw' => $proximity, 'weight' => $w['proximity'],
             'note' => sprintf('%.1f km from the current unlawful pitch', $dist)],

            ['key' => 'trade_fit', 'label' => 'Suitability of the market for this trade',
             'raw' => $tradeFit, 'weight' => $w['trade_fit'],
             'note' => self::tradeFitNote((string) $vendor['business_category'], $m)],

            ['key' => 'equity', 'label' => 'Vulnerability and equity weighting',
             'raw' => $equity, 'weight' => $w['equity'],
             'note' => self::equityNote($vendor)],

            ['key' => 'viability', 'label' => 'Headroom remaining in the destination market',
             'raw' => $viability, 'weight' => $w['viability'],
             'note' => sprintf('%s%% occupied (%s of %s pitches taken)',
                 number_format($occ, 1), num((int) $m['registered_vendors']), num($cap))],

            ['key' => 'affordability', 'label' => 'Affordability against the vendor’s turnover',
             'raw' => $affordability, 'weight' => $w['affordability'],
             'note' => $rent === 0
                 ? 'No rent band — KCCA does not charge for the identified vacant spaces'
                 : sprintf('Rent band UGX %s per month against UGX %s turnover (%.0f%%)',
                     num($rent), num($turnover), $ratio)],

            ['key' => 'facilities', 'label' => 'Facilities the trade requires',
             'raw' => $facilities, 'weight' => $w['facilities'],
             'note' => self::facilityNote($m)],
        ];
    }

    /* ----------------------------------------------------------------- */

    private static function tradeFit(string $category, string $mix, array $m): float
    {
        $needs = self::tradeRequirements($category);
        $has   = array_filter($needs, static fn(string $f) => !empty($m[$f]));

        // Section labelling match: does the market advertise this kind of trade?
        $mixWords = strtolower($mix);
        $tag = self::tradeTag($category);
        $sectionMatch = $mixWords !== '' && str_contains($mixWords, $tag) ? 100.0 : 55.0;

        if ($needs === []) {
            return $sectionMatch;
        }
        $score = count($has) / count($needs) * 100;
        return ($score * 0.55) + ($sectionMatch * 0.45);
    }

    private static function tradeFitNote(string $category, array $m): string
    {
        $needs = self::tradeRequirements($category);
        if ($needs === []) {
            return 'General goods — no specialist facilities required';
        }
        $missing = array_values(array_filter($needs, static fn(string $f) => empty($m[$f])));
        $label   = ucwords(str_replace('_', ' ', $category));
        return $missing === []
            ? sprintf('%s section with every required facility available', $label)
            : sprintf('%s section — missing %s', $label, implode(', ', array_map(
                static fn($f) => str_replace('_', ' ', $f), $missing)));
    }

    /** @return array<int,string> facility columns this trade genuinely needs */
    private static function tradeRequirements(string $category): array
    {
        return match ($category) {
            'food-cooking'        => ['water_available', 'toilets', 'waste_collection', 'power_available'],
            'meat-fish'           => ['water_available', 'waste_collection', 'cold_chain', 'toilets'],
            'fresh-produce'       => ['waste_collection', 'storage_rooms', 'accessible_ramps'],
            'beverages'           => ['water_available', 'power_available', 'waste_collection'],
            'crops-grains'        => ['storage_rooms', 'waste_collection'],
            'livestock'           => ['waste_collection', 'water_available'],
            'electronics-repairs' => ['power_available'],
            'salon-services'      => ['power_available', 'water_available'],
            default               => ['waste_collection'],
        };
    }

    private static function tradeTag(string $category): string
    {
        return match ($category) {
            'food-cooking'        => 'food',
            'meat-fish'           => 'meat',
            'fresh-produce'       => 'produce',
            'beverages'           => 'beverage',
            'crops-grains'        => 'grain',
            'household-goods'     => 'household',
            'clothing-textiles'   => 'clothing',
            'electronics-repairs' => 'electronics',
            'salon-services'      => 'salon',
            'mobile-services'     => 'telecoms',
            'handicraft'          => 'handicraft',
            'livestock'           => 'poultry',
            default               => 'general',
        };
    }

    private static function facilityScore(string $category, array $m): float
    {
        $needs = self::tradeRequirements($category);
        if ($needs === []) {
            return 80.0;
        }
        $have = count(array_filter($needs, static fn(string $f) => !empty($m[$f])));
        $base = $have / count($needs) * 100;
        // Accessible ramps matter for vendors with a disability regardless of trade.
        if (!empty($m['accessible_ramps'])) {
            $base = min(100.0, $base + 5);
        }
        return $base;
    }

    private static function facilityNote(array $m): string
    {
        $on  = [];
        $off = [];
        foreach ([
            'water_available'  => 'water',
            'power_available'  => 'power',
            'toilets'          => 'toilets',
            'waste_collection' => 'waste collection',
            'storage_rooms'    => 'storage',
            'cold_chain'       => 'cold chain',
            'accessible_ramps' => 'accessible ramps',
        ] as $col => $label) {
            if (!empty($m[$col])) { $on[] = $label; } else { $off[] = $label; }
        }
        return 'Available: ' . implode(', ', $on) . '. Not available: ' . implode(', ', $off) . '.';
    }

    /* ----------------------------------------------------------------- */

    private static function equityScore(array $v): float
    {
        $score = 30.0;

        if (!empty($v['is_person_with_disability'])) $score += 22;
        if (!empty($v['is_single_parent']))           $score += 18;
        if (!empty($v['is_youth']))                   $score += 8;
        if (!empty($v['is_veteran_ex_enforcee']))     $score += 12;

        $dep = (int) ($v['dependants'] ?? 0);
        $score += min(20, $dep * 2.5);                      // dependants weighed

        $hh = (int) ($v['household_size'] ?? 1);
        $score += min(10, max(0, $hh - 1) * 2.5);

        // Low capital means a bad relocation ends the business entirely.
        $capital = (int) ($v['startup_capital_ugx'] ?? 0);
        $turnover = max(1, (int) ($v['monthly_turnover_ugx'] ?? 1));
        if ($capital < 150_000)        $score += 12;
        elseif ($capital < 400_000)    $score += 8;
        elseif ($capital < 900_000)    $score += 4;

        // Long-standing traders have an established clientele to lose.
        $years = max(0, (int) date('Y') - (int) ($v['started_year'] ?? (int) date('Y')));
        if ($years >= 15)     $score += 8;
        elseif ($years >= 8)  $score += 5;
        elseif ($years >= 3)  $score += 2;

        if (empty($v['has_trading_licence'])) $score += 6;

        return min(100.0, $score);
    }

    private static function equityNote(array $v): string
    {
        $bits = [];
        if (!empty($v['is_person_with_disability'])) $bits[] = 'person with disability';
        if (!empty($v['is_single_parent']))           $bits[] = 'single parent';
        if (!empty($v['is_youth']))                   $bits[] = 'youth';
        if (!empty($v['is_veteran_ex_enforcee']))     $bits[] = 'former enforcement agent';
        if ((int) ($v['dependants'] ?? 0) > 0)         $bits[] = (int) $v['dependants'] . ' dependants';
        if (empty($v['has_trading_licence']))         $bits[] = 'no current trading licence';

        $years = max(0, (int) date('Y') - (int) ($v['started_year'] ?? (int) date('Y')));
        if ($years >= 3) {
            $bits[] = $years . ' years in trade';
        }

        return $bits === [] ? 'No elevated vulnerability factors recorded' : ucfirst(implode(', ', $bits));
    }

    /* ----------------------------------------------------------------- */

    /** @return array<int,string> */
    private static function reasons(array $criteria, float $total): array
    {
        $ranked = $criteria;
        usort($ranked, static fn($a, $b) => ($b['raw'] * $b['weight']) <=> ($a['raw'] * $a['weight']));
        $reasons = [];
        foreach (array_slice($ranked, 0, 2) as $c) {
            $reasons[] = sprintf('%s: %s', $c['label'], $c['note']);
        }
        $weak = array_values(array_filter($ranked, static fn($c) => $c['raw'] < 45));
        if ($weak !== []) {
            $reasons[] = 'Weakest criterion — ' . $weak[0]['label'] . ': ' . $weak[0]['note'];
        }
        $reasons[] = sprintf('Weighted total %.1f out of 100 across six criteria.', $total);

        return $reasons;
    }

    /* ----------------------------------------------------------------- */

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0;                                   // mean Earth radius, km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}