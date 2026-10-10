<?php
/**
 * GET /api/export.php?report=zones
 *
 * Streams a register as CSV. Every export is written to the audit trail
 * before the file is served, because a download of the vendor register is
 * an accountable act.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requirePerm('report');

$report = q('report', 'summary');

/** Column definition: [heading, sql expression, cast]. */
$reports = [
    'zones' => [
        'name' => 'trade-zone-register',
        'title' => 'Trade zone register — Kampala Capital City',
        'columns' => [
            ['Code',            'z.code'],
            ['Name',            'z.name'],
            ['Division',        'd.name'],
            ['Parish',          'z.parish'],
            ['Road or feature', 'z.road_or_feature'],
            ['Type',            'z.zone_type'],
            ['Latitude',        'z.lat'],
            ['Longitude',       'z.lng'],
            ['Estimated vendors', 'z.estimated_vendors'],
            ['Footpath obstruction %', 'z.footpath_obstruction_pct'],
            ['Carriageway encroachment %', 'z.carriageway_encroachment_pct'],
            ['Months occupied', 'z.months_occupied'],
            ['Complaints 30d',  'z.complaints_30d'],
            ['Strategic corridor', "IF(z.strategic_corridor,'yes','no')"],
            ['Public asset affected', 'z.near_public_asset'],
            ['Peak days',       'z.peak_days'],
            ['Peak hours',      'z.peak_hours'],
            ['Trade mix',       'z.category_mix'],
            ['Congestion score', '(:score)'],
            ['Band',            '(:band)'],
            ['Status',          'z.status'],
            ['Survey date',     'z.survey_date'],
            ['Notified date',   'z.notified_date'],
            ['Deadline date',   'z.deadline_date'],
        ],
        'sql' => 'FROM trade_zones z JOIN divisions d ON d.id = z.division_id ORDER BY z.code',
        'scored' => true,
    ],

    'vendors' => [
        'name' => 'vendor-register',
        'title' => 'Vendor register — Kampala Capital City',
        'columns' => [
            ['Vendor code',     'v.vendor_code'],
            ['Full name',       'v.full_name'],
            ['National ID',     'v.national_id'],
            ['Telephone',       'v.phone'],
            ['Gender',          'v.gender'],
            ['Division',        'd.name'],
            ['Parish',          'v.parish'],
            ['Trade zone',      'z.name'],
            ['Business category', 'v.business_category'],
            ['Business description', 'v.business_description'],
            ['Trading days',    'v.trading_days'],
            ['Started year',    'v.started_year'],
            ['Startup capital UGX', 'v.startup_capital_ugx'],
            ['Monthly turnover UGX', 'v.monthly_turnover_ugx'],
            ['Household size',  'v.household_size'],
            ['Dependants',      'v.dependants'],
            ['Person with disability', "IF(v.is_person_with_disability,'yes','no')"],
            ['Single parent',   "IF(v.is_single_parent,'yes','no')"],
            ['Youth',           "IF(v.is_youth,'yes','no')"],
            ['Former enforcement agent', "IF(v.is_veteran_ex_enforcee,'yes','no')"],
            ['Has trading licence', "IF(v.has_trading_licence,'yes','no')"],
            ['Licence class',   'v.licence_class'],
            ['Licence year',    'v.licence_year'],
            ['Association',     'v.association'],
            ['Status',          'v.status'],
        ],
        'sql' => 'FROM vendors v
                  JOIN divisions d ON d.id = v.division_id
                  LEFT JOIN trade_zones z ON z.id = v.current_zone_id
                 ORDER BY v.vendor_code',
    ],

    'markets' => [
        'name' => 'market-register',
        'title' => 'Market register — Kampala Capital City',
        'columns' => [
            ['Code',            'm.code'],
            ['Name',            'm.name'],
            ['Category',        'm.category'],
            ['Market type',     'm.market_type'],
            ['Division',        'd.name'],
            ['Parish',          'm.parish'],
            ['Latitude',        'm.lat'],
            ['Longitude',       'm.lng'],
            ['Capacity',        'm.capacity'],
            ['Registered vendors', 'm.registered_vendors'],
            ['Vacant spaces',   'm.vacant_spaces'],
            ['Occupancy %',     '(:util)'],
            ['Water',           "IF(m.water_available,'yes','no')"],
            ['Power',           "IF(m.power_available,'yes','no')"],
            ['Toilets',         "IF(m.toilets,'yes','no')"],
            ['Waste collection', "IF(m.waste_collection,'yes','no')"],
            ['Storage',         "IF(m.storage_rooms,'yes','no')"],
            ['Cold chain',      "IF(m.cold_chain,'yes','no')"],
            ['Accessible ramps', "IF(m.accessible_ramps,'yes','no')"],
            ['Opened year',     'm.opened_year'],
            ['Condition score', 'm.condition_score'],
            ['Administrator',   'm.admin_name'],
            ['Administrator phone', 'm.admin_phone'],
            ['Status',          'm.status'],
            ['Figure basis',    'm.data_basis'],
            ['Trade mix',       'm.category_mix'],
            ['Notes',           'm.notes'],
        ],
        'sql' => 'FROM markets m JOIN divisions d ON d.id = m.division_id ORDER BY m.category, m.code',
        'util' => true,
    ],

    'allocations' => [
        'name' => 'allocation-register',
        'title' => 'Allocation register — Kampala Capital City',
        'columns' => [
            ['Reference',       'a.reference'],
            ['Vendor code',     'v.vendor_code'],
            ['Vendor',          'v.full_name'],
            ['Division',        'd.name'],
            ['Current zone',    'z.name'],
            ['Business category', 'v.business_category'],
            ['Market',          'm.name'],
            ['Market category', 'm.category'],
            ['Pitch',           's.space_code'],
            ['Distance km',     'a.distance_km'],
            ['Total score',     'a.total_score'],
            ['Rank',            'a.rank'],
            ['Status',          'a.status'],
            ['Offer expires',   'a.offer_expires'],
            ['Issued',          'a.created_at'],
            ['Decided',         'a.decided_at'],
            ['Reasoning',       'a.reasoning'],
        ],
        'sql' => 'FROM allocations a
                  JOIN vendors v ON v.id = a.vendor_id
                  JOIN markets m ON m.id = a.market_id
                  JOIN divisions d ON d.id = v.division_id
                  LEFT JOIN trade_zones z ON z.id = v.current_zone_id
                  LEFT JOIN market_spaces s ON s.id = a.market_space_id
                 ORDER BY a.created_at DESC',
    ],

    'enforcement' => [
        'name' => 'enforcement-log',
        'title' => 'Enforcement log — Kampala Capital City',
        'columns' => [
            ['Reference',       'e.reference'],
            ['Zone code',       'z.code'],
            ['Zone',            'z.name'],
            ['Division',        'd.name'],
            ['Action type',     'e.action_type'],
            ['Status',          'e.status'],
            ['Issued date',     'e.issued_date'],
            ['Deadline date',   'e.deadline_date'],
            ['Completed date',  'e.completed_date'],
            ['Officers deployed', 'e.officers_deployed'],
            ['Vendors affected', 'e.vendors_affected'],
            ['Vendors relocated', 'e.vendors_relocated'],
            ['Verandas cleared', 'e.verandas_cleared'],
            ['Materials removed m3', 'e.materials_removed_m3'],
            ['Authorised by',   'e.authorised_by'],
            ['Officer',         'e.officer_name'],
            ['Officer number',  'e.officer_number'],
            ['Outcome',         'e.outcome'],
        ],
        'sql' => 'FROM enforcement_actions e
                  JOIN trade_zones z ON z.id = e.zone_id
                  JOIN divisions d ON d.id = z.division_id
                 ORDER BY e.issued_date DESC, e.id DESC',
    ],

    'pitches' => [
        'name' => 'public-market-pitches',
        'title' => 'Public market pitch register — Kampala Capital City',
        'columns' => [
            ['Market code',     'm.code'],
            ['Market',          'm.name'],
            ['Division',        'd.name'],
            ['Pitch code',      's.space_code'],
            ['Section',         's.section'],
            ['Size sqm',        's.size_sqm'],
            ['Status',          's.status'],
            ['Monthly rent UGX', 's.monthly_rent_ugx'],
            ['Water',           "IF(s.has_water,'yes','no')"],
            ['Power',           "IF(s.has_power,'yes','no')"],
            ['Near toilet',     "IF(s.near_toilet,'yes','no')"],
            ['Step free',       "IF(s.step_free,'yes','no')"],
        ],
        'sql' => 'FROM market_spaces s
                  JOIN markets m ON m.id = s.market_id
                  JOIN divisions d ON d.id = m.division_id
                 ORDER BY m.code, s.space_code',
    ],

    'licensing' => [
        'name' => 'licensing-position',
        'title' => 'Licensing position — Kampala Capital City',
        'columns' => [
            ['Vendor code',     'v.vendor_code'],
            ['Full name',       'v.full_name'],
            ['Division',        'd.name'],
            ['Trade',           'v.business_category'],
            ['Has trading licence', "IF(v.has_trading_licence,'yes','no')"],
            ['Licence class',   'v.licence_class'],
            ['Licence year',    'v.licence_year'],
            ['Monthly turnover UGX', 'v.monthly_turnover_ugx'],
            ['Current zone',    'z.name'],
            ['Status',          'v.status'],
        ],
        'sql' => 'FROM vendors v
                  JOIN divisions d ON d.id = v.division_id
                  LEFT JOIN trade_zones z ON z.id = v.current_zone_id
                 ORDER BY v.has_trading_licence, v.vendor_code',
    ],

    'footfall' => [
        'name' => 'footfall-observations',
        'title' => 'Footfall observations — Kampala Capital City',
        'columns' => [
            ['Market code',    'm.code'],
            ['Market',         'm.name'],
            ['Division',       'd.name'],
            ['Date',           'o.observed_on'],
            ['Day type',       'o.day_type'],
            ['Time band',      'o.hour_band'],
            ['People in',      'o.people_in'],
            ['People out',     'o.people_out'],
            ['Traders present','o.traders_present'],
            ['Source',         'o.source'],
            ['Observer',       'o.observer'],
            ['Weather',        'o.weather'],
            ['Event note',     'o.event_note'],
        ],
        'sql' => 'FROM footfall_observations o
                  JOIN markets m ON m.id = o.market_id
                  JOIN divisions d ON d.id = m.division_id
                 ORDER BY o.observed_on DESC, m.name, o.hour_band',
    ],

    'restrictions' => [
        'name' => 'land-use-restrictions',
        'title' => 'Land use and trading restrictions — Kampala Capital City',
        'columns' => [
            ['Code',           'r.code'],
            ['Title',          'r.title'],
            ['Market',         'm.name'],
            ['Division',       'd.name'],
            ['Blocks trades',  "COALESCE(NULLIF(r.excluded_categories,''), 'Every trade')"],
            ['Section',        "COALESCE(NULLIF(r.applies_to_section,''), 'Whole site')"],
            ['Authority',      'r.authority'],
            ['Legal basis',    'r.legal_basis'],
            ['Effective from', 'r.effective_from'],
            ['Effective to',   "COALESCE(r.effective_to, 'Open ended')"],
            ['In force',       "IF(r.is_active,'yes','no')"],
        ],
        'sql' => 'FROM land_use_restrictions r
                  JOIN markets m ON m.id = r.market_id
                  JOIN divisions d ON d.id = m.division_id
                 ORDER BY r.is_active DESC, m.name, r.code',
    ],

    'recommendations' => [
        'name' => 'ai-recommendations',
        'title' => 'AI recommendations — Kampala Capital City',
        'columns' => [
            ['Reference',      'r.reference'],
            ['Vendor',         'v.full_name'],
            ['Trade',          'v.business_category'],
            ['Location',       'm.name'],
            ['Rank',           'r.rank'],
            ['Reach score',    'r.reach_score'],
            ['Viability score','r.viability_score'],
            ['Footfall score', 'r.footfall_score'],
            ['Confidence %',   'ROUND(r.confidence * 100, 0)'],
            ['Projected customers/day', 'ROUND(r.projected_customers_day, 0)'],
            ['Projected revenue UGX/month', 'ROUND(r.projected_revenue_ugx, 0)'],
            ['Distance km',    'r.distance_km'],
            ['Model',          'r.model_version'],
            ['Status',         'r.status'],
            ['Decided by',     'u.full_name'],
            ['Decision',       'COALESCE(r.decision_note, "")'],
        ],
        'sql' => 'FROM recommendations r
                  JOIN vendors v ON v.id = r.vendor_id
                  JOIN markets m ON m.id = r.market_id
                  LEFT JOIN users u ON u.id = r.decided_by
                 ORDER BY r.status, r.reach_score DESC',
    ],

    'relocation-history' => [
        'name' => 'relocation-history',
        'title' => 'Relocation history — Kampala Capital City',
        'columns' => [
            ['When',           'e.occurred_at'],
            ['Event',          'e.event_type'],
            ['From',           'COALESCE(e.from_status, "")'],
            ['To',             'e.to_status'],
            ['Vendor',         'COALESCE(v.full_name, "")'],
            ['Location',       'COALESCE(m.name, "")'],
            ['Allocation',     'COALESCE(a.reference, "")'],
            ['Actor',          'e.actor'],
            ['Note',           'COALESCE(e.note, "")'],
        ],
        'sql' => 'FROM relocation_events e
                  LEFT JOIN vendors v ON v.id = e.vendor_id
                  LEFT JOIN markets m ON m.id = e.market_id
                  LEFT JOIN allocations a ON a.id = e.allocation_id
                 ORDER BY e.occurred_at DESC, e.id DESC
                 LIMIT 5000',
    ],

    'stall-requests' => [
        'name' => 'stall-requests',
        'title' => 'Stall requests — Kampala Capital City',
        'columns' => [
            ['Reference',      'sr.reference'],
            ['Market',         'm.name'],
            ['Pitch',          's.space_code'],
            ['Section',        's.section'],
            ['Trader',         'sr.full_name'],
            ['Telephone',      'sr.phone'],
            ['National ID',    "COALESCE(sr.national_id, '')"],
            ['Trade',          "COALESCE(sr.business_category, '')"],
            ['Goods',          "COALESCE(sr.trade_detail, '')"],
            ['Reason',         "COALESCE(sr.reason, '')"],
            ['Vendor code',    "COALESCE(v.vendor_code, 'not on the register')"],
            ['Decision',       'sr.status'],
            ['Decided by',     "COALESCE(u.full_name, '')"],
            ['Note',           "COALESCE(sr.decision_note, '')"],
            ['Requested on',   'sr.created_at'],
        ],
        'sql' => 'FROM stall_requests sr
                  JOIN market_spaces s ON s.id = sr.space_id
                  JOIN markets m ON m.id = sr.market_id
                  LEFT JOIN vendors v ON v.id = sr.vendor_id
                  LEFT JOIN users u ON u.id = sr.decided_by
                 ORDER BY sr.status = \'pending\' DESC, sr.created_at DESC
                 LIMIT 5000',
    ],

    'summary' => [
        'name' => 'citywide-summary',
        'title' => 'Citywide summary by division — Kampala Capital City',
        'columns' => [
            ['Division',        'd.name'],
            ['Mayor',           'd.mayor'],
            ['Town clerk',      'd.town_clerk'],
            ['Area sqkm',       'd.area_sqkm'],
            ['Parishes',        'd.parishes'],
            ['LC1 wards',       'd.lc1_wards'],
            ['Population',      'd.population'],
            ['Affected sites',  '(:zones)'],
            ['Vendors affected', '(:affected)'],
            ['Relocated',       '(:relocated)'],
            ['Declined',        '(:declined)'],
            ['Clearance rate %', '(:rate)'],
            ['Vacant spaces',   '(:vacant)'],
            ['Public vacant',   '(:pubvac)'],
            ['Private vacant',  '(:privac)'],
        ],
        'sql' => 'FROM divisions d ORDER BY d.name',
        'summary' => true,
    ],
];

