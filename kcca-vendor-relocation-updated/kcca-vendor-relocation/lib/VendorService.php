<?php
/**
 * KCCA VRSP — Vendor registration and management.
 *
 * Kept as a service rather than living inside the JSON endpoint, so that
 * the endpoint and the modal form validate identically. There is one set
 * of rules and one code path that writes the row; a form cannot drift
 * away from what the API actually accepts.
 *
 * The governing rule for validation here is specific: reject the bad
 * field, keep everything else. A trader mistyping a telephone number must
 * not have to complete the whole registration again.
 */

declare(strict_types=1);

final class VendorService
{
    /**
     * The field rules. Declared once, used by the validator and mirrored
     * in the form markup.
     */
    public static function rules(): array
    {
        return [
            'full_name'    => ['type' => 'text', 'label' => 'Full name', 'required' => true, 'min' => 3, 'max' => 140],
            'national_id'  => ['type' => 'national_id', 'label' => 'National ID number'],
            'phone'        => ['type' => 'phone', 'label' => 'Telephone number', 'required' => true],
            'alternative_phone' => ['type' => 'phone', 'label' => 'Alternative telephone'],
            'email'        => ['type' => 'email', 'label' => 'Email address'],
            'gender'       => ['type' => 'enum', 'label' => 'Gender', 'required' => true,
                               'options' => ['male', 'female', 'other']],
            'date_of_birth' => ['type' => 'date', 'label' => 'Date of birth', 'not_future' => true],
            'division_id'  => ['type' => 'int', 'label' => 'Division', 'required' => true, 'min' => 1],
            'parish'       => ['type' => 'text', 'label' => 'Parish', 'max' => 120],
            'current_zone_id' => ['type' => 'int', 'label' => 'Current trading site', 'min' => 0],
            'business_category' => ['type' => 'enum', 'label' => 'What the trader sells', 'required' => true,
                'options' => ['food-cooking', 'fresh-produce', 'meat-fish', 'beverages', 'crops-grains',
                              'household-goods', 'clothing-textiles', 'electronics-repairs', 'salon-services',
                              'mobile-services', 'handicraft', 'livestock', 'other']],
            'business_description' => ['type' => 'text', 'label' => 'Description of goods', 'max' => 200],
            'trading_days' => ['type' => 'text', 'label' => 'Trading days', 'max' => 60],
            'started_year' => ['type' => 'int', 'label' => 'Year trading began', 'required' => true,
                               'min' => 1950, 'max' => (int) date('Y')],
            'startup_capital_ugx' => ['type' => 'money', 'label' => 'Starting capital', 'min' => 0, 'default' => 0],
            'monthly_turnover_ugx' => ['type' => 'money', 'label' => 'Monthly turnover', 'min' => 0, 'default' => 0],
            'household_size' => ['type' => 'int', 'label' => 'Household size', 'required' => true,
                                 'min' => 1, 'max' => 30],
            'dependants'   => ['type' => 'int', 'label' => 'Dependants', 'min' => 0, 'max' => 40, 'default' => 0],
            'is_person_with_disability' => ['type' => 'checkbox', 'label' => 'Person with disability'],
            'is_single_parent' => ['type' => 'checkbox', 'label' => 'Single parent'],
            'is_youth'     => ['type' => 'checkbox', 'label' => 'Under 30'],
            'is_veteran_ex_enforcee' => ['type' => 'checkbox', 'label' => 'Former enforcement agent'],
            'has_trading_licence' => ['type' => 'checkbox', 'label' => 'Holds a trading licence'],
            'licence_class' => ['type' => 'enum', 'label' => 'Licence class', 'default' => 'unlicensed',
                'options' => ['unlicensed', 'street-grade-1', 'street-grade-2', 'market-standard']],
            'association'  => ['type' => 'text', 'label' => 'Traders association', 'max' => 160],
            /* Defaults match the schema. They are declared here because the
               validator writes an explicit value for every rule it knows,
               and an explicit NULL would override the column default. */
            'status'       => ['type' => 'enum', 'label' => 'Stage', 'default' => 'surveyed',
                'options' => ['surveyed', 'notified', 'offered-space', 'relocated', 'declined', 'deferred', 'complaint']],
        ];
    }

