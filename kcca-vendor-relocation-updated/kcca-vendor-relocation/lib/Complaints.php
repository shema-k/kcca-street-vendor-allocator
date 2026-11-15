<?php
/**
 * KCCA VRSP — complaints raised by traders, routed through the chairman.
 *
 * A trader's complaint goes to their chairman, not to the Authority. That is
 * the intended route, not a fallback: the chairman is the person standing in
 * that market who can actually do something about a queue at the entrance or
 * a trader who has taken a pitch, and routing everything past them would put
 * the Authority in the position of answering questions only the market can
 * answer. The chairman may answer it, or pass it on.
 *
 * The status field records which of those has happened, and the four stages
 * are kept apart on purpose:
 *
 *   with-chairman    raised, the chairman has it, nothing has moved
 *   forwarded        the chairman passed it to the Authority
 *   with-authority   the Authority has replied, the chairman is reading it
 *   resolved         closed, with what was done recorded
 *   declined         closed without escalation, with the reason recorded
 *
 * Collapsing "forwarded" and "with-authority" would lose the only window that
 * matters operationally — the time between escalation and reply. Collapsing
 * "declined" into "resolved" would lose the fact that somebody decided not to
 * act, which is exactly what a trader who feels ignored needs to be able to
 * see accounted for.
 */

declare(strict_types=1);

final class Complaints
{
    public const CATEGORIES = [
        'attire'          => 'Workwear not supplied',
        'allocation'      => 'Space allocated to me',
        'fee'             => 'Fees and licences',
        'sanitation'      => 'Sanitation',
        'enforcement'     => 'Enforcement action',
        'market-condition'=> 'Condition of the market',
        'other'           => 'Something else',
    ];

    public const STATUSES = [
        'with-chairman' => 'With your chairman',
        'forwarded'     => 'Passed to the Authority',
        'with-authority'=> 'With the Authority',
        'resolved'      => 'Resolved',
        'declined'      => 'Closed without escalation',
    ];

    /**
     * A trader raises a complaint.
     *
     * The chairman is taken from the trader, not from the request. A vendor
     * who could name a different chairman in the form would be able to send a
     * complaint about one market's chairman to another market's chairman, which
     * is both a privacy leak and a way to launder a grievance into the wrong
     * queue. It is their chairman by construction, and there is nothing to
     * choose.
     *
     * @return array{ok:bool,error?:string,message?:string,id?:int,reference?:string}
     */
    public static function raiseFrom(int $vendorId, array $input): array
    {
        $vendor = Database::one(
            'SELECT v.id, v.full_name, v.chairman_id, m.name AS market_name
               FROM vendors v
          LEFT JOIN market_chairmen vc ON vc.id = v.chairman_id
          LEFT JOIN markets m ON m.id = vc.market_id
              WHERE v.id = ?',
            [$vendorId]
        );
        if (!$vendor) {
            return ['ok' => false, 'error' => 'That trader does not exist.'];
        }
        if (!$vendor['chairman_id']) {
            /* Unplaced, so there is no chairman to route to. The complaint is
               refused rather than sent to the Authority directly, because
               silently changing the route would make the trader think they had
               used it. */
            return ['ok' => false, 'error' => 'You are not yet in a market, so there is no chairman to send this to.'];
        }

        $subject = trim((string) ($input['subject'] ?? ''));
        $body    = trim((string) ($input['body'] ?? ''));

        if (str_len($subject) < 4) {
            return ['ok' => false, 'error' => 'Give the complaint a subject.'];
        }
        if (str_len($subject) > 200) {
            return ['ok' => false, 'error' => 'The subject is too long. Use 200 characters or fewer.'];
        }
        if (str_len($body) < 15) {
            return ['ok' => false, 'error' => 'Say what happened. A few lines is enough.'];
        }
        if (str_len($body) > 6000) {
            return ['ok' => false, 'error' => 'That is too long. Keep it under 6,000 characters.'];
        }

        $category = isset(self::CATEGORIES[$input['category'] ?? '']) ? $input['category'] : 'other';

        $reference = self::nextReference('CMP');
        $id = Database::insert('trader_complaints', [
            'reference'   => $reference,
            'vendor_id'   => $vendorId,
            'chairman_id' => (int) $vendor['chairman_id'],
            'category'    => $category,
            'subject'     => $subject,
            'body'        => $body,
            'status'      => 'with-chairman',
        ]);

        Auth::log('raise-complaint', 'trader_complaints', $id,
            $reference . ' — ' . $subject . ' (' . $vendor['full_name'] . ')');

        return [
            'ok'        => true,
            'id'        => $id,
            'reference' => $reference,
            'message'   => 'Sent to your chairman as ' . $reference . '.',
        ];
    }

