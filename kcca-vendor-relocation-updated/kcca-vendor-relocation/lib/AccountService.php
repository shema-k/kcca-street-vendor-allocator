<?php
/**
 * KCCA VRSP — account administration.
 *
 * The System Administrator's instrument over every account in the system:
 * creating staff, changing what they may do, and setting their password.
 *
 * Three rules are enforced here rather than left to a page:
 *
 *   1. Nobody edits an account at or above their own level of authority,
 *      and nobody edits their own. That is what a profile is for.
 *   2. Nobody is appointed to a level at or above the officer's own,
 *      which is how an officer would otherwise create a peer.
 *   3. A password set by an officer is logged as a password event, not
 *      buried in an ordinary edit — because an officer who can create an
 *      account can already own it, and what the system must add is the
 *      record.
 *
 * The validator does the field work. What this class adds is the authority
 * check, which is not a field rule and cannot be expressed as one.
 */

declare(strict_types=1);

final class AccountService
{
    /** Minimum password length, applied on creation and on change. */
    public const MIN_PASSWORD = 10;

    /**
     * Field rules. $creating adds the username, which is set once and never
     * offered for edit — an officer is renamed by an administrator changing
     * the person, not the login.
     */
    public static function rules(bool $creating): array
    {
        $rules = [
            'full_name'   => ['type' => 'text', 'label' => 'Full name', 'required' => true,
                              'min' => 3, 'max' => 140],
            'title'       => ['type' => 'text', 'label' => 'Position', 'max' => 140],
            'directorate' => ['type' => 'text', 'label' => 'Directorate', 'max' => 160],
            'email'       => ['type' => 'email', 'label' => 'Email address'],
            'phone'       => ['type' => 'phone', 'label' => 'Telephone number'],
            'division_id' => ['type' => 'int', 'label' => 'Division'],
            'role'        => ['type' => 'enum', 'label' => 'Role', 'required' => true,
                              'options' => Auth::roles()],
            // Whether the name and title are published by KCCA or invented for
            // demonstration. The audit trail attributes every action to a
            // name, so a reader is entitled to know which it is — and the
            // Administrator is the only person who can say so.
            'name_basis'  => ['type' => 'enum', 'label' => 'Basis of the name',
                              'default' => 'illustrative',
                              'options' => ['published', 'illustrative']],
            /* The two links that attach an account to the register. A
               chairman account is scoped by the market it chairs; a trader
               account reaches its market and chairman through the vendor
               record it belongs to. Both are optional in the form and
               checked for consistency afterwards, because a market may not
               yet have a chairman recorded and a vendor not yet placed. */
            'chairman_id' => ['type' => 'int', 'label' => 'Market chaired', 'min' => 0],
            'vendor_id'   => ['type' => 'int', 'label' => 'Vendor record', 'min' => 0],
        ];

        if ($creating) {
            $rules['username'] = [
                'type' => 'text', 'label' => 'Username', 'required' => true,
                'min' => 3, 'max' => 60,
                'pattern' => '/^[a-z0-9._-]+$/',
                'pattern_message' => 'Lower-case letters, digits, dot, underscore and hyphen only.',
            ];
        }

        return $rules;
    }