    /**
     * Validate and write.
     *
     * @param  array $input  raw request body
     * @param  string $action create | update | delete
     * @return array{ok:bool, status:int, error?:string, errors:array, message?:string, id?:int}
     */
    public static function save(array $input, string $action): array
    {
        if ($action === 'delete') {
            return self::delete((int) ($input['vendor_id'] ?? 0));
        }

        $v = new Validator($input, self::rules());

        /* Referential checks on the ids the user picked from a list, so a
           tampered id is rejected rather than trusted. */
        $v->checkExists('division_id', 'divisions', $input['division_id'] ?? null);
        if (!empty($input['current_zone_id'])) {
            $v->checkExists('current_zone_id', 'trade_zones', $input['current_zone_id']);
        }

        /* A trader in a zone is in that zone's division. The register joins
           divisions with an inner join, so a vendor saved without one is
           stored and then never appears in any list — the form accepted it
           and the trader vanished. Derive it, and only ask if neither is
           known. */
        if (empty($input['division_id']) && !empty($input['current_zone_id'])) {
            $fromZone = Database::value(
                'SELECT division_id FROM trade_zones WHERE id = ?', [(int) $input['current_zone_id']]
            );
            if ($fromZone !== null) {
                $input['division_id'] = (int) $fromZone;
                $v = new Validator($input, self::rules());
                $v->checkExists('division_id', 'divisions', $input['division_id']);
                if (!empty($input['current_zone_id'])) {
                    $v->checkExists('current_zone_id', 'trade_zones', $input['current_zone_id']);
                }
            }
        }
        if (empty($input['division_id'])) {
            return ['ok' => false, 'status' => 422,
                    'error'  => 'Choose the division, or the trading site the division follows from.',
                    'errors' => ['division_id' => 'A trader must belong to a division. '
                        . 'Pick one, or name the site they trade at.']];
        }

        if ($v->fails()) {
            return [
                'ok'     => false,
                'status' => 422,
                'error'  => 'Some details could not be saved. Correct the highlighted fields and submit again.',
                'errors' => $v->errors(),
            ];
        }

        $data = $v->validated();

        /* A chairman registers traders into their own market. The market is
           theirs, so it is taken from their account rather than chosen from
           a list — a submitted chairman_id or division_id is replaced, not
           trusted, or a chairman could attach a trader to somebody else's
           market by editing the request. */
        $chairmanId = (int) (Auth::user()['chairman_id'] ?? 0);
        if (Auth::role() === 'chairman' && $chairmanId > 0) {
            $data['chairman_id'] = $chairmanId;
            $divisionId = Database::value(
                'SELECT m.division_id FROM market_chairmen ch
                   JOIN markets m ON m.id = ch.market_id
                  WHERE ch.id = ?',
                [$chairmanId]
            );
            if ($divisionId !== null) {
                $data['division_id'] = (int) $divisionId;
            }
        }

        return $action === 'update'
            ? self::update((int) ($input['vendor_id'] ?? 0), $data)
            : self::create($data);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Is this trader already in the register?
     *
     * The same person registered twice can be offered two pitches, and counted
     * twice in every figure the Authority reports. National ID numbers and
     * telephone numbers identify a person, so a second record carrying either
     * is refused, naming the record that already holds it.
     *
     * @return array<string,string> field => message, empty when there is no clash
     */
    private static function duplicateErrors(array $data, int $exceptId = 0): array
    {
        $errors = [];

        $nin = strtoupper(trim((string) ($data['national_id'] ?? '')));
        if ($nin !== '') {
            $hit = Database::one(
                'SELECT vendor_code FROM vendors WHERE UPPER(TRIM(national_id)) = ? AND id <> ? LIMIT 1',
                [$nin, $exceptId]
            );
            if ($hit) {
                $errors['national_id'] = 'This National ID is already registered as ' . $hit['vendor_code'] . '.';
            }
        }

        $phone = preg_replace('/[\s\-]+/', '', (string) ($data['phone'] ?? ''));
        if ($phone !== '') {
            $hit = Database::one(
                "SELECT vendor_code FROM vendors
                  WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') = ? AND id <> ? LIMIT 1",
                [$phone, $exceptId]
            );
            if ($hit) {
                $errors['phone'] = 'This telephone number already belongs to ' . $hit['vendor_code'] . '.';
            }
        }

        return $errors;
    }

    private static function create(array $data): array
    {
        $clash = self::duplicateErrors($data);
        if ($clash !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $clash];
        }

        $data['business_description'] = ($data['business_description'] ?? '')
            ?: tradeDescription((string) $data['business_category']);
        $data['registered_at'] = date('Y-m-d H:i:s');

        try {
            /* The vendor code is allocated inside the transaction, so two
               concurrent registrations cannot be issued the same code. */
            $id = Database::transaction(static function () use (&$data) {
                $next = 1 + (int) Database::value('SELECT COALESCE(MAX(id), 0) FROM vendors');
                $data['vendor_code'] = 'KCCA-V' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                return Database::insert('vendors', $data);
            });
        } catch (Throwable $e) {
            /* Do not echo the driver's message: it can quote SQL the user
               has no business seeing. The reference is in the log. */
            error_log('vendor register failed: ' . $e->getMessage());
            return [
                'ok'     => false,
                'status' => 500,
                'error'  => 'The vendor could not be registered. Nothing was saved — please try again.',
                'errors' => [],
            ];
        }

        if (!empty($data['current_zone_id'])) {
            self::recountZone((int) $data['current_zone_id']);
        }

        RecommendationService::logEvent(null, $id, null, null, 'surveyed', 'created',
            'Vendor registered: ' . $data['full_name']);

        Auth::log('register-vendor', 'vendors', $id,
            $data['vendor_code'] . ' — ' . $data['full_name']);

        return [
            'ok'      => true,
            'status'  => 200,
            'id'      => $id,
            'code'    => $data['vendor_code'],
            'message' => sprintf('%s has been registered as %s.',
                $data['full_name'], $data['vendor_code']),
        ];
    }

