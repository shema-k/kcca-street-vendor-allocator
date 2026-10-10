<?php
/**
 * KCCA VRSP — Backups.
 *
 * "Backup important records" taken seriously means two things: a copy that
 * can actually be restored, and a record that the copy exists and who took
 * it. This writes both.
 *
 * Format is line-oriented SQL INSERTs with a table-ordered restore note, so
 * a backup is readable without the application and can be replayed with the
 * mysql client alone.
 */

declare(strict_types=1);

final class Backup
{
    /** Tables that matter, in the order they must be restored. */
    public const TABLES = [
        'divisions', 'parishes', 'markets', 'market_spaces', 'trade_zones',
        'vendors', 'trade_categories', 'footfall_observations',
        'land_use_restrictions', 'market_trade_mix', 'recommendations',
        'allocations', 'relocation_events', 'enforcement_actions',
        'strategic_indicators', 'kcca_services', 'notices', 'settings',
    ];

    /** Tables never included: derived, or enormous and rebuildable. */
    public const EXCLUDED = ['users', 'audit_log', 'backups'];

    /** Where backups are written. */
    public static function directory(): string
    {
        $dir = sys_get_temp_dir() . '/kcca-vrsp-backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir();
    }

    /**
     * Write a full SQL backup. Returns the recorded backup row.
     *
     * @throws RuntimeException when the backup cannot be written
     */
    public static function create(string $reason = ''): array
    {
        $reference = 'BKP-' . date('Ymd-His');
        $path      = self::directory() . '/' . $reference . '.sql';

        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException(
                'The backup directory is not writable, so no backup was taken. '
                . 'Contact the System Administrator.'
            );
        }

        $pdo = Database::pdo();

        fwrite($handle, self::header($reference, $reason));

        /* The schema goes in first, so the file is self-contained. A
           backup that needs a separate schema step is a backup that can
           be restored wrongly, which is worse than none at all. */
        $schema = @file_get_contents(ROOT_PATH . '/database/schema.sql');
        if ($schema !== false && $schema !== '') {
            fwrite($handle, "\n-- ---------------------------------------------------------------------\n");
            fwrite($handle, "-- Schema, so this file restores on its own\n");
            fwrite($handle, "-- ---------------------------------------------------------------------\n");
            fwrite($handle, "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4;\n");
            fwrite($handle, "USE `" . DB_NAME . "`;\n\n");
            fwrite($handle, $schema);
            fwrite($handle, "\n");
        }

        $rows = 0;
        foreach (self::TABLES as $table) {
            $count = (int) Database::value("SELECT COUNT(*) FROM `$table`");
            if ($count === 0) {
                continue;
            }

            fwrite($handle, "\n-- ----------------------------------------------------------\n");
            fwrite($handle, "-- Table: $table ($count rows)\n");
            fwrite($handle, "-- ----------------------------------------------------------\n");
            fwrite($handle, "DELETE FROM `$table`;\n");

            /* Write in chunks so a large table does not exhaust memory. */
            $offset = 0;
            $chunk  = 500;
            while ($offset < $count) {
                $batch = Database::all(
                    "SELECT * FROM `$table` ORDER BY 1 LIMIT $chunk OFFSET $offset"
                );
                if ($batch === []) {
                    break;
                }

                foreach ($batch as $row) {
                    $cols = array_keys($row);
                    $vals = array_map(
                        static function (string $c) use ($row, $pdo) {
                            /* A NULL must be written as NULL, not as an empty
                               string: the latter is refused by numeric
                               columns and would silently corrupt a
                               restored database. */
                            if ($row[$c] === null) {
                                return 'NULL';
                            }
                            return $pdo->quote((string) $row[$c]);
                        },
                        $cols
                    );
                    fwrite($handle, sprintf(
                        "INSERT INTO `%s` (%s) VALUES (%s);\n",
                        $table,
                        '`' . implode('`,`', $cols) . '`',
                        implode(',', $vals)
                    ));
                    $rows++;
                }

                $offset += $chunk;
            }
        }

