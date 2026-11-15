<?php
/**
 * What colleagues have just changed.
 *
 * Every officer works against the same database, so a change one of them
 * saves is in the register the moment it is saved. What they would not know,
 * without reloading every page, is THAT something changed. This turns the
 * audit trail into a feed every officer can see: the bell in the top bar, a
 * list of recent changes, and a quiet alert when a colleague makes one.
 *
 * What it shows, and to whom
 *  - Only the staff roles. A trader or a market chairman does not see how the
 *    Authority's officers are working.
 *  - Only changes to the shared record (registering a trader, making or
 *    answering an offer, approving a plan, editing an account). Signing in,
 *    downloading, reading a notice and changing one's own colours are not
 *    news to anyone else and are left out.
 *  - The detail line only to roles that already hold the Audit permission,
 *    because it can name a trader. Everybody else sees who did what kind of
 *    thing, and nothing more.
 *
 * It reads the audit trail and writes nothing.
 */

declare(strict_types=1);

final class ChangeFeed
{
    /** action => what the person did, in the past tense, after their name */
    private const LABELS = [
        'register-vendor'          => 'registered a trader',
        'update-vendor'            => 'updated a trader’s record',
        'delete-vendor'            => 'removed a trader',
        'offer-space'              => 'offered a pitch',
        'accept-allocation'        => 'recorded an accepted offer',
        'decline-allocation'       => 'recorded a declined offer',
        'withdraw-allocation'      => 'withdrew an allocation',
        'occupy-allocation'        => 'confirmed a trader in their pitch',
        'offer-expired'            => 'let an unanswered offer lapse',
        'recommendations-expired'  => 'closed undecided proposals',
        'generate-recommendations' => 'generated placement recommendations',
        'approve-recommendation'   => 'approved a recommendation',
        'reject-recommendation'    => 'rejected a recommendation',
        'ai-plan-submit'           => 'submitted an AI plan for approval',
        'create-market'            => 'added a market',
        'update-market'            => 'updated a market',
        'update-space'             => 'updated a pitch',
        'record-footfall'          => 'recorded a footfall count',
        'delete-footfall'          => 'removed a footfall count',
        'save-restriction'         => 'set a land-use restriction',
        'delete-restriction'       => 'lifted a land-use restriction',
        'save-attire'              => 'updated the trading attire',
        'log-enforcement'          => 'logged an enforcement action',
        'escalate-enforcement'     => 'escalated an enforcement case',
        'complete-enforcement'     => 'closed an enforcement case',
        'raise-complaint'          => 'raised a complaint',
        'forward-complaint'        => 'forwarded a complaint',
        'decline-complaint'        => 'declined a complaint',
        'confirm-support'          => 'confirmed a trader’s support request',
        'request-stall'            => 'received a stall request',
        'review-stall-request'     => 'reviewed a stall request',
        'approve-stall-request'    => 'approved a stall request',
        'reject-stall-request'     => 'rejected a stall request',
        'cancel-stall-request'     => 'cancelled a stall request',
        'publish-notice'           => 'published a notice',
        'send-notice'              => 'sent a notice',
        'create-account'           => 'created an account',
        'update-account'           => 'changed an account',
        'deactivate-account'       => 'deactivated an account',
        'reset-password'           => 'issued a temporary password',
        'set-password'             => 'set an account’s password',
        'update-own-account'       => 'updated their own details',
        'change-own-password'      => 'changed their own password',
        'delete-own-account'       => 'deleted their own account',
        'update-settings'          => 'changed system settings',
        'create-backup'            => 'made a backup',
        'delete-backup'            => 'deleted a backup',
    ];

    /** entity => the screen that shows it, so an event can link to it */
    private const SCREEN = [
        'vendors' => ['vendors', 'vendors.php?id='], 'allocations' => ['allocations', 'allocations.php'],
        'recommendations' => ['recommendations', 'recommendations.php'], 'markets' => ['locations', 'locations.php'],
        'users' => ['accounts', 'users.php'],
    ];

    /** Staff roles only. */
    public static function allowed(): bool
    {
        return Auth::check() && !in_array(Auth::role(), ['vendor', 'chairman'], true);
    }

    public static function latestId(): int
    {
        return (int) Database::value('SELECT COALESCE(MAX(id), 0) FROM audit_log');
    }

    /**
     * Changes newer than $afterId (or the latest few when it is 0), newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function events(int $afterId, int $limit, bool $withDetail): array
    {
        $actions = array_keys(self::LABELS);
        $marks   = implode(',', array_fill(0, count($actions), '?'));

        $rows = Database::all(
            "SELECT id, user_id, actor, action, entity, entity_id, detail, created_at
               FROM audit_log
              WHERE id > ? AND action IN ($marks)
              ORDER BY id DESC
              LIMIT " . max(1, min(60, $limit)),
            array_merge([max(0, $afterId)], $actions)
        );

        $me  = Auth::id();
        $out = [];
        foreach ($rows as $r) {
            $href = null;
            $map  = self::SCREEN[(string) $r['entity']] ?? null;
            if ($map !== null && Auth::screenAllows($map[0])) {
                $href = $map[1] . (str_ends_with($map[1], '=') ? (int) $r['entity_id'] : '');
            }

            $out[] = [
                'id'     => (int) $r['id'],
                'who'    => (string) $r['actor'],
                'did'    => self::LABELS[$r['action']],
                'detail' => $withDetail ? (string) ($r['detail'] ?? '') : '',
                'at'     => date('c', strtotime((string) $r['created_at'])),
                'mine'   => $r['user_id'] !== null && (int) $r['user_id'] === $me,
                'system' => $r['user_id'] === null,
                'href'   => $href,
            ];
        }
        return $out;
    }
}
