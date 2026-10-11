<?php
/**
 * KCCA VRSP — Database seeder.
 *
 * Division figures, market vacancy totals, Usafi Market's 3,000/1,600
 * capacity and registration, Kamwokya's 250 identified spaces and the
 * 17 public / 69 private market counts are OFFICIAL KCCA figures and
 * reconcile exactly with the citywide totals.
 *
 * Operational layers (individual pitches, trade zones, vendor records,
 * enforcement actions) are generated at realistic Kampala scale and are
 * marked as such in the interface through the `data_basis` column.
 *
 *   php database/seed.php            seed (drops and rebuilds)
 *   php database/seed.php --schema   apply schema only
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$schemaOnly = in_array('--schema', $argv, true);

$pdo = Database::pdo();

echo "KCCA VRSP seeder — " . APP_VERSION . PHP_EOL;
echo str_repeat('=', 64) . PHP_EOL;

/* ---------------------------------------------------------------------
 * 1. Schema
 * ------------------------------------------------------------------ */
echo "[1/13] Applying schema … ";
$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($sql);
echo "done\n";

if ($schemaOnly) {
    echo "Schema only. Stopping.\n";
    exit(0);
}

/* ---------------------------------------------------------------------
 * 2. Divisions — KCCA Strategic Plan FY2025/26-29/30, Table 1.1
 * ------------------------------------------------------------------ */
echo "[2/13] Seeding divisions … ";
Database::run('DELETE FROM divisions');
Database::run('ALTER TABLE divisions AUTO_INCREMENT = 1');

$divisions = [
    ['code' => 'CTR', 'name' => 'Kampala Central', 'mayor' => 'Salim Uhuru',
     'town_clerk' => 'Douglas Nsubuga', 'area_sqkm' => 15.2, 'parishes' => 20, 'lc1_wards' => 135,
     'population' => 81658, 'lat' => 0.316667, 'lng' => 32.583333,
     'summary' => 'The smallest of the five divisions by area, and the busiest by far. It contains the '
        . 'Central Business District, Old Kampala, Nakasero, Kololo, Kamwokya, Kisenyi and the '
        . 'Industrial Area, and carries the densest concentration of street vending in the city.'],

    ['code' => 'KWM', 'name' => 'Kawempe', 'mayor' => 'Dr. Emmanuel Serunjogi',
     'town_clerk' => 'Geoffrey Rwakabala', 'area_sqkm' => 30.9, 'parishes' => 22, 'lc1_wards' => 141,
     'population' => 390170, 'lat' => 0.379200, 'lng' => 32.557400,
     'summary' => 'The northern division, home to Makerere University, Mulago, Wandegeya, Bwaise, Kyebando '
        . 'and Kawempe itself. High student and staff footfall supports substantial roadside trade around '
        . 'the Makerere main gate and the Bwaise and Wandegeya junctions.'],

    ['code' => 'RBG', 'name' => 'Rubaga', 'mayor' => 'Mberaze Mawula Zacchy',
     'town_clerk' => 'John Bosco Bashinyora', 'area_sqkm' => 58.6, 'parishes' => 13, 'lc1_wards' => 133,
     'population' => 410400, 'lat' => 0.345000, 'lng' => 32.548000,
     'summary' => 'The largest division by area, taking in Kasubi, Nateete, Kibuli, Mengo and the western '
        . 'hills. Trading concentrates on the Nateete and Kasubi approaches and along the Kibuli corridor.'],

    ['code' => 'MKY', 'name' => 'Makindye', 'mayor' => 'Yasin Omar',
     'town_clerk' => 'Thomas Ssentongo', 'area_sqkm' => 52.8, 'parishes' => 23, 'lc1_wards' => 235,
     'population' => 486762, 'lat' => 0.305000, 'lng' => 32.585000,
     'summary' => 'The most populous division, covering the southern and south-western slopes. Namungoona, '
        . 'Kanjokya, Kabalagala, Kibuli and the Katwe approaches carry heavy commuter and vendor activity.'],

    ['code' => 'NKW', 'name' => 'Nakawa', 'mayor' => 'Buken Ali',
     'town_clerk' => 'Moses Ssempa', 'area_sqkm' => 36.9, 'parishes' => 23, 'lc1_wards' => 641,
     'population' => 428732, 'lat' => 0.362000, 'lng' => 32.612000,
     'summary' => 'The eastern division, containing Naguru, Bukoto, Kireka, Bugolobi, Luzira and the KCCA Bus '
        . 'Terminal. Highest LC1 count in the city and extensive informal trade along the Kireka, Naguru and '
        . 'Kampala–Jinja approaches.'],
];

foreach ($divisions as $d) {
    Database::insert('divisions', $d);
}
$divId = [];
foreach (Database::all('SELECT id, code FROM divisions') as $r) {
    $divId[$r['code']] = (int) $r['id'];
}
printf("done (%d)\n", count($divisions));

/* ---------------------------------------------------------------------
 * 3. Parishes — 101 in total, per the Strategic Plan
 * ------------------------------------------------------------------ */
echo "[3/13] Seeding parishes … ";
Database::run('DELETE FROM parishes');

$parishList = [
    'CTR' => ['Bukesa','Civic Centre','Industrial Area','Kagugube','Kamwokya I','Kamwokya II',
              'Kisenyi I','Kisenyi II','Kisenyi III','Kololo','Mengo','Nakasero','Nakivubo',
              'Old Kampala','KCCA','Buikwe','Kawau','Kimwanyi','Kira','Nalufenya'],
    'KWM' => ['Bwaise I','Bwaise II','Bwaise III','Kanyanya','Kazo','Kyebando','Kawempe I',
              'Kawempe II','Makerere I','Makerere II','Makerere III','Komambogo','Kikaya','Mpererwe',
              'Mulago I','Mulago II','Mulago III','Wandegeya','Mulago Central','Kira West','Kasaato','Kawempe North'],
    'RBG' => ['Kasubi','Kasubi Tanwa','Kibuli','Kibuli East','Lubaga','Mengo','Nabukalu',
              'Nateete','Nateete West','Ggaba','Bulenga','Buwambo','Kawongo'],
    'MKY' => ['Bukoto','Kiswa','Kisubi','Kanjokya','Katwe','Katwe North','Kawempe','Kibuli',
              'Kisenyi','Mengo','Nabugabo','Naguru East','Naguru West','Nakasero','Nambuye','Nampanya',
              'Ggaba','Kabowa','Kamwokya','Kasaato','Katwe West','Kirudu','Kyanjja'],
    'NKW' => ['Bukoto East','Bukoto West','Bunagana','Buwagi','Dvajire','Gombe','Kampala Central',
              'Kireka','Kiswa','Kizinda','Kololo','Kyaninga','Luwala','Malika','Mukasa','Naguru',
              'Nakawa','Nakulabye','Nsambya','Ntinda','Upper Kololo','Wanale','Kiswa East'],
];

$totalParishes = 0;
foreach ($parishList as $code => $names) {
    foreach ($names as $name) {
        Database::insert('parishes', ['division_id' => $divId[$code], 'name' => $name]);
        $totalParishes++;
    }
}
printf("done (%d)\n", $totalParishes);

/* ---------------------------------------------------------------------
 * 4. Markets
 *    Public market vacant spaces must total exactly 2,520 (KCCA, Feb 2026).
 *    Usafi Market: capacity 3,000 / 1,600 registered  (KCCA, Feb 2026).
 *    Kamwokya Market: 250 identified spaces           (KCCA, Feb 2026).
 * ------------------------------------------------------------------ */
echo "[4/13] Seeding markets … ";
Database::run('DELETE FROM markets');
Database::run('ALTER TABLE markets AUTO_INCREMENT = 1');

/** category_mix drives the relocation engine's trade-fit criterion. */
$mixAll = 'Food, produce, household goods, clothing, footwear, general merchandise';
$mixFood = 'Food court, beverages, fresh produce, meat and fish, general merchandise';
$mixProduce = 'Fresh produce, crops and grains, food, household goods';
$mixMixed = 'General merchandise, food, clothing and textiles, produce, footwear';
$mixWholesale = 'Wholesale produce, hardware, general merchandise, packaging';

