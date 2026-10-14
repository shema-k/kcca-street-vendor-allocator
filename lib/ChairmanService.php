<?php
/**
 * KCCA VRSP — market chairmen.
 *
 * One chairman per market, recorded against the market rather than against
 * the login that reads it. The two are separate on purpose: a market can
 * have a chairman on record before their account exists, and a report must
 * be able to name a chairman without implying that person can sign in.
 *
 * What this service is responsible for is the join between the two. When a
 * vendor is placed into a market, the chairman of that market is written
 * onto the vendor, and from then on the vendor's chairman follows them
 * through the register, the allocation record and every report — without
 * anybody having to remember to update it.
 */

declare(strict_types=1);

final class ChairmanService
{
    /**
     * Record or update the chairman of one market.
     *
     * Returns ['ok' => false, 'error' => ...] rather than throwing for a
     * bad request: the caller is an HTTP endpoint, and the distinction
     * between "this market does not exist" and "the database is down" is
     * one the reader should see in the status line, not in a stack trace.
     *
     * @param array{full_name:string,phone:string,email:string,since:string,status:string,notes:string} $input
     * @return array{ok:bool,error?:string,message?:string,id?:int}
     */
    public static function save(int $marketId, array $input, ?int $asChairmanId = null): array
    {
        if ($marketId <= 0) {
            return ['ok' => false, 'error' => 'Choose the market this chairman administers.'];
        }

        $market = Database::one('SELECT id, name FROM markets WHERE id = ?', [$marketId]);
        if (!$market) {
            return ['ok' => false, 'error' => 'That market does not exist.'];
        }

        // A chairman may only edit their own record, and only the fields
        // that describe them.
        if ($asChairmanId !== null) {
            $mine = Database::one('SELECT market_id FROM market_chairmen WHERE id = ?', [$asChairmanId]);
            if (!$mine || (int) $mine['market_id'] !== $marketId) {
                return ['ok' => false, 'error' => 'You may only edit your own market’s record.'];
            }
        }

        $name  = trim($input['full_name']);
        $phone = trim($input['phone']);
        if ($name === '' || str_len($name) < 3) {
            return ['ok' => false, 'error' => 'Give the chairman’s full name.'];
        }
        if (str_len($name) > 140) {
            return ['ok' => false, 'error' => 'The name is too long. Use 140 characters or fewer.'];
        }
        if ($phone === '') {
            return ['ok' => false, 'error' => 'A telephone number is how a trader reaches their chairman.'];
        }

        $email = trim($input['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'That email address is not a valid address.'];
        }

        $status = in_array($input['status'], ['active', 'interim', 'vacant'], true)
            ? $input['status']
            : 'active';

        $since = trim($input['since']);
        if ($since !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            return ['ok' => false, 'error' => 'Give the tenure start as a date.'];
        }

        $existing = Database::one('SELECT id FROM market_chairmen WHERE market_id = ?', [$marketId]);

        $data = [
            'full_name' => $name,
            'phone'     => $phone,
            'email'     => $email !== '' ? $email : null,
            'since'     => $since !== '' ? $since : null,
            'status'    => $status,
            'notes'     => trim($input['notes']) !== '' ? trim($input['notes']) : null,
        ];

        if ($existing) {
            Database::update('market_chairmen', $data, 'id = ?', [(int) $existing['id']]);
            $id = (int) $existing['id'];
            $message = 'Chairman recorded for ' . $market['name'] . '.';
        } else {
            $data['market_id'] = $marketId;
            $id = Database::insert('market_chairmen', $data);
            $message = 'Chairman recorded for ' . $market['name'] . '.';
        }

        /* A change of chairman is the point at which the vendors under the
           old one should follow the new one, but only for a chairman who is
           actually taking over. Leaving an interim or vacant record in place
           must not strip a market of its chairman. */
        if ($status === 'active' && $existing) {
            Database::run(
                'UPDATE vendors SET chairman_id = ? WHERE chairman_id = ?',
                [$id, $id]
            );
        }

        Auth::log(
            $existing ? 'update-chairman' : 'create-chairman',
            'market_chairmen',
            $id,
            $market['name'] . ' → ' . $name
        );

        return ['ok' => true, 'message' => $message, 'id' => $id];
    }

    /**
     * Every market with its chairman, for the management screen.
     *
     * The chairman's name is carried through a LEFT JOIN so a market with
     * no chairman on record still appears — the gaps are what the register
     * is for.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function register(string $divisionCode = '', string $q = '', int $limit = 200): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($divisionCode !== '') {
            $where[] = 'd.code = :div';
            $params['div'] = $divisionCode;
        }
        if ($q !== '') {
            $where[] = '(m.name LIKE :q OR ch.full_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        return Database::all(
            'SELECT m.id, m.code AS market_code, m.name AS market_name, m.capacity, m.registered_vendors,
                    m.vacant_spaces,
                    d.name AS division_name, d.code AS division_code,
                    ch.id AS chairman_id, ch.full_name, ch.phone, ch.email, ch.since, ch.status,
                    (SELECT COUNT(*) FROM vendors v WHERE v.chairman_id = ch.id) AS vendors_under
               FROM markets m
               JOIN divisions d ON d.id = m.division_id
               LEFT JOIN market_chairmen ch ON ch.market_id = m.id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY d.name, m.name
              LIMIT ' . (int) $limit,
            $params
        );
    }

    /** The record for one chairman, or null. */
    public static function find(int $chairmanId): ?array
    {
        return Database::one(
            'SELECT ch.*, m.name AS market_name, m.code AS market_code, m.capacity,
                    m.vacant_spaces, m.registered_vendors, d.name AS division_name
               FROM market_chairmen ch
               JOIN markets m ON m.id = ch.market_id
               JOIN divisions d ON d.id = m.division_id
              WHERE ch.id = ?',
            [$chairmanId]
        );
    }

    /**
     * Markets with no chairman recorded — the gaps worth closing.
     *
     * Takes a division to narrow the question to it, because a mayor is
     * shown their own gaps and not the whole city's.
     */
    public static function unassignedCount(?int $divisionId = null): int
    {
        if ($divisionId !== null) {
            return (int) Database::value(
                'SELECT COUNT(*) FROM markets m
                   LEFT JOIN market_chairmen ch ON ch.market_id = m.id
                  WHERE ch.id IS NULL AND m.division_id = ?',
                [$divisionId]
            );
        }

        return (int) Database::value(
            'SELECT COUNT(*) FROM markets m
               LEFT JOIN market_chairmen ch ON ch.market_id = m.id
              WHERE ch.id IS NULL'
        );
    }
}
