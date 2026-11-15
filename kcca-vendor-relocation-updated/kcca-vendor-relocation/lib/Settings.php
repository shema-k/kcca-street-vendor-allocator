<?php
/**
 * KCCA VRSP — Settings, read from the database with the code defaults as
 * a fallback. Lets an administrator change policy without a deployment.
 */

declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    /** Every setting, grouped for the settings screen. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $grouped = [];
        try {
            foreach (Database::all('SELECT * FROM settings ORDER BY group_name, setting_key') as $row) {
                $grouped[$row['group_name']][] = $row;
            }
        } catch (Throwable $e) {
            // Settings table missing (pre-migration): fall back to defaults.
            $grouped = [];
        }

        return self::$cache = $grouped;
    }

    /** One setting value, or the supplied default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        static $values = null;
        if ($values === null) {
            $values = [];
            try {
                foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $r) {
                    $values[$r['setting_key']] = $r['setting_value'];
                }
            } catch (Throwable $e) {
                $values = [];
            }
        }

        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $row = null;
        foreach (self::all() as $group) {
            foreach ($group as $s) {
                if ($s['setting_key'] === $key) {
                    $row = $s;
                    break 2;
                }
            }
        }

        return self::cast($values[$key], $row['input_type'] ?? 'text');
    }

    private static function cast(string $raw, string $type): mixed
    {
        return match ($type) {
            'number'   => is_numeric($raw) ? (float) $raw : 0.0,
            'boolean'  => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            default    => $raw,
        };
    }

    /** Numeric setting as a float, always. */
    public static function num(string $key, float $default): float
    {
        $v = self::get($key, $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    /** Boolean setting. */
    public static function bool(string $key, bool $default): bool
    {
        $v = self::get($key, $default);
        return is_bool($v) ? $v : (bool) $v;
    }

    /** Integer setting. */
    public static function int(string $key, int $default): int
    {
        return (int) self::num($key, (float) $default);
    }

    /**
     * Save one setting after validating it against its declared bounds.
     * Returns an error message, or null on success.
     */
    public static function put(string $key, string $value, array $spec): ?string
    {
        if (!empty($spec['min']) && is_numeric($value) && (float) $value < (float) $spec['min']) {
            return $spec['label'] . ' cannot be less than ' . $spec['min'] . '.';
        }
        if (!empty($spec['max']) && is_numeric($value) && (float) $value > (float) $spec['max']) {
            return $spec['label'] . ' cannot be more than ' . $spec['max'] . '.';
        }

        Database::run(
            'UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?',
            [$value, Auth::check() ? (Auth::user()['full_name'] ?? 'system') : 'system', $key]
        );

        self::$cache = null;
        return null;
    }

    /**
     * Relocation weights, from settings if present, else from config.
     * The two must always sum to 1.
     */
    public static function relocationWeights(): array
    {
        $weights = RELOCATION_WEIGHTS;
        foreach (array_keys($weights) as $k) {
            $key = 'relocation_weight_' . $k;
            $v = self::get($key);
            if (is_numeric($v)) {
                $weights[$k] = (float) $v;
            }
        }
        return self::normalise($weights);
    }

    /** Congestion weights, same treatment. */
    public static function zoneWeights(): array
    {
        $weights = ZONE_SCORING_WEIGHTS;
        foreach (array_keys($weights) as $k) {
            $key = 'congestion_weight_' . $k;
            $v = self::get($key);
            if (is_numeric($v)) {
                $weights[$k] = (float) $v;
            }
        }
        return self::normalise($weights);
    }

    /** Scale a weight set so it sums to exactly 1. */
    private static function normalise(array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return $weights;
        }
        foreach ($weights as $k => $v) {
            $weights[$k] = round($v / $sum, 6);
        }
        return $weights;
    }
}