    /**
     * The chairman answers, and may or may not pass it on.
     *
     * A chairman may act on a complaint without escalating it — usually
     * because it was their own market's doing and there was nothing for the
     * Authority to do. That is recorded as declined with the reason, not as
     * resolved: nothing was fixed by the Authority, and saying otherwise would
     * overstate what happened.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function chairmanAct(int $complaintId, int $chairmanId, array $input): array
    {
        $complaint = self::find($complaintId);
        if (!$complaint) {
            return ['ok' => false, 'error' => 'That complaint is not on record.'];
        }
        if ((int) ($complaint['chairman_id'] ?? 0) !== $chairmanId) {
            return ['ok' => false, 'error' => 'That complaint was sent to another chairman.'];
        }
        if (!in_array($complaint['status'], ['with-chairman', 'forwarded'], true)) {
            return ['ok' => false, 'error' => 'That complaint is already closed.'];
        }

        $action = (string) ($input['action'] ?? '');
        $note   = trim((string) ($input['chairman_note'] ?? ''));

        if ($action === 'decline') {
            if (str_len($note) < 5) {
                return ['ok' => false, 'error' => 'Say why you are closing it. The trader will see this.'];
            }
            Database::update('trader_complaints', [
                'status'        => 'declined',
                'chairman_note' => str_cut($note, 0, 1000),
                'resolved_at'   => date('Y-m-d H:i:s'),
            ], 'id = ?', [$complaintId]);

            Auth::log('decline-complaint', 'trader_complaints', $complaintId, $complaint['reference']);
            return ['ok' => true, 'message' => 'Closed. The trader can see why.'];
        }

        if ($action !== 'forward') {
            return ['ok' => false, 'error' => 'Choose what to do with it.'];
        }

        /* Already escalated: recording a second forward would overwrite the
           first, and the first is the one that shows how long it has been
           sitting with the Authority. */
        if ($complaint['status'] === 'forwarded') {
            return ['ok' => false, 'error' => 'You have already passed this to the Authority.'];
        }

        Database::update('trader_complaints', [
            'status'        => 'forwarded',
            'chairman_note' => $note !== '' ? str_cut($note, 0, 1000) : null,
            'forwarded_at'  => date('Y-m-d H:i:s'),
            'forwarded_by'  => Auth::id(),
        ], 'id = ?', [$complaintId]);

        Auth::log('forward-complaint', 'trader_complaints', $complaintId, $complaint['reference']);

