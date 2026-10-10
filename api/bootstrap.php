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

/** Require a valid CSRF token on a state-changing request. */
function requireToken(): void
{
    requireSession();
    if (!Auth::checkCsrf($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonOut(['error' => 'CSRF token mismatch — reload the page', 'code' => 419], 419);
    }
}


/* scoredZones(), candidateMarkets(), vendorForMatching(), vendorStatusCounts(),
   q() and qInt() are shared query helpers. They live in lib/bootstrap.php so
   the pages and the JSON endpoints compute identical figures. */