$publicMarkets = [
    // code, name, division, parish, capacity, registered, vacant, basis, facilities, opened
    ['USF-01','Usafi Market','CTR','Kisenyi I', 3000, 1600, 1400,'official',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>0,'accessible_ramps'=>1], 2018,
        'Capacity 3,000 with 1,600 registered vendors, as confirmed by the Supervisor of Market Development. '
        .'Holds by far the largest single block of identified vacant public space in the city.'],

    ['KMW-01','Kamwokya Market','CTR','Kamwokya II', 1600, 1350, 250,'official',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>1,'accessible_ramps'=>1], 2019,
        'KCCA identified an open section with at least 250 available trading spaces ready for immediate allocation.'],

    ['KBL-01','Kabaale Market','CTR','Kisenyi I', 1800, 1610, 190,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>0,'accessible_ramps'=>0], 2014,
        'Central Division’s largest wholesale market, immediately behind KCCA.'],

    ['KSK-01','Kiseka Market','CTR','Kisenyi II', 1200, 1120, 80,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2021,
        'Recovered by KCCA following the Presidential Directive on markets; an open section is being developed.'],

    ['STG-01','St. George’s Market (Nakasero)','CTR','Nakasero', 2200, 2075, 125,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>1,'accessible_ramps'=>0], 2011,
        'The oldest market in the Central Division, on Nakasero Hill.'],

    ['WDG-01','Wandegeya Market','KWM','Wandegeya', 1500, 1390, 110,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>0,'accessible_ramps'=>1], 2017,
        'Cited by the Executive Director in September 2026 as a market with free trading spaces.'],

    ['NKW-01','Nakawa Market','NKW','Nakawa', 900, 830, 70,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2016, null],

    ['BGL-01','Bugolobi Market','NKW','Bukoto East', 800, 745, 55,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2015, null],

    ['LUZ-01','Luzira Market','NKW','Kiswa', 1400, 1365, 35,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>0,'accessible_ramps'=>1], 2013,
        'Cited by the Executive Director in September 2026 as a market with free trading spaces.'],

    ['NTT-01','Nateete Market','RBG','Nateete', 700, 660, 40,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2016, null],

    ['BSG-01','Busega Market','NKW','Nsambya', 2500, 2470, 30,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>1,
         'cold_chain'=>1,'accessible_ramps'=>1], 2023,
        'Market leaders at Busega Market have publicly invited street traders to take up the available stalls.'],

    ['NSB-01','Nsambya Market','NKW','Nsambya', 1100, 1075, 25,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2014, null],

    ['MKR-01','Makerere Market','KWM','Makerere III', 600, 580, 20,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2015, null],

    ['GGB-01','Ggaba Market','NKW','Ggaba', 750, 735, 15,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2017, null],

    ['KBL-02','Kibuli Market','MKY','Kibuli', 1000, 975, 25,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2016, null],

    ['KTW-01','Katwe Market','NKW','Katwe', 650, 630, 20,'derived',
        ['water_available'=>1,'power_available'=>1,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2018, null],

    ['MNY-01','Munyonyo Market','NKW','Nakawa', 850, 820, 30,'derived',
        ['water_available'=>1,'power_available'=>0,'toilets'=>1,'waste_collection'=>1,'storage_rooms'=>0,
         'cold_chain'=>0,'accessible_ramps'=>0], 2019, null],
];

$mixedByName = [
    'CTR' => $mixMixed, 'KWM' => $mixAll, 'RBG' => $mixAll, 'MKY' => $mixMixed, 'NKW' => $mixMixed,
];

$publicVacantTotal = 0;
foreach ($publicMarkets as $m) {
    [$code,$name,$div,$parish,$cap,$reg,$vac,$basis,$fac,$opened,$note] = $m;
    $mix = match ($name) {
        'Usafi Market'          => $mixFood,
        'Kamwokya Market'       => $mixMixed,
        'St. George’s Market (Nakasero)' => $mixWholesale,
        'Kabaale Market'        => $mixWholesale,
        'Nsambya Market', 'Ggaba Market' => $mixProduce,
        'Bugolobi Market', 'Luzira Market' => $mixFood,
        default => $mixedByName[$div],
    };

    Database::insert('markets', array_merge([
        'code' => $code, 'name' => $name, 'division_id' => $divId[$div], 'category' => 'public',
        'market_type' => 'permanent', 'parish' => $parish,
        'lat' => self_lat($code), 'lng' => self_lng($code),
        'capacity' => $cap, 'registered_vendors' => $reg, 'vacant_spaces' => $vac,
        'data_basis' => $basis, 'category_mix' => $mix, 'opened_year' => $opened,
        'condition_score' => seededRandom($code, 2, 5),
        'admin_name' => 'KCCA Market Administration',
        'admin_phone' => '0417 900 100',
        'status' => $name === 'Kiseka Market' ? 'upgrading' : 'operational',
        'notes' => $note,
        'source_note' => $basis === 'official'
            ? 'Figures published by KCCA'
            : 'Derived from KCCA citywide market totals',
    ], $fac));
    $publicVacantTotal += $vac;
}

// Real coordinates for the named markets; small offsets elsewhere.
function self_lat(string $code): float {
    static $coords = [
        'USF-01' => [0.309512, 32.584455],   // Usafi Market, 3rd Street
        'KMW-01' => [0.312980, 32.597520],   // Kamwokya Market
        'KBL-01' => [0.305480, 32.589920],   // Kabaale Road
        'KSK-01' => [0.302490, 32.587530],   // Kiseka
        'STG-01' => [0.307980, 32.590510],   // Nakasero
        'WDG-01' => [0.329010, 32.568010],   // Wandegeya
        'NKW-01' => [0.362000, 32.612000],   // Nakawa
        'BGL-01' => [0.318010, 32.616020],   // Bugolobi
        'LUZ-01' => [0.348010, 32.628020],   // Luzira
        'NTT-01' => [0.334520, 32.583010],   // Nateete
        'BSG-01' => [0.313480, 32.629010],   // Busega
        'NSB-01' => [0.365010, 32.610010],   // Nsambya
        'MKR-01' => [0.371010, 32.562010],   // Makerere
        'GGB-01' => [0.292010, 32.622010],   // Ggaba
        'KBL-02' => [0.318020, 32.582010],   // Kibuli
        'KTW-01' => [0.319520, 32.635010],   // Katwe
        'MNY-01' => [0.339010, 32.636010],   // Munyonyo
    ];
    return $coords[$code][0] ?? 0.316667;
}
function self_lng(string $code): float {
    static $coords = [
        'USF-01' => 32.584455, 'KMW-01' => 32.597520, 'KBL-01' => 32.589920, 'KSK-01' => 32.587530,
        'STG-01' => 32.590510, 'WDG-01' => 32.568010, 'NKW-01' => 32.612000, 'BGL-01' => 32.616020,
        'LUZ-01' => 32.628020, 'NTT-01' => 32.583010, 'BSG-01' => 32.629010, 'NSB-01' => 32.610010,
        'MKR-01' => 32.562010, 'GGB-01' => 32.622010, 'KBL-02' => 32.582010, 'KTW-01' => 32.635010,
        'MNY-01' => 32.636010,
    ];
    return $coords[$code] ?? 32.583333;
}

// ---- Private markets: 69 registered markets holding 1,980 vacant spaces ----
$privatePlaces = [
    ['Nakasero','CTR'],['Kisenyi','CTR'],['Kamwokya','CTR'],['Kawempe','KWM'],['Wandegeya','KWM'],
    ['Makerere','KWM'],['Mulago','KWM'],['Kibuli','MKY'],['Katwe','MKY'],['Namungoona','MKY'],
    ['Kanjokya','MKY'],['Ggaba','NKW'],['Bugolobi','NKW'],['Nakawa','NKW'],['Kireka','NKW'],
    ['Ntinda','NKW'],['Naguru','NKW'],['Luzira','NKW'],['Bukoto','NKW'],['Nsambya','NKW'],
    ['Kasubi','RBG'],['Nateete','RBG'],['Mengo','RBG'],['Lubaga','RBG'],['Kisenyi II','CTR'],
    ['Buikwe','CTR'],['Kawau','CTR'],['Kira','CTR'],['Kololo','CTR'],['Bukesa','CTR'],
    ['Kyebando','KWM'],['Bwaise','KWM'],['Kanyanya','KWM'],['Kazo','KWM'],['Mpererwe','KWM'],
    ['Kiswa','NKW'],['Kisubi','MKY'],['Nabugabo','MKY'],['Kabowa','MKY'],['Kirudu','MKY'],
    ['Kyanja','MKY'],['Kiruddu','MKY'],['Naguru East','NKW'],['Naguru West','NKW'],['Bukoto East','NKW'],
    ['Kireka Road','NKW'],['Nakawa B','NKW'],['Buwagi','NKW'],['Malika','NKW'],['Mukasa','NKW'],
    ['Kampala Central','NKW'],['Gombe','NKW'],['Dvajire','NKW'],['Luwala','NKW'],['Wanale','NKW'],
    ['Kasubi Tanwa','RBG'],['Nabukalu','RBG'],['Nateete West','RBG'],['Ggaba West','RBG'],['Bulenga','RBG'],
    ['Mengo Hill','RBG'],['Kibuli East','MKY'],['Kampala Road','CTR'],['Kafumbe','CTR'],['Butabika','NKW'],
    ['Nsambya Hill','NKW'],['Kira Road','KWM'],['Kamwokya II','CTR'],['Industrial Area','CTR'],
];

// KCCA publishes at least 1,980 vacant spaces across 69 private markets.
// Distribute that total deterministically so the seeded sum reconciles exactly.
$privateTotalVacant = 1980;
$privateVacantTarget = [];
{
    $n = count($privatePlaces);
    $base = intdiv($privateTotalVacant, $n);
    $rem  = $privateTotalVacant % $n;
    for ($i = 0; $i < $n; $i++) {
        $privateVacantTarget[$i] = $base + ($i < $rem ? 1 : 0);
    }
    // Give the largest private markets a larger share, as would be expected,
    // taking the difference from mid-sized ones so the total is unchanged.
    $offset = 0;
    foreach ([3, 14, 24] as $boost) {
        $privateVacantTarget[$boost] += 40;
        $offset += 40;
    }
    for ($i = 0; $i < $n && $offset > 0; $i += 9) {
        if (in_array($i, [3, 14, 24], true)) {
            continue;
        }
        $take = min(25, $offset, $privateVacantTarget[$i] - 5);
        $privateVacantTarget[$i] -= $take;
        $offset -= $take;
    }
}