        return ['ok' => true, 'message' => 'Passed to the Authority.'];
    }

    /**
     * An officer answers on the Authority's behalf.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function reply(int $complaintId, array $input): array
    {
        $complaint = self::find($complaintId);
        if (!$complaint) {
            return ['ok' => false, 'error' => 'That complaint is not on record.'];
        }
        if ($complaint['status'] === 'with-chairman') {
            return ['ok' => false, 'error' => 'The chairman has not passed this to the Authority yet.'];
        }
        /* Once closed it is a record, not a queue. Letting an officer reopen a
           resolved complaint by replying to it would overwrite what the
           trader and the chairman were both shown as the outcome. */
        if (!in_array($complaint['status'], ['forwarded', 'with-authority'], true)) {
            return ['ok' => false, 'error' => 'That complaint is already closed.'];
        }

        $reply = trim((string) ($input['authority_reply'] ?? ''));
        if (str_len($reply) < 15) {
            return ['ok' => false, 'error' => 'Write the reply. The chairman will read it before the trader does.'];
        }

        $close = !empty($input['close']);

        $data = [
            'authority_reply' => str_cut($reply, 0, 6000),
            'status'          => $close ? 'resolved' : 'with-authority',
        ];
        if ($close) {
            $resolution = trim((string) ($input['resolution'] ?? ''));
            $data['resolution']   = $resolution !== '' ? str_cut($resolution, 0, 1000) : str_cut($reply, 0, 1000);
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }

        Database::update('trader_complaints', $data, 'id = ?', [$complaintId]);

        Auth::log($close ? 'resolve-complaint' : 'reply-complaint', 'trader_complaints', $complaintId, $complaint['reference']);

        return ['ok' => true, 'message' => $close ? 'Closed and recorded.' : 'Reply sent to the chairman.'];
    }

    /** One complaint, joined to everything both sides need to see it. */
    public static function find(int $complaintId): ?array
    {
        return Database::one(
            'SELECT c.*, v.full_name, v.vendor_code, v.phone AS vendor_phone,
                    v.business_category AS trade,
                    ch.full_name AS chairman_name, ch.phone AS chairman_phone,
                    m.name AS market_name, d.name AS division_name,
                    u.full_name AS forwarded_by_name,
                    TIMESTAMPDIFF(DAY, c.created_at, CURDATE()) AS days_open
               FROM trader_complaints c
               JOIN vendors v ON v.id = c.vendor_id
          LEFT JOIN market_chairmen ch ON ch.id = c.chairman_id
          LEFT JOIN markets m ON m.id = ch.market_id
          LEFT JOIN divisions d ON d.id = m.division_id
          LEFT JOIN users u ON u.id = c.forwarded_by
              WHERE c.id = ?',
            [$complaintId]
        );
    }

    /**
     * Complaints in one chairman's market, oldest unresolved first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forChairman(int $chairmanId, string $status = 'open'): array
    {
        $where  = ['c.chairman_id = :ch'];
        $params = ['ch' => $chairmanId];

        if ($status === 'open') {
            $where[] = "c.status IN ('with-chairman','forwarded','with-authority')";
        } elseif ($status === 'with-chairman') {
            $where[] = "c.status = 'with-chairman'";
        } elseif ($status === 'closed') {
            $where[] = "c.status IN ('resolved','declined')";
        }

        return Database::all(
            'SELECT c.*, v.full_name, v.vendor_code, v.business_category AS trade,
                    m.name AS market_name,
                    TIMESTAMPDIFF(DAY, c.created_at, CURDATE()) AS days_open
               FROM trader_complaints c
               JOIN vendors v ON v.id = c.vendor_id
          LEFT JOIN market_chairmen cm ON cm.id = c.chairman_id
          LEFT JOIN markets m ON m.id = cm.market_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY FIELD(c.status, "with-chairman", "forwarded", "with-authority", "resolved", "declined"),
                       c.created_at DESC',
            $params
        );
    }

    /**
     * Complaints that reached the Authority, across the city or one division.
     *
     * Only those a chairman actually escalated. A complaint sitting with a
     * chairman is not an Authority matter yet, and counting it here would put
     * a number on the officer dashboard that nobody can act on.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function register(?int $divisionId = null, string $status = 'open'): array
    {
        $where  = ["c.status IN ('forwarded','with-authority','resolved')"];
        $params = [];

        if ($divisionId !== null) {
            $where[]  = 'm.division_id = :div';
            $params['div'] = $divisionId;
        }
        if ($status === 'open') {
            $where[] = "c.status IN ('forwarded','with-authority')";
        } elseif ($status === 'closed') {
            $where[] = "c.status = 'resolved'";
        }

        return Database::all(
            'SELECT c.*, v.full_name, v.vendor_code,
                    ch.full_name AS chairman_name,
                    m.name AS market_name, d.name AS division_name,
                    u.full_name AS forwarded_by_name,
                    TIMESTAMPDIFF(DAY, c.created_at, CURDATE()) AS days_open,
                    (c.forwarded_at IS NOT NULL
                     AND c.authority_reply IS NULL) AS awaiting_reply
               FROM trader_complaints c
               JOIN vendors v ON v.id = c.vendor_id
          LEFT JOIN market_chairmen ch ON ch.id = c.chairman_id
          LEFT JOIN markets m ON m.id = ch.market_id
          LEFT JOIN divisions d ON d.id = m.division_id
          LEFT JOIN users u ON u.id = c.forwarded_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.forwarded_at IS NULL, c.created_at DESC',
            $params
        );
    }

    /** Every complaint a trader has raised, in their own words and the reply. */
    public static function forVendor(int $vendorId): array
    {
        return Database::all(
            'SELECT c.*, ch.full_name AS chairman_name
               FROM trader_complaints c
          LEFT JOIN market_chairmen ch ON ch.id = c.chairman_id
              WHERE c.vendor_id = ?
              ORDER BY c.created_at DESC, c.id DESC',
            [$vendorId]
        );
    }

    /**
     * @return array{open:int,with_chairman:int,awaiting_reply:int,resolved:int,declined:int}
     */
    public static function counts(?int $chairmanId = null, ?int $divisionId = null): array
    {
        $scope = '';
        if ($chairmanId !== null) {
            $scope = ' AND chairman_id = ' . (int) $chairmanId;
        } elseif ($divisionId !== null) {
            $scope = ' AND vendor_id IN (SELECT v.id FROM vendors v
                                          JOIN market_chairmen cm ON cm.id = v.chairman_id
                                          JOIN markets m ON m.id = cm.market_id
                                         WHERE m.division_id = ' . (int) $divisionId . ')';
        }

        $one = static fn(string $extra): int => (int) Database::value(
            'SELECT COUNT(*) FROM trader_complaints WHERE 1 = 1' . $scope . $extra
        );

        return [
            'open'           => $one(" AND status IN ('with-chairman','forwarded','with-authority')"),
            'with_chairman'  => $one(" AND status = 'with-chairman'"),
            'awaiting_reply' => $one(" AND status = 'forwarded' AND authority_reply IS NULL"),
            'resolved'       => $one(" AND status = 'resolved'"),
            'declined'       => $one(" AND status = 'declined'"),
        ];
    }

    private static function nextReference(string $prefix): string
    {
        $n = (int) Database::value(
            'SELECT COUNT(*) FROM trader_complaints WHERE reference LIKE ?',
            [$prefix . '-%']
        );
        do {
            $n++;
            $candidate = sprintf('%s-%06d', $prefix, $n);
            $taken = Database::value(
                'SELECT COUNT(*) FROM trader_complaints WHERE reference = ?',
                [$candidate]
            );
        } while ((int) $taken > 0);

        return $candidate;
    }
}