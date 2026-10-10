<?php
/**
 * Role-based access control and session handling.
 */

declare(strict_types=1);

final class Auth
{
    private const PERMISSIONS = [
        'administrator' => ['*'],
        'executive'     => ['view', 'allocate', 'approve', 'enforce', 'report', 'manage'],
        'mayor'         => ['view', 'allocate', 'enforce', 'report'],
        'planner'       => ['view', 'allocate', 'report'],
        'enforcement'   => ['view', 'enforce', 'report'],
        'revenue'       => ['view', 'report'],
        'market-admin'  => ['view', 'allocate', 'manage', 'report'],
        'auditor'       => ['view', 'report', 'audit'],
        'viewer'        => ['view'],
    ];

    /**
     * The order of authority, lowest to highest.
     *
     * Two things hang off this. A role's dashboard is built from the tier
     * it sits in, so nobody sees another tier's figures by accident. And
     * "can this officer edit that user's record" is answered by comparing
     * positions, rather than by a second permission list that would have
     * to be kept in step with this one.
     */
    private const HIERARCHY = [
        'viewer'       => 1,   // read-only observer
        'revenue'      => 2,   // Revenue Collection
        'auditor'      => 3,   // Internal Audit — oversight, no operations
        'planner'      => 4,   // Physical Planning
        'enforcement'  => 4,   // Legal Affairs
        'market-admin' => 4,   // Market Administration
        'mayor'        => 5,   // a Division Mayor — authority across own division
        'executive'    => 6,   // Executive Director — citywide
        'administrator'=> 7,   // System Administrator — everything
    ];

    /**
     * What each role is actually responsible for.
     *
     * Used to build the dashboard, so the screen an officer opens says
     * what their own remit is rather than showing them the whole city and
     * leaving them to work out which slice is theirs.
     */
    private const REMIT = [
        'administrator' => [
            'label'   => 'System Administrator',
            'scope'   => 'system',
            'blurb'  => 'The whole system: every division, every register, the accounts that access it.',
        ],
        'executive' => [
            'label'   => 'Executive Director',
            'scope'   => 'citywide',
            'blurb'  => 'The whole programme across all five divisions, and its approval decisions.',
        ],
        'mayor' => [
            'label'   => 'Division Mayor',
            'scope'   => 'division',
            'blurb'  => 'Your own division: its sites, its vendors and its relocation progress.',
        ],
        'planner' => [
            'label'   => 'Physical Planning',
            'scope'   => 'directorate',
            'blurb'  => 'Survey, congestion measurement and where vendors are matched.',
        ],
        'enforcement' => [
            'label'   => 'Legal Affairs',
            'scope'   => 'directorate',
            'blurb'  => 'Notices, ultimatums and clear-up operations, and the record of each.',
        ],
        'market-admin' => [
            'label'   => 'Market Administration',
            'scope'   => 'directorate',
            'blurb'  => 'Gazetted space, the pitch register and the allocation of it.',
        ],
        'revenue' => [
            'label'   => 'Revenue Collection',
            'scope'   => 'directorate',
            'blurb'  => 'Trading licences, and the licence position of every vendor.',
        ],
        'auditor' => [
            'label'   => 'Internal Audit',
            'scope'   => 'oversight',
            'blurb'  => 'The audit trail: every decision, who took it, and when.',
        ],
        'viewer' => [
            'label'   => 'Read-only',
            'scope'   => 'readonly',
            'blurb'  => 'The position across the city, with no ability to change it.',
        ],
    ];

    private static ?array $user = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function attempt(string $username, string $password): bool
    {
        $row = Database::one(
            'SELECT id, username, password_hash, full_name, title, directorate, role, division_id, is_active
               FROM users WHERE username = ? LIMIT 1',
            [$username]
        );

        if (!$row || (int) $row['is_active'] !== 1 || !password_verify($password, $row['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        $_SESSION['role']     = $row['role'];
        $_SESSION['name']     = $row['full_name'];
        $_SESSION['csrf']     = bin2hex(random_bytes(32));
        $_SESSION['seen']     = [];

        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $row['id']]);
        self::log('login', 'users', (int) $row['id'], 'Signed in as ' . $row['role']);

        self::$user = $row;
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::log('logout', 'users', (int) self::user()['id'], 'Signed out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $row = Database::one(
            'SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.division_id,
                    u.email, u.phone, u.is_active, u.last_login_at,
                    d.name AS division_name
               FROM users u
               LEFT JOIN divisions d ON d.id = u.division_id
              WHERE u.id = ? LIMIT 1',
            [(int) $_SESSION['user_id']]
        );
        self::$user = $row ?: [];
        return self::$user;
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function role(): string
    {
        return (string) ($_SESSION['role'] ?? 'guest');
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        $granted = self::PERMISSIONS[$role] ?? ['view'];
        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /** This role's position in the order of authority; 0 if unknown. */
    public static function rank(string $role = null): int
    {
        return self::HIERARCHY[$role ?? self::role()] ?? 0;
    }

    /**
     * What this role is responsible for.
     *
     * @return array{label:string,scope:string,blurb:string}
     */
    public static function remit(string $role = null): array
    {
        $key = $role ?? self::role();
        return self::REMIT[$key]
            ?? ['label' => humanise($key), 'scope' => 'readonly', 'blurb' => ''];
    }

    /**
     * Can this officer edit that user's record?
     *
     * Answered by position in the hierarchy, not by a second permission
     * list: one rule that cannot fall out of step with the roles
     * themselves. An officer never edits their own account through this
     * screen — that is what their own profile is for — and nobody edits
     * an account at or above their own level.
     */
    public static function canManageUser(array $target): bool
    {
        if (!self::check() || !self::can('manage')) {
            return false;
        }
        if ((int) ($target['id'] ?? 0) === self::id()) {
            return false;
        }
        return self::rank() > self::rank((string) ($target['role'] ?? 'viewer'));
    }

    /** Every role, highest first, for the account screens. */
    public static function roles(): array
    {
        $out = self::HIERARCHY;
        arsort($out);
        return array_keys($out);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ../auth/login.php');
            exit;
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            $facts = require __DIR__ . '/../config/kcca_facts.php';
            require __DIR__ . '/../layouts/forbidden.php';
            exit;
        }
    }

    public static function scopeDivision(): ?int
    {
        // Division-level roles see only their own division.
        if (in_array(self::role(), ['mayor'], true)) {
            $u = self::user();
            return !empty($u['division_id']) ? (int) $u['division_id'] : null;
        }
        return null;
    }

    /* ---------------- CSRF ---------------- */

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::checkCsrf($token)) {
            http_response_code(419);
            echo 'CSRF token mismatch — reload the page and try again.';
            exit;
        }
    }

    /* ---------------- Audit ---------------- */

    public static function log(string $action, ?string $entity = null, ?int $entityId = null, ?string $detail = null): void
    {
        $user = self::check() ? self::user() : [];
        try {
            Database::insert('audit_log', [
                'user_id'    => self::check() ? (int) $user['id'] : null,
                'actor'      => self::check() ? (string) $user['full_name'] : 'anonymous',
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'detail'     => $detail,
                'ip_address' => serverClientIp(),
            ]);
        } catch (Throwable $e) {
            // Auditing must never take the application down.
        }
    }
}