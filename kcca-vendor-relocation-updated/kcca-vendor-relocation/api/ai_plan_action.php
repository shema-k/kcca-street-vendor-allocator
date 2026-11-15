<?php
/**
 * POST /api/ai_plan_action.php
 *   action=submit   mode, batch, floor, division, stay, zone, fingerprint
 *
 * Saves an AI allocation plan as ordinary recommendations awaiting approval.
 *
 * The plan is recomputed here from the same parameters rather than taken from
 * the browser, so a client cannot post an assignment the planner did not make.
 * The fingerprint is the digest of the plan the officer was shown; if the
 * register has moved on since - a pitch taken, a trader placed - it no longer
 * matches and the save is refused, so nobody approves a plan they did not see.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
requirePerm('allocate');
requireScreenApi('ai-planner');

$action = (string) ($_POST['action'] ?? '');
if ($action !== 'submit') {
    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

@set_time_limit(180);

$params = AllocationPlanner::params($_POST);
$plan   = AllocationPlanner::plan($params);

if ($plan['assignments'] === []) {
    jsonOut(['ok' => false, 'error' => 'There is nothing in this plan to submit.'], 409);
}

if (!hash_equals($plan['fingerprint'], (string) ($_POST['fingerprint'] ?? ''))) {
    jsonOut([
        'ok'    => false,
        'error' => 'The register has changed since this plan was drawn up (a pitch was taken or a trader was placed). '
                 . 'Run the plan again so you approve what is current.',
    ], 409);
}

try {
    $result = AllocationPlanner::submit($plan);
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'The plan could not be saved. Nothing was changed.'], 500);
}

Auth::log(
    'ai-plan-submit', 'allocation_plan', null,
    sprintf(
        '%s (%s policy): %d recommendation(s) proposed for approval, %d could not be saved. Fingerprint %s.',
        $plan['reference'], AllocationPlanner::MODES[$params['mode']]['label'],
        $result['saved'], count($result['failed']), substr($plan['fingerprint'], 0, 12)
    )
);

$msg = sprintf(
    '%d recommendation%s sent for approval under plan %s.',
    $result['saved'], $result['saved'] === 1 ? '' : 's', $plan['reference']
);
if ($result['failed'] !== []) {
    $msg .= ' ' . count($result['failed']) . ' could not be saved and were left out.';
}

jsonOut([
    'ok'        => $result['saved'] > 0,
    'message'   => $msg,
    'error'     => $result['saved'] > 0 ? null : 'No recommendation could be saved from this plan.',
    'saved'     => $result['saved'],
    'failed'    => $result['failed'],
    'reference' => $plan['reference'],
]);
