<?php
/**
 * Appearance preferences, one row per account.
 *
 * Each user chooses how their own account looks: frosted glass or plain
 * panels, animations on or off, guidance text shown or hidden. The choice
 * belongs to the account, not to the browser, so it follows the user to
 * another device and does not leak between two people who share a computer.
 *
 * The table is created the first time it is needed, so an existing database
 * does not have to be migrated by hand. If the database account cannot
 * create tables, or anything else here fails, the defaults apply and the
 * system carries on: appearance is never a reason to refuse a page.
 */

declare(strict_types=1);

final class Preferences
{
    public const DEFAULTS = [
        'glass'   => 'on',            // frosted panels
        'motion'  => 'on',            // animations
        'guide'   => 'off',           // explanatory text
        'notify'  => 'on',            // pop-up alerts when colleagues change something
        'density' => 'comfortable',   // or 'compact'
    ];

    /** What each setting may hold. Anything else is ignored, never stored. */
    private const ALLOWED = [
        'glass'   => ['on', 'off'],
        'motion'  => ['on', 'off'],
        'guide'   => ['on', 'off'],
        'notify'  => ['on', 'off'],
        'density' => ['comfortable', 'compact'],
    ];

    private static array $cache = [];
    private static ?bool $ready = null;

    /** The signed-in account's preferences, always complete. */
    public static function get(int $userId): array
    {
        if ($userId <= 0) {
            return self::DEFAULTS;
        }
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $prefs = self::DEFAULTS;
        try {
            if (self::ensure()) {
                $row = Database::one(
                    'SELECT glass, motion, guide, notify, density FROM user_preferences WHERE user_id = ? LIMIT 1',
                    [$userId]
                );
                if ($row) {
                    foreach (self::ALLOWED as $key => $values) {
                        if (isset($row[$key]) && in_array($row[$key], $values, true)) {
                            $prefs[$key] = $row[$key];
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            /* defaults */
        }

        return self::$cache[$userId] = $prefs;
    }

    /**
     * Save any of glass / motion / guide. Anything else is ignored, and a
     * value other than "on" or "off" is ignored, so the table can only ever
     * hold what the interface can display.
     *
     * @return array the account's preferences after saving
     */
    public static function save(int $userId, array $in): array
    {
        /* Only the keys actually sent are written, in one statement, so two
           requests that each change a different setting cannot overwrite one
           another with stale values. */
        $set = [];
        foreach (self::ALLOWED as $key => $values) {
            if (isset($in[$key]) && in_array($in[$key], $values, true)) {
                $set[$key] = (string) $in[$key];
            }
        }

        if ($userId > 0 && $set !== [] && self::ensure()) {
            $cols = array_keys($set);
            Database::run(
                'INSERT INTO user_preferences (user_id, ' . implode(', ', $cols) . ')
                 VALUES (?' . str_repeat(', ?', count($cols)) . ')
                 ON DUPLICATE KEY UPDATE '
                . implode(', ', array_map(static fn(string $c): string => "$c = VALUES($c)", $cols)),
                array_merge([$userId], array_values($set))
            );
        }

        unset(self::$cache[$userId]);
        return self::get($userId);
    }

    /** Create the table once, when it is missing. */
    private static function ensure(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $exists = (int) Database::value(
                "SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = 'user_preferences'"
            );
            if ($exists === 0) {
                Database::run(
                    "CREATE TABLE user_preferences (
                        user_id    INT UNSIGNED NOT NULL PRIMARY KEY,
                        glass      VARCHAR(3)  NOT NULL DEFAULT 'on',
                        motion     VARCHAR(3)  NOT NULL DEFAULT 'on',
                        guide      VARCHAR(3)  NOT NULL DEFAULT 'off',
                        notify     VARCHAR(3)  NOT NULL DEFAULT 'on',
                        density    VARCHAR(12) NOT NULL DEFAULT 'comfortable',
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } else {
                /* A table made by an earlier version gains the newer columns. */
                $have = array_column(Database::all(
                    "SELECT column_name AS c FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'user_preferences'"
                ), 'c');
                $have = array_map('strtolower', $have);
                if (!in_array('notify', $have, true)) {
                    Database::run("ALTER TABLE user_preferences ADD COLUMN notify VARCHAR(3) NOT NULL DEFAULT 'on'");
                }
                if (!in_array('density', $have, true)) {
                    Database::run("ALTER TABLE user_preferences ADD COLUMN density VARCHAR(12) NOT NULL DEFAULT 'comfortable'");
                }
            }
            return self::$ready = true;
        } catch (Throwable $e) {
            return self::$ready = false;
        }
    }
}
