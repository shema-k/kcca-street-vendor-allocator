<?php
/**
 * KCCA VRSP — notices from the Authority to market chairmen.
 *
 * A trader does not read the Authority's correspondence. They read what their
 * chairman tells them. So a notice written by an officer does not go to 8,386
 * traders; it goes to the chairmen, and the chairman publishes it to the
 * market. That indirection is the whole point, and it is why sending and
 * reaching are tracked in two separate columns rather than one:
 *
 *   issued_at      the Authority wrote it
 *   forwarded_at   a chairman put it in front of their traders
 *
 * A notice that was sent and never forwarded is a real and common state, and
 * it is invisible if you keep only one timestamp. It is also the state worth
 * chasing, because a chairman who has not published is the only part of the
 * chain that can still be fixed.
 *
 * A notice can be addressed to one chairman, to every chairman in a
 * division, or to every chairman in the city. Audience is resolved to a list
 * of concrete chairmen at the moment of sending, and those become receipt
 * rows — so a chairman elected next month does not inherit a notice written
 * before they took office, and a notice aimed at one division does not quietly
 * widen when someone is appointed.
 */

declare(strict_types=1);

final class Notices
{
    public const CATEGORIES = [
        'general'     => 'General',
        'trade-order' => 'Trade order enforcement',
        'licensing'   => 'Licensing and fees',
        'sanitation'  => 'Sanitation',
        'relocation'  => 'Relocation',
        'support'     => 'Support and supplies',
        'safety'      => 'Safety and enforcement',
        'meeting'     => 'Meetings and assemblies',
    ];

    /** Audiences an officer may address. */
    public const AUDIENCES = [
        'all'      => 'Every chairman',
        'division' => 'Every chairman in a division',
        'market'   => 'One chairman',
    ];