if (!isset($reports[$report])) {
    jsonOut(['error' => 'Unknown report. Choose one of: ' . implode(', ', array_keys($reports))], 400);
}

$spec = $reports[$report];

/* ---------------- Build the query ----------------
   Alias each selected column by its heading, not by its SQL expression:
   two columns can select the same bare column name (the zone register
   reads both z.name and d.name), and the second would overwrite the
   first. Placeholder columns such as (:score) are computed in PHP. */
$select = [];
foreach ($spec['columns'] as [$heading, $expr]) {
    if (!str_contains($expr, '(:')) {
        $select[] = $expr . ' AS `' . export_key($heading) . '`';
    }
}
$selectSql = $select === [] ? '1 AS placeholder' : implode(', ', $select);

$rows = Database::all("SELECT $selectSql " . $spec['sql']);

$filename = $spec['name'] . '-' . date('Y-m-d') . '.csv';

/* Log before streaming: the download must be accountable even if the
   client disconnects part-way through. */
Auth::log('export', 'reports', null,
    'Downloaded ' . $spec['title'] . ' (' . count($rows) . ' rows) as ' . $filename);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'wb');

// UTF-8 BOM so Excel opens the file correctly.
fwrite($out, "\xEF\xBB\xBF");

/* Provenance header, so a file that leaves the system still explains itself. */
fputcsv($out, [$spec['title']]);
fputcsv($out, ['Generated', date('d F Y H:i') . ' EAT']);
fputcsv($out, ['Generated by', APP_SHORT . ' v' . APP_VERSION]);
fputcsv($out, ['Requested by', (string) (Auth::user()['full_name'] ?? 'unknown')]);
fputcsv($out, ['Citywide totals', 'Official KCCA figures; see kcca.go.ug for the source of each total']);
fputcsv($out, []);   // spacer row

