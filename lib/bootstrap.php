<?php
/**
 * Common bootstrap for every page and endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Engines.php';
require_once __DIR__ . '/ReachModel.php';
require_once __DIR__ . '/Recommendations.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/VendorService.php';
require_once __DIR__ . '/AccountService.php';
require_once __DIR__ . '/RoleDashboard.php';
require_once __DIR__ . '/Activities.php';
/* Read wherever a market is named, so a page can show a market's chairman
   without reaching into the market register itself. */
require_once __DIR__ . '/ChairmanService.php';
require_once __DIR__ . '/Backup.php';
require_once __DIR__ . '/ErrorHandler.php';

Auth::startSession();
ErrorHandler::register();

/** Official KCCA facts, loaded once per request. */
function facts(): array
{
    static $facts = null;
    if ($facts === null) {
        $facts = require __DIR__ . '/../config/kcca_facts.php';
    }
    return $facts;
}

/** The page's own navigation definition — also used to draw the sidebar. */
/**
 * Navigation catalogue.
 *
 * Every screen in the system, grouped. This is the full list; who sees what
 * is decided by navigationFor(), below. Adding a screen means one entry
 * here and a decision about its audience — not a change to every role.
 */
function navigation(): array
{
    return [
        ['group' => 'Work', 'items' => [
            ['id' => 'dashboard',       'file' => 'pages/dashboard.php',       'label' => 'Dashboard',      'icon' => 'gauge',    'audience' => ['all']],
            ['id' => 'vendors',         'file' => 'pages/vendors.php',         'label' => 'Vendors',        'icon' => 'people',   'audience' => ['administrator', 'executive', 'mayor', 'planner', 'enforcement', 'revenue', 'chairman']],
            ['id' => 'locations',       'file' => 'pages/locations.php',       'label' => 'Markets',        'icon' => 'store',    'audience' => ['all']],
            ['id' => 'market-map',      'file' => 'pages/market_map.php',      'label' => 'Stall Map',      'icon' => 'map',      'audience' => ['all']],
        ]],
        ['group' => 'Relocation', 'items' => [
            ['id' => 'relocation',      'file' => 'pages/relocation.php',      'label' => 'Matching',       'icon' => 'list',     'audience' => ['administrator', 'executive', 'mayor', 'planner']],
            ['id' => 'recommendations', 'file' => 'pages/recommendations.php', 'label' => 'AI Forecast',    'icon' => 'chart',    'audience' => ['administrator', 'executive', 'mayor', 'planner']],
            ['id' => 'allocations',     'file' => 'pages/allocations.php',      'label' => 'Allocations',    'icon' => 'swap',     'audience' => ['administrator', 'executive', 'mayor', 'planner']],
        ]],
        ['group' => 'Operations', 'items' => [
            ['id' => 'city-map',        'file' => 'pages/city-map.php',       'label' => 'City Map',       'icon' => 'map',      'audience' => ['all']],
            ['id' => 'congestion',      'file' => 'pages/congestion.php',     'label' => 'Congestion',     'icon' => 'pulse',    'audience' => ['administrator', 'executive', 'mayor', 'planner', 'enforcement']],
            ['id' => 'enforcement',     'file' => 'pages/enforcement.php',     'label' => 'Enforcement',    'icon' => 'shield',   'audience' => ['administrator', 'executive', 'mayor', 'enforcement']],
            ['id' => 'licensing',       'file' => 'pages/licensing.php',       'label' => 'Licensing',      'icon' => 'doc',      'audience' => ['administrator', 'executive', 'mayor', 'revenue']],
        ]],
        ['group' => 'Records', 'items' => [
            ['id' => 'analytics',       'file' => 'pages/analytics.php',       'label' => 'Performance',    'icon' => 'trend',    'audience' => ['administrator', 'executive', 'mayor']],
            ['id' => 'reports',         'file' => 'pages/reports.php',         'label' => 'Reports',        'icon' => 'download', 'audience' => ['administrator', 'executive', 'mayor', 'planner', 'enforcement', 'revenue']],
            ['id' => 'audit',           'file' => 'pages/audit.php',           'label' => 'Audit Trail',    'icon' => 'log',      'audience' => ['administrator', 'executive', 'mayor']],
            /* Who chairs each market. Officers maintain it; a chairman reads
               it to see the tenure and contact details held for their own. */
            ['id' => 'chairmen',        'file' => 'pages/chairmen.php',        'label' => 'Chairmen',       'icon' => 'user',     'audience' => ['administrator', 'executive', 'mayor', 'chairman']],
            ['id' => 'accounts',        'file' => 'pages/users.php',           'label' => 'Accounts',       'icon' => 'people',   'audience' => ['administrator', 'executive']],
            ['id' => 'settings',        'file' => 'pages/settings.php',        'label' => 'Settings',       'icon' => 'cog',      'audience' => ['administrator']],
            ['id' => 'portal',          'file' => 'pages/portal.php',          'label' => 'Trader Portal',  'icon' => 'user',     'audience' => ['all']],
        ]],
    ];
}