    /**
     * Create an account.
     *
     * @return array{ok:bool, error?:string, errors:array, id?:int}
     */
    public static function create(array $input): array
    {
        $errors = [];
        $v = new Validator($input, self::rules(true));
        $v->checkExists('division_id', 'divisions', $input['division_id'] ?? null);

        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            $errors['password'] = 'A password is required for a new account.';
        } elseif (strlen($password) < self::MIN_PASSWORD) {
            $errors['password'] = 'A password must be at least ' . self::MIN_PASSWORD . ' characters.';
        } elseif ($password !== (string) ($input['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        $username = (string) ($input['username'] ?? '');
        if ($username === '') {
            $errors['username'] = 'A username is required.';
        } elseif (Database::one('SELECT id FROM users WHERE username = ?', [$username])) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($v->fails() || $errors !== []) {
            return ['ok' => false, 'errors' => $errors + $v->errors()];
        }

        $data = $v->validated();
        $data['is_active'] = isset($input['is_active']) ? 1 : 0;

        /* The register links are settled after field validation so a
           refusal names the field rather than aborting the whole save. */
        $linkErrors = self::settleLinks($data, (string) $data['role']);
        if ($linkErrors !== []) {
            return ['ok' => false, 'errors' => $linkErrors];
        }

        $id = Database::insert('users', array_merge($data, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]));

        Auth::log('create-account', 'users', $id,
            $data['username'] . ' — ' . Auth::remit($data['role'])['label']);

        self::logPassword($id, $data['username'], (string) $data['role'], 'set on creation');

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Settle the two links that attach an account to the register.
     *
     * A chairman account is scoped by the market it chairs and a trader
     * account reaches its market and chairman through the vendor record it
     * belongs to. Neither link means anything on any other role, so the
     * field is cleared rather than left holding a value that would quietly
     * do nothing — and on the two roles where the link *is* the account, a
     * missing one is refused rather than stored, because a chairman with no
     * market would be shown nothing and appear to be broken.
     *
     * @return array<string,string> field errors
     */
    private static function settleLinks(array &$data, string $role): array
    {
        $errors = [];

        if ($role === 'chairman') {
            $data['vendor_id'] = null;
            $chairmanId = (int) ($data['chairman_id'] ?? 0);
            if ($chairmanId <= 0) {
                $errors['chairman_id'] = 'Choose the market this chairman administers.';
            } elseif (!Database::one('SELECT id FROM market_chairmen WHERE id = ?', [$chairmanId])) {
                $errors['chairman_id'] = 'That chairman record does not exist.';
            } else {
                $data['chairman_id'] = $chairmanId;
            }
        } elseif ($role === 'vendor') {
            $data['chairman_id'] = null;
            $vendorId = (int) ($data['vendor_id'] ?? 0);
            if ($vendorId <= 0) {
                $errors['vendor_id'] = 'Choose the vendor record this account belongs to.';
            } elseif (!Database::one('SELECT id FROM vendors WHERE id = ?', [$vendorId])) {
                $errors['vendor_id'] = 'That vendor record does not exist.';
            } else {
                $data['vendor_id'] = $vendorId;
            }
        } else {
            $data['chairman_id'] = null;
            $data['vendor_id']   = null;
        }

        return $errors;
    }

    /**
     * Change an existing account. A blank password leaves it alone.
     *
     * @return array{ok:bool, error?:string, errors:array}
     */
    public static function update(int $id, array $input): array
    {
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) {
            return ['ok' => false, 'error' => 'That account does not exist.', 'errors' => []];
        }

        /* The authority rules. Not field validation, so not in the Validator. */
        if ((int) $row['id'] === Auth::id()) {
            return ['ok' => false,
                    'error' => 'You cannot edit your own account here. Use your profile instead.',
                    'errors' => []];
        }
        if (Auth::rank() <= Auth::rank((string) $row['role'])) {
            return ['ok' => false,
                    'error' => sprintf('You cannot change %s’s account — they hold the same '
                                     . 'or a higher level of authority.', $row['full_name']),
                    'errors' => []];
        }

        $errors = [];
        $v = new Validator($input, self::rules(false));
        $v->checkExists('division_id', 'divisions', $input['division_id'] ?? null);

        $password = (string) ($input['password'] ?? '');
        $setPassword = false;
        if ($password !== '') {
            if (strlen($password) < self::MIN_PASSWORD) {
                $errors['password'] = 'A password must be at least ' . self::MIN_PASSWORD . ' characters.';
            } elseif ($password !== (string) ($input['password_confirm'] ?? '')) {
                $errors['password_confirm'] = 'The two passwords do not match.';
            } else {
                $setPassword = true;
            }
        }

        if ($v->fails() || $errors !== []) {
            return ['ok' => false, 'errors' => $errors + $v->errors()];
        }

        $data = $v->validated();
        $data['is_active'] = isset($input['is_active']) ? 1 : 0;

        $linkErrors = self::settleLinks($data, (string) $data['role']);
        if ($linkErrors !== []) {
            return ['ok' => false, 'errors' => $linkErrors];
        }

        /* Nobody is appointed to their own level or above. */
        if ($data['role'] !== 'administrator'
            && Auth::rank() <= Auth::rank($data['role'])
            && (string) $row['role'] !== $data['role']) {
            return ['ok' => false, 'errors' => ['role' => sprintf(
                'You cannot appoint somebody to %s — that is your own level of authority or above.',
                Auth::remit($data['role'])['label'])]];
        }

        if ($setPassword) {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $data, 'id = :id', ['id' => $id]);

        Auth::log('update-account', 'users', $id,
            $row['username'] . ' — ' . implode(', ', array_diff(array_keys($data), ['password_hash'])));

        if ($setPassword) {
            self::logPassword($id, (string) $row['username'], (string) $row['role'], 'set by an officer');
        }

        return ['ok' => true];
    }

    /** Issue a temporary password, shown once and never stored in the clear. */
    public static function issueTemporaryPassword(int $id): array
    {
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) {
            return ['ok' => false, 'error' => 'That account does not exist.'];
        }
        if ((int) $row['id'] === Auth::id()) {
            return ['ok' => false, 'error' => 'Use your profile to change your own password.'];
        }
        if (Auth::rank() <= Auth::rank((string) $row['role'])) {
            return ['ok' => false, 'error' => sprintf(
                'You cannot reset %s’s password — they hold the same or a higher level of authority.',
                $row['full_name'])];
        }

        $temp = 'KCCA-' . strtoupper(bin2hex(random_bytes(4)));
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($temp, PASSWORD_DEFAULT), $id]);

        Auth::log('reset-password', 'users', $id, $row['username'] . ' — temporary password issued');
        self::logPassword($id, (string) $row['username'], (string) $row['role'], 'temporary password issued');

        return [
            'ok'      => true,
            'temp'    => $temp,
            'message' => 'Give this temporary password to ' . $row['full_name']
                       . ' and have them change it on first sign-in.',
        ];
    }

    /** Remove an account. Live work points at officers, so check first. */
    public static function delete(int $id): array
    {
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) {
            return ['ok' => false, 'error' => 'That account does not exist.'];
        }
        if ((int) $row['id'] === Auth::id()) {
            return ['ok' => false, 'error' => 'You cannot remove your own account.'];
        }
        if (Auth::rank() <= Auth::rank((string) $row['role'])) {
            return ['ok' => false, 'error' => sprintf(
                'You cannot remove %s’s account — they hold the same or a higher level of authority.',
                $row['full_name'])];
        }

        /* Everything this account did stays attributable. The account row
           goes, but the audit trail names the officer, so deleting the
           login would orphan the record. Accounts are deactivated instead. */
        Database::run('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
        Auth::log('deactivate-account', 'users', $id,
            $row['username'] . ' — account deactivated');

        return [
            'ok'      => true,
            'message' => $row['full_name'] . ' can no longer sign in. The audit trail keeps '
                       . 'everything they did.',
        ];
    }

    /** A password event, recorded apart from an ordinary edit. */
    private static function logPassword(int $id, string $username, string $role, string $how): void
    {
        Auth::log('set-password', 'users', $id,
            $username . ' — ' . Auth::remit($role)['label'] . ' — ' . $how);
    }
}
