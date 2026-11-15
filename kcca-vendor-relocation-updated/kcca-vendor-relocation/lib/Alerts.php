<?php
/**
 * Alerts — short messages the system sends to one signed-in person.
 *
 * Used when a trader takes (or fails to take) a pitch: the trader is told the
 * outcome with the stall's details and their chairman's contact, and the
 * market's chairman is told a trader has joined. Traders and chairmen can also
 * message each other about a stall.
 *
 * The table is created on first use, so an existing database needs no
 * migration. Sending never breaks the action that triggered it: a failure to
 * alert is logged and swallowed.
 */

declare(strict_types=1);

final class Alerts
{
    private static bool $ready = false;

    public static function ensure(): void
    {
        if (self::$ready) {
            return;
        }
        Database::run(
            'CREATE TABLE IF NOT EXISTS `alerts` (
               `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
               `user_id`    INT UNSIGNED NOT NULL,
               `kind`       VARCHAR(40)  NOT NULL,
               `tone`       VARCHAR(10)  NOT NULL DEFAULT \'info\',
               `title`      VARCHAR(200) NOT NULL,
               `body`       TEXT NOT NULL,
               `link`       VARCHAR(200) DEFAULT NULL,
               `meta`       TEXT DEFAULT NULL,
               `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               `read_at`    DATETIME DEFAULT NULL,
               PRIMARY KEY (`id`),
               KEY `ix_alert_user` (`user_id`, `read_at`, `id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$ready = true;
    }

    /* ------------------------------------------------------------------
     * Storing and reading
     * ---------------------------------------------------------------- */

    public static function push(
        int $userId, string $kind, string $tone, string $title, string $body,
        string $link = '', array $meta = []
    ): int {
        self::ensure();
        return Database::insert('alerts', [
            'user_id' => $userId,
            'kind'    => $kind,
            'tone'    => $tone,
            'title'   => mb_substr($title, 0, 200),
            'body'    => $body,
            'link'    => $link !== '' ? $link : null,
            'meta'    => $meta ? json_encode($meta) : null,
        ]);
    }

    /** @return array<int,array<string,mixed>> newest first */
    public static function recent(int $userId, int $afterId = 0, int $limit = 25): array
    {
        self::ensure();
        $rows = Database::all(
            'SELECT id, kind, tone, title, body, link, meta, created_at, read_at
               FROM alerts WHERE user_id = ? AND id > ?
              ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            [$userId, $afterId]
        );
        foreach ($rows as &$r) {
            $r['id']   = (int) $r['id'];
            $r['read'] = $r['read_at'] !== null;
            $r['at']   = date('c', (int) strtotime((string) $r['created_at']));
            $r['meta'] = $r['meta'] ? (json_decode((string) $r['meta'], true) ?: []) : [];
            unset($r['read_at'], $r['created_at']);
        }
        unset($r);
        return $rows;
    }

    public static function unread(int $userId): int
    {
        self::ensure();
        return (int) Database::value(
            'SELECT COUNT(*) FROM alerts WHERE user_id = ? AND read_at IS NULL', [$userId]
        );
    }

    public static function latestId(int $userId): int
    {
        self::ensure();
        return (int) Database::value('SELECT COALESCE(MAX(id),0) FROM alerts WHERE user_id = ?', [$userId]);
    }

    /** Mark one alert (or every alert, when $id is null) as read. */
    public static function markRead(int $userId, ?int $id = null): void
    {
        self::ensure();
        if ($id === null) {
            Database::run('UPDATE alerts SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
        } else {
            Database::run(
                'UPDATE alerts SET read_at = NOW() WHERE user_id = ? AND id = ? AND read_at IS NULL',
                [$userId, $id]
            );
        }
    }

    /* ------------------------------------------------------------------
     * Who to tell
     * ---------------------------------------------------------------- */

    /** @return int[] sign-in accounts that belong to this trader */
    public static function usersForVendor(int $vendorId): array
    {
        return array_map('intval', Database::column(
            'SELECT id FROM users WHERE vendor_id = ? AND is_active = 1', [$vendorId]
        ));
    }

    /** @return int[] sign-in accounts that chair this market's chairman record */
    public static function usersForChairman(int $chairmanId): array
    {
        return array_map('intval', Database::column(
            'SELECT id FROM users WHERE chairman_id = ? AND is_active = 1', [$chairmanId]
        ));
    }

    /** The chairman answerable for a market, if one is on record. */
    public static function chairmanOfMarket(int $marketId): ?array
    {
        return Database::one(
            "SELECT id, full_name, phone, email, status FROM market_chairmen
              WHERE market_id = ? AND status IN ('active','interim') LIMIT 1",
            [$marketId]
        );
    }

    /* ------------------------------------------------------------------
     * Stall details, written once and reused
     * ---------------------------------------------------------------- */

    /** @return array<string,mixed>|null */
    public static function stall(int $spaceId): ?array
    {
        return Database::one(
            'SELECT s.*, m.name AS market_name, m.id AS market_id, d.name AS division_name
               FROM market_spaces s
               JOIN markets m ON m.id = s.market_id
               LEFT JOIN divisions d ON d.id = m.division_id
              WHERE s.id = ? LIMIT 1',
            [$spaceId]
        );
    }

    /** The facilities of a stall, as words. */
    public static function facilities(array $s): string
    {
        $out = [];
        if (!empty($s['has_water']))   { $out[] = 'water'; }
        if (!empty($s['has_power']))   { $out[] = 'power'; }
        if (!empty($s['near_toilet'])) { $out[] = 'toilet nearby'; }
        if (!empty($s['step_free']))   { $out[] = 'step-free access'; }
        return $out ? ucfirst(implode(', ', $out)) : 'None listed';
    }

    /** Plain-text lines describing a stall, with its chairman's contact. */
    public static function stallLines(array $s, ?array $chairman): string
    {
        $lines = [];
        $lines[] = 'Stall: ' . $s['space_code'] . (!empty($s['section']) ? ' (' . $s['section'] . ')' : '');
        $lines[] = 'Market: ' . $s['market_name'] . (!empty($s['division_name']) ? ', ' . $s['division_name'] . ' Division' : '');
        $lines[] = 'Size: ' . rtrim(rtrim(number_format((float) $s['size_sqm'], 1), '0'), '.') . ' m²'
                 . ' · Rent: UGX ' . number_format((int) $s['monthly_rent_ugx']) . ' a month';
        $lines[] = 'Facilities: ' . self::facilities($s);
        $lines[] = $chairman
            ? 'Chairman: ' . $chairman['full_name'] . ' · ' . $chairman['phone']
            : 'Chairman: not yet recorded for this market';
        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------
     * The three events
     * ---------------------------------------------------------------- */

    /**
     * A trader has a pitch (taken straight away, or approved by an officer).
     * Tells the trader, with the stall's details, and tells the chairman.
     *
     * @param string $how 'taken' (immediately theirs) or 'approved' (an officer approved a request)
     */
    public static function pitchTaken(
        int $vendorId, int $spaceId, string $reference, string $how = 'taken', ?string $validUntil = null
    ): void {
        try {
            $stall = self::stall($spaceId);
            $vendor = Database::one('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
            if (!$stall || !$vendor) {
                return;
            }
            $chairman = self::chairmanOfMarket((int) $stall['market_id']);
            $lines = self::stallLines($stall, $chairman);

            $detail = $lines . "\nReference: " . $reference;
            if ($validUntil) {
                $detail .= "\nConfirm your place by: " . date('d M Y', (int) strtotime($validUntil));
            }

            $title = $how === 'approved'
                ? 'Request approved: stall ' . $stall['space_code']
                : 'Success: stall ' . $stall['space_code'] . ' is yours';

            foreach (self::usersForVendor($vendorId) as $uid) {
                self::push($uid, 'pitch-success', 'ok', $title, $detail, 'my_market.php',
                    ['vendor_id' => $vendorId, 'space_id' => $spaceId]);
            }

            if ($chairman) {
                $forChair = 'Trader: ' . $vendor['full_name'] . ' (' . $vendor['vendor_code'] . ")\n"
                    . 'Phone: ' . ($vendor['phone'] ?: 'not recorded') . "\n"
                    . 'Trade: ' . humanise((string) ($vendor['business_category'] ?? 'other')) . "\n"
                    . 'Stall: ' . $stall['space_code'] . (!empty($stall['section']) ? ' (' . $stall['section'] . ')' : '') . "\n"
                    . 'Market: ' . $stall['market_name'] . "\n"
                    . 'Reference: ' . $reference;
                foreach (self::usersForChairman((int) $chairman['id']) as $uid) {
                    self::push($uid, 'new-trader', 'info',
                        'New trader at stall ' . $stall['space_code'] . ': ' . $vendor['full_name'],
                        $forChair, 'my_market.php',
                        ['vendor_id' => $vendorId, 'space_id' => $spaceId]);
                }
            }
        } catch (Throwable $e) {
            error_log('[alerts] pitchTaken: ' . $e->getMessage());
        }
    }

    /** A trader tried for a pitch and did not get it. */
    public static function pitchFailed(int $vendorId, string $spaceCode, string $reason, string $marketName = ''): void
    {
        try {
            $title = 'Not successful: stall ' . $spaceCode;
            $body  = 'Reason: ' . $reason
                   . ($marketName !== '' ? "\nMarket: " . $marketName : '')
                   . "\nYou can choose another vacant stall on the stall map.";
            foreach (self::usersForVendor($vendorId) as $uid) {
                self::push($uid, 'pitch-failed', 'bad', $title, $body, 'market_map.php',
                    ['vendor_id' => $vendorId]);
            }
        } catch (Throwable $e) {
            error_log('[alerts] pitchFailed: ' . $e->getMessage());
        }
    }

    /** An officer declined a request the trader had made. */
    public static function requestRejected(int $vendorId, int $spaceId, string $reference, string $reason): void
    {
        try {
            $stall = self::stall($spaceId);
            $code  = $stall ? (string) $stall['space_code'] : 'the pitch';
            $body  = 'Reason: ' . ($reason !== '' ? $reason : 'not given')
                   . "\nReference: " . $reference
                   . ($stall ? "\nMarket: " . $stall['market_name'] : '')
                   . "\nYou can choose another vacant stall on the stall map.";
            foreach (self::usersForVendor($vendorId) as $uid) {
                self::push($uid, 'pitch-failed', 'bad', 'Not successful: stall ' . $code, $body,
                    'market_map.php', ['vendor_id' => $vendorId, 'space_id' => $spaceId]);
            }
        } catch (Throwable $e) {
            error_log('[alerts] requestRejected: ' . $e->getMessage());
        }
    }
}
