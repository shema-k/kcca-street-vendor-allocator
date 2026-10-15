<?php
/**
 * KCCA VRSP — prescribed workwear and trader support.
 *
 * Two ideas sit at the heart of this service, and both come from how markets
 * actually work rather than from the data model.
 *
 * The first is that a trader wears the job. A butcher in a red apron and a
 * cook in a white one is readable across a market without a sign, which is
 * what makes the rule worth having: it polices itself. The garment, its
 * colour and its price are therefore properties of the trade, not of the
 * person — one rule per trade, held in trade_attire, that every trader in
 * that trade is subject to.
 *
 * The second is whose hand supplies it. In a public market the Authority
 * issues the attire, because the Authority owns the ground and the traders
 * are its tenants. In a private market the chairman of that market issues it,
 * because the Authority has nobody there to hand anything over and the
 * chairman is the one actually in the market. Same obligation, different hand,
 * and the rule that decides which is stored on the attire rather than decided
 * again at every handover.
 *
 * Everything handed to a trader — attire, a mosquito net, a ration, a
 * dustbin — is recorded as an issue against that trader, and an issue is not
 * closed by being recorded. It is closed by the trader confirming they have
 * it. Until then it is outstanding, and the follow-up list is built from
 * exactly that: because "we distributed" and "they received" are different
 * claims, and only one of them is evidence that anything happened.
 */

declare(strict_types=1);

final class TraderSupport
{
    /**
     * The prescribed attire for one trade.
     *
     * Call this from inside the class only. PHP 8.4.24 on this host fails to
     * resolve a *literal* `TraderSupport::attireFor()` call made from a page:
     * it throws "Call to undefined method" while method_exists() and
     * get_class_methods() both report the method as present, and the same
     * call succeeds from another file, from inside this class, and through a
     * variable class name. It is a runtime fault, not a property of this
     * code, and it is not worth working around in more than one place — so
     * callers outside the class ask issuerFor(), which returns this same rule
     * alongside who has to supply it.
     *
     * @return array<string,mixed>|null
     */
    public static function attireFor(?string $tradeCode): ?array
    {
        if ($tradeCode === null || $tradeCode === '') {
            return null;
        }
        return Database::one('SELECT * FROM trade_attire WHERE trade_code = ?', [$tradeCode]);
    }

    /**
     * Who issues the attire for a trader.
     *
     * Reads the trade's own rule first, then overrides it from the market: a
     * market that is not the Authority's is somebody else's to run, whoever
     * the trade normally is. That ordering matters because it is the market
     * that decides whether the Authority is present at all.
     *
     * The whole answer to "what workwear, and who gives it to me" — asked
     * together, because they are one question with one owner.
     *
     * The rule is carried back in the same call rather than fetched separately
     * by the page. That is the tidier shape anyway — a caller that wants the
     * garment already has the trade code and the market, so it would have had
     * to make a second call for the rest — and it avoids a call site that this
     * PHP build fails to resolve. PHP 8.4.24 on this host throws "undefined
     * method" for a literal TraderSupport::attireFor() call from a page,
     * while reporting the method as present via method_exists() and
     * get_class_methods(); the same call runs correctly from inside the class
     * and from other files. See the note on attireFor().
     *
     * @return array{by:string,label:string,reason:string,rule:?array}
     */
    public static function issuerFor(?string $tradeCode, ?string $marketCategory): array
    {
        $public = $marketCategory !== null && $marketCategory === 'public';
        $rule   = self::attireFor($tradeCode);
        $ruleBy = $rule['issued_by'] ?? 'kcca';

        if ($public) {
            return [
                'by'     => 'kcca',
                'label'  => 'KCCA',
                'reason' => 'A public market is run by the Authority, so the Authority issues the workwear.',
                'rule'   => $rule,
            ];
        }

        if ($ruleBy === 'chairman') {
            return [
                'by'     => 'chairman',
                'label'  => 'The market chairman',
                'reason' => 'This trade is issued by the market chairman.',
                'rule'   => $rule,
            ];
        }

        return [
            'by'     => 'chairman',
            'label'  => 'The market chairman',
            'reason' => 'A private market has no Authority officer on site, so its chairman issues the workwear.',
            'rule'   => $rule,
        ];
    }

