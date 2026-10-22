<?php
/**
 * What a user may do to their own account.
 *
 * Deleting is a soft delete, the same rule the Administrator's "delete" uses
 * for other people: the account can no longer sign in and its contact details
 * are erased, but the rows it made stay, and the audit trail still names who
 * did what. A register that forgets who approved an allocation cannot be
 * audited, so the person leaves the system and the record of their work does
 * not.
 *
 * Guards
 *  - The password is asked for again, and the word DELETE typed, so a left-open
 *    session or a stray click cannot end an account.
 *  - The last active System Administrator cannot delete themselves; the system
 *    would be left with nobody able to manage accounts.
 *  - Wrong passwords here count toward the same sign-in lockout as the login page.
 */

declare(strict_types=1);

final class SelfAccount
{
    /** @return array{ok:bool,error?:string} */
    public static function delete(string $password, string $typed): array
    {
        $id  = Auth::id();
        $row = Database::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$row || (int) $row['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'This account is not active.'];
        }

        if ($typed !== 'DELETE') {
            return ['ok' => false, 'error' => 'Type DELETE in capital letters to confirm.'];
        }

        if (Auth::lockedOut((string) $row['username'])) {
            return ['ok' => false, 'error' => 'Too many wrong passwords. Please wait ten minutes and try again.'];
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            Auth::log('login-failed', 'users', $id, 'user:' . strtolower((string) $row['username']));
            return ['ok' => false, 'error' => 'That password is not correct.'];
        }

        if ($row['role'] === 'administrator') {
            $others = (int) Database::value(
                "SELECT COUNT(*) FROM users WHERE role = 'administrator' AND is_active = 1 AND id <> ?",
                [$id]
            );
            if ($others === 0) {
                return ['ok' => false, 'error' => 'You are the only active System Administrator. '
                    . 'Appoint another administrator before you delete your account.'];
            }
        }

        /* Logged first, while the session still names the person. */
        Auth::log('delete-own-account', 'users', $id,
            $row['username'] . ' (' . $row['role'] . ') deleted their own account');

        Database::transaction(static function () use ($id): void {
            Database::run(
                'UPDATE users SET is_active = 0, email = NULL, phone = NULL, username = ?, password_hash = ? WHERE id = ?',
                [
                    'deleted-' . $id . '-' . substr(bin2hex(random_bytes(4)), 0, 6),
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    $id,
                ]
            );
        });

        try {
            Database::run('DELETE FROM user_preferences WHERE user_id = ?', [$id]);
        } catch (Throwable $e) {
            /* the table may not exist; nothing to remove */
        }

        Auth::logout();
        return ['ok' => true];
    }

    /** Everything the system holds about the signed-in user, for them to keep. */
    public static function export(): array
    {
        $id = Auth::id();

        $account = Database::one(
            'SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.email, u.phone,
                    d.name AS division, u.created_at, u.last_login_at
               FROM users u LEFT JOIN divisions d ON d.id = u.division_id
              WHERE u.id = ?',
            [$id]
        ) ?: [];

        $activity = Database::all(
            'SELECT created_at AS at, action, entity, entity_id, detail
               FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 500',
            [$id]
        );

        return [
            'exported_at'  => date('c'),
            'system'       => 'KCCA Vendor Relocation and Stall Allocation',
            'account'      => $account,
            'preferences'  => Preferences::get($id),
            'recent_activity' => $activity,
            'note' => 'Records you made in the register (allocations, approvals, notices) belong to the '
                    . 'Authority and remain after an account is deleted, so that the audit trail stays complete.',
        ];
    }
}
