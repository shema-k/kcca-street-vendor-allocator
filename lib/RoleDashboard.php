<?php
/**
 * KCCA VRSP — role dashboard.
 *
 * One composer, many screens. A Division Mayor and the System Administrator
 * open the same URL and get a different screen, because the figures they
 * are accountable for are different.
 *
 * The rule that keeps this from turning into nine near-identical
 * dashboards: each role declares the sections it owns, and every figure is
 * scoped to that role's authority before it is fetched. Nothing is
 * assembled generically and then filtered by permission afterwards,
 * because that is what produces a screen where every role sees the same
 * numbers and a viewer sees figures they cannot act on.
 */

declare(strict_types=1);

final class RoleDashboard
{
    /**
     * Build the dashboard for the signed-in user.
     *
     * @return array{
     *   role:string, label:string, scope:string, blurb:string,
     *   rank:int, stats:array<int,array>, sections:array<int,array>,
     *   decisions:array<int,array>
     * }
     */
    public static function build(): array
    {
        $role   = Auth::role();
        $remit  = Auth::remit();
        $scope  = self::scope();

        return [
            'role'      => $role,
            'label'     => $remit['label'],
            'scope'     => $remit['scope'],
            'blurb'     => $remit['blurb'],
            'rank'      => Auth::rank(),
            'stats'     => self::headline($scope),
            'sections'  => self::sections($role, $scope),
            'decisions' => self::waiting($scope),
        ];
    }

    /**
     * The row limit that confines every figure to this officer's authority.
     */
    public static function scope(): array
    {
        $me = Auth::check() ? Auth::user() : [];

        // A Division Mayor sees only their own division. Everyone else is
        // citywide; a directorate role is confined by which sections it is
        // given rather than by filtering the figures.
        if (Auth::role() === 'mayor' && !empty($me['division_id'])) {
            return ['division_id' => (int) $me['division_id']];
        }

        return [];
    }

    /** SQL fragment restricting a table to this officer's scope. */
    public static function scopeSql(array $scope, string $tableAlias = ''): string
    {
        $p = $tableAlias !== '' ? $tableAlias . '.' : '';
        return isset($scope['division_id'])
            ? ' AND ' . $p . 'division_id = ' . (int) $scope['division_id']
            : '';
    }

    /* =================================================================
     * Headline figures — four, and only four, whatever the role
     * ================================================================= */
    private static function headline(array $scope): array
    {
        $div = self::scopeSql($scope);

        $vendors = (int) Database::value("SELECT COUNT(*) FROM vendors WHERE 1 = 1" . $div);
        $toMove  = (int) Database::value(
            "SELECT COALESCE(SUM(estimated_vendors),0) FROM trade_zones WHERE 1 = 1" . $div
        );
        $moved   = (int) Database::value(
            "SELECT COUNT(*) FROM vendors WHERE status = 'relocated'" . $div
        );
        $vacant  = (int) Database::value(
            "SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE 1 = 1" . $div
        );
        $pending = (int) Database::value(
            "SELECT COUNT(*) FROM vendors WHERE status IN ('offered-space','notified')" . $div
        );
        $capacity   = (int) Database::value("SELECT COALESCE(SUM(capacity),0) FROM markets WHERE 1 = 1" . $div);
        $registered = (int) Database::value("SELECT COALESCE(SUM(registered_vendors),0) FROM markets WHERE 1 = 1" . $div);

        return [
            [
                'label' => 'Vendors in register',
                'value' => num($vendors),
                'meta'  => num($toMove) . ' to relocate',
                'tone'  => '',
            ],
            [
                'label' => 'Relocated',
                'value' => num($moved),
                'meta'  => $toMove > 0 ? pct($moved / $toMove * 100) . ' clearance' : '—',
                'tone'  => 'green',
            ],
            [
                'label' => 'Space available',
                'value' => num($vacant),
                'meta'  => $capacity > 0 ? pct($registered / $capacity * 100, 1) . ' of markets full' : '—',
                'tone'  => 'info',
            ],
            [
                'label' => 'Awaiting a decision',
                'value' => num($pending),
                'meta'  => 'offers and notices outstanding',
                'tone'  => $pending > 0 ? 'warn' : '',
            ],
        ];
    }

