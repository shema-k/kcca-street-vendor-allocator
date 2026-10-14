<?php
/**
 * Formatting and small helpers used across the interface.
 */

declare(strict_types=1);

/**
 * Human description of what a given business category sells. Used when
 * seeding the register and when displaying a vendor's trade.
 */
function tradeDescription(string $category): string
{
    return match ($category) {
        'food-cooking'        => 'Prepared food cooked and sold on site',
        'fresh-produce'       => 'Fresh fruit and vegetables',
        'meat-fish'           => 'Fresh meat and fish',
        'beverages'           => 'Drinks and other beverages',
        'crops-grains'        => 'Dry crops, grains and pulses',
        'household-goods'     => 'Household and plastic goods',
        'clothing-textiles'   => 'Second-hand and new clothing and textiles',
        'electronics-repairs' => 'Phone accessories and electrical repairs',
        'salon-services'      => 'Hair, beauty and barbering services',
        'mobile-services'     => 'Mobile money and airtime services',
        'handicraft'          => 'Baskets, mats and other handicraft',
        'livestock'           => 'Poultry and small livestock',
        default               => 'General merchandise',
    };
}

/**
 * Inline SVG icons. Kept in PHP as well as JavaScript so server-rendered
 * markup never depends on a script having run.
 */
function KCCA_icon(string $name, int $size = 18): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = [
            'gauge'    => '<circle cx="12" cy="12" r="9"/><path d="M12 12l4.5-4.5"/><path d="M3.3 17a9 9 0 1117.4 0"/>',
            'map'      => '<path d="M9 3L3 5.5v15L9 18l6 3 6-2.5v-15L15 6 9 3z"/><path d="M9 3v15M15 6v15"/>',
            'pulse'    => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'swap'     => '<path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/>',
            'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'store'    => '<path d="M3 9l1-5h16l1 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
            'people'   => '<path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 00-3-3.87M16 3.13A4 4 0 0119 7"/>',
            'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
            'doc'      => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
            'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
            'log'      => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 21h16"/>',
            'link'     => '<path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7l1.7-1.7"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>',
            'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'moon'     => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
            'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'x'        => '<path d="M18 6L6 18M6 6l12 12"/>',
            'external' => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6M10 14L21 3"/>',
            'print'    => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="1"/><path d="M6 15h12v6H6z"/>',
            'phone'    => '<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6A19.8 19.8 0 012 4.2 2 2 0 014 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.5 2.1L8 9.9a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.8.6 2.8.7A2 2 0 0122 16.9z"/>',
            'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>',
            'bell'     => '<path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'alert'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>',
            'check'    => '<path d="M20 6L9 17l-5-5"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
            'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/>',
            'layers'   => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
            'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1A1.7 1.7 0 008 19.4a1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H2a2 2 0 110-4h.1A1.7 1.7 0 003.6 8a1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H8a1.7 1.7 0 001-1.5V2a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V8a1.7 1.7 0 001.5 1H22a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
            'trend'    => '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/><path d="M19 7h-4M19 7v4"/>',
        ];
    }

    $p = $icons[$name] ?? $icons['info'];

    return sprintf(
        '<svg class="ico" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $size, $size, $p
    );
}

/**
 * Length in characters, not bytes.
 *
 * Counts UTF-8 characters correctly without mbstring, which is not installed
 * on this host and is not something a deployment can assume is present. The
 * mb_* functions are used where they exist because they are faster and
 * battle-tested; the fallback is here because mb_strlen() was being called
 * directly in three services and every one of them died the moment it was
 * reached on a host without the extension.
 *
 * This matters more than it looks now that a catalogue can be in Luganda or
 * Runyankole: a byte count would tell you a 40-character name is "140 long"
 * and reject it, or worse, cut a name in half down the middle of a character.
 */
function str_len(?string $s): int
{
    if ($s === null || $s === '') {
        return 0;
    }
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($s, 'UTF-8');
    }
    $n = preg_match_all('/./us', $s);
    return $n === false ? strlen($s) : $n;
}

/**
 * Substring by character rather than by byte.
 *
 * Same reasoning as str_len(): a byte-based substr() on a Luganda string cuts
 * a multibyte character in half and emits a replacement character, which then
 * goes into the database and comes back as a question mark in the trader's own
 * name.
 */
