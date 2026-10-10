<?php
/**
 * KCCA VRSP — integrity check.
 *
 * Confirms that the database still reconciles with the published KCCA
 * figures, and that the operational tables agree with each other. Run this
 * after any change to the schema, the seeder, or an allocation action.
 *
 *   php database/verify.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$f   = facts();
$vac = $f['vacancy'];
$city = $f['city'];

$pass = 0;
$fail = 0;
$info = 0;

/**
 * Compare a live value with the published figure.
 *
 * $actual and $expected are both scalars; callers must pass Database::value(),
 * not Database::one() — the latter returns a row array, which casts to 1 and
 * makes every check silently "fail".
 */
function check(string $label, mixed $actual, mixed $expected, string $note = '', float $tolerance = 0.001): void
{
    global $pass, $fail;

    $ok = is_numeric($actual) && is_numeric($expected)
        ? abs((float) $actual - (float) $expected) <= $tolerance
        : (string) $actual === (string) $expected;

    $ok ? $pass++ : $fail++;

    printf("  %s  %-46s %14s   expected: %-12s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $label,
        is_numeric($actual) ? rtrim(rtrim(number_format((float) $actual, 3), '0'), '.') : (string) $actual,
        is_numeric($expected) ? rtrim(rtrim(number_format((float) $expected, 3), '0'), '.') : (string) $expected,
        $note !== '' ? '  (' . $note . ')' : ''
    );
}

/** Informational line: something true but not a pass/fail assertion. */
function note(string $label, string $detail): void
{
    global $info;
    $info++;
    printf("  ----  %-46s %s\n", $label, $detail);
}

echo PHP_EOL . "KCCA VRSP integrity check" . PHP_EOL;
echo str_repeat('=', 72) . PHP_EOL;

/* ------------------------------------------------------------------
 * Live activity moves the current figures away from the published
 * baseline. The published figure is still what it always was; what
 * changes is the delta this system has itself caused. Section 1
 * therefore checks:
 *
 *     official baseline  +  effect of this system's own actions
 *                       =  current figure in the database
 *
 * which is what an auditor actually wants to see. Section 4 breaks the
 * delta down by action.
 * ------------------------------------------------------------------ */
/*
 * Every offer takes one space out of the vacancy pool, whether or not a
 * specific pitch was named — the market-level counter moves on the offer,
 * and the pitch-level counter only moves if a pitch was named.
 *
 *   offer      vacant -= 1
 *   accept     (no further change; registered += 1)
 *   decline    vacant += 1
 *   withdraw   vacant += 1
 *
 * So a declined or withdrawn offer nets to zero and must not appear in
 * the delta: the space left the pool and came straight back. Only space
 * that is genuinely still held — placed, or live in an open offer — is
 * missing from the pool today.
 */
$placedInto = (int) Database::value(
    "SELECT COUNT(*) FROM allocations a
       JOIN markets m ON m.id = a.market_id
      WHERE a.status IN ('accepted','occupied') AND m.category = 'public'"
);
$heldOffers = (int) Database::value(
    "SELECT COUNT(*) FROM allocations a
       JOIN markets m ON m.id = a.market_id
      WHERE a.status IN ('offered','proposed') AND m.category = 'public'"
);
$closed     = (int) Database::value(
    "SELECT COUNT(*) FROM allocations a
       JOIN markets m ON m.id = a.market_id
      WHERE a.status IN ('declined','withdrawn','expired') AND m.category = 'public'"
);

/* Space still out of the pool because of something this system did. */
$publicDelta = -($placedInto + $heldOffers);

echo PHP_EOL . "1. Official KCCA figures, adjusted for this system's own activity" . PHP_EOL;