$privateVacantTotal = 0;
foreach ($privatePlaces as $i => [$place, $div]) {
    $vac = $privateVacantTarget[$i];
    $cap = max($vac + 40, seededRandom('pc' . $i, 90, 420));
    $reg = $cap - $vac;

    Database::insert('markets', [
        'code' => 'PRV-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
        'name' => $place . ' Private Market',
        'division_id' => $divId[$div], 'category' => 'private', 'market_type' => 'permanent',
        'ownership' => 'Privately operated; register of vendors kept by the operator (Markets Act 2023, s.17(2))',
        'parish' => $place,
        'lat' => round(0.280000 + seededRandom('plat' . $i, 0, 140000) / 1000000, 6),
        'lng' => round(32.510000 + seededRandom('plng' . $i, 0, 150000) / 1000000, 6),
        'capacity' => $cap, 'registered_vendors' => $reg, 'vacant_spaces' => $vac,
        'data_basis' => 'estimated',
        'water_available' => seededRandom('pw' . $i, 0, 1),
        'power_available' => seededRandom('pp' . $i, 0, 1),
        'toilets'         => seededRandom('pt' . $i, 0, 1),
        'waste_collection'=> seededRandom('pd' . $i, 0, 1),
        'storage_rooms'   => seededRandom('ps' . $i, 0, 1),
        'cold_chain'      => seededRandom('pc2' . $i, 0, 1),
        'accessible_ramps'=> seededRandom('pa' . $i, 0, 1),
        'opened_year'     => seededRandom('py' . $i, 2005, 2024),
        'condition_score' => seededRandom('pcond' . $i, 2, 5),
        'admin_name'      => $place . ' Market Administrator',
        'admin_phone'     => '0' . seededRandom('pp2' . $i, 700000000, 799999999),
        'status'          => 'operational',
        'source_note'     => 'Aggregate of 1,980 vacant spaces in 69 private markets is published by KCCA; '
                            . 'individual market figures are modelled.',
    ]);
    $privateVacantTotal += $vac;
}

printf("done — %d public markets (%d vacant), %d private markets (%d vacant)\n",
    count($publicMarkets), $publicVacantTotal, count($privatePlaces), $privateVacantTotal);

/* ---------------------------------------------------------------------
 * 5. Market pitches — the register KCCA holds for public markets.
 *    Under the Markets Act 2023 the register of vendors for a private
 *    market is kept by the market operator, so private pitches are not
 *    seeded here.
 * ------------------------------------------------------------------ */
echo "[5/13] Seeding public market pitches … ";
Database::run('DELETE FROM market_spaces');
Database::run('ALTER TABLE market_spaces AUTO_INCREMENT = 1');

$sections = ['A','B','C','D','E','F'];
$batch = [];
$totalPitches = 0;

/* Pitches are given coordinates so the register can be drawn stall by
   stall on a map. Each market is laid out as a grid, grouped into six
   horizontal bands — one per section — so sections are spatially
   contiguous, which is how a market actually works and how a trader
   reading the map will expect it. */
$SPACING_LAT = 0.000090;   // roughly 10 metres
$SPACING_LNG = 0.000110;

foreach (Database::all("SELECT id, code, capacity, vacant_spaces, lat, lng FROM markets WHERE category='public'") as $m) {
    $vacant = (int) $m['vacant_spaces'];
    $cap    = (int) $m['capacity'];

    // A market is wider than it is deep. cols/rows give a plausible
    // footprint rather than a line.
    $cols  = max(2, (int) ceil(sqrt($cap * 1.35)));
    $rows  = max(1, (int) ceil($cap / $cols))
    ;
    $rowsPerSection = max(1, (int) ceil($rows / 6));

    $lat0 = (float) $m['lat'] - (($rows - 1) * $SPACING_LAT) / 2;
    $lng0 = (float) $m['lng'] - (($cols - 1) * $SPACING_LNG) / 2;

    for ($i = 1; $i <= $cap; $i++) {
        $isVacant = $i <= $vacant;             // vacant pitches first within each market
        $status = $isVacant ? 'vacant'
            : (seededRandom($m['code'] . 'm' . $i, 0, 100) < 3 ? 'under-maintenance' : 'occupied');

        $col  = ($i - 1) % $cols;
        $row  = intdiv($i - 1, $cols);
        $sec  = $sections[min(5, (int) floor($row / $rowsPerSection))];

        $batch[] = [
            'market_id'   => (int) $m['id'],
            'space_code'  => sprintf('%s-%04d', $m['code'], $i),
            'section'     => 'Section ' . $sec,
            'lat'         => lnglat($lat0 + $row * $SPACING_LAT),
            'lng'         => lnglat($lng0 + $col * $SPACING_LNG),
            'size_sqm'    => [9.0, 12.0, 16.0, 20.0, 25.0][seededRandom($m['code'] . 's' . $i, 0, 4)],
            'status'      => $status,
            // KCCA states the identified vacant spaces carry no charge.
            'monthly_rent_ugx' => 0,
            'has_water'   => seededRandom($m['code'] . 'w' . $i, 0, 100) < 62 ? 1 : 0,
            'has_power'   => seededRandom($m['code'] . 'p' . $i, 0, 100) < 45 ? 1 : 0,
            'near_toilet' => seededRandom($m['code'] . 't' . $i, 0, 100) < 78 ? 1 : 0,
            'step_free'   => seededRandom($m['code'] . 'f' . $i, 0, 100) < 35 ? 1 : 0,
        ];

        if (count($batch) >= 1000) {
            Database::insertMany('market_spaces', $batch);
            $totalPitches += count($batch);
            $batch = [];
        }
    }
}
if ($batch) {
    Database::insertMany('market_spaces', $batch);
    $totalPitches += count($batch);
}
printf("done (%d pitches, each placed on the market grid)\n", $totalPitches);

/* ---------------------------------------------------------------------
 * 6. Trade zones — the affected areas to be vacated
 * ------------------------------------------------------------------ */
echo "[6/13] Seeding trade zones … ";
Database::run('DELETE FROM trade_zones');
Database::run('ALTER TABLE trade_zones AUTO_INCREMENT = 1');