    /* =================================================================
     * Sections — what this role owns
     * ================================================================= */
    private static function sections(string $role, array $scope): array
    {
        $out = [];

        /* The programme funnel is shared. It is the point of the system, and
           a Mayor needs the citywide shape as much as the Executive Director
           does. Declared once here rather than repeated in each branch. A
           vendor does not get it — a trader has no programme to track, and
           showing them one would be noise. */
        if ($role !== 'vendor') {
            $out[] = [
                'id'     => 'programme',
                'title'  => 'The relocation programme',
                'desc'   => 'Where the surveyed vendors have reached.',
                'render' => 'programme',
            ];
        }

        switch ($role) {

            case 'administrator':
                $out[] = ['id' => 'accounts', 'title' => 'Accounts and access',
                          'desc' => 'Who can sign in, and at what level of authority.', 'render' => 'accounts'];
                $out[] = ['id' => 'divisions', 'title' => 'Progress by division',
                          'desc' => 'Clearance and space across all five divisions.', 'render' => 'divisions'];
                $out[] = ['id' => 'decisions', 'title' => 'Recent decisions',
                          'desc' => 'Every state change, and who took it.', 'render' => 'decisions'];
                $out[] = ['id' => 'system', 'title' => 'System',
                          'desc' => 'Backups, settings and the audit trail.', 'render' => 'system'];
                break;

            case 'executive':
                /* The Executive Director's screen is a summary first: what is
                   moving, what is not, and what is waiting on them. */
                $out[] = ['id' => 'divisions', 'title' => 'Progress by division',
                          'desc' => 'Clearance and space across all five divisions.', 'render' => 'divisions'];
                $out[] = ['id' => 'approvals', 'title' => 'Awaiting your decision',
                          'desc' => 'Recommendations and stall requests put to you.', 'render' => 'approvals'];
                $out[] = ['id' => 'decisions', 'title' => 'Recent decisions',
                          'desc' => 'What has been decided across the Authority.', 'render' => 'decisions'];
                $out[] = ['id' => 'plan', 'title' => 'Against the Strategic Plan',
                          'desc' => 'The FY2025/26–29/30 targets this system reports on.', 'render' => 'plan'];
                break;

            case 'mayor':
                $out[] = ['id' => 'divisions', 'title' => 'Your division',
                          'desc' => 'Sites, clearance and space where you have authority.', 'render' => 'divisions'];
                $out[] = ['id' => 'myzone', 'title' => 'Sites in your division',
                          'desc' => 'Ranked by how badly each needs clearing.', 'render' => 'myzone'];
                $out[] = ['id' => 'managers', 'title' => 'Your managers',
                          'desc' => 'What Planning and Enforcement are doing under you.', 'render' => 'managers'];
                $out[] = ['id' => 'approvals', 'title' => 'Awaiting your decision',
                          'desc' => 'Recommendations and stall requests in your division.', 'render' => 'approvals'];
                break;

            case 'planner':
                /* The Planner's screen leads with the forecast, because that
                   is the tool they asked for. */
                $out[] = ['id' => 'ai', 'title' => 'Forecast',
                          'desc' => 'Projected reach and revenue for every location with space.', 'render' => 'ai'];
                $out[] = ['id' => 'congestion', 'title' => 'Sites by congestion score',
                          'desc' => 'The seven criteria, ranked. This is the clearance order.', 'render' => 'congestion'];
                $out[] = ['id' => 'footfall', 'title' => 'Footfall coverage',
                          'desc' => 'Where footfall has been counted, and where it is only estimated.', 'render' => 'footfall'];
                $out[] = ['id' => 'method', 'title' => 'How a site is scored',
                          'desc' => 'The weights in force, and what each criterion measures.', 'render' => 'method'];
                break;

            case 'revenue':
                /* The Revenue Officer's screen answers how much is licensed and how much is not. */
                $out[] = ['id' => 'licensing', 'title' => 'Licensing position',
                          'desc' => 'Licence classes across the register.', 'render' => 'licensing'];
                $out[] = ['id' => 'unlicensed', 'title' => 'The compliance gap',
                          'desc' => 'Who holds no licence, and where they are.', 'render' => 'unlicensed'];
                break;

            case 'enforcement':
                $out[] = ['id' => 'enforcement', 'title' => 'Your operations',
                          'desc' => 'Notices, ultimatums and clear-ups, newest first.', 'render' => 'enforcement'];
                $out[] = ['id' => 'escalations', 'title' => 'Open work',
                          'desc' => 'Records not yet closed off, and what they are waiting on.', 'render' => 'escalations'];
                $out[] = ['id' => 'notices', 'title' => 'Instructions from above',
                          'desc' => 'What you have been told to carry out, and by whom.', 'render' => 'notices'];
                break;

            case 'vendor':
                /* A trader's three questions: where is there space, how busy
                   is it, and what happened to my application. */
                $out[] = ['id' => 'vendor-markets', 'title' => 'Markets with space',
                          'desc' => 'Every gazetted location with a free pitch.', 'render' => 'vendor_markets'];
                $out[] = ['id' => 'vendor-footfall', 'title' => 'Footfall, to choose well',
                          'desc' => 'How many people pass each market a day.', 'render' => 'vendor_footfall'];
                $out[] = ['id' => 'vendor-applications', 'title' => 'My applications',
                          'desc' => 'Every stall you have asked for, and its decision.', 'render' => 'vendor_applications'];
                break;
        }

        return $out;
    }