    /**
     * Record something handed to a trader.
     *
     * Exactly one of $issuedBy and $issuedByChairman is set. That is not a
     * convenience: a handover has a single author, and a row claiming both an
     * officer and a chairman issued the same item would let the follow-up
     * list ask the wrong person to chase their own handover.
     *
     * @return array{ok:bool,error?:string,message?:string,id?:int,reference?:string}
     */
    public static function issue(array $input, ?int $asUserId = null, ?int $asChairmanId = null): array
    {
        $vendorId = (int) ($input['vendor_id'] ?? 0);
        $itemId   = (int) ($input['item_id'] ?? 0);

        if ($vendorId <= 0) {
            return ['ok' => false, 'error' => 'Choose the trader receiving this.'];
        }
        if ($itemId <= 0) {
            return ['ok' => false, 'error' => 'Choose what is being handed over.'];
        }
        if ($asUserId === null && $asChairmanId === null) {
            return ['ok' => false, 'error' => 'A handover has to be recorded by somebody.'];
        }

        $vendor = Database::one(
            'SELECT v.id, v.full_name, v.vendor_code, v.chairman_id, v.business_category AS trade,
                    m.category AS market_category, m.name AS market_name
               FROM vendors v
          LEFT JOIN market_chairmen vc ON vc.id = v.chairman_id
          LEFT JOIN markets m ON m.id = vc.market_id
              WHERE v.id = ?',
            [$vendorId]
        );
        if (!$vendor) {
            return ['ok' => false, 'error' => 'That trader does not exist.'];
        }

        $item = Database::one('SELECT id, code, name, requires_receipt FROM support_items WHERE id = ?', [$itemId]);
        if (!$item) {
            return ['ok' => false, 'error' => 'That item is not in the catalogue.'];
        }

        $qty = (int) ($input['quantity'] ?? 1);
        if ($qty < 1 || $qty > 999) {
            return ['ok' => false, 'error' => 'Quantity must be between 1 and 999.'];
        }

        $issuedOn = trim((string) ($input['issued_on'] ?? ''));
        if ($issuedOn === '') {
            $issuedOn = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedOn)) {
            return ['ok' => false, 'error' => 'Give the handover date as a date.'];
        }
        if ($issuedOn > date('Y-m-d', strtotime('+1 day'))) {
            return ['ok' => false, 'error' => 'The handover date is in the future.'];
        }

        /* A chairman may only hand things to traders in their own market. */
        if ($asChairmanId !== null && (int) ($vendor['chairman_id'] ?? 0) !== $asChairmanId) {
            return ['ok' => false, 'error' => 'You may only hand things to traders in your own market.'];
        }

        $size = trim((string) ($input['size'] ?? ''));

