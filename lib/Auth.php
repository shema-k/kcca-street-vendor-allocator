<?php
/**
 * Role-based access control and session handling.
 */

declare(strict_types=1);

final class Auth
{
    private const PERMISSIONS = [
        'administrator' => ['*'],
        'executive'     => ['view', 'allocate', 'approve', 'enforce', 'report', 'manage', 'audit'],
        'mayor'         => ['view', 'allocate', 'enforce', 'report', 'audit'],
        'planner'       => ['view', 'allocate', 'report'],
        'enforcement'   => ['view', 'enforce', 'report'],
        'revenue'       => ['view', 'report'],
        // A chairman sees their own market and nothing else. They trade no
        // decision, so they hold no grant beyond viewing: what follows which
        // vendor is theirs to know, not to act on.
        'chairman'      => ['view'],
        // A trader views markets, applies for a stall and tracks footfall.
        // The stall request is a separate, public endpoint with its own
        // validation, so this role needs no further grant.
        'vendor'        => ['view'],
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
        'vendor'        => 1,   // the trader
        'revenue'       => 2,   // licences and fees: a regulatory desk, not command
        'enforcement'   => 3,   // carries the decision out on the ground
        'chairman'      => 4,   // administers one market; elected, not an officer
        'planner'       => 4,   // decides how the relocation is done
        'mayor'         => 5,   // oversees the managers of one division
        'executive'     => 6,   // the whole Authority, and the final decision
        'administrator' => 7,   // the system itself
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
            'label'  => 'System Administrator',
            'scope'  => 'system',
            'blurb' => 'Everything on the system, and every account that reaches it.',
        ],
        'executive' => [
            'label'  => 'Executive Director',
            'scope'  => 'citywide',
            'blurb' => 'All progress across the Authority, and the final decisions.',
        ],
        'mayor' => [
            'label'  => 'Mayor',
            'scope'  => 'division',
            'blurb' => 'Your division, and the managers working under you.',
        ],
        'planner' => [
            'label'  => 'Planner',
            'scope'  => 'directorate',
            'blurb' => 'How the relocation is planned, and what the forecast says.',
        ],
        'enforcement' => [
            'label'  => 'Enforcement Officer',
            'scope'  => 'directorate',
            'blurb' => 'The Authority on the ground, and the vendors following instructions.',
        ],
        'revenue' => [
            'label'  => 'Revenue Officer',
            'scope'  => 'directorate',
            'blurb' => 'Licences and fees across the register.',
        ],
        'chairman' => [
            'label'  => 'Market Chairman',
            'scope'  => 'market',
            'blurb' => 'The traders allocated to your market, and what the Authority has done for them.',
        ],
        'vendor' => [
            'label'  => 'Vendor',
            'scope'  => 'vendor',
            'blurb' => 'Find space, apply for a stall, and follow the footfall.',
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
                    u.chairman_id, u.vendor_id, u.email, u.phone, u.is_active, u.last_login_at,
                    d.name AS division_name,
                    ch.market_id AS my_market_id, ch.full_name AS chairman_name,
                    m.name AS market_name
               FROM users u
               LEFT JOIN divisions d ON d.id = u.division_id
               LEFT JOIN market_chairmen ch ON ch.id = u.chairman_id
               LEFT JOIN markets m ON m.id = ch.market_id
              WHERE u.id = ? LIMIT 1',
            [(int) $_SESSION['user_id']]
        );
        self::$user = $row ?: [];
        return self::$user;
    }

    /**
     * Drop the memoised account row.
     *
     * Auth::user() caches for the life of the request, which is right until
     * the request itself changes the row — a profile edit, or a password
     * change. Without this the page would go on displaying the old values.
     */
    public static function clearUserCache(): void
    {
        self::$user = null;
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

    /**
     * What one role may do, for any screen that needs to explain the model
     * rather than enforce it — the permission table on the audit page, for
     * instance. Enforcing code uses can(), not this.
     *
     * @return array<int,string>
     */
    public static function permissionsFor(string $role): array
    {
        return self::PERMISSIONS[$role] ?? ['view'];
    }

    /** The top of the order, for display: "level N of M". */
    public static function maxRank(): int
    {
        return max(self::HIERARCHY);
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