function str_cut(?string $s, int $start, ?int $length = null): string
{
    if ($s === null || $s === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($s, $start, $length, 'UTF-8');
    }
    if (preg_match_all('/./us', $s, $m) === false) {
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
    return implode('', array_slice($m[0], $start, $length));
}

/**
 * Truncate on a word boundary, appending an ellipsis if it was shortened.
 * Written without mbstring so it runs on a bare PHP install.
 */
function truncateText(?string $s, int $len = 140, string $suffix = '…'): string
{
    $s = trim((string) $s);
    if ($s === '' || strlen($s) <= $len) {
        return $s;
    }
    $cut = substr($s, 0, $len);
    $sp  = strrpos($cut, ' ');
    if ($sp !== false && $sp > $len * 0.6) {
        $cut = substr($cut, 0, $sp);
    }
    return $cut . $suffix;
}

/** "Hajjat Buzeki Sharifah" -> "S. Buzeki" style shortening for tight chrome. */
function shortName(string $full): string
{
    $parts = preg_split('/\s+/', trim($full)) ?: [];
    if (count($parts) < 2) {
        return $full;
    }
    $last = array_pop($parts);
    $first = $parts[0];
    return $first . ' ' . $last;
}

/**
 * Escape for HTML output. Accepts any scalar because PDO returns numbers
 * as float/int and DECIMAL columns as numeric strings.
 */
function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSON for embedding in an HTML attribute or inline script. Escapes the
 * characters that could break out of the attribute context.
 */
function ejs(mixed $data): string
{
    return (string) json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
    );
}

/** Kampala shillings, e.g. UGX 2,520 */
function ugx(int|float|string|null $n): string
{
    if ($n === null) {
        return '—';
    }
    return 'UGX ' . number_format((float) $n);
}

/** Compact shillings, e.g. UGX 1.4m — for tight chart axes. */
function ugxShort(int|float|string|null $n): string
{
    if ($n === null) {
        return '—';
    }
    $n = (float) $n;
    if ($n >= 1_000_000_000) return 'UGX ' . number_format($n / 1_000_000_000, 1) . 'b';
    if ($n >= 1_000_000)     return 'UGX ' . number_format($n / 1_000_000, 1) . 'm';
    if ($n >= 1_000)         return 'UGX ' . number_format($n / 1_000, 0) . 'k';
    return 'UGX ' . number_format($n);
}

/**
 * MySQL DECIMAL columns come back from PDO as strings, so these formatters
 * accept numeric strings as well as real numbers.
 */
function pct(int|float|string|null $n, int $decimals = 1): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((float) $n, $decimals) . '%';
}

function num(int|float|string|null $n, int $decimals = 0): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((float) $n, $decimals);
}

function fmtDate(?string $d, string $format = 'd M Y'): string
{
    if (!$d || str_starts_with($d, '0000')) {
        return '—';
    }
    $ts = strtotime($d);
    return $ts ? date($format, $ts) : '—';
}

function relativeDays(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $ts = strtotime($d);
    if (!$ts) {
        return '—';
    }
    $diff = (int) floor((time() - $ts) / 86400);
    return match (true) {
        $diff === 0  => 'today',
        $diff === 1  => 'yesterday',
        $diff === -1 => 'tomorrow',
        $diff < 0    => 'in ' . abs($diff) . ' days',
        $diff < 30   => $diff . ' days ago',
        $diff < 365  => floor($diff / 30) . ' months ago',
        default      => floor($diff / 365) . ' years ago',
    };
}

function daysUntil(?string $d): ?int
{
    if (!$d) {
        return null;
    }
    $ts = strtotime($d);
    return $ts ? (int) ceil(($ts - time()) / 86400) : null;
}

/** Initials from a person's name, for the avatar circle. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= strtoupper(substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function clamp(int|float $v, int|float $lo, int|float $hi): int|float
{
    return max($lo, min($hi, $v));
}

/** Deterministic pseudo-random from a string seed — keeps demo data stable across loads. */
function seededRandom(string $seed, int $min, int $max): int
{
    $h = crc32($seed);
    $v = ($h % 100000) / 100000;
    return (int) round($min + $v * ($max - $min));
}

function slug(string $s): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($s), 1) ?? '');
}

/** Build a URL preserving the current query string but overriding some keys. */
function urlWith(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return $params ? $script . '?' . http_build_query($params) : $script;
}

/** Current page id, used for sidebar highlighting. */
/**
 * Id of the page being viewed, used for sidebar highlighting.
 * The layout keeps its own variables prefixed, so this stays collision-free.
 */
function currentPage(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '', '.php');
}

/**
 * Link that switches the language and returns the reader to this page.
 *
 * Carries the current path so the switcher works from any screen without a
 * form post, and so the "back" it asks for is this site and not whatever a
 * caller supplied.
 */
function langUrl(string $code, ?string $path = null): string
{
    $path = $path ?? (currentPage() !== '' ? 'pages/' . currentPage() . '.php' : 'index.php');
    return '/api/language.php?' . http_build_query([
        'lang' => $code,
        'back' => $path,
    ]);
}

/**
 * Tone for a complaint's stage.
 *
 * A separate mapping rather than a reuse of statusTone(): a complaint's stages
 * are not allocation stages, and borrowing that table would have meant
 * reading across it to work out what "with-authority" should look like. The
 * one thing worth sharing is the rule — waiting on somebody is amber, done is
 * green, and "we decided not to act" is not green.
 */