        /* Trimmed, and defaulted if the browser sent the field empty. See the
           note at the insert. */
        $followUp = trim((string) ($input['follow_up_on'] ?? ''));
        if ($followUp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUp)) {
            return ['ok' => false, 'error' => 'Give the follow-up date as a date.'];
        }

        $reference = self::nextReference('SUP');
        $id = Database::insert('support_issues', [
            'reference'          => $reference,
            'vendor_id'          => $vendorId,
            'item_id'            => $itemId,
            'quantity'           => $qty,
            'size'               => $size !== '' ? $size : null,
            'issued_on'          => $issuedOn,
            'issued_by'          => $asUserId,
            'issued_by_chairman' => $asChairmanId,
            /* Scheduled by default, not left open. An item that needs a
               signature should be chased; one with no date tends to sit.

               Read with a trim and an ?? fallback, not with ??. A browser
               sends every field in the form, including an empty one, so
               $_POST['follow_up_on'] is '' rather than absent — and ??
               treats '' as supplied and passes it straight through, where
               MySQL rejects it as an invalid date. Every officer's handover
               failed on this because of one operator. */
            'follow_up_on'       => $item['requires_receipt']
                ? ($followUp !== '' ? $followUp : date('Y-m-d', strtotime($issuedOn . ' +7 days')))
                : null,
            'notes'              => trim((string) ($input['notes'] ?? '')) ?: null,
        ]);

        Auth::log(
            'issue-support',
            'support_issues',
            $id,
            $reference . ' — ' . $item['name'] . ' ×' . $qty . ' to ' . $vendor['full_name']
        );

        return [
            'ok'        => true,
            'id'        => $id,
            'reference' => $reference,
            'message'   => 'Recorded ' . $reference . ': ' . $item['name'] . ' ×' . $qty . ' to '
                        . $vendor['full_name'] . '. Awaiting their confirmation.',
        ];
    }

    /**
     * A trader confirms they have it. This is the step that closes an issue.
     *
     * The trader's own confirmation is the point of the whole table: it is
     * the only thing that separates "we distributed" from "they received". So
     * a vendor is the first caller this accepts, not an afterthought —
     * otherwise the button on their own screen could never work, and every
     * handover would sit outstanding forever.
     *
     * A trader is confined to their own: the test is on the issue's vendor,
     * not on the caller's account, so it holds however the request was
     * assembled. A chairman is confined to their market, and an officer may
     * confirm on someone's behalf only with the grant that says they speak
     * for the Authority.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function confirmReceipt(
        int $issueId,
        ?int $asUserId = null,
        ?int $asChairmanId = null,
        ?int $asVendorId = null
    ): array {
        $issue = Database::one(
            'SELECT i.id, i.reference, i.received_at, i.vendor_id,
                    v.full_name, v.chairman_id, it.name AS item_name
               FROM support_issues i
               JOIN vendors v ON v.id = i.vendor_id
               JOIN support_items it ON it.id = i.item_id
              WHERE i.id = ?',
            [$issueId]
        );

        if (!$issue) {
            return ['ok' => false, 'error' => 'That handover is not on record.'];
        }
        if ($issue['received_at'] !== null) {
            return ['ok' => false, 'error' => 'That handover was already confirmed.'];
        }
        if ($asVendorId !== null) {
            if ((int) $issue['vendor_id'] !== $asVendorId) {
                return ['ok' => false, 'error' => 'That handover was not issued to you.'];
            }
        } elseif ($asChairmanId !== null) {
            if ((int) ($issue['chairman_id'] ?? 0) !== $asChairmanId) {
                return ['ok' => false, 'error' => 'That handover is not in your market.'];
            }
        } elseif ($asUserId === null || !Auth::can('report')) {
            return ['ok' => false, 'error' => 'You may not confirm on a trader’s behalf.'];
        }

        Database::update('support_issues', [
            'received_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$issueId]);

        Auth::log('confirm-support', 'support_issues', $issueId,
            $issue['reference'] . ' received by ' . $issue['full_name']);

        return ['ok' => true, 'message' => 'Confirmed. ' . $issue['item_name']
            . ' is marked as received by ' . $issue['full_name'] . '.'];
    }

    /** Everything handed to one trader, newest first. */
    public static function forVendor(int $vendorId): array
    {
        return Database::all(
            'SELECT i.*, it.name AS item_name, it.code AS item_code, it.category AS item_category,
                    it.unit_price_ugx,
                    u.full_name AS officer_name, ch.full_name AS chairman_name
               FROM support_issues i
               JOIN support_items it ON it.id = i.item_id
          LEFT JOIN users u ON u.id = i.issued_by
          LEFT JOIN market_chairmen ch ON ch.id = i.issued_by_chairman
              WHERE i.vendor_id = ?
              ORDER BY i.issued_on DESC, i.id DESC',
            [$vendorId]
        );
    }

    /**
     * Handovers still outstanding, oldest and most overdue first.
     *
     * Ordered so the thing that has been waiting longest is the first thing
     * anybody sees. A list sorted by date issued puts yesterday's overshadow
     * the three-month-old one, which is the opposite of useful.
     */
    public static function outstanding(?int $chairmanMarketId = null, string $status = 'all'): array
    {
        $where  = ['i.received_at IS NULL'];
        $params = [];

        if ($chairmanMarketId !== null) {
            $where[]  = 'v.chairman_id IN (SELECT id FROM market_chairmen WHERE market_id = :mkt)';
            $params['mkt'] = $chairmanMarketId;
        }
        if ($status === 'overdue') {
            $where[] = 'i.follow_up_on IS NOT NULL AND i.follow_up_on < CURDATE()';
        } elseif ($status === 'due') {
            $where[] = 'i.follow_up_on IS NOT NULL AND i.follow_up_on >= CURDATE()';
        }

        return Database::all(
            'SELECT i.*, it.name AS item_name, it.category AS item_category,
                    v.full_name, v.vendor_code, v.phone AS vendor_phone,
                    m.name AS market_name,
                    u.full_name AS officer_name, ch.full_name AS chairman_name,
                    DATEDIFF(CURDATE(), i.issued_on) AS days_waiting,
                    (i.follow_up_on IS NOT NULL AND i.follow_up_on < CURDATE()) AS overdue
               FROM support_issues i
               JOIN vendors v ON v.id = i.vendor_id
               JOIN support_items it ON it.id = i.item_id
          LEFT JOIN market_chairmen vm ON vm.id = v.chairman_id
          LEFT JOIN markets m ON m.id = vm.market_id
          LEFT JOIN users u ON u.id = i.issued_by
          LEFT JOIN market_chairmen ch ON ch.id = i.issued_by_chairman
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY overdue DESC, days_waiting DESC, i.id DESC',
            $params
        );
    }

    /** Counts for the cards at the top of the support screen. */
    public static function counts(?int $chairmanMarketId = null): array
    {
        $clause = $chairmanMarketId !== null
            ? ' AND v.chairman_id IN (SELECT id FROM market_chairmen WHERE market_id = ' . (int) $chairmanMarketId . ')'
            : '';

        return [
            'issued'     => (int) Database::value(
                'SELECT COUNT(*) FROM support_issues i JOIN vendors v ON v.id = i.vendor_id WHERE 1=1' . $clause
            ),
            'received'   => (int) Database::value(
                'SELECT COUNT(*) FROM support_issues i JOIN vendors v ON v.id = i.vendor_id
                  WHERE i.received_at IS NOT NULL' . $clause
            ),
            'outstanding' => (int) Database::value(
                'SELECT COUNT(*) FROM support_issues i JOIN vendors v ON v.id = i.vendor_id
                  WHERE i.received_at IS NULL' . $clause
            ),
            'overdue'    => (int) Database::value(
                'SELECT COUNT(*) FROM support_issues i JOIN vendors v ON v.id = i.vendor_id
                  WHERE i.received_at IS NULL AND i.follow_up_on IS NOT NULL
                    AND i.follow_up_on < CURDATE()' . $clause
            ),
        ];
    }

    /**
     * Traders in a market who have not been given their prescribed attire.
     *
     * The gap list, not the issued list. Asking "who has had their apron"
     * answers a question nobody was asking; asking "who has not" is the one
     * that needs an answer before the rains or the trading season.
     */
    public static function attireGap(int $marketId): array
    {
        return Database::all(
            "SELECT v.id, v.vendor_code, v.full_name, v.phone, v.business_category AS trade,
                    ta.garment, ta.colour, ta.colour_hex, ta.unit_price_ugx, ta.issued_by,
                    (SELECT MAX(i.issued_on) FROM support_issues i
                       JOIN support_items it ON it.id = i.item_id
                      WHERE i.vendor_id = v.id AND it.category = 'attire') AS last_issued
               FROM vendors v
               JOIN trade_attire ta ON ta.trade_code = v.business_category
              WHERE v.chairman_id IN (SELECT id FROM market_chairmen WHERE market_id = :mkt)
                AND NOT EXISTS (
                      SELECT 1 FROM support_issues i
                        JOIN support_items it ON it.id = i.item_id
                       WHERE i.vendor_id = v.id AND it.category = 'attire'
                    )
              ORDER BY v.full_name",
            ['mkt' => $marketId]
        );
    }

    /**
     * A human reference in the series SUP-000123.
     *
     * Derived from the row count rather than a sequence, because this schema
     * is restored from a dump and a sequence would not come with it — a
     * restored database would then start issuing references that collide.
     */
    private static function nextReference(string $prefix): string
    {
        $n = (int) Database::value(
            'SELECT COUNT(*) FROM support_issues WHERE reference LIKE ?',
            [$prefix . '-%']
        );
        /* Step past anything already issued, so a reference is never reused. */
        do {
            $n++;
            $candidate = sprintf('%s-%06d', $prefix, $n);
            $taken = Database::value(
                'SELECT COUNT(*) FROM support_issues WHERE reference = ?',
                [$candidate]
            );
        } while ((int) $taken > 0);

        return $candidate;
    }
}