    /* =================================================================
     * What is waiting on this officer
     * ================================================================= */
    private static function waiting(array $scope): array
    {
        $out  = [];
        $role = Auth::role();

        if (in_array($role, ['administrator', 'executive', 'mayor'], true)) {
            $n = (int) Database::value("SELECT COUNT(*) FROM recommendations WHERE status = 'proposed'");
            if ($n > 0) {
                $out[] = ['label' => num($n) . ' recommendation' . ($n === 1 ? '' : 's') . ' awaiting approval',
                          'href' => 'recommendations.php?status=proposed', 'tone' => 'info'];
            }
        }

        if (in_array($role, ['administrator', 'executive', 'mayor'], true)) {
            $n = (int) Database::value(
                "SELECT COUNT(*) FROM stall_requests WHERE status IN ('pending','reviewing')"
            );
            if ($n > 0) {
                $out[] = ['label' => num($n) . ' stall request' . ($n === 1 ? '' : 's') . ' to decide',
                          'href' => 'market_map.php', 'tone' => 'warn'];
            }
        }

        if (in_array($role, ['administrator', 'executive', 'mayor', 'planner'], true)) {
            $n = (int) Database::value(
                "SELECT COUNT(*) FROM markets m
                  WHERE m.vacant_spaces > 0
                    AND NOT EXISTS (SELECT 1 FROM footfall_observations o WHERE o.market_id = m.id)"
            );
            if ($n > 0) {
                $out[] = ['label' => num($n) . ' market' . ($n === 1 ? '' : 's')
                                 . ' with space but no counted footfall',
                          'href' => 'locations.php?view=footfall', 'tone' => 'warn'];
            }
        }

        if ($role === 'enforcement') {
            $n = (int) Database::value(
                "SELECT COUNT(*) FROM enforcement_actions WHERE status IN ('planned','in-progress')"
            );
            if ($n > 0) {
                $out[] = ['label' => num($n) . ' enforcement record' . ($n === 1 ? '' : 's')
                                 . ' not yet closed',
                          'href' => 'enforcement.php', 'tone' => 'warn'];
            }
        }

        if ($role === 'vendor') {
            $n = (int) Database::value(
                "SELECT COUNT(*) FROM stall_requests
                  WHERE status = 'pending' AND phone = ?",
                [preg_replace('/[^0-9+]/', '', (string) (Auth::user()['phone'] ?? ''))]
            );
            if ($n > 0) {
                $out[] = ['label' => num($n) . ' application' . ($n === 1 ? '' : 's') . ' awaiting a decision',
                          'href' => 'market_map.php', 'tone' => 'info'];
            }
        }

        if ($out === []) {
            $out[] = ['label' => 'Nothing is waiting on you.', 'href' => 'analytics.php', 'tone' => 'ok'];
        }

        return $out;
    }
}