    private static function update(int $id, array $data): array
    {
        $existing = Database::one('SELECT * FROM vendors WHERE id = ?', [$id]);
        if (!$existing) {
            return ['ok' => false, 'status' => 404, 'error' => 'That vendor is not in the register.', 'errors' => []];
        }

        /* Only a number the officer is actually changing is checked, so a
           duplicate that is already in the register does not stop an
           unrelated correction to the same record. */
        $toCheck = $data;
        foreach (['national_id', 'phone'] as $field) {
            if (isset($toCheck[$field])
                && preg_replace('/[\\s\\-]+/', '', strtoupper((string) $toCheck[$field]))
                   === preg_replace('/[\\s\\-]+/', '', strtoupper((string) ($existing[$field] ?? '')))) {
                unset($toCheck[$field]);
            }
        }
        $clash = self::duplicateErrors($toCheck, $id);
        if ($clash !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $clash];
        }

        /* A chairman may correct a trader in their own market and nobody
           else's. Without this they could amend any record in the register
           by editing the request. */
        $chairmanId = (int) (Auth::user()['chairman_id'] ?? 0);
        if (Auth::role() === 'chairman' && $chairmanId > 0
            && (int) ($existing['chairman_id'] ?? 0) !== $chairmanId) {
            return ['ok' => false, 'status' => 403,
                    'error' => 'That trader is not in your market.', 'errors' => []];
        }

        $oldZone = (int) ($existing['current_zone_id'] ?? 0);

        Database::update('vendors', $data, 'id = :id', ['id' => $id]);

        /* Both the old and the new zone need recounting. */
        foreach (array_unique([$oldZone, (int) ($data['current_zone_id'] ?? 0)]) as $zoneId) {
            if ($zoneId > 0) {
                self::recountZone($zoneId);
            }
        }

        $changed = [];
        foreach ($data as $k => $val) {
            if ((string) ($existing[$k] ?? '') !== (string) $val) {
                $changed[] = humanise($k);
            }
        }

        if ($changed !== []) {
            RecommendationService::logEvent(null, $id, null, null, (string) $existing['status'], 'note',
                'Vendor record updated: ' . implode(', ', $changed));
            Auth::log('update-vendor', 'vendors', $id,
                $existing['vendor_code'] . ' — changed: ' . implode(', ', $changed));
        }

        return [
            'ok'      => true,
            'status'  => 200,
            'id'      => $id,
            'changed' => count($changed),
            'message' => sprintf('%s’s record has been updated.', $existing['full_name']),
        ];
    }

    private static function delete(int $id): array
    {
        $row = Database::one('SELECT * FROM vendors WHERE id = ?', [$id]);
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'error' => 'That vendor is not in the register.', 'errors' => []];
        }

        /* A vendor holding a live allocation must not be deleted out from
           under it, or the pitch would be left orphaned. */
        $live = Database::one(
            "SELECT reference FROM allocations
              WHERE vendor_id = ? AND status IN ('proposed','offered','accepted','occupied') LIMIT 1",
            [$id]
        );
        if ($live) {
            return [
                'ok'     => false,
                'status' => 409,
                'error'  => sprintf(
                    '%s holds a live allocation (%s). Close or withdraw it before removing the vendor.',
                    $row['full_name'], $live['reference']),
                'errors' => [],
            ];
        }

        Database::run('DELETE FROM vendors WHERE id = ?', [$id]);

        if (!empty($row['current_zone_id'])) {
            self::recountZone((int) $row['current_zone_id']);
        }

        Auth::log('delete-vendor', 'vendors', $id,
            $row['vendor_code'] . ' — ' . $row['full_name'] . ' removed');

        return ['ok' => true, 'status' => 200,
                'message' => $row['full_name'] . ' has been removed from the register.'];
    }

    /**
     * Recompute a zone's vendor count from the register, so the figure on
     * the zone can never drift away from the rows behind it.
     */
    public static function recountZone(int $zoneId): void
    {
        Database::run(
            'UPDATE trade_zones SET estimated_vendors =
               (SELECT COUNT(*) FROM vendors WHERE current_zone_id = trade_zones.id)
             WHERE id = ?',
            [$zoneId]
        );
    }
}