/**
 * Navigation for one officer.
 *
 * Each screen declares which roles need it, and this selects from that.
 * "Need" follows two principles from the management of organisations, both
 * aimed at the failure mode where an officer is handed screens they cannot
 * act on:
 *
 *   Span of control. A manager sees their own work and the decisions put
 *   to them — not the working screens of another manager. The Enforcement
 *   Officer does not plan the relocation; the Planner does not serve
 *   notices. Those are separate tasks under the Mayor, and showing each
 *   the other's screens is how a rail becomes a maze.
 *
 *   Altitude. Everyone sees the citywide position, because that is the
 *   shared picture. As authority rises the officer gains the screens that
 *   summarise and decide; as it falls they keep the screens they operate.
 *   A Mayor sees enforcement because they oversee the officer who runs it;
 *   a vendor sees none of it, because a trader's question is where the
 *   space is, not how the Authority is organised.
 *
 * Internal Audit is the deliberate exception to both. It is not a role
 * anybody signs in as: the Authority's instruction is that it is viewed by
 * the Executive Director, the Mayor and the System Administrator, so the
 * audit trail is a screen those three hold rather than a separate desk.
 *
 * The result: no two roles see the same rail, and no role is shown a link
 * to somewhere it has no standing to act.
 */
function navigationFor(string $role): array
{
    $out = [];

    foreach (navigation() as $group) {
        $kept = [];

        foreach ($group['items'] as $item) {
            $audience = $item['audience'] ?? ['all'];

            $visible = in_array('all', $audience, true)
                    || in_array($role, $audience, true);

            if ($visible) {
                unset($item['audience']);   // the template has no use for it
                $kept[] = $item;
            }
        }

        if ($kept !== []) {
            $out[] = ['group' => $group['group'], 'items' => $kept];
        }
    }

    return $out;
}

/**
 * Citywide totals used in the page headers. Cached per request.
 */