function complaintTone(?string $status): string
{
    return match ($status) {
        'with-chairman' => 'warn',       // the trader's chairman owes them an answer
        'forwarded'     => 'bad',        // escalated and the Authority has not answered
        'with-authority'=> 'info',       // answered, not yet closed
        'resolved'      => 'ok',
        'declined'      => 'muted',      // closed without escalation: not a success
        default         => 'muted',
    };
}

/** Label for the trading zone / status / business category enums. */
function humanise(?string $v): string
{
    if (!$v) {
        return '—';
    }
    $map = [
        'food-cooking'        => 'Cooked food & beverages',
        'fresh-produce'       => 'Fresh produce',
        'meat-fish'           => 'Meat & fish',
        'beverages'           => 'Beverages',
        'crops-grains'        => 'Crops & grains',
        'household-goods'     => 'Household goods',
        'clothing-textiles'   => 'Clothing & textiles',
        'electronics-repairs' => 'Electronics & repairs',
        'salon-services'      => 'Salon & beauty services',
        'mobile-services'     => 'Mobile money & airtime',
        'handicraft'          => 'Handicraft',
        'livestock'           => 'Livestock & poultry',
        'under-survey'        => 'Under survey',
        'notice-issued'       => 'Notice issued',
        'clearing'            => 'Clearing in progress',
        'relocating'          => 'Relocating vendors',
        'cleared'             => 'Cleared',
        'litigation-hold'     => 'Litigation hold',
        'under-rehabilitation' => 'Under rehabilitation',
        'notified'            => 'Notified',
        'offered-space'       => 'Space offered',
        'relocated'           => 'Relocated',
        'declined'            => 'Declined offer',
        'deferred'            => 'Deferred',
        'complaint'           => 'Complaint lodged',
        'surveyed'            => 'Surveyed',
        'unlicensed'          => 'Unlicensed',
        'street-grade-1'      => 'Street trade licence — Grade 1',
        'street-grade-2'      => 'Street trade licence — Grade 2',
        'market-standard'     => 'Standard market licence',
        'administrator'       => 'System Administrator',
        'executive'           => 'Executive Director',
        'mayor'               => 'Mayor',
        'planner'             => 'Planner',
        'enforcement'         => 'Enforcement Officer',
        'vendor'              => 'Vendor',
        /* Directorates, as KCCA names them. */
        'office-of-the-executive-director'     => 'Office of the Executive Director',
        'information-communication-technology' => 'Information & Communication Technology',
        'physical-planning'                    => 'Physical Planning',
        'internal-audit'                       => 'Internal Audit',
        'revenue'                              => 'Revenue Collection',
        'legal'                                => 'Legal Affairs',
        'systems-ict'                          => 'Systems & ICT',
    ];
    if (isset($map[$v])) {
        return $map[$v];
    }
    return ucwords(str_replace(['-', '_'], ' ', $v));
}

/** Maps a status string to its semantic colour token used in the CSS. */
function statusTone(?string $status): string
{
    return match ($status) {
        'cleared', 'relocated', 'occupied', 'operational', 'completed', 'accepted', 'issued' => 'ok',
        'in-progress', 'relocating', 'clearing', 'upgrading', 'surveyed', 'planned', 'offered',
        'under-survey', 'proposed', 'under-rehabilitation'                               => 'info',
        'pending', 'reserved', 'suspended'                                               => 'warn',
        'litigation-hold', 'declined', 'complaint', 'closed', 'escalated', 'expired'    => 'bad',
        default                                                                        => 'muted',
    };
}

function riskTone(int|float|null $score): string
{
    if ($score === null) return 'muted';
    return match (true) {
        $score >= 75 => 'bad',
        $score >= 50 => 'warn',
        $score >= 25 => 'info',
        default      => 'ok',
    };
}

/** Turn "0.3456789" into a Leaflet-safe float. */
function lnglat(mixed $v): float
{
    return round((float) $v, 7);
}

/**
 * Build a "search across several columns" SQL fragment.
 *
 * With PDO::ATTR_EMULATE_PREPARES disabled — the secure setting — MySQL will
 * not accept the same named placeholder used more than once in a statement,
 * and raises SQLSTATE[HY093]. So the fragment binds one placeholder per
 * column, all sharing the same value.
 *
 *     [$sql, $params] = orLike('q', ['v.full_name', 'v.vendor_code'], $term);
 *     // ['v.full_name LIKE :q1 OR v.vendor_code LIKE :q2',
 *     //  'q1' => '%term%', 'q2' => '%term%']
 *
 * @return array{0:string,1:array<string,string>}
 */
function orLike(string $prefix, array $columns, string $term): array
{
    $frag   = [];
    $params = [];
    $n      = 0;

    foreach ($columns as $column) {
        $n++;
        $key           = $prefix . $n;
        $frag[]        = $column . ' LIKE :' . $key;
        $params[$key]  = '%' . trim($term, " \t\n\r\0\x0B%") . '%';
    }

    return ['(' . implode(' OR ', $frag) . ')', $params];
}

function jsonOut(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function serverClientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}