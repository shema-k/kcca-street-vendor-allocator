<?php
/**
 * GET /api/role_panel.php
 *
 * The signed-in officer's dashboard, as HTML for the drawer behind the
 * menu button in the top bar.
 *
 * It is the same sections the dashboard page renders, drawn by the same
 * partials, so there is one version of every figure rather than a summary
 * copy that drifts. The drawer is a way to reach it from anywhere, not a
 * second dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    jsonOut(['error' => 'Not signed in'], 401);
}

$dash  = RoleDashboard::build();
$scope = RoleDashboard::scope();
$f     = facts();
$me    = Auth::user();
$remit = Auth::remit();

/* The section partials share a small set of variables, which the dashboard
   page also supplies. Duplicating the list here rather than inventing a
   context object keeps the partials plain PHP includes, which is what makes
   them easy to read and easy to test. */
$funnel = [
    ['label' => 'To relocate',   'value' => (int) Database::value("SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE 1=1" . RoleDashboard::scopeSql($scope)), 'color' => '#464646'],
    ['label' => 'Offered space', 'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'offered-space'" . RoleDashboard::scopeSql($scope)), 'color' => '#EAA515'],
    ['label' => 'Relocated',     'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'relocated'" . RoleDashboard::scopeSql($scope)), 'color' => '#1B7F4D'],
    ['label' => 'Declined',      'value' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE status = 'declined'" . RoleDashboard::scopeSql($scope)), 'color' => '#C0261A'],
];

$stageOrder = ['surveyed', 'notified', 'offered-space', 'relocated', 'declined', 'deferred', 'complaint'];
$stageData  = [];
foreach ($stageOrder as $s) {
    $n = (int) Database::value(
        "SELECT COUNT(*) FROM vendors WHERE status = :s" . RoleDashboard::scopeSql($scope),
        ['s' => $s]
    );
    if ($n > 0) {
        $stageData[] = ['label' => humanise($s), 'value' => $n, 'color' => '#DC351D'];
    }
}

ob_start();
foreach ($dash['sections'] as $section) {
    require __DIR__ . '/../partials/dash_' . $section['render'] . '.php';
}
$body = (string) ob_get_clean();
jsonOut([
    'ok'      => true,
    'role'    => $dash['role'],
    'label'   => $dash['label'],
    'scope'   => $dash['scope'],
    'blurb'   => $dash['blurb'],
    'stats'   => $dash['stats'],
    'decisions' => $dash['decisions'],
    'sections' => $body,
    'charts'  => substr_count($body, 'class="chart"'),
]);