$zones = [
    ['ZR-01','Luwum Street CBD','CTR','Civic Centre','Luwum Street','junction',0.310040,32.592130,520,96,34,86,148,'yes','KCCA City Hall','Mon–Sat 06:00–20:00','Peak 07:00–10:00, 16:00–19:00',$mixMixed,'notice-issued','2026-02-03','2026-02-19'],
    ['ZR-02','Kisenyi–Kamwokya Link','CTR','Kisenyi I','Kisenyi Road','roadside',0.307560,32.592480,410,92,28,74,96,'yes','Kisenyi Health Centre IV','Mon–Sat 05:30–19:00','Peak 07:00–09:00, 15:00–18:00',$mixFood,'clearing','2026-02-03','2026-02-19'],
    ['ZR-03','Kabaale Road','CTR','Kisenyi I','Kabaale Road','roadside',0.305420,32.589880,380,88,31,82,110,'yes','Kabaale Market entrance','Mon–Sun 06:00–20:00','Peak 06:00–09:00, 16:00–19:00',$mixWholesale,'relocating','2026-02-03','2026-02-19'],
    ['ZR-04','Namirembe Road','CTR','Mengo','Namirembe Road','pavement',0.316720,32.590480,340,84,19,68,94,'yes','St Paul’s Cathedral','Mon–Sun 06:00–19:00','Peak 08:00–12:00',$mixProduce,'notice-issued','2026-02-03','2026-02-19'],
    ['ZR-05','City Hall Approach','CTR','Civic Centre','Apollo Kaggwa Road','entrance',0.308640,32.586050,290,78,41,58,72,'yes','KCCA City Hall','Mon–Fri 08:00–17:00','Peak 08:00–16:00',$mixMixed,'notice-issued','2026-02-03','2026-02-19'],
    ['ZR-06','Nakivubo Road','CTR','Nakivubo','Nakivubo Road','veranda',0.310280,32.591240,310,81,24,77,88,'no','Nakivubo War Memorial','Mon–Sat 07:00–20:00','Peak 09:00–18:00',$mixMixed,'notice-issued','2026-02-03','2026-02-19'],
    ['ZR-07','Yusuf Lule Road','CTR','Nakasero','Yusuf Lule Road','roadside',0.312480,32.587720,265,76,22,64,70,'yes','Kampala City Festival route','Mon–Sat 07:00–19:00','Peak 07:00–10:00, 15:00–18:00',$mixMixed,'relocating','2026-02-03','2026-02-19'],
    ['ZR-08','Kisenyi Market Frontage','CTR','Kisenyi II','Kisenyi Road','pavement',0.305960,32.589180,215,74,17,58,62,'no','Kisenyi Market','Mon–Sun 06:00–20:00','Peak 07:00–18:00',$mixFood,'relocating','2026-02-03','2026-02-19'],
    ['ZR-09','Industrial Area Footpaths','CTR','Industrial Area','Kafumbe Road','pavement',0.314820,32.598640,195,69,15,52,66,'no','KCCA Industrial Area workshops','Mon–Sat 06:00–18:00','Peak 06:00–08:00, 16:00–18:00',$mixAll,'under-survey',null,null],
    ['ZR-10','Buikwe Road Verge','CTR','Buikwe','Buikwe Road','roadside',0.309180,32.586320,148,63,21,44,58,'no','','Mon–Sat 07:00–19:00','Peak 08:00–18:00',$mixAll,'under-survey',null,null],

    ['ZR-11','Makerere Main Gate','KWM','Makerere III','Makerere Hill Road','entrance',0.371180,32.562080,390,94,33,80,132,'yes','Makerere University Main Building','Mon–Sat 07:00–20:00','Peak 08:00–10:00, 17:00–20:00',$mixAll,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-12','Bwaise Roundabout','KWM','Bwaise II','Bwaise Road','roundabout',0.348060,32.565120,275,71,36,61,79,'yes','Bwaise Community Health Centre','Mon–Sun all day','Peak 07:00–09:00, 17:00–19:00',$mixFood,'clearing','2026-02-05','2026-02-19'],
    ['ZR-13','Wandegeya Roundabout','KWM','Wandegeya','Kawempe Road','roundabout',0.329040,32.568040,255,79,27,57,84,'yes','Wandegeya Market','Mon–Sun 06:00–20:00','Peak 07:00–09:00, 16:00–19:00',$mixMixed,'relocating','2026-02-05','2026-02-19'],
    ['ZR-14','Bombo Road','KWM','Mulago II','Bombo Road','roadside',0.351020,32.569040,225,68,26,50,63,'no','Mulago National Referral Hospital','Mon–Sat 06:00–20:00','Peak 08:00–18:00',$mixProduce,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-15','Kawempe–Lugogo Road','KWM','Kawempe I','Kawempe Road','roadside',0.363220,32.560540,185,61,19,44,55,'no','','Mon–Sat 07:00–19:00','Peak 07:00–10:00',$mixAll,'under-survey',null,null],
    ['ZR-16','Nateete–Wakiso Link','KWM','Nateete','Nateete Road','roadside',0.337420,32.582640,162,58,17,42,48,'no','','Mon–Sat 06:00–19:00','Peak 07:00–09:00, 16:00–18:00',$mixProduce,'under-survey',null,null],
    ['ZR-17','Kira Road Verge','KWM','Kira West','Kira Road','pavement',0.374240,32.576120,128,55,14,38,52,'yes','Kira Road clinics','Mon–Sat 07:00–19:00','Peak 08:00–17:00',$mixAll,'under-survey',null,null],

    ['ZR-18','Kireka Road','NKW','Kireka','Kireka Road','roadside',0.342020,32.635040,360,86,30,74,96,'yes','KCCA Bus Terminal access','Mon–Sun 05:00–21:00','Peak 05:00–09:00, 17:00–21:00',$mixAll,'clearing','2026-02-05','2026-02-19'],
    ['ZR-19','Ntinda Roundabout','NKW','Ntinda','Naguru Road','roundabout',0.337020,32.590540,295,82,33,66,82,'yes','Ntinda Primary School','Mon–Sun 06:00–20:00','Peak 07:00–09:00, 16:00–19:00',$mixMixed,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-20','KCCA Bus Terminal Stage','NKW','Kireka','Kireka Road','bus-stage',0.340520,32.630020,330,77,29,59,91,'yes','KCCA Bus Terminal','Mon–Sun 05:00–21:00','Peak 05:00–09:00, 17:00–21:00',$mixFood,'relocating','2026-02-05','2026-02-19'],
    ['ZR-21','Naguru Road','NKW','Naguru West','Naguru Road','pavement',0.335040,32.605480,245,66,18,52,68,'no','Naguru Hospital','Mon–Sat 06:00–20:00','Peak 07:00–18:00',$mixProduce,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-22','Bukoto Road','NKW','Bukoto West','Bukoto Road','roadside',0.317520,32.618540,205,61,21,47,60,'no','','Mon–Sat 07:00–20:00','Peak 07:00–10:00, 16:00–19:00',$mixAll,'under-survey',null,null],
    ['ZR-23','Kizinga Street','NKW','Bukoto East','Kizinga Street','pavement',0.314480,32.616220,138,54,16,35,44,'no','Kabaka’s Palace approach','Mon–Sun 07:00–19:00','Peak 09:00–17:00',$mixMixed,'under-survey',null,null],
    ['ZR-24','Kazzindira Bus Stage','NKW','Mukasa','Kazzindira Road','bus-stage',0.346120,32.620480,178,64,24,41,56,'yes','Kazzindira Primary School','Mon–Sat 06:00–20:00','Peak 07:00–09:00, 17:00–19:00',$mixFood,'under-survey',null,null],
    ['ZR-25','Upper Kololo Heights','NKW','Upper Kololo','Kololo Hill Drive','pavement',0.329820,32.612480,112,48,12,30,38,'no','','Mon–Sat 09:00–18:00','Peak 10:00–16:00',$mixMixed,'litigation-hold','2026-02-05','2026-02-19'],

    ['ZR-26','Kasubi Market Approach','RBG','Kasubi','Kasubi Road','roadside',0.334540,32.573480,235,72,22,58,74,'yes','Kasubi Tombs','Mon–Sun 06:00–19:00','Peak 07:00–18:00',$mixProduce,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-27','Nateete Roundabout','RBG','Nateete','Nateete Road','roundabout',0.334520,32.583020,205,75,30,52,68,'yes','Nateete Community School','Mon–Sun 06:00–20:00','Peak 07:00–09:00, 16:00–19:00',$mixMixed,'relocating','2026-02-05','2026-02-19'],
    ['ZR-28','Mengo Mosque Road','RBG','Mengo','Kibuli Road','pavement',0.301020,32.570040,168,59,15,44,50,'yes','Mengo Mosque','Mon–Sun 05:00–21:00','Peak 05:00–09:00, 17:00–21:00',$mixFood,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-29','Kibuli Road Descent','RBG','Kibuli','Kibuli Road','roadside',0.318020,32.582020,142,57,18,39,47,'no','Kibuli Hospital','Mon–Sat 07:00–19:00','Peak 07:00–10:00',$mixProduce,'under-survey',null,null],
    ['ZR-30','Ggaba Roundabout','RBG','Ggaba','Ggaba Road','roundabout',0.292020,32.622020,118,51,26,33,42,'no','Ggaba Health Centre III','Mon–Sat 07:00–19:00','Peak 07:00–09:00, 17:00–19:00',$mixAll,'under-survey',null,null],

    ['ZR-31','Namungoona Road','MKY','Namungoona','Namungoona Road','pavement',0.308020,32.572040,215,66,17,55,61,'no','St Mary’s College Kisubi','Mon–Sat 07:00–19:00','Peak 07:00–10:00',$mixAll,'under-survey',null,null],
    ['ZR-32','Kanjokya Street','MKY','Kanjokya','Kanjokya Street','roadside',0.314220,32.576540,188,62,20,49,58,'yes','Kabaka’s Mulago Hospital','Mon–Sat 06:00–20:00','Peak 07:00–09:00, 16:00–19:00',$mixMixed,'notice-issued','2026-02-05','2026-02-19'],
    ['ZR-33','Makindye Bus Stage','MKY','Katwe','Makindye Road','bus-stage',0.310420,32.584820,232,70,25,56,70,'yes','Makindye Bus Stage','Mon–Sat 05:30–20:00','Peak 07:00–09:00, 17:00–19:00',$mixFood,'relocating','2026-02-05','2026-02-19'],
    ['ZR-34','Katwe–Kisenyi Link','MKY','Katwe','Katwe Road','roadside',0.311820,32.589120,175,64,23,47,59,'no','','Mon–Sat 07:00–20:00','Peak 07:00–10:00, 16:00–19:00',$mixMixed,'under-survey',null,null],
    ['ZR-35','Kabalagala Road','MKY','Kabalagala','Kabalagala Road','pavement',0.320420,32.614240,152,56,14,41,53,'no','Kabalagala Mosque','Mon–Sun 06:00–20:00','Peak 07:00–18:00',$mixProduce,'under-survey',null,null],
];

$vendorsInZones = 0;
foreach ($zones as $i => $z) {
    [$code,$name,$div,$parish,$road,$type,$lat,$lng,$vend,$foot,$carr,$months,$comp,$strat,$asset,$days,$hours,$mix,$status,$notified,$deadline] = $z;

    Database::insert('trade_zones', [
        'code' => $code, 'name' => $name, 'division_id' => $divId[$div], 'parish' => $parish,
        'road_or_feature' => $road, 'zone_type' => $type, 'lat' => $lat, 'lng' => $lng,
        'estimated_vendors' => $vend,
        'footpath_obstruction_pct' => $foot,
        'carriageway_encroachment_pct' => $carr,
        'months_occupied' => $months, 'complaints_30d' => $comp,
        'strategic_corridor' => $strat === 'yes' ? 1 : 0,
        'near_public_asset' => $asset,
        'peak_days' => $days, 'peak_hours' => $hours, 'category_mix' => $mix,
        'status' => $status,
        'survey_date' => '2026-01-' . str_pad((string) (10 + ($i % 18)), 2, '0', STR_PAD_LEFT),
        'notified_date' => $notified,
        'deadline_date' => $deadline,
        'notes' => null,
    ]);
    $vendorsInZones += $vend;
}
printf("done (%d zones, %d vendors affected)\n", count($zones), $vendorsInZones);

/* ---------------------------------------------------------------------
 * 7. Vendor register — every vendor sits in exactly one surveyed zone
 * ------------------------------------------------------------------ */
echo "[7/13] Seeding vendor register … ";
Database::run('DELETE FROM vendors');
Database::run('ALTER TABLE vendors AUTO_INCREMENT = 1');

$firstNames = ['Amina','Grace','Fatuma','Esther','Sarah','Nadia','Ibrahim','Joseph','Moses','Peter','David',
  'Hassan','Yusuf','Daniel','Stephen','Andrew','Christopher','George','John','Paul','Samuel','Robert',
  'Alice','Mary','Ruth','Rebecca','Jane','Agnes','Mercy','Joy','Patience','Margaret','Dorothy','Elizabeth',
  'Rose','Kato','Ssali','Nabirye','Nansubuga','Namutebi','Naggya','Luwum','Kisekka','Wandera','Tumwine',
  'Mwebaze','Kawooya','Kirunda','Kintu','Byaruhanga','Ochieng','Mutesa','Mukasa','Kibirige','Nsibambi'];
$lastNames = ['Nabirye','Nansubuga','Namutebi','Naggya','Luwum','Kisekka','Wandera','Tumwine','Mwebaze',
  'Kawooya','Kirunda','Kintu','Byaruhanga','Mutesa','Mukasa','Ssali','Kato','Ouma','Ochieng','Lubega',
  'Bazanyanengo','Kakyama','Kisekka','Ndaula','Kintu','Wekesa','Namusoke','Kabugho','Rwanjuga','Balirwa',
  'Kirabo','Kato','Lukwago','Nabirye','Mugisha','Ntambi','Gumyiragra','Kyarisire','Twagirayezu','Nabbumba'];

$categoryWeights = [
    'fresh-produce' => 22, 'food-cooking' => 19, 'clothing-textiles' => 12, 'household-goods' => 10,
    'beverages' => 9, 'crops-grains' => 8, 'meat-fish' => 7, 'mobile-services' => 5,
    'electronics-repairs' => 4, 'handicraft' => 2, 'salon-services' => 2, 'livestock' => 2,
];
$categoryBag = [];
foreach ($categoryWeights as $cat => $w) {
    for ($i = 0; $i < $w; $i++) { $categoryBag[] = $cat; }
}

$associations = ['NETA','FUTA','UNATA','KACITA','KATA','UATEA','KAAFO',
  'Kampala Arcaders Advocacy Forum','Federation of Uganda Traders Associations', null, null];

$batch = [];
$seq = 0;
$zoneRows = Database::all('SELECT id, division_id, parish, estimated_vendors, status FROM trade_zones ORDER BY id');

foreach ($zoneRows as $zone) {
    $count = (int) $zone['estimated_vendors'];
    $zoneStatus = $zone['status'];

    for ($n = 1; $n <= $count; $n++) {
        $seq++;
        $first = $firstNames[seededRandom('fn' . $seq, 0, count($firstNames) - 1)];
        $last  = $lastNames[seededRandom('ln' . $seq, 0, count($lastNames) - 1)];
        $name  = "$first $last";
        $seed  = 'v' . $seq;

        $category = $categoryBag[seededRandom($seed . 'c', 0, count($categoryBag) - 1)];
        $isF      = seededRandom($seed . 'g', 0, 100) < 58;
        $dep      = seededRandom($seed . 'd', 0, 7);
        $started  = (int) date('Y') - seededRandom($seed . 'y', 1, 26);
        $capital  = [50000, 90000, 150000, 250000, 400000, 700000, 1200000, 2000000][seededRandom($seed . 'k', 0, 7)];
        $turnover = (int) round($capital * seededRandom($seed . 't', 60, 260) / 100) * 1000;
        $turnover = max(60000, min(18000000, $turnover));

        $hasLicence = seededRandom($seed . 'l', 0, 100) < 46;
        $licClass = $hasLicence
            ? (['street-grade-1','street-grade-2','market-standard'][seededRandom($seed . 'lc', 0, 2)])
            : 'unlicensed';

        $isPwd     = seededRandom($seed . 'p', 0, 100) < 6;
        $isParent  = $isF && seededRandom($seed . 'sp', 0, 100) < 31;
        $isYouth   = ((int) date('Y') - seededRandom('by' . $seq, 18, 30)) <= 30;
        $isExEnf   = seededRandom($seed . 'ex', 0, 100) < 3;

        // Vendor status follows the stage their zone has reached.
        $status = match ($zoneStatus) {
            'cleared'        => 'relocated',
            'relocating'     => seededRandom($seed . 'st', 0, 100) < 55 ? 'offered-space' : 'notified',
            'clearing'       => ['notified','offered-space','relocated','declined'][seededRandom($seed . 'st', 0, 3)],
            'notice-issued'  => ['surveyed','notified','offered-space','declined'][seededRandom($seed . 'st', 0, 3)],
            'litigation-hold'=> 'declined',
            default          => 'surveyed',
        };

        $assoc = $associations[seededRandom($seed . 'as', 0, count($associations) - 1)];

        $batch[] = [
            'vendor_code'   => 'KCCA-V' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            'full_name'     => $name,
            'national_id'   => seededRandom('id' . $seq, 0, 100) < 88 ? (string) seededRandom('idn' . $seq, 100000000000, 299999999999) : null,
            'phone'         => '+2567' . seededRandom('ph' . $seq, 10000000, 99999999),
            'alternative_phone' => seededRandom($seed . 'ap', 0, 100) < 42 ? '+2567' . seededRandom('aph' . $seq, 10000000, 99999999) : null,
            'email'         => null,
            'gender'        => $isF ? 'female' : 'male',
            'date_of_birth' => sprintf('%d-%02d-%02d', (int) date('Y') - seededRandom('by' . $seq, 18, 62),
                                       seededRandom('bm' . $seq, 1, 12), seededRandom('bd' . $seq, 1, 28)),
            'division_id'   => (int) $zone['division_id'],
            'parish'        => $zone['parish'],
            'current_zone_id' => (int) $zone['id'],
            'business_category' => $category,
            'business_description' => tradeDescription($category),
            'trading_days'  => ['Monday–Saturday','Daily','Weekdays only','Monday–Friday, Saturday'][seededRandom($seed . 'td', 0, 3)],
            'started_year'  => $started,
            'monthly_turnover_ugx' => $turnover,
            'startup_capital_ugx' => $capital,
            'household_size'=> seededRandom($seed . 'hh', 1, 9),
            'dependants'    => $dep,
            'is_person_with_disability' => $isPwd,
            'is_single_parent' => $isParent,
            'is_youth'      => $isYouth,
            'is_veteran_ex_enforcee' => $isExEnf,
            'has_trading_licence' => $hasLicence,
            'licence_class' => $licClass,
            'licence_year'  => $hasLicence ? (int) date('Y') : null,
            'association'   => $assoc,
            'status'        => $status,
        ];

        if (count($batch) >= 1000) {
            Database::insertMany('vendors', $batch);
            $batch = [];
        }
    }
}
if ($batch) {
    Database::insertMany('vendors', $batch);
}
printf("done (%d vendors)\n", $seq);

/* ---------------------------------------------------------------------
 * 8. Enforcement actions, users, indicators, services, notices
 * ------------------------------------------------------------------ */
echo "[8/13] Seeding enforcement, users, indicators and notices … ";
Database::run('DELETE FROM enforcement_actions');
Database::run('ALTER TABLE enforcement_actions AUTO_INCREMENT = 1');

$officers = [
    ['Officer M. Nakato',   'ENF-014', 'Supervisor, Market Development'],
    ['Officer R. Kiwanuka', 'ENF-023', 'Enforcement Team Leader'],
    ['Officer S. Atew',     'ENF-031', 'Enforcement Team Leader'],
    ['Officer D. Okello',   'ENF-047', 'Legal Affairs'],
    ['Officer J. Nsubuga',  'ENF-052', 'Enforcement Team Leader'],
    ['Officer P. Musinguzi','ENF-068', 'Enforcement Team Leader'],
];
$refSeq = 0;

foreach (Database::all("SELECT id, code, name, status, deadline_date, estimated_vendors FROM trade_zones ORDER BY id") as $z) {
    $refSeq++;

    // Everyone who has been worked gets a survey record.
    Database::insert('enforcement_actions', [
        'reference' => sprintf('ENF-%s-01', $z['code']),
        'zone_id' => (int) $z['id'], 'action_type' => 'survey', 'status' => 'completed',
        'issued_date' => '2026-01-' . str_pad((string) (10 + ($refSeq % 18)), 2, '0', STR_PAD_LEFT),
        'completed_date' => '2026-01-' . str_pad((string) (20 + ($refSeq % 8)), 2, '0', STR_PAD_LEFT),
        'officers_deployed' => seededRandom('so' . $refSeq, 2, 5),
        'vendors_affected' => (int) $z['estimated_vendors'],
        'authorised_by' => 'KCCA Office of the Executive Director',
        'officer_name' => $officers[$refSeq % 6][0], 'officer_number' => $officers[$refSeq % 6][1],
        'outcome' => 'Field survey completed. Obstruction measured, vendor count recorded, '
            . 'vulnerability profile captured.',
    ]);

    if (in_array($z['status'], ['notice-issued','clearing','relocating','cleared','litigation-hold'], true)) {
        $noticeStatus = match ($z['status']) {
            'cleared'     => 'completed',
            'clearing', 'relocating' => 'in-progress',
            default       => 'completed',
        };
        Database::insert('enforcement_actions', [
            'reference' => sprintf('ENF-%s-02', $z['code']),
            'zone_id' => (int) $z['id'], 'action_type' => 'notice', 'status' => $noticeStatus,
            'issued_date' => '2026-02-03', 'deadline_date' => $z['deadline_date'],
            'completed_date' => $noticeStatus === 'completed' ? '2026-02-09' : null,
            'vendors_affected' => (int) $z['estimated_vendors'],
            'authorised_by' => 'Hon. Hajjati Minsa Kabanda, Minister for Kampala City',
            'outcome' => 'Two-week notice directing vendors to vacate streets, pavements and verandas '
                . 'and move to available gazetted market space.',
        ]);
    }

    if (in_array($z['status'], ['clearing','relocating','cleared'], true)) {
        $relocated = (int) round((int) $z['estimated_vendors']
            * ['clearing' => 0.35, 'relocating' => 0.72, 'cleared' => 0.96][$z['status']]);
        Database::insert('enforcement_actions', [
            'reference' => sprintf('ENF-%s-03', $z['code']),
            'zone_id' => (int) $z['id'], 'action_type' => 'eviction', 'status' =>
                $z['status'] === 'cleared' ? 'completed' : 'in-progress',
            'issued_date' => '2026-02-19',
            'completed_date' => $z['status'] === 'cleared' ? '2026-02-21' : null,
            'officers_deployed' => seededRandom('eo' . $refSeq, 8, 22),
            'vendors_affected' => (int) $z['estimated_vendors'],
            'vendors_relocated' => $relocated,
            'verandas_cleared' => seededRandom('ev' . $refSeq, 5, 90),
            'materials_removed_m3' => round(seededRandom('em' . $refSeq, 8, 190) / 10, 2),
            'authorised_by' => 'Hajjat Buzeki Sharifah, Executive Director',
            'officer_name' => $officers[($refSeq + 2) % 6][0],
            'officer_number' => $officers[($refSeq + 2) % 6][1],
            'outcome' => $z['status'] === 'cleared'
                ? 'Zone cleared. Carriageway and footpath returned to public use.'
                : 'Joint enforcement operation under way. Vendors offered gazetted alternatives on site.',
        ]);
    }
}

/* ---- Users ---- */
Database::run('DELETE FROM users');
Database::run('ALTER TABLE users AUTO_INCREMENT = 1');

/* Officers and traders who can sign in.
 *
 * Six roles, in the order of authority the Authority sets out: the System
 * Administrator runs the system; the Executive Director oversees everything
 * and takes the final decisions; the Mayor oversees the managers of one
 * division; the Planner decides how the relocation is planned and holds the
 * forecast; the Enforcement Officer carries it out on the ground; the vendor
 * views markets, applies for a stall and follows the footfall.
 *
 * Internal Audit is deliberately not a sign-in role. It is viewed by the
 * Executive Director, the Mayor and the System Administrator, so it is a
 * screen those three hold rather than a separate desk.
 *
 * Names and titles follow KCCA's published leadership where KCCA publishes
 * it. Where it does not — the ICT department, Internal Audit, and the two
 * traders — the account is marked illustrative rather than a real person's
 * name being attached to a post they do not hold.
 */
$users = [
    ['admin','KCCA VRSP 2026','Robert Ssempijja','Director, Information & Communication Technology','administrator','information-communication-technology',null,'ict@kcca.go.ug','0772 441 908',0],
    ['ed','kcca@2026','Hajjat Buzeki Sharifah','Executive Director','executive','office-of-the-executive-director',null,'ed@kcca.go.ug','0772 641 118',1],
    ['mayor.central','kcca@2026','Salim Uhuru','Mayor, Kampala Central Division','mayor','office-of-the-executive-director','CTR','mayor.ctr@kcca.go.ug','0772 510 220',1],
    ['mayor.nakawa','kcca@2026','Buken Ali','Mayor, Nakawa Division','mayor','office-of-the-executive-director','NKW','mayor.nkw@kcca.go.ug','0772 510 224',1],
    ['planner','kcca@2026','Godfrey Luyombya','Executive Secretary, Civil Works and Physical Planning','planner','physical-planning',null,'planning@kcca.go.ug','0772 532 011',1],
    ['enforcement','kcca@2026','Daniel Nuwabine','Spokesperson & Enforcement Liaison','enforcement','legal',null,'legal@kcca.go.ug','0772 533 640',1],
    ['revenue','kcca@2026','Ezra Ssebufu','Deputy Director, Revenue Collection','revenue','revenue',null,'revenue@kcca.go.ug','0772 540 772',1],
    ['vendor','kcca@2026','Nakato Rehema','Street vendor — Kisenyi','vendor',null,null,'rehema.nakato@example.co.ug','0772 990 441',0],
];
foreach ($users as [$u,$p,$n,$t,$r,$d,$dv,$e,$ph,$basis]) {
    Database::insert('users', [
        'username' => $u, 'password_hash' => password_hash($p, PASSWORD_DEFAULT),
        'full_name' => $n, 'title' => $t, 'directorate' => $d, 'role' => $r,
        'division_id' => $dv ? $divId[$dv] : null, 'email' => $e, 'phone' => $ph,
        'name_basis' => $basis ? 'published' : 'illustrative',
        'is_active' => 1,
    ]);
}

/* ---- Strategic indicators ---- */
Database::run('DELETE FROM strategic_indicators');
foreach (facts()['indicators'] as $i) {
    Database::insert('strategic_indicators', $i);
}

/* ---- Official online services ---- */
Database::run('DELETE FROM kcca_services');
$order = 0;
foreach (facts()['services'] as $s) {
    Database::insert('kcca_services', [
        'name' => $s['name'], 'url' => $s['url'], 'category' => $s['category'],
        'purpose' => $s['purpose'], 'sort_order' => $order += 10,
    ]);
}

/* ---- Public notices ---- */
Database::run('DELETE FROM notices');
$notices = [
    ['KCCA confirms 2,520 vacant spaces in 17 public markets',
     'KCCA has confirmed that it has identified 2,520 vacant spaces across its 17 public markets, and at '
     . 'least 1,980 additional spaces in 69 private markets. Allocation is on a first-come, first-served '
     . 'basis and KCCA does not charge for the identified vacant spaces. Street vendors are called upon to '
     . 'occupy these available spaces.',
     '2026-02-17', 'https://www.kcca.go.ug/news/1078/vendors-in-city-markets-rally-street-traders-to-relocate'],

    ['Enforcement against unlawful street vending begins 19 February 2026',
     'The two-week ultimatum directing vendors to vacate streets, pavements and verandas expires on 19 February '
     . '2026. Joint enforcement teams will be identifiable by number on their uniform. Vendors who decline to '
     . 'relocate risk arrest.',
     '2026-02-18', 'https://www.kcca.go.ug/news/1078/vendors-in-city-markets-rally-street-traders-to-relocate'],

    ['Authority calls for 550 free spaces to be filled',
     'The Executive Director has stated that KCCA has over 550 free spaces in its markets, citing Wandegeya, '
     . 'Usafi and Luzira among markets with available trading spaces, and called on traders to fill them in '
     . 'all the public markets in the city.',
     '2026-09-29', 'https://www.kcca.go.ug/news/1203/kcca-clarifies-on-individuals-misidentified-as-enforcement-officers'],

    ['KCCA engages trader associations on trade order',
     'KCCA has met NETA, FUTA, UNATA, KACITA, KATA, UATEA and KAAFO to discuss trade order, market space, '
     . 'waste management and street vending, and to work with traders so businesses operate in lawful, safe '
     . 'and organised spaces while public infrastructure remains accessible.',
     '2026-09-29', 'https://www.kcca.go.ug/news'],

    ['Public participation: report obstruction and illegal trade',
     'Traders, residents and institutions may report unlawful vending obstructing public infrastructure through '
     . 'the KCCA Citizen Feedback portal. All enforcement officers are numbered and identified on uniform.',
     '2026-10-07', 'https://www.kcca.go.ug/citizen-feedback'],
];
foreach ($notices as $i => [$h,$b,$d,$u]) {
    Database::insert('notices', [
        'headline' => $h, 'body' => $b, 'published_on' => $d,
        'source_url' => $u, 'category' => 'Trade Order', 'sort_order' => $i * 10,
    ]);
}

printf("done — %d enforcement records, %d users\n",
    (int) Database::value('SELECT COUNT(*) FROM enforcement_actions'),
    (int) Database::value('SELECT COUNT(*) FROM users'));

/* ---------------------------------------------------------------------
 * 9. Trade categories, footfall, land use, recommendations, settings
 * ------------------------------------------------------------------ */
echo "[9/13] Seeding trade categories … ";
Database::run('DELETE FROM trade_categories');
Database::run('ALTER TABLE trade_categories AUTO_INCREMENT = 1');

/* Demand index and typical daily take are properties of the trade in
   Kampala, not of any one market. */
/*
 * Average spend per customer is set so that typical daily take divided by
 * it gives a realistic customer count for a Kampala stall — roughly 150 a
 * day for cooked food, 35 for clothing, 15 for phone repairs. Getting this
 * wrong is what makes a projection read as fantasy.
 */
$categories = [
    //  code, name, description, demand, daily take, avg spend/customer,
    //  substitution risk, walk-in share, cold chain, power, water, perishable, seasonality
    ['food-cooking',        'Cooked food',          'Rice, chips, stew, chapati, tea',            78,  85000,   700, 0.28, 0.78, 0, 1, 1, 1, 'Peak at breakfast and the evening meal; near markets do best.'],
    ['fresh-produce',       'Fresh produce',        'Fruit and vegetables',                        82,  60000,   600, 0.18, 0.85, 0, 0, 0, 1, 'Rains bring supply down and prices up.'],
    ['meat-fish',           'Meat and fish',        'Fresh beef, chicken, tilapia',                74, 105000,  1750, 0.22, 0.70, 1, 0, 1, 1, 'Cold chain materially reduces loss.'],
    ['beverages',           'Beverages',            'Water, juice, soft drinks',                   68,  45000,   300, 0.35, 0.88, 0, 0, 1, 0, 'Strong all day; competes with sachet water on price.'],
    ['crops-grains',        'Crops and grains',     'Dry beans, maize, rice',                      55,  70000,  1750, 0.30, 0.60, 0, 0, 0, 0, 'Bulk trade; storage matters more than footfall.'],
    ['household-goods',     'Household goods',      'Plastic ware, brooms, small items',            62,  55000,   700, 0.42, 0.66, 0, 0, 0, 0, 'Widely substituted; price-led.'],
    ['clothing-textiles',   'Clothing and textiles','New and second-hand clothing',                 66,  80000,  2300, 0.48, 0.72, 0, 0, 0, 0, 'Weekend and market-day peaks.'],
    ['electronics-repairs', 'Electronics and repairs','Phones, accessories, repairs',              58,  95000,  6300, 0.44, 0.64, 0, 1, 0, 0, 'Needs power.'],
    ['salon-services',      'Salon and beauty',     'Hair, barbering, beauty',                      72,  70000,  2300, 0.25, 0.70, 0, 1, 1, 0, 'Repeat custom; location near transport matters.'],
    ['mobile-services',     'Mobile money and airtime','Agent services',                           88, 110000,  2750, 0.20, 0.90, 0, 0, 0, 0, 'Near-total demand; the least dependent on footfall.'],
    ['handicraft',          'Handicraft',           'Baskets, mats, woodwork',                      44,  40000,  6700, 0.55, 0.40, 0, 0, 0, 0, 'Tourist-facing locations only.'],
    ['livestock',           'Livestock and poultry','Poultry, eggs, small stock',                   56,  85000,  3400, 0.24, 0.62, 0, 0, 1, 1, 'Needs space and waste handling.'],
    ['other',               'General merchandise',  'Everything else',                              40,  45000,   750, 0.50, 0.65, 0, 0, 0, 0, null],
];

foreach ($categories as $c) {
    Database::insert('trade_categories', [
        'code' => $c[0], 'name' => $c[1], 'description' => $c[2],
        'demand_index' => $c[3], 'typical_daily_take_ugx' => $c[4],
        'avg_spend_per_customer_ugx' => $c[5],
        'substitution_risk' => $c[6], 'walk_in_share' => $c[7],
        'requires_cold_chain' => $c[8], 'requires_power' => $c[9],
        'requires_water' => $c[10], 'perishable' => $c[11],
        'seasonality' => $c[12],
    ]);
}
printf("done (%d)\n", count($categories));

/* ---------------- Land use and trading restrictions ---------------- */
echo "[10/13] Seeding land use restrictions … ";
Database::run('DELETE FROM land_use_restrictions');

$restrictions = [
    // Markets beside a school or hospital: no cooking on the frontage.
    ['KBL-01', 'LUR-SCHOOL-01', 'No cooking adjacent to a school', 'Prepared food may not be cooked or sold within the frontage zone of Nakasero Primary School.', 'food-cooking', 'KCCA Physical Planning', 'KCC Act 2010 s.46 — zoning and public safety', 1],
    ['STG-01', 'LUR-CORRIDOR-01','No trading in the road reserve', 'The 9-metre road reserve along the Nakasero Hill carriageway is kept clear for traffic and emergency access.', null, 'KCCA Physical Planning', 'KCC Act 2010 s.46', 2],
    ['KSK-01', 'LUR-REHAB-01',  'Section closed pending rehabilitation', 'Sections B and C of Kiseka Market are closed pending rehabilitation works.', null, 'KCCA Engineering', 'KCCA Capital Works Programme', 3],
    ['GGB-01', 'LUR-LIVESTOCK-01', 'Livestock prohibited', 'Livestock and poultry may not be kept within the Ggaba market boundary.', 'livestock', 'KCCA Public Health and Environment', 'Public Health Act — zoonotic control', 4],
    ['MNY-01', 'LUR-RESIDENTIAL-01', 'Residential catchment — quiet hours', 'Amplified trading is prohibited and trading must cease by 19:00 within the residential catchment.', null, 'KCCA Physical Planning', 'KCC Act 2010 s.46', 5],
    ['BSG-01', 'LUR-FUEL-01',   'No fuel or gas storage', 'No pitch may store fuel, gas or other inflammable goods.', null, 'KCCA Public Health and Environment', 'Public Health Act — fire safety', 6],
    ['WDG-01', 'LUR-SIDEWALK-01','No trading on the footway', 'Vendors may not occupy the footway at any time; pitches only.', null, 'KCCA Physical Planning', 'KCC Act 2010 s.46', 7],
    ['LUZ-01', 'LUR-WATER-01',  'No trading in the flood channel', 'The Luzira market perimeter channel must remain clear; the flood risk is mapped annually.', null, 'KCCA Engineering', 'Flood risk management plan', 8],
    ['MKR-01', 'LUR-UNIVERSITY-01', 'Makerere University precinct conditions', 'Trading is permitted only between 07:00 and 19:00, and not during examinations.', null, 'KCCA Physical Planning', 'Agreement with Makerere University', 9],
    ['NKW-01', 'LUR-ELECTRIC-01','No trading under the power line', 'The easement beneath the transmission line carries a no-trade condition.', null, 'KCCA Engineering', 'Uganda Electricity Board easement', 10],
];

$restrictionCount = 0;
foreach ($restrictions as $r) {
    [$code, $rcode, $title, $desc, $excluded, $authority, $basis, $daysAgo] = $r;
    $marketId = (int) Database::value('SELECT id FROM markets WHERE code = ?', [$code]);
    if ($marketId === 0) {
        continue;
    }
    Database::insert('land_use_restrictions', [
        'market_id'          => $marketId,
        'code'               => $rcode,
        'title'              => $title,
        'description'        => $desc,
        'excluded_categories'=> $excluded,
        'authority'          => $authority,
        'legal_basis'        => $basis,
        'effective_from'     => date('Y-m-d', strtotime("-$daysAgo days")),
        'is_active'          => 1,
    ]);
    $restrictionCount++;
}
printf("done (%d)\n", $restrictionCount);

/* ---------------- Footfall observations ---------------- */
echo "[11/13] Counting footfall … ";
Database::run('DELETE FROM footfall_observations');
Database::run('ALTER TABLE footfall_observations AUTO_INCREMENT = 1');

/*
 * Six four-hour bands across four day types. Counts are generated to be
 * internally consistent — a market's bands sum to its daily figure — and
 * the seed is deterministic so the demo is reproducible.
 *
 * Coverage is deliberately uneven: some markets have been counted often
 * (high confidence), others barely at all. That is realistic, and it lets
 * the model show how confidence changes the recommendation.
 */
$bandWeights = [0.06, 0.14, 0.28, 0.24, 0.20, 0.08];   // 00-04 … 20-24
$dayFactors  = ['weekday' => 1.00, 'saturday' => 1.18, 'sunday' => 0.62, 'holiday' => 0.48];

$observations = [];
$marketRows = Database::all(
    "SELECT m.id, m.code, m.capacity, m.registered_vendors, m.status
       FROM markets m WHERE m.category = 'public' ORDER BY m.id"
);

/* How thoroughly has each market been counted? Deliberately varied. */
$coverage = [
    'USF-01' => ['days' => 34, 'types' => 3, 'source' => 'sensor'],
    'KMW-01' => ['days' => 22, 'types' => 3, 'source' => 'manual-count'],
    'KBL-01' => ['days' => 16, 'types' => 2, 'source' => 'manual-count'],
    'STG-01' => ['days' => 11, 'types' => 2, 'source' => 'sensor'],
    'WDG-01' => ['days' => 26, 'types' => 3, 'source' => 'manual-count'],
    'LUZ-01' => ['days' => 14, 'types' => 2, 'source' => 'trader-log'],
    'BSG-01' => ['days' => 9,  'types' => 2, 'source' => 'manual-count'],
    'NKW-01' => ['days' => 6,  'types' => 1, 'source' => 'trader-log'],
    'BGL-01' => ['days' => 3,  'types' => 1, 'source' => 'estimate'],
    // Everything else: barely counted, or not at all.
];

$footCount = 0;
foreach ($marketRows as $mk) {
    $cov = $coverage[$mk['code']] ?? (seededRandom($mk['code'] . 'cov', 0, 100) < 35
        ? ['days' => seededRandom($mk['code'] . 'cd', 2, 7), 'types' => 1, 'source' => 'estimate']
        : null);

    if ($cov === null) {
        continue;   // No counting at all: the model will estimate and say so.
    }

    /* Market size drives daily movements, but the relationship is sublinear:
       a market twice the size does not see twice the passing trade, because
       some of that trade would have gone elsewhere anyway. Movements per
       registered vendor settle around 4-9 a day across Kampala markets. */
    $traders = max(1, (int) $mk['registered_vendors']);
    $perVendor = seededRandom($mk['code'] . 'fb', 40, 90) / 10;      // 4.0 – 9.0
    $dailyBase = (int) round($traders * $perVendor);
    $dailyBase = max(120, min(30000, $dailyBase));
    $peoplePresent = (int) min(65000, max(0, round($traders * seededRandom($mk['code'] . 'fp', 30, 85) / 100)));

    $dayTypes = $cov['types'] >= 3 ? ['weekday', 'saturday', 'sunday']
              : ($cov['types'] === 2 ? ['weekday', 'saturday'] : ['weekday']);

    $weather = ['clear', 'overcast', 'rain'];

    for ($d = 0; $d < $cov['days']; $d++) {
        $date = date('Y-m-d', strtotime('-' . seededRandom($mk['code'] . 'd' . $d, 1, 150) . ' days'));
        $dayType = $dayTypes[$d % count($dayTypes)];
        $eventNote = null;
        if (strtotime($date) >= strtotime('2026-02-19') && strtotime($date) <= strtotime('2026-02-21')) {
            $eventNote = 'City Festival';
        } elseif ($dayType === 'saturday' && seededRandom($mk['code'] . 'mk' . $d, 0, 100) < 40) {
            $eventNote = 'Market day';
        }

        $dayFactor = $dayFactors[$dayType] * seededRandom($mk['code'] . 'df' . $d, 82, 118) / 100;
        $dayTotal = (int) round($dailyBase * $dayFactor);

        foreach ($bandWeights as $band => $weight) {
            $in  = (int) round($dayTotal * $weight * seededRandom($mk['code'] . $d . $band . 'i', 85, 115) / 100);
            $out = (int) round($in * seededRandom($mk['code'] . $d . $band . 'o', 90, 130) / 100);

            $observations[] = [
                'market_id'       => (int) $mk['id'],
                'observed_on'     => $date,
                'hour_band'       => $band,
                'day_type'        => $dayType,
                'people_in'       => $in,
                'people_out'      => $out,
                'traders_present' => $peoplePresent,
                'source'          => $cov['source'],
                'observer'        => $cov['source'] === 'sensor' ? 'Fixed counter' : 'Field survey team',
                'weather'         => $weather[seededRandom($mk['code'] . $d . $band . 'w', 0, 2)],
                'event_note'      => $eventNote,
            ];
        }
    }
}

foreach (array_chunk($observations, 1000) as $chunk) {
    Database::insertMany('footfall_observations', $chunk);
    $footCount += count($chunk);
}
printf("done (%d observations across %d counted markets, the rest estimated)\n",
    $footCount, count($coverage));

/* ---------------- Market trade mix ---------------- */
echo "[12/13] Seeding market trade mix … ";
Database::run('DELETE FROM market_trade_mix');

/* Each market has an established mix of trades. Without this the
   competition factor would always read zero, because vendors only appear
   against a market once an allocation is accepted. */
$mixRows = Database::all(
    'SELECT id, code, category_mix, registered_vendors FROM markets WHERE vacant_spaces > 0'
);

$mixCount = 0;
foreach ($mixRows as $mk) {
    $registered = max(1, (int) $mk['registered_vendors']);

    /* Trades the market's stated section mix implies, plus a long tail.
       Weights reflect the mix text already held on the market row. */
    $implied = [];
    $text = strtolower((string) $mk['category_mix']);
    $weight = 0;
    foreach ([
        'food' => 'food-cooking', 'produce' => 'fresh-produce', 'meat' => 'meat-fish',
        'beverage' => 'beverages', 'grain' => 'crops-grains', 'household' => 'household-goods',
        'clothing' => 'clothing-textiles', 'footwear' => 'clothing-textiles',
        'hardware' => 'household-goods', 'telecoms' => 'mobile-services',
        'general merchandise' => 'other',
    ] as $needle => $code) {
        if (str_contains($text, $needle)) {
            $implied[$code] = ($implied[$code] ?? 0) + seededRandom($mk['code'] . $needle . 'w', 12, 30);
            $weight += 1;
        }
    }
    if ($implied === []) {
        $implied = ['other' => 30];
    }

    /* Scale the implied weights so the counts sum to registered_vendors. */
    $sum = array_sum($implied);
    $allocated = 0;
    $keys = array_keys($implied);
    foreach ($keys as $i => $code) {
        $n = $i === count($keys) - 1
            ? $registered - $allocated
            : (int) round($registered * ($implied[$code] / $sum));
        $allocated += $n;
        if ($n <= 0) {
            continue;
        }
        Database::insert('market_trade_mix', [
            'market_id' => (int) $mk['id'],
            'business_category' => $code,
            'vendor_count' => $n,
        ]);
        $mixCount++;
    }
}
printf("done (%d market/trade pairs)\n", $mixCount);

/* ---------------------------------------------------------------------
 * 13. Settings, verification
 * ------------------------------------------------------------------ */
echo "[13/13] Seeding settings and verifying … ";
Database::run('DELETE FROM settings');

$settings = [
    // Relocation engine weights
    ['relocation_weight_proximity',     '0.24', 'Proximity weight', 'How strongly distance from the current pitch counts when matching a vendor to a market.', 'relocation', 'number', 0, 1, 1],
    ['relocation_weight_trade_fit',     '0.22', 'Trade fit weight', 'How strongly the market’s suitability for the goods being sold counts.', 'relocation', 'number', 0, 1, 1],
    ['relocation_weight_equity',        '0.20', 'Equity weight', 'How strongly vulnerability and household circumstances count.', 'relocation', 'number', 0, 1, 1],
    ['relocation_weight_viability',     '0.15', 'Viability weight', 'How strongly remaining room in the destination market counts.', 'relocation', 'number', 0, 1, 1],
    ['relocation_weight_affordability', '0.11', 'Affordability weight', 'How strongly rent against turnover counts.', 'relocation', 'number', 0, 1, 1],
    ['relocation_weight_facilities',    '0.08', 'Facilities weight', 'How strongly the facilities a trade requires count.', 'relocation', 'number', 0, 1, 1],

    // Congestion engine weights
    ['congestion_weight_obstruction',    '0.26', 'Obstruction weight', 'Physical obstruction of footpath and carriageway in the congestion score.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_vendor_load',    '0.20', 'Vendor load weight', 'Number of vendors affected by clearing the site.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_traffic_impact','0.16', 'Traffic impact weight', 'Effect on traffic flow and emergency access.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_duration',       '0.12', 'Duration weight', 'How long the unlawful occupation has persisted.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_strategic',      '0.12', 'Strategic corridor weight', 'Importance of the corridor the site sits on.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_public_access',  '0.08', 'Public facility weight', 'Whether a hospital, school or place of worship is obstructed.', 'congestion', 'number', 0, 1, 1],
    ['congestion_weight_complaints',     '0.06', 'Complaints weight', 'Volume of logged public complaints.', 'congestion', 'number', 0, 1, 1],

    // Reach model policy
    ['reach_confidence_floor', '0.35', 'Confidence floor for a recommendation',
     'A recommendation below this confidence is shown with a warning that footfall should be counted before space is offered.', 'reach', 'number', 0, 1, 1],
    ['reach_footfall_strong_daily', '12000', 'Daily movements considered a strong market',
     'Footfall at or above this level scores full marks on the footfall factor. Total movements in and out, all bands, all day types.', 'reach', 'number', 500, 40000, 1],
    ['reach_capture_base', '0.012', 'Base share of passing trade a stall wins',
     'The starting conversion rate before demand, competition and substitution are applied. Calibrated so a full-score stall in a strong market serves roughly 150 customers a day.', 'reach', 'number', 0.001, 0.1, 1],
    ['reach_trading_days', '26', 'Trading days used in the revenue projection',
     'Kampala markets typically trade 26 days a month, allowing for Sundays and one rest day.', 'reach', 'number', 20, 31, 1],
    ['reach_require_counted_footfall', '0', 'Require counted footfall before offering space',
     'When on, the engine will not produce an approvable recommendation for a location with no counted footfall.', 'reach', 'boolean', 0, 1, 1],

    // Programme policy
    ['offer_validity_days', '14', 'Days an offer stays open', 'How long a trader has to accept an offer of space before it expires.', 'programme', 'number', 1, 90, 1],
    ['recommendation_validity_days', '14', 'Days a recommendation stays open', 'How long a generated recommendation remains open for approval.', 'programme', 'number', 1, 90, 1],
    ['max_recommendations_per_vendor', '5', 'Recommendations shown per vendor', 'How many ranked locations the engine offers for one trader.', 'programme', 'number', 1, 20, 1],
    ['export_max_rows', '20000', 'Maximum rows in an export', 'Larger extracts are refused so the server is not overwhelmed.', 'programme', 'number', 100, 200000, 1],

    // Authority identity, shown on the public portal
    ['authority_portal_phone', '0417 900 100', 'Public telephone number', 'Displayed on the trader portal.', 'authority', 'text', null, null, 1],
    ['authority_portal_email', 'info@kcca.go.ug', 'Public email address', 'Displayed on the trader portal.', 'authority', 'text', null, null, 1],
    ['authority_portal_hours', 'Mon–Fri 08:00–17:00', 'Public opening hours', 'Displayed on the trader portal.', 'authority', 'text', null, null, 1],
];

foreach ($settings as [$key, $value, $label, $desc, $group, $type, $min, $max, $editable]) {
    Database::insert('settings', [
        'setting_key' => $key, 'setting_value' => $value, 'label' => $label,
        'description' => $desc, 'group_name' => $group, 'input_type' => $type,
        'min_value' => $min, 'max_value' => $max, 'is_editable' => $editable,
        'updated_by' => 'seeder',
    ]);
}
printf("done (%d settings)\n", count($settings));

/* ---------------------------------------------------------------------
 * Verification
 * ------------------------------------------------------------------ */
echo "
Verification";

$f = facts()['vacancy'];
$checks = [
    ['Public markets registered',
        (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='public'"), $f['public_markets']],
    ['Vacant spaces, public markets',
        (int) Database::value("SELECT SUM(vacant_spaces) FROM markets WHERE category='public'"),
        $f['public_vacant_spaces']],
    ['Private markets registered',
        (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='private'"), $f['private_markets']],
    ['Vacant spaces, private markets',
        (int) Database::value("SELECT SUM(vacant_spaces) FROM markets WHERE category='private'"),
        $f['private_vacant_spaces']],
    ['Usafi Market capacity',
        (int) Database::value("SELECT capacity FROM markets WHERE code='USF-01'"), 3000],
    ['Usafi Market registered vendors',
        (int) Database::value("SELECT registered_vendors FROM markets WHERE code='USF-01'"), 1600],
    ['Divisions', (int) Database::value('SELECT COUNT(*) FROM divisions'), 5],
    ['City population', (int) Database::value('SELECT SUM(population) FROM divisions'), 1797722],
];

$allOk = true;
echo PHP_EOL;
foreach ($checks as [$label, $actual, $expected]) {
    $ok = $actual === $expected;
    $allOk = $allOk && $ok;
    printf("  %s  %-34s %8s  (official: %s)%s", $ok ? 'PASS' : 'FAIL', $label, $actual, $expected, PHP_EOL);
}
echo PHP_EOL . ($allOk
    ? "All citywide totals reconcile with the published KCCA figures." . PHP_EOL
    : "WARNING: one or more totals do not reconcile." . PHP_EOL);

echo str_repeat('-', 64) . PHP_EOL;
printf("Vendors %d · Markets %d · Pitches %d · Zones %d · Enforcement %d · Queries %d%s",
    (int) Database::value('SELECT COUNT(*) FROM vendors'),
    (int) Database::value('SELECT COUNT(*) FROM markets'),
    (int) Database::value('SELECT COUNT(*) FROM market_spaces'),
    (int) Database::value('SELECT COUNT(*) FROM trade_zones'),
    (int) Database::value('SELECT COUNT(*) FROM enforcement_actions'),
    Database::queryCount(), PHP_EOL);

echo "Sign in with  username: admin   password: KCCA VRSP 2026" . PHP_EOL;