function headlineStats(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }

    $facts = facts();
    $v = $facts['vacancy'];

    $db = Database::one(
        "SELECT
            (SELECT COUNT(*) FROM trade_zones)                                   AS zones_total,
            (SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones)        AS vendors_in_zones,
            (SELECT COUNT(*) FROM vendors)                                      AS vendors_registered,
            (SELECT COUNT(*) FROM markets WHERE category='public')              AS public_markets,
            (SELECT COUNT(*) FROM markets WHERE category='private')             AS private_markets,
            (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public')  AS public_vacant,
            (SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private') AS private_vacant,
            (SELECT COUNT(*) FROM market_spaces WHERE status='vacant')          AS vacant_pitches,
            (SELECT COUNT(*) FROM market_spaces WHERE status='occupied')        AS occupied_pitches,
            (SELECT COUNT(*) FROM allocations WHERE status IN ('accepted','occupied')) AS completed_relocations,
            (SELECT COUNT(*) FROM vendors WHERE status='relocated')             AS relocated_vendors
    ");

    $db['official_public_vacant']  = (int) $v['public_vacant_spaces'];
    $db['official_private_vacant'] = (int) $v['private_vacant_spaces'];
    $db['official_total_vacant']   = (int) $v['total_vacant'];
    $db['official_public_markets'] = (int) $v['public_markets'];
    $db['official_private_markets']= (int) $v['private_markets'];
    $db['zones_cleared'] = (int) Database::value("SELECT COUNT(*) FROM trade_zones WHERE status='cleared'");
    $db['zones_pending'] = (int) Database::value("SELECT COUNT(*) FROM trade_zones WHERE status NOT IN ('cleared')");

    return $s = $db;
}

/* =====================================================================
 * Shared query helpers
 *
 * These live here rather than in the API layer because pages need them
 * too — the dashboards and the JSON endpoints must compute congestion
 * scores and candidate markets the same way.
 * ===================================================================== */

/**
 * Score every trade zone with the congestion engine.
 *
 * @return array<int,array> zone rows each carrying score/band/tone/reasons
 */
function scoredZones(string $divisionCode = '', int $limit = 0): array
{
    $sql = "SELECT z.*, d.code AS div_code, d.name AS division_name
              FROM trade_zones z
              JOIN divisions d ON d.id = z.division_id
             WHERE 1 = 1";
    $params = [];
    if ($divisionCode !== '') {
        $sql .= " AND d.code = :div";
        $params['div'] = $divisionCode;
    }
    $sql .= " ORDER BY z.id";
    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $out = [];
    foreach (Database::all($sql, $params) as $z) {
        $s = ZoneScoringEngine::score($z);
        $z['score']   = $s['score'];
        $z['band']    = $s['band'];
        $z['tone']    = $s['tone'];
        $z['reasons'] = $s['reasons'];
        $out[] = $z;
    }
    return $out;
}

/**
 * Candidate markets for relocation, with the average rent of their vacant
 * pitches so the affordability criterion has something to test against.
 *
 * @return array<int,array>
 */
function candidateMarkets(int $limit = 400): array
{
    return Database::all(
        "SELECT m.*, d.code AS div_code, d.name AS division_name,
                (SELECT COALESCE(AVG(monthly_rent_ugx), 0) FROM market_spaces s
                  WHERE s.market_id = m.id AND s.status = 'vacant') AS avg_rent_ugx
           FROM markets m
           JOIN divisions d ON d.id = m.division_id
          WHERE m.vacant_spaces > 0 AND m.status IN ('operational','upgrading')
          ORDER BY m.vacant_spaces DESC
          LIMIT " . (int) $limit
    );
}

/** Vendor row plus the coordinates of the zone they currently trade in. */
function vendorForMatching(int $vendorId): ?array
{
    return Database::one(
        "SELECT v.*, z.lat AS current_lat, z.lng AS current_lng, z.name AS zone_name,
                d.code AS div_code, d.name AS division_name
           FROM vendors v
           LEFT JOIN trade_zones z ON z.id = v.current_zone_id
           LEFT JOIN divisions d ON d.id = v.division_id
          WHERE v.id = ? LIMIT 1",
        [$vendorId]
    );
}

/** Count of vendors by register status, for header pills. */
function vendorStatusCounts(): array
{
    $out = [];
    foreach (Database::all('SELECT status, COUNT(*) AS n FROM vendors GROUP BY status') as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Approximate boundary of a Division Urban Council as [lat,lng] pairs.
 * Traced from the real division limits. Both map engines need this shape,
 * so it lives here rather than being written twice.
 *
 * @return array<int,array{0:float,1:float}>
 */
function divisionOutline(string $code, float $lat, float $lng): array
{
    static $outline = [
        'CTR' => [[0.2965,32.5700],[0.2965,32.5955],[0.3025,32.6030],[0.3140,32.6030],
                  [0.3230,32.5965],[0.3290,32.5890],[0.3300,32.5760],[0.3230,32.5670],
                  [0.3120,32.5630],[0.3030,32.5660]],
        'KWM' => [[0.3290,32.5250],[0.3290,32.5580],[0.3230,32.5670],[0.3120,32.5630],
                  [0.3030,32.5660],[0.2965,32.5700],[0.2965,32.5400],[0.3100,32.5280],
                  [0.3220,32.5220]],
        'RBG' => [[0.2965,32.5220],[0.2965,32.5400],[0.2965,32.5700],[0.2880,32.5680],
                  [0.2800,32.5560],[0.2790,32.5320],[0.2860,32.5200],[0.2900,32.5160],
                  [0.2930,32.5180]],
        'MKY' => [[0.2790,32.5560],[0.2880,32.5680],[0.2965,32.5700],[0.3030,32.5660],
                  [0.3030,32.5900],[0.2965,32.6000],[0.2880,32.6070],[0.2790,32.6060],
                  [0.2740,32.5900],[0.2745,32.5680]],
        'NKW' => [[0.3230,32.5670],[0.3300,32.5760],[0.3290,32.5890],[0.3230,32.5965],
                  [0.3140,32.6030],[0.3025,32.6030],[0.2965,32.5955],[0.2965,32.5900],
                  [0.3030,32.5900],[0.3120,32.5980],[0.3200,32.5990],[0.3280,32.5900],
                  [0.3360,32.5960],[0.3450,32.6050],[0.3500,32.6200],[0.3480,32.6400],
                  [0.3350,32.6480],[0.3220,32.6420],[0.3160,32.6280],[0.3100,32.6150],
                  [0.3020,32.6100],[0.3030,32.6000],[0.3100,32.5980],[0.3180,32.5900],
                  [0.3230,32.5850],[0.3180,32.5760]],
    ];

    if (isset($outline[$code])) {
        return $outline[$code];
    }

    // Unknown division: a small box around its centre so the map still draws.
    $d = 0.012;
    return [
        [$lat - $d, $lng - $d * 1.2], [$lat - $d, $lng + $d * 1.2],
        [$lat + $d, $lng + $d * 1.2], [$lat + $d, $lng - $d * 1.2],
    ];
}

/**
 * Division lock for Division-level roles.
 *
 * A Mayor has no authority outside their own division, so the filter must be
 * applied to the query rather than after it — otherwise the sidebar and the
 * counters leak another division's figures.
 *
 * Returns null for roles that may see the whole city; those callers fall back
 * to the requested ?division= value.
 *
 * @return array{id:int,code:string,name:string}|null
 */
function divisionLock(): ?array
{
    $myId = Auth::scopeDivision();
    if ($myId === null) {
        return null;
    }

    $mine = Database::one('SELECT id, code, name FROM divisions WHERE id = ?', [$myId]);
    if (!$mine) {
        return null;
    }

    return [
        'id'   => (int) $mine['id'],
        'code' => (string) $mine['code'],
        'name' => (string) $mine['name'],
    ];
}

/**
 * The market a chairman administers, or null for every other role.
 *
 * The market-level counterpart to divisionLock(): a mayor is confined by
 * the division they are recorded against, a chairman by the market they
 * chair. Returning null for every other role means the call sites need no
 * role check of their own.
 */
function chairmanLock(): ?array
{
    if (Auth::role() !== 'chairman') {
        return null;
    }

    $me = Auth::user();
    if (empty($me['chairman_id'])) {
        return null;
    }

    $mine = Database::one(
        'SELECT ch.id, ch.full_name, ch.status, ch.market_id, m.name AS market_name,
                m.code AS market_code, m.vacant_spaces, m.capacity,
                d.name AS division_name
           FROM market_chairmen ch
           JOIN markets m ON m.id = ch.market_id
           JOIN divisions d ON d.id = m.division_id
          WHERE ch.id = ?',
        [(int) $me['chairman_id']]
    );
    if (!$mine) {
        return null;
    }

    return [
        'id'           => (int) $mine['id'],
        'market_id'    => (int) $mine['market_id'],
        'market_code'  => (string) $mine['market_code'],
        'market_name'  => (string) $mine['market_name'],
        'full_name'    => (string) $mine['full_name'],
        'division'     => (string) $mine['division_name'],
        'status'       => (string) $mine['status'],
        'vacant'       => (int) $mine['vacant_spaces'],
        'capacity'     => (int) $mine['capacity'],
    ];
}

/**
 * The chairman responsible for a vendor, if the market has one on record.
 *
 * Read at the point of display rather than cached on the vendor, so a
 * change of chairman is reflected everywhere at once instead of leaving
 * stale names behind in rows written before the change.
 */
function chairmanForVendor(int $vendorId): ?array
{
    return Database::one(
        'SELECT ch.id, ch.full_name, ch.phone, ch.status, m.name AS market_name
           FROM vendors v
           JOIN market_chairmen ch ON ch.id = v.chairman_id
           LEFT JOIN markets m ON m.id = ch.market_id
          WHERE v.id = ?',
        [$vendorId]
    );
}

/**
 * Record the chairman responsible for a vendor, taken from the market they
 * have just been placed in.
 *
 * The chairman is not a second assignment to be made and kept in step: it
 * is the chairman of the vendor's own market, written at the moment of
 * placement so it travels with them through the register, the allocation
 * record and every report. A market with no chairman on record leaves the
 * link alone rather than clearing one that exists.
 */
function syncVendorChairman(int $vendorId, int $marketId): void
{
    $chairmanId = Database::value(
        "SELECT id FROM market_chairmen WHERE market_id = ? AND status = 'active' LIMIT 1",
        [$marketId]
    );
    if ($chairmanId === null) {
        return;
    }
    Database::run('UPDATE vendors SET chairman_id = ? WHERE id = ?', [(int) $chairmanId, $vendorId]);
}

/**
 * Drop the chairman from a vendor whose placement has been undone.
 *
 * Only for a move that actually removes them from the market: a vendor back
 * in the pool is nobody's responsibility until they are placed again, and
 * leaving the old chairman attached would show a name against a vendor who
 * is no longer in that market.
 */
function clearVendorChairman(int $vendorId): void
{
    Database::run('UPDATE vendors SET chairman_id = NULL WHERE id = ?', [$vendorId]);
}

/**
 * Render the division filter control for a filter bar.
 * For a Division-level role it shows the locked division instead of a select,
 * so the scoping is visible rather than implicit.
 *
 * @var string      $fieldId  element id, must be unique on the page
 * @var string      $selected currently selected division code
 */
function renderDivisionFilter(string $fieldId, string $selected): void
{
    $lock = divisionLock();

    if ($lock !== null) {
        echo '<div class="field">';
        echo '  <label for="' . e($fieldId) . 'Locked">Division</label>';
        echo '  <input type="text" id="' . e($fieldId) . 'Locked" value="'
            . e($lock['name'] . ' Division') . '" disabled>';
        echo '  <input type="hidden" name="division" value="' . e($lock['code']) . '">';
        echo '  <span class="hint">Scoped to your division.</span>';
        echo '</div>';
        return;
    }

    echo '<div class="field">';
    echo '  <label for="' . e($fieldId) . '">Division</label>';
    echo '  <select name="division" id="' . e($fieldId) . '" data-autosubmit>';
    echo '    <option value="">All five divisions</option>';
    foreach (Database::all('SELECT code, name FROM divisions ORDER BY name') as $d) {
        echo '      <option value="' . e($d['code']) . '"'
            . ($selected === $d['code'] ? ' selected' : '') . '>'
            . e($d['name']) . '</option>';
    }
    echo '  </select>';
    echo '</div>';
}

/** Distinct colour per division, used consistently across every map. */
function divisionColor(string $code): string
{
    return match ($code) {
        'CTR' => '#DC351D',
        'KWM' => '#EAA515',
        'RBG' => '#0A6339',
        'MKY' => '#1D6FA5',
        'NKW' => '#8E44AD',
        default => '#6B7280',
    };
}

/** Read a GET parameter as a trimmed string. */
function q(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

/** Read a GET parameter as an int clamped to a range. */
function qInt(string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $v = $_GET[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int) clamp((int) $v, $min, $max);
}