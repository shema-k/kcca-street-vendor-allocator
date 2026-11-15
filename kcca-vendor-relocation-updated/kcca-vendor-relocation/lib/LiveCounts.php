<?php
/**
 * KCCA VRSP — the live counts.
 *
 * One definition, two callers. The endpoint api/live_counts.php serves it to
 * the poller, and the pages emit the same figure in a data attribute when
 * they render. If the two were computed separately they would drift the
 * moment either changed, and a drift here shows as "1 new" on every poll or
 * as nothing ever arriving — which is exactly the failure that makes a live
 * indicator worse than none.
 *
 * Every count is scoped to what the reader may already see, using the same
 * services the pages use. A signed-in vendor sees the complaints they have
 * open. A chairman sees what is queued on them. An officer sees what has
 * escalated to the Authority. Nothing here exposes a number the reader could
 * not have reached from a page they already hold.
 */

declare(strict_types=1);

final class LiveCounts
{
    /**
     * What is queued on this reader.
     *
     * @return array<string,int>
     */
    public static function for(?array $user = null): array
    {
        /* No signature at all — a cron job, a CLI run, a page that calls this
           before a login — is nobody's queue, so it is empty rather than an
           error. Polling an unauthenticated screen is otherwise a way to
           discover how many complaints exist. */
        if ($user === null) {
            if (!Auth::check()) {
                return [];
            }
            $user = Auth::user();
        }

        $role = (string) ($user['role'] ?? 'guest');

        if ($role === 'vendor') {
            $vendorId = (int) ($user['vendor_id'] ?? 0);
            return [
                'complaints_for_me' => $vendorId > 0
                    ? (int) Database::value(
                        "SELECT COUNT(*) FROM trader_complaints
                          WHERE vendor_id = ? AND status IN ('with-chairman','forwarded','with-authority')",
                        [$vendorId]
                    )
                    : 0,
            ];
        }

        if ($role === 'chairman') {
            $chairmanId = (int) ($user['chairman_id'] ?? 0);
            if ($chairmanId <= 0) {
                return [];
            }
            $c = Complaints::counts($chairmanId);
            return [
                'complaints_for_me'    => $c['with_chairman'] + $c['awaiting_reply'],
                'notices_unread'       => (int) Database::value(
                    'SELECT COUNT(*) FROM chairman_notice_receipts
                      WHERE chairman_id = ? AND read_at IS NULL',
                    [$chairmanId]
                ),
                'notices_unpublished'  => (int) Database::value(
                    'SELECT COUNT(*) FROM chairman_notice_receipts
                      WHERE chairman_id = ? AND forwarded_at IS NULL',
                    [$chairmanId]
                ),
                'support_outstanding'  => TraderSupport::counts((int) ($user['my_market_id'] ?? 0))['outstanding'],
                'stalls_to_review'     => (int) Database::value(
                    "SELECT COUNT(*) FROM stall_requests
                      WHERE market_id = ? AND status IN ('pending','reviewing')",
                    [(int) ($user['my_market_id'] ?? 0)]
                ),
            ];
        }

        /* The grant is read from the permissions this role holds, not from the
           session. Auth::can() resolves against whoever is signed in, so a
           method handed an explicit user would silently answer for the wrong
           one — which made every officer's queue come back empty whenever
           this was called outside a request. */
        $granted = Auth::permissionsFor($role);
        $canReport   = in_array('*', $granted, true) || in_array('report', $granted, true);
        $canAllocate = $canReport || in_array('allocate', $granted, true);

        if ($canReport) {
            $c = Complaints::counts();
            $n = Notices::pendingCounts();
            $sup = TraderSupport::counts();
            return [
                'complaints_for_me'    => $c['awaiting_reply'],
                'notices_unread'       => $n['unread'],
                'notices_unpublished'  => $n['unpublished'],
                'support_outstanding'  => $sup['outstanding'],
                'stalls_to_review'     => (int) Database::value(
                    "SELECT COUNT(*) FROM stall_requests
                      WHERE status IN ('pending','reviewing')"
                ),
            ];
        }

        if ($canAllocate) {
            return [
                'stalls_to_review' => (int) Database::value(
                    "SELECT COUNT(*) FROM stall_requests
                      WHERE status IN ('pending','reviewing')"
                ),
            ];
        }

        return [];
    }

    /** The figure the page renders, so the poller has something to compare to. */
    public static function total(): int
    {
        return (int) array_sum(self::for());
    }
}