/* Header row */
fputcsv($out, array_map(static fn(array $c) => $c[0], $spec['columns']));

foreach ($rows as $row) {
    $line = [];
    foreach ($spec['columns'] as [$heading, $expr]) {
        $line[] = export_cell($row, $heading, $expr, $spec);
    }
    fputcsv($out, $line);
}

/* Totals row for the registers that have a meaningful total. */
$totals = export_totals($report, $rows, $spec);
if ($totals !== []) {
    fputcsv($out, []);
    fputcsv($out, $totals);
}

fclose($out);
exit;

/* ------------------------------------------------------------------ */

/**
 * Resolve one cell: either a direct column alias or a computed value
 * such as the congestion score, which depends on the whole row.
 */
function export_cell(array $row, string $heading, string $expr, array $spec): string
{
    $k = export_key($heading);

    switch (true) {
        case str_starts_with($expr, '(:score'):
        case str_starts_with($expr, '(:band'):
            $idx = export_zone_scores();
            $z = $idx[(string) ($row['Code'] ?? '')] ?? null;
            return $z === null ? '' : (string) $z[str_starts_with($expr, '(:score') ? 'score' : 'band'];

        case str_starts_with($expr, '(:util'):
            $cap = (int) ($row['Capacity'] ?? 0);
            return $cap > 0
                ? (string) round((int) ($row['Registered vendors'] ?? 0) / $cap * 100, 1)
                : '';

        case !empty($spec['summary']):
            return (string) (export_summary_value($heading, $row) ?? '');
    }

    $val = $row[$k] ?? '';
    return $val === null ? '' : (string) $val;
}

