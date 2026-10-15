<?php
/**
 * POST /api/enforcement_action.php
 *   action=notify     zone_id, deadline
 *   action=issue-ultimatum zone_id, deadline
 *   action=log        zone_id, action_type, officers, vendors, outcome
 *   action=complete   action_id, vendors_relocated, verandas_cleared
 *   action=escalate   action_id
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
requirePerm('enforce');

$action = (string) ($_POST['action'] ?? '');

$zoneId = (int) ($_POST['zone_id'] ?? 0);
$zone = null;
if ($zoneId > 0) {
    $zone = Database::one(
        'SELECT z.*, d.name AS division_name FROM trade_zones z
           JOIN divisions d ON d.id = z.division_id WHERE z.id = ? LIMIT 1',
        [$zoneId]
    );
    if (!$zone) {
        jsonOut(['error' => 'That trade zone is not in the register'], 404);
    }
}

switch ($action) {

    /* ---------------- Issue a notice or ultimatum ---------------- */
    case 'notify':
    case 'issue-ultimatum': {
        $isUltimatum = $action === 'issue-ultimatum';
        $days  = (int) ($_POST['days'] ?? ($isUltimatum ? 14 : 7));
        $days  = (int) clamp($days, 1, 90);
        $deadline = date('Y-m-d', strtotime("+$days days"));
        $type = $isUltimatum ? 'ultimatum' : 'notice';

        $ref = sprintf('ENF-%s-%02d', $zone['code'], (int) Database::value(
            'SELECT COUNT(*) FROM enforcement_actions WHERE zone_id = ?', [$zoneId]) + 1);

        $id = Database::transaction(static function () use (
            $ref, $zoneId, $type, $deadline, $zone, $isUltimatum, $days
        ): int {
            $id = Database::insert('enforcement_actions', [
                'reference'     => $ref,
                'zone_id'       => $zoneId,
                'action_type'   => $type,
                'status'        => 'issued',
                'issued_date'   => date('Y-m-d'),
                'deadline_date' => $deadline,
                'vendors_affected' => (int) $zone['estimated_vendors'],
                /* Trimmed with a fallback, not ??. A browser sends the field
                   empty when the officer left it alone, and ?? passes that
                   through, so an empty string would be stored as the authority
                   that authorised the action where the default was meant. */
                'authorised_by' => trim((string) ($_POST['authorised_by'] ?? '')) !== ''
                                    ? trim((string) $_POST['authorised_by'])
                                    : 'KCCA Office of the Executive Director',
                'officer_name'  => $_POST['officer_name'] ?? null,
                'officer_number'=> $_POST['officer_number'] ?? null,
                'outcome'       => $isUltimatum
                    ? sprintf('Two-week ultimatum issued directing vendors to vacate %s and move to available gazetted market space by %s.',
                              $zone['name'], $deadline)
                    : sprintf('Formal notice issued to vendors at %s. Vacancy required by %s.',
                              $zone['name'], $deadline),
            ]);

            Database::run(
                "UPDATE trade_zones SET status = 'notice-issued', notified_date = ?, deadline_date = ? WHERE id = ?",
                [date('Y-m-d'), $deadline, $zoneId]
            );
            Database::run(
                "UPDATE vendors SET status = 'notified' WHERE current_zone_id = ? AND status = 'surveyed'",
                [$zoneId]
            );
            return $id;
        });

        Auth::log('issue-' . $type, 'enforcement_actions', $id, $ref . ' for ' . $zone['name']);
        jsonOut([
            'ok' => true,
            'message' => ($isUltimatum ? 'Ultimatum' : 'Notice') . ' issued for ' . $zone['name']
                       . '. Deadline ' . fmtDate($deadline) . '.',
            'reference' => $ref,
            'deadline' => fmtDate($deadline),
        ]);
    }

    /* ---------------- Log an enforcement operation ---------------- */
    case 'log': {
        $type = (string) ($_POST['action_type'] ?? 'follow-up');
        if (!in_array($type, ['survey','notice','ultimatum','eviction','relocation-support','follow-up','litigation'], true)) {
            jsonOut(['error' => 'Unknown action type'], 400);
        }

        $ref = sprintf('ENF-%s-%02d', $zone['code'], (int) Database::value(
            'SELECT COUNT(*) FROM enforcement_actions WHERE zone_id = ?', [$zoneId]) + 1);

        $id = Database::insert('enforcement_actions', [
            'reference'      => $ref,
            'zone_id'        => $zoneId,
            'action_type'    => $type,
            'status'         => 'in-progress',
            'issued_date'    => date('Y-m-d'),
            'deadline_date'  => ($_POST['deadline_date'] ?? '') ?: null,
            'officers_deployed' => (int) ($_POST['officers_deployed'] ?? 0),
            'vendors_affected'  => (int) ($_POST['vendors_affected'] ?? $zone['estimated_vendors']),
            'vendors_relocated' => (int) ($_POST['vendors_relocated'] ?? 0),
            'verandas_cleared'  => (int) ($_POST['verandas_cleared'] ?? 0),
            'materials_removed_m3' => (float) ($_POST['materials_removed_m3'] ?? 0),
            'authorised_by'  => trim((string) ($_POST['authorised_by'] ?? '')) !== ''
                                ? trim((string) $_POST['authorised_by'])
                                : 'KCCA Office of the Executive Director',
            'officer_name'   => trim((string) ($_POST['officer_name'] ?? '')) ?: null,
            'officer_number' => trim((string) ($_POST['officer_number'] ?? '')) ?: null,
            'outcome'        => trim((string) ($_POST['outcome'] ?? '')) ?: null,
        ]);

        Auth::log('log-enforcement', 'enforcement_actions', $id, $ref . ' — ' . $type . ' at ' . $zone['name']);
        jsonOut(['ok' => true, 'message' => 'Enforcement record ' . $ref . ' logged.', 'reference' => $ref]);
    }

    /* ---------------- Close off an operation ---------------- */
    case 'complete': {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $rec = Database::one('SELECT * FROM enforcement_actions WHERE id = ? LIMIT 1', [$actionId]);
        if (!$rec) {
            jsonOut(['error' => 'That record does not exist'], 404);
        }

        $relocated = (int) ($_POST['vendors_relocated'] ?? $rec['vendors_relocated']);
        $affected  = max(1, (int) $rec['vendors_affected']);
        $pct = min(100, (int) round($relocated / $affected * 100));

        $verandas = (int) ($_POST['verandas_cleared'] ?? $rec['verandas_cleared']);

        Database::transaction(static function () use (
            $actionId, $relocated, $verandas, $pct, $zoneId
        ): void {
            Database::run(
                "UPDATE enforcement_actions SET status = 'completed', completed_date = CURDATE(),
                        vendors_relocated = ?, verandas_cleared = ? WHERE id = ?",
                [$relocated, $verandas, $actionId]
            );

            // A zone is cleared once essentially every affected vendor has a pitch.
            if ($pct >= 90) {
                Database::run("UPDATE trade_zones SET status = 'cleared' WHERE id = ?", [$zoneId]);
            } elseif ($pct > 0) {
                Database::run("UPDATE trade_zones SET status = 'relocating' WHERE id = ?", [$zoneId]);
            }
        });

        Auth::log('complete-enforcement', 'enforcement_actions', $actionId, $rec['reference'] . ' completed');
        jsonOut([
            'ok' => true,
            'message' => 'Record ' . $rec['reference'] . ' closed. ' . $relocated . ' of '
                       . $affected . ' vendors relocated (' . $pct . '%).',
            'zone_status' => $pct >= 90 ? 'cleared' : ($pct > 0 ? 'relocating' : 'clearing'),
        ]);
    }

    /* ---------------- Escalate to prosecution ---------------- */
    case 'escalate': {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $rec = Database::one('SELECT * FROM enforcement_actions WHERE id = ? LIMIT 1', [$actionId]);
        if (!$rec) {
            jsonOut(['error' => 'That record does not exist'], 404);
        }

        Database::transaction(static function () use ($actionId, $zoneId): void {
            Database::run("UPDATE enforcement_actions SET status = 'escalated' WHERE id = ?", [$actionId]);
            Database::run("UPDATE trade_zones SET status = 'litigation-hold' WHERE id = ?", [$zoneId]);
        });

        Auth::log('escalate-enforcement', 'enforcement_actions', $actionId, $rec['reference'] . ' escalated');
        jsonOut(['ok' => true, 'message' => 'Case escalated. The zone is now on litigation hold.']);
    }

    default:
        jsonOut(['error' => 'Unknown action'], 400);
}