        fwrite($handle, "\n-- Backup complete.\n");
        fclose($handle);

        $bytes = (int) @filesize($path);
        $checksum = hash_file('sha256', $path) ?: null;

        /* Record the backup. Note that the registration is written after the
           file is complete, so a half-written backup is never recorded. */
        $id = Database::insert('backups', [
            'reference'    => $reference,
            'kind'         => 'sql',
            'report'       => 'full-database',
            'row_count'    => $rows,
            'bytes'        => $bytes,
            'path'         => $path,
            'checksum'     => $checksum,
            'requested_by' => (string) (Auth::check() ? (Auth::user()['full_name'] ?? 'system') : 'system'),
            'reason'       => $reason !== '' ? $reason : null,
        ]);

        return [
            'id'         => $id,
            'reference'  => $reference,
            'path'       => $path,
            'rows'       => $rows,
            'bytes'      => $bytes,
            'checksum'   => $checksum,
        ];
    }

    private static function header(string $reference, string $reason): string
    {
        $me = Auth::check() ? (Auth::user()['full_name'] ?? 'system') : 'system';

        return implode("\n", [
            '-- ---------------------------------------------------------------------',
            '-- ' . APP_NAME . ' — database backup',
            '-- Reference : ' . $reference,
            '-- Taken     : ' . date('Y-m-d H:i:s') . ' EAT',
            '-- Taken by  : ' . $me,
            '-- Reason    : ' . ($reason !== '' ? $reason : 'not stated'),
            '-- Server    : ' . DB_NAME . ' on ' . DB_HOST,
            '--',
            '-- Restore with:  mysql < ' . $reference . '.sql',
            '--',
            '-- Contains personal data: vendor names, national ID numbers and',
            '-- telephone numbers. Handle in accordance with the KCCA data',
            '-- protection policy.',
            '-- ---------------------------------------------------------------------',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            '',
        ]);
    }

    /** Recent backups, newest first. */
    public static function recent(int $limit = 25): array
    {
        return Database::all(
            'SELECT * FROM backups ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
    }

    /**
     * Delete a backup file and its record. The file is only removed once
     * the record has gone, so a failure cannot orphan the file silently.
     */
    public static function delete(int $id): bool
    {
        $row = Database::one('SELECT * FROM backups WHERE id = ?', [$id]);
        if (!$row) {
            return false;
        }

        Database::run('DELETE FROM backups WHERE id = ?', [$id]);

        if ($row['path'] && is_file($row['path'])) {
            @unlink($row['path']);
        }

        return true;
    }

    /**
     * Statistics for the settings screen.
     *
     * @return array{rows:int, bytes:int, size_human:string, last:?string, oldest:?string}
     */
    public static function stats(): array
    {
        $row = Database::one(
            "SELECT COALESCE(SUM(row_count),0) AS rows_total,
                    COALESCE(SUM(bytes),0) AS bytes_total,
                    MAX(created_at) AS newest,
                    MIN(created_at) AS oldest,
                    COUNT(*) AS count
               FROM backups WHERE kind = 'sql'"
        ) ?: ['rows_total' => 0, 'bytes_total' => 0, 'newest' => null, 'oldest' => null, 'count' => 0];

        $bytes = (int) $row['bytes_total'];

        return [
            'rows'       => (int) $row['rows_total'],
            'bytes'      => $bytes,
            'size_human' => $bytes > 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format($bytes / 1024, 0) . ' KB',
            'count'      => (int) $row['count'],
            'last'       => $row['newest'],
            'oldest'     => $row['oldest'],
        ];
    }

    /** Count the records that matter, for the backup summary line. */
    public static function recordCounts(): array
    {
        $out = [];
        foreach (self::TABLES as $t) {
            $out[$t] = (int) Database::value("SELECT COUNT(*) FROM `$t`");
        }
        return $out;
    }
}