/** Row key for a column, matching the alias used in the SELECT. */
function export_key(string $heading): string
{
    return $heading;
}

/**
 * Congestion score and band per zone code. Computed once per request:
 * the engine scores all 35 zones in a single pass.
 *
 * @return array<string,array{score:string,band:string}>
 */
function export_zone_scores(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    $index = [];
    foreach (scoredZones() as $z) {
        $index[(string) $z['code']] = ['score' => (string) $z['score'], 'band' => (string) $z['band']];
    }
    return $index;
}

/** Computed columns for the citywide summary report. */
function export_summary_value(string $heading, array $row): int|float|string
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        foreach (Database::all('SELECT id, name FROM divisions') as $d) {
            $ids[$d['name']] = (int) $d['id'];
        }
    }

    $id = $ids[(string) ($row['Division'] ?? '')] ?? null;

    // Direct columns (Division, Mayor, Town clerk, area, parishes, LC1
    // wards, population) come straight from the row.
    if ($id === null) {
        return $row[$heading] ?? '';
    }

    return match ($heading) {
        'Affected sites'   => (int) Database::value('SELECT COUNT(*) FROM trade_zones WHERE division_id = ?', [$id]),
        'Vendors affected' => (int) Database::value('SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE division_id = ?', [$id]),
        'Relocated'        => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE division_id = ? AND status = 'relocated'", [$id]),
        'Declined'         => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE division_id = ? AND status = 'declined'", [$id]),
        'Clearance rate %' => (function () use ($id): string {
            $affected  = (int) Database::value('SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE division_id = ?', [$id]);
            $relocated = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE division_id = ? AND status = 'relocated'", [$id]);
            return $affected > 0 ? (string) round($relocated / $affected * 100, 1) : '0';
        })(),
        'Vacant spaces'   => (int) Database::value('SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE division_id = ?', [$id]),
        'Public vacant'   => (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE division_id = ? AND category = 'public'", [$id]),
        'Private vacant'  => (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE division_id = ? AND category = 'private'", [$id]),
        default            => $row[$heading] ?? '',
    };
}

/**
 * Totals row, where a total is meaningful.
 *
 * Cells are emitted by heading rather than by position, so a column added
 * to a report cannot silently shift every total along by one.
 */
function export_totals(string $report, array $rows, array $spec): array
{
    /** Sum one numeric column across the exported rows. */
    $sum = static function (string $heading) use ($rows): string {
        $t = 0;
        foreach ($rows as $r) {
            $t += (int) ($r[$heading] ?? 0);
        }
        return (string) $t;
    };

    /** Put a value in the column with this heading, blanks elsewhere. */
    $row = static function (array $pairs) use ($spec): array {
        $out = [];
        foreach ($spec['columns'] as [$heading]) {
            $out[] = $pairs[$heading] ?? '';
        }
        return $out;
    };

    return match ($report) {
        'zones' => $row([
            'Code' => 'TOTAL',
            'Estimated vendors' => $sum('Estimated vendors'),
        ]),
        'vendors' => $row([
            'Vendor code' => 'TOTAL',
            'Startup capital UGX' => $sum('Startup capital UGX'),
            'Monthly turnover UGX' => $sum('Monthly turnover UGX'),
            'Household size' => $sum('Household size'),
            'Dependants' => $sum('Dependants'),
        ]),
        'markets' => $row([
            'Code' => 'TOTAL',
            'Capacity' => $sum('Capacity'),
            'Registered vendors' => $sum('Registered vendors'),
            'Vacant spaces' => $sum('Vacant spaces'),
        ]),
        'allocations' => $row([
            'Reference' => 'TOTAL',
            'Distance km' => $sum('Distance km'),
        ]),
        'enforcement' => $row([
            'Reference' => 'TOTAL',
            'Officers deployed' => $sum('Officers deployed'),
            'Vendors affected' => $sum('Vendors affected'),
            'Vendors relocated' => $sum('Vendors relocated'),
            'Verandas cleared' => $sum('Verandas cleared'),
            'Materials removed m3' => $sum('Materials removed m3'),
        ]),
        'pitches' => $row([
            'Market code' => 'TOTAL',
            'Size sqm' => $sum('Size sqm'),
            'Monthly rent UGX' => $sum('Monthly rent UGX'),
        ]),
        'licensing' => $row([
            'Vendor code' => 'TOTAL',
            'Monthly turnover UGX' => $sum('Monthly turnover UGX'),
        ]),
        'footfall' => $row([
            'Market code' => 'TOTAL',
            'People in' => $sum('People in'),
            'People out' => $sum('People out'),
            'Traders present' => $sum('Traders present'),
        ]),
        'recommendations' => $row([
            'Reference' => 'TOTAL',
            'Projected customers/day' => $sum('Projected customers/day'),
            'Projected revenue UGX/month' => $sum('Projected revenue UGX/month'),
            'Distance km' => $sum('Distance km'),
        ]),
        default => [],
    };
}