    /**
     * Send a notice, and open a receipt against every chairman it reaches.
     *
     * The audience is resolved to chairman ids here rather than at read time,
     * so the record of who was told is fixed at the moment of telling. A
     * chairman elected afterwards was not told, and must not appear as though
     * they were.
     *
     * @return array{ok:bool,error?:string,message?:string,id?:int,reference?:string,reached?:int}
     */
    public static function send(array $input, int $asUserId): array
    {
        $subject = trim((string) ($input['subject'] ?? ''));
        $body    = trim((string) ($input['body'] ?? ''));

        if (str_len($subject) < 4) {
            return ['ok' => false, 'error' => 'Give the notice a subject a chairman could act on.'];
        }
        if (str_len($subject) > 200) {
            return ['ok' => false, 'error' => 'The subject is too long. Use 200 characters or fewer.'];
        }
        if (str_len($body) < 20) {
            /* A notice shorter than a sentence reaches nobody: the chairman
               has nothing to read out and the traders nothing to act on. */
            return ['ok' => false, 'error' => 'Write the notice itself. At least a sentence, please.'];
        }

        $audience = in_array($input['audience'] ?? '', array_keys(self::AUDIENCES), true)
            ? $input['audience']
            : 'all';
        $category = isset(self::CATEGORIES[$input['category'] ?? '']) ? $input['category'] : 'general';

        $divisionId = (int) ($input['division_id'] ?? 0) ?: null;
        $marketId   = (int) ($input['market_id'] ?? 0) ?: null;

        if ($audience === 'division' && $divisionId === null) {
            return ['ok' => false, 'error' => 'Choose the division this notice goes to.'];
        }
        if ($audience === 'market' && $marketId === null) {
            return ['ok' => false, 'error' => 'Choose the market whose chairman should receive this.'];
        }

        $chairmen = self::resolveAudience($audience, $divisionId, $marketId);
        if ($chairmen === []) {
            return ['ok' => false, 'error' => 'No chairman is recorded for that audience, so there is nobody to send it to.'];
        }

        $acknowledgeBy = trim((string) ($input['acknowledge_by'] ?? ''));
        if ($acknowledgeBy !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $acknowledgeBy)) {
            return ['ok' => false, 'error' => 'Give the reply-by date as a date.'];
        }
        if ($acknowledgeBy !== '' && $acknowledgeBy < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'The reply-by date has already passed.'];
        }

        $reference = self::nextReference('NTC');

        $id = Database::transaction(static function () use (
            $reference, $subject, $body, $category, $audience,
            $divisionId, $marketId, $asUserId, $input, $acknowledgeBy, $chairmen
        ) {
            $noticeId = Database::insert('chairman_notices', [
                'reference'      => $reference,
                'subject'        => $subject,
                'body'           => $body,
                'category'       => $category,
                'audience'       => $audience,
                /* One chairman keeps the chairman_id; the rest are resolved
                   through receipts. Storing the pointer on the notice as well
                   is what lets a report ask "did I answer this?" without
                   walking the receipts. */
                'chairman_id'    => $audience === 'market' ? (int) ($input['chairman_id'] ?? 0) ?: null : null,
                'division_id'    => $divisionId,
                'market_id'      => $marketId,
                'issued_by'      => $asUserId,
                'requires_ack'   => !empty($input['requires_ack']) ? 1 : 0,
                'acknowledge_by' => $acknowledgeBy !== '' ? $acknowledgeBy : null,
            ]);

            foreach ($chairmen as $chairmanId) {
                Database::insert('chairman_notice_receipts', [
                    'notice_id'   => $noticeId,
                    'chairman_id' => $chairmanId,
                ]);
            }

            return $noticeId;
        });

        Auth::log('send-notice', 'chairman_notices', $id,
            $reference . ' — ' . $subject . ' (' . count($chairmen) . ' '
            . (count($chairmen) === 1 ? 'chairman' : 'chairmen') . ')');

        return [
            'ok'        => true,
            'id'        => $id,
            'reference' => $reference,
            'reached'   => count($chairmen),
            'message'   => 'Sent ' . $reference . ' to ' . count($chairmen) . ' '
                        . (count($chairmen) === 1 ? 'chairman' : 'chairmen') . '.',
        ];
    }

    /**
     * The chairman ids a given audience reaches.
     *
     * Only chairmen who can actually act are included. A vacant record is
     * nobody and an interim one is somebody until they are replaced, so both
     * are in; a market with no chairman record at all is out, because sending
     * a notice to a market that has no chairman puts it somewhere nobody will
     * read it.
     *
     * @return array<int,int>
     */
    public static function resolveAudience(string $audience, ?int $divisionId, ?int $marketId): array
    {
        $sql = "SELECT ch.id
                  FROM market_chairmen ch
                  JOIN markets m ON m.id = ch.market_id
                 WHERE ch.status IN ('active','interim')";

        if ($audience === 'division' && $divisionId !== null) {
            $sql .= ' AND m.division_id = ' . (int) $divisionId;
        } elseif ($audience === 'market' && $marketId !== null) {
            $sql .= ' AND ch.market_id = ' . (int) $marketId;
        } elseif ($audience !== 'all') {
            return [];
        }

        return array_map('intval', Database::column($sql . ' ORDER BY ch.full_name'));
    }

    /**
     * A chairman marks a notice read.
     *
     * Separate from publishing, because reading it and putting it in front of
     * the traders are different acts, and only the second is evidence that
     * anybody was told.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function markRead(int $noticeId, int $chairmanId): array
    {
        $receipt = self::receipt($noticeId, $chairmanId);
        if (!$receipt) {
            return ['ok' => false, 'error' => 'That notice was not addressed to you.'];
        }
        if ($receipt['read_at'] !== null) {
            return ['ok' => true, 'message' => 'Already marked as read.'];
        }

        Database::update('chairman_notice_receipts', [
            'read_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $receipt['id']]);

        Auth::log('read-notice', 'chairman_notices', $noticeId, $receipt['reference'] ?? '');

        return ['ok' => true, 'message' => 'Marked as read.'];
    }

    /**
     * A chairman publishes a notice to their traders.
     *
     * The note is kept because "I have passed it on" and "I have passed it on,
     * and I am telling them to bring their nets on Tuesday" are different
     * things, and the second is the one that gets compliance.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function publish(int $noticeId, int $chairmanId, string $note = ''): array
    {
        $receipt = self::receipt($noticeId, $chairmanId);
        if (!$receipt) {
            return ['ok' => false, 'error' => 'That notice was not addressed to you.'];
        }
        if ($receipt['forwarded_at'] !== null) {
            return ['ok' => false, 'error' => 'You have already published this notice to your traders.'];
        }
        if ($receipt['read_at'] === null) {
            /* Publishing something you have not read is allowed — a chairman
               may act on a title alone — but the read is recorded with it so
               the trail is not left pretending they saw it first. */
            Database::update('chairman_notice_receipts', ['read_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $receipt['id']]);
        }

        Database::update('chairman_notice_receipts', [
            'forwarded_at'   => date('Y-m-d H:i:s'),
            'forwarded_note' => $note !== '' ? str_cut($note, 0, 500) : null,
        ], 'id = ?', [(int) $receipt['id']]);

        Auth::log('publish-notice', 'chairman_notices', $noticeId,
            ($receipt['reference'] ?? '') . ' published to ' . ($receipt['market_name'] ?? 'the market'));

        return ['ok' => true, 'message' => 'Published to your traders.'];
    }

    /**
     * Notices addressed to one chairman, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forChairman(int $chairmanId, string $filter = 'all'): array
    {
        $where  = ['r.chairman_id = :ch'];
        $params = ['ch' => $chairmanId];

        if ($filter === 'unread') {
            $where[] = 'r.read_at IS NULL';
        } elseif ($filter === 'unpublished') {
            $where[] = 'r.forwarded_at IS NULL';
        }

        return Database::all(
            'SELECT n.*, r.read_at, r.forwarded_at, r.forwarded_note,
                    u.full_name AS issued_by_name, u.title AS issued_by_title
               FROM chairman_notice_receipts r
               JOIN chairman_notices n ON n.id = r.notice_id
          LEFT JOIN users u ON u.id = n.issued_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY n.issued_at DESC, n.id DESC',
            $params
        );
    }

    /**
     * Every notice, with how far it has got.
     *
     * The counts are computed per notice rather than carried on it, because a
     * notice's progress is a property of its receipts and nothing else — and
     * a stored counter goes stale the moment a chairman opens it.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function register(?int $divisionId = null, string $q = '', int $limit = 150): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($divisionId !== null) {
            /* A notice belongs to a mayor's division if it reached any
               chairman in it, not if the notice's own division_id says so. A
               citywide notice has division_id NULL, yet it plainly concerns a
               mayor whose chairmen received it, and filtering on the notice's
               own division would hide it from every mayor who must act on it.
               The test is therefore over the receipts, which are the record of
               who was actually reached.

               Two placeholders rather than one used twice: prepares are native
               here (PDO::ATTR_EMULATE_PREPARES is false), and a native prepare
               binds a named parameter exactly once. Repeating :div fails at
               prepare time rather than at query time, which is why this showed
               as a 500 only for a mayor, the one role taking this branch.

               The subquery matches on the chairman's DIVISION, not their
               market. Projecting markets.id here looks right and is wrong: a
               division and a market both count from 1, so division 3 silently
               matched market 3 and a mayor was shown notices addressed to a
               part of the city they have no reach over. */
            $where[] = '(:div_n = n.division_id OR EXISTS (
                            SELECT 1
                              FROM chairman_notice_receipts r2
                              JOIN market_chairmen ch2 ON ch2.id = r2.chairman_id
                              JOIN markets md ON md.id = ch2.market_id
                             WHERE r2.notice_id = n.id
                               AND md.division_id = :div_r))';
            $params['div_n'] = $divisionId;
            $params['div_r'] = $divisionId;
        }
        if ($q !== '') {
            /* :q twice needs :q1 and :q2 for the same reason as the division
               scope above — a native prepare binds a named parameter once. */
            $where[]  = '(n.subject LIKE :q1 OR n.reference LIKE :q2)';
            $params['q1'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        return Database::all(
            'SELECT n.*, u.full_name AS issued_by_name, d.name AS division_name,
                    COUNT(r.id) AS reached,
                    SUM(CASE WHEN r.read_at IS NOT NULL THEN 1 ELSE 0 END) AS read_count,
                    SUM(CASE WHEN r.forwarded_at IS NOT NULL THEN 1 ELSE 0 END) AS published_count,
                    (n.acknowledge_by IS NOT NULL AND n.acknowledge_by < CURDATE()) AS overdue
               FROM chairman_notices n
          LEFT JOIN users u ON u.id = n.issued_by
          LEFT JOIN divisions d ON d.id = n.division_id
          LEFT JOIN market_chairmen ch ON ch.id = n.chairman_id
          LEFT JOIN markets m ON m.id = COALESCE(n.market_id, ch.market_id)
          LEFT JOIN chairman_notice_receipts r ON r.notice_id = n.id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY n.id
              ORDER BY n.issued_at DESC, n.id DESC
              LIMIT ' . (int) $limit,
            $params
        );
    }

    /**
     * Notices sitting unread or unpublished, and how long they have been there.
     *
     * @return array{unread:int,unpublished:int,overdue:int}
     */
    public static function pendingCounts(?int $divisionId = null): array
    {
        $div = $divisionId !== null ? ' AND m.division_id = ' . (int) $divisionId : '';

        return [
            'unread'      => (int) Database::value(
                'SELECT COUNT(*) FROM chairman_notice_receipts r
                   JOIN chairman_notices n ON n.id = r.notice_id
              LEFT JOIN markets m ON m.id = n.market_id
                  WHERE r.read_at IS NULL' . $div
            ),
            'unpublished' => (int) Database::value(
                'SELECT COUNT(*) FROM chairman_notice_receipts r
                   JOIN chairman_notices n ON n.id = r.notice_id
              LEFT JOIN markets m ON m.id = n.market_id
                  WHERE r.forwarded_at IS NULL' . $div
            ),
            'overdue'     => (int) Database::value(
                'SELECT COUNT(*) FROM chairman_notices n
              LEFT JOIN markets m ON m.id = n.market_id
                  WHERE n.requires_ack = 1 AND n.acknowledge_by IS NOT NULL
                    AND n.acknowledge_by < CURDATE()' . $div
            ),
        ];
    }

    /** One receipt, joined to everything the chairman's screen needs. */
    public static function receipt(int $noticeId, int $chairmanId): ?array
    {
        return Database::one(
            'SELECT r.*, n.reference, n.subject, n.body, n.category, n.audience,
                    n.issued_at, n.requires_ack, n.acknowledge_by,
                    u.full_name AS issued_by_name, u.title AS issued_by_title,
                    ch.full_name AS chairman_name, m.name AS market_name
               FROM chairman_notice_receipts r
               JOIN chairman_notices n ON n.id = r.notice_id
               JOIN market_chairmen ch ON ch.id = r.chairman_id
               JOIN markets m ON m.id = ch.market_id
          LEFT JOIN users u ON u.id = n.issued_by
              WHERE r.notice_id = ? AND r.chairman_id = ?',
            [$noticeId, $chairmanId]
        );
    }

    /** One notice with its receipts, for the officer's detail view. */
    public static function find(int $noticeId): ?array
    {
        $notice = Database::one(
            'SELECT n.*, u.full_name AS issued_by_name, u.title AS issued_by_title,
                    d.name AS division_name, m.name AS market_name,
                    (SELECT COUNT(*) FROM chairman_notice_receipts r WHERE r.notice_id = n.id) AS reached,
                    (SELECT COUNT(*) FROM chairman_notice_receipts r
                      WHERE r.notice_id = n.id AND r.read_at IS NOT NULL) AS read_count,
                    (SELECT COUNT(*) FROM chairman_notice_receipts r
                      WHERE r.notice_id = n.id AND r.forwarded_at IS NOT NULL) AS published_count,
                    (n.requires_ack = 1 AND n.acknowledge_by IS NOT NULL
                     AND n.acknowledge_by < CURDATE()) AS overdue
               FROM chairman_notices n
          LEFT JOIN users u ON u.id = n.issued_by
          LEFT JOIN divisions d ON d.id = n.division_id
          LEFT JOIN markets m ON m.id = n.market_id
              WHERE n.id = ?',
            [$noticeId]
        );
        if (!$notice) {
            return null;
        }
        $notice['receipts'] = Database::all(
            'SELECT r.*, ch.full_name, ch.status, m.name AS market_name, d.name AS division_name
               FROM chairman_notice_receipts r
               JOIN market_chairmen ch ON ch.id = r.chairman_id
               JOIN markets m ON m.id = ch.market_id
               JOIN divisions d ON d.id = m.division_id
              WHERE r.notice_id = ?
              ORDER BY ch.full_name',
            [$noticeId]
        );
        return $notice;
    }

    private static function nextReference(string $prefix): string
    {
        $n = (int) Database::value(
            'SELECT COUNT(*) FROM chairman_notices WHERE reference LIKE ?',
            [$prefix . '-%']
        );
        do {
            $n++;
            $candidate = sprintf('%s-%06d', $prefix, $n);
            $taken = Database::value(
                'SELECT COUNT(*) FROM chairman_notices WHERE reference = ?',
                [$candidate]
            );
        } while ((int) $taken > 0);

        return $candidate;
    }
}