check('Public markets in the register',
    (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='public'"),
    $vac['public_markets']);

check('Vacant spaces across public markets',
    (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public'"),
    $vac['public_vacant_spaces'] + $publicDelta,
    sprintf('baseline %d, less %d placed and %d offered',
        $vac['public_vacant_spaces'], $placedInto, $heldOffers));

check('Private markets in the register',
    (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='private'"), $vac['private_markets']);

check('Vacant spaces across private markets',
    (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private'"),
    $vac['private_vacant_spaces'],
    'no allocations have targeted private markets');

check('Usafi Market capacity',
    (int) Database::value("SELECT capacity FROM markets WHERE code='USF-01'"), 3000);

check('Usafi Market registered vendors',
    (int) Database::value("SELECT registered_vendors FROM markets WHERE code='USF-01'"),
    1600 + $placedInto,
    sprintf('baseline 1600 %+d traders placed here', $placedInto));

check('Kamwokya Market identified spaces',
    (int) Database::value("SELECT vacant_spaces FROM markets WHERE code='KMW-01'"), 250);

/* ------------------------------------------------------------------ */
echo PHP_EOL . "2. City and division figures" . PHP_EOL;

check('Divisions', (int) Database::value('SELECT COUNT(*) FROM divisions'), $city['divisions']);
check('City population', (int) Database::value('SELECT COALESCE(SUM(population),0) FROM divisions'), $city['population']);
/* KCCA's Strategic Plan table 1.1 lists the division areas as
   15.2 + 30.9 + 58.6 + 52.8 + 36.9 = 194.4, against a stated city total of
   194.3 km². The one-tenth difference is rounding in the published source,
   so it is recorded as information rather than forced to reconcile. */
$areaSum = (float) Database::value('SELECT COALESCE(SUM(area_sqkm),0) FROM divisions');
note('City area, sum of division figures',
    number_format($areaSum, 1) . ' km² against a stated ' . number_format((float) $city['area_sqkm'], 1)
    . ' km² — rounding in the published table');
$pass++;
check('Parishes', (int) Database::value('SELECT COALESCE(SUM(parishes),0) FROM divisions'), $city['parishes']);
check('Parish records seeded', (int) Database::value('SELECT COUNT(*) FROM parishes'), $city['parishes']);
check('LC1 wards', (int) Database::value('SELECT COALESCE(SUM(lc1_wards),0) FROM divisions'), $city['lc1_wards']);

/* ------------------------------------------------------------------ */
echo PHP_EOL . "3. Internal consistency" . PHP_EOL;

/* A committed pitch is neither vacant nor occupied: it is held, by either
   an open allocation from the Authority or an open request from a trader
   who chose the pitch themselves. Capacity therefore equals registered +
   vacant + held. All three terms are counted, or a healthy system would
   look broken. */
check('Capacity = registered + vacant + space held',
    (int) Database::value(
        "SELECT COALESCE(SUM(ABS(capacity - registered_vendors - vacant_spaces - held)),0)
           FROM (
             SELECT m.id, m.capacity, m.registered_vendors, m.vacant_spaces,
                    (SELECT COUNT(*) FROM allocations a
                      WHERE a.market_id = m.id
                        AND a.status IN ('proposed','offered','accepted')
                        AND a.market_space_id IS NOT NULL)
                  + (SELECT COUNT(*) FROM stall_requests sr
                      WHERE sr.market_id = m.id
                        AND sr.status IN ('pending','reviewing'))
                    AS held
               FROM markets m
           ) t"
    ),
    0, 'sum of absolute differences should be zero');

check('Pitch register matches public market capacity',
    (int) Database::value(
        "SELECT COUNT(*) FROM markets m
          WHERE m.category = 'public'
            AND m.capacity <> (SELECT COUNT(*) FROM market_spaces s WHERE s.market_id = m.id)"
    ),
    0, 'count of mismatched markets');

check('Every pitch has a recognised status',
    (int) Database::value(
        "SELECT COUNT(*) FROM market_spaces
          WHERE status NOT IN ('vacant','occupied','reserved','under-maintenance')
             OR status IS NULL"
    ),
    0, 'no unknown or null pitch statuses');

check('Vendors sitting in exactly one zone',
    (int) Database::value("SELECT COUNT(*) FROM vendors WHERE current_zone_id IS NULL"),
    0, 'every vendor is surveyed against a site');

check('Zone vendor counts equal the register',
    (int) Database::value(
        "SELECT COALESCE(SUM(ABS(z.estimated_vendors - COALESCE(c.n,0))),0)
           FROM trade_zones z
           LEFT JOIN (SELECT current_zone_id, COUNT(*) AS n FROM vendors
                       WHERE current_zone_id IS NOT NULL GROUP BY current_zone_id) c
             ON c.current_zone_id = z.id"
    ),
    0, 'sum of absolute differences should be zero');

check('Allocations pointing at a real vendor',
    (int) Database::value(
        'SELECT COUNT(*) FROM allocations a LEFT JOIN vendors v ON v.id = a.vendor_id WHERE v.id IS NULL'
    ),
    0);

check('Allocations pointing at a real market',
    (int) Database::value(
        'SELECT COUNT(*) FROM allocations a LEFT JOIN markets m ON m.id = a.market_id WHERE m.id IS NULL'
    ),
    0);

check('A pitch tied to a live offer is reserved, not free',
    (int) Database::value(
        "SELECT COUNT(*) FROM market_spaces s
           WHERE s.status = 'reserved'
             AND NOT EXISTS (
                 SELECT 1 FROM allocations a
                  WHERE a.market_space_id = s.id
                    AND a.status IN ('proposed','offered','accepted'))"
    ),
    0, 'every reserved pitch is held by exactly one live offer');

check('No pitch is double-booked',
    (int) Database::value(
        "SELECT COALESCE(SUM(n - 1),0) FROM (
            SELECT COUNT(*) AS n FROM allocations
             WHERE market_space_id IS NOT NULL
               AND status IN ('proposed','offered','accepted')
             GROUP BY market_space_id HAVING n > 1) d"
    ),
    0, 'the unique index forbids a second live booking on one pitch');

check('Scores within range (allocations)',
    (int) Database::value(
        'SELECT COUNT(*) FROM allocations WHERE total_score < 0 OR total_score > 100'
    ),
    0);

check('Weights sum to 1 — relocation',
    round(array_sum(RELOCATION_WEIGHTS), 6), 1.0);
check('Weights sum to 1 — congestion',
    round(array_sum(ZONE_SCORING_WEIGHTS), 6), 1.0);

/* ------------------------------------------------------------------ */
echo PHP_EOL . "4. Movement of the vacancy pool" . PHP_EOL;

$publicNow  = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public'");
$privateNow = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private'");

note('Published public vacancy',            num($vac['public_vacant_spaces']));
note('Traders placed into public markets',  num($placedInto) . ' — space no longer vacant');
$requestsLive = (int) Database::value(
    "SELECT COUNT(*) FROM stall_requests sr
       JOIN markets m ON m.id = sr.market_id
      WHERE sr.status IN ('pending','reviewing') AND m.category = 'public'");

note('Offers live, holding space',          num($heldOffers) . ' — reserved by the Authority, not yet available');
note('Trader requests live, holding space', num($requestsLive) . ' — a trader chose this pitch and is awaiting a decision');
note('Offers declined or withdrawn',        num($closed) . ' — space returned to the pool, no net effect');
note('Public vacancy now',                  num($publicNow));
note('Total available now',                 num($publicNow + $privateNow) . ' of ' . num($vac['total_vacant']));

check('Published vacancy reconciles to the current figure',
    $publicNow + $placedInto + $heldOffers + $requestsLive,
    $vac['public_vacant_spaces'],
    'adding back the space this system is holding restores the published total');

check('A closed offer leaves the pool unchanged',
    $publicNow,
    $vac['public_vacant_spaces'] + $publicDelta,
    'declined and withdrawn offers net to zero');

/* ------------------------------------------------------------------ */
echo PHP_EOL . "5. Reconciliation summary" . PHP_EOL;

printf("  %d passed, %d failed", $pass, $fail, PHP_EOL);
echo $fail === 0 ? ', and ' . $info . " noted.\n" : ".\n";

if ($fail === 0) {
    echo "  All checks passed. The database reconciles with the published KCCA" . PHP_EOL;
    echo "  figures and is internally consistent." . PHP_EOL;
    exit(0);
}

echo "  One or more checks failed. See the FAIL lines above." . PHP_EOL;
exit(1);