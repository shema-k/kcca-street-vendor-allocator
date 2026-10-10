<?php
/**
 * POST /api/backup.php
 *   action=create | delete
 *
 * Backups are taken through the settings screen or this endpoint. Every
 * backup is recorded before it is reported as taken.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();
require_once __DIR__ . '/../lib/Backup.php';

$action = (string) ($_POST['action'] ?? '');

switch ($action) {

    case 'create': {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (strlen($reason) > 255) {
            jsonOut(['ok' => false, 'error' => 'The reason must be 255 characters or fewer.'], 422);
        }

        try {
            $result = Backup::create($reason);
        } catch (Throwable $e) {
            jsonOut(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        Auth::log('create-backup', 'backups', $result['id'],
            $result['reference'] . ' — ' . num($result['rows']) . ' rows');

        jsonOut([
            'ok'      => true,
            'message' => sprintf('Backup %s written: %s rows.',
                $result['reference'], num($result['rows'])),
            'reference' => $result['reference'],
            'rows'    => $result['rows'],
            'bytes'   => $result['bytes'],
            'checksum'=> $result['checksum'],
        ]);
    }

    case 'delete': {
        $id = (int) ($_POST['backup_id'] ?? 0);
        if (!Backup::delete($id)) {
            jsonOut(['ok' => false, 'error' => 'That backup does not exist.'], 404);
        }
        Auth::log('delete-backup', 'backups', $id, 'Backup removed');
        jsonOut(['ok' => true, 'message' => 'The backup has been deleted.']);
    }

    default:
        jsonOut(['error' => 'Unknown action'], 400);
}