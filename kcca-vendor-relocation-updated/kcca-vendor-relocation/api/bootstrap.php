<?php
/**
 * Shared bootstrap for JSON endpoints.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

Auth::startSession();

/** Require an authenticated session; JSON 401 otherwise. */
function requireSession(): void
{
    if (!Auth::check()) {
        jsonOut(['error' => 'Not signed in', 'code' => 401], 401);
    }
}

/** Require a permission; JSON 403 otherwise. */
function requirePerm(string $perm): void
{
    requireSession();
    if (!Auth::can($perm)) {
        jsonOut(['error' => 'Your role does not permit this action', 'code' => 403], 403);
    }
}

/** Require that this role is in the audience of a screen, as the rail defines it. */
function requireScreenApi(string $screenId): void
{
    requireSession();
    if (!Auth::screenAllows($screenId)) {
        jsonOut(['error' => 'This view is not part of your role', 'code' => 403], 403);
    }
}

/** Require a valid CSRF token on a state-changing request. */
function requireToken(): void
{
    requireSession();
    if (!Auth::checkCsrf($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonOut(['error' => 'CSRF token mismatch — reload the page', 'code' => 419], 419);
    }
}


/* ---------------------------------------------------------------------
   Division scope, enforced where the change is made.

   A Division Mayor is shown only their own division, but a screen that hides
   another division's traders does not stop a request that names one. These
   guards refuse it on the server, so the scope holds for a hand-written POST
   as well as for the page.
   --------------------------------------------------------------------- */

/** Refuse unless this trader belongs to the signed-in Mayor's division. */
function requireVendorInScope(int $vendorId): void
{
    $lock = divisionLock();
    if ($lock === null || $vendorId <= 0) {
        return;
    }
    $division = (int) Database::value('SELECT division_id FROM vendors WHERE id = ?', [$vendorId]);
    if ($division !== (int) $lock['id']) {
        jsonOut(['ok' => false, 'error' => 'That trader is outside your division.', 'code' => 403], 403);
    }
}

/** Refuse unless this market is in the signed-in Mayor's division. */
function requireMarketInScope(int $marketId): void
{
    $lock = divisionLock();
    if ($lock === null || $marketId <= 0) {
        return;
    }
    $division = (int) Database::value('SELECT division_id FROM markets WHERE id = ?', [$marketId]);
    if ($division !== (int) $lock['id']) {
        jsonOut(['ok' => false, 'error' => 'That market is outside your division.', 'code' => 403], 403);
    }
}

/** Refuse unless this allocation's trader is in the Mayor's division. */
function requireAllocationInScope(int $allocationId): void
{
    if (divisionLock() === null) {
        return;
    }
    requireVendorInScope((int) Database::value('SELECT vendor_id FROM allocations WHERE id = ?', [$allocationId]));
}

/** Refuse unless this recommendation's trader is in the Mayor's division. */
function requireRecommendationInScope(int $recommendationId): void
{
    if (divisionLock() === null) {
        return;
    }
    requireVendorInScope((int) Database::value('SELECT vendor_id FROM recommendations WHERE id = ?', [$recommendationId]));
}


/* scoredZones(), candidateMarkets(), vendorForMatching(), vendorStatusCounts(),
   q() and qInt() are shared query helpers. They live in lib/bootstrap.php so
   the pages and the JSON endpoints compute identical figures. */
