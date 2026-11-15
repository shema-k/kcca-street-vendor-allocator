<?php
/**
 * Offers that lapse.
 *
 * Every offer carries an expiry date (fourteen days after it is made) and the
 * allocation status list has "expired", but nothing ever moved an offer to it.
 * A trader who never answered kept the pitch reserved for ever, the market
 * showed it as taken, and the next trader in the queue could not have it.
 * This closes the loop: an offer still unanswered after its date is expired,
 * the pitch goes back on the market, the trader returns to the waiting list,
 * and the change is written to the audit trail as the system's own act.
 *
 * It runs lazily, at most once every ten minutes, from whichever page is
 * opened next, so the system needs no scheduler to stay correct. A cron job
 * is not required; calling OfferSweeper::sweep() from one is harmless.
 */

declare(strict_types=1);

final class OfferSweeper
{
    private const EVERY_SECONDS = 600;

    /** Sweep if it is due. Cheap when it is not: one file-time check. */
    public static function sweepIfDue(): void
    {
        $stamp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'kcca_offer_sweep.stamp';
        $last  = @filemtime($stamp);
        if ($last !== false && (time() - $last) < self::EVERY_SECONDS) {
            return;
        }
        @touch($stamp);              // claim the slot first, so two requests do not both sweep

        try {
            self::sweep();
        } catch (Throwable $e) {
            error_log('offer sweep failed: ' . $e->getMessage());
        }
    }

    /**
     * Expire what has lapsed.
     *
     * @return array{offers:int,recommendations:int}
     */
    public static function sweep(): array
    {
        $offers = 0;

        $lapsed = Database::all(
            "SELECT id, vendor_id, market_id, market_space_id, reference, offer_expires
               FROM allocations
              WHERE status = 'offered' AND offer_expires IS NOT NULL AND offer_expires < CURDATE()
              LIMIT 200"
        );

        foreach ($lapsed as $a) {
            $done = Database::transaction(static function () use ($a): bool {
                /* The status test in the WHERE makes this safe if an officer
                   or the trader acts on the same offer at the same moment. */
                $changed = Database::run(
                    "UPDATE allocations
                        SET status = 'expired', decided_at = NOW(),
                            reasoning = CONCAT(COALESCE(reasoning, ''), '\nExpired: the offer lapsed on ', ?, ' without an answer.')
                      WHERE id = ? AND status = 'offered'",
                    [(string) $a['offer_expires'], (int) $a['id']]
                )->rowCount();

                if ($changed === 0) {
                    return false;
                }

                if ($a['market_space_id'] !== null) {
                    Database::run("UPDATE market_spaces SET status = 'vacant' WHERE id = ?", [(int) $a['market_space_id']]);
                }
                Database::run('UPDATE markets SET vacant_spaces = vacant_spaces + 1 WHERE id = ?', [(int) $a['market_id']]);
                Database::run("UPDATE vendors SET status = 'notified' WHERE id = ? AND status = 'offered-space'", [(int) $a['vendor_id']]);
                clearVendorChairman((int) $a['vendor_id']);
                return true;
            });

            if ($done) {
                $offers++;
                self::systemLog('offer-expired', 'allocations', (int) $a['id'],
                    $a['reference'] . ' lapsed on ' . $a['offer_expires'] . '; the pitch is back on the market.');
            }
        }

        /* Proposals nobody decided in time also lapse, so the approval queue
           shows what is live rather than everything that was ever suggested. */
        $recs = Database::run(
            "UPDATE recommendations SET status = 'expired'
              WHERE status = 'proposed' AND expires_on IS NOT NULL AND expires_on < CURDATE()"
        )->rowCount();
        if ($recs > 0) {
            self::systemLog('recommendations-expired', 'recommendations', null,
                $recs . ' undecided proposal(s) passed their date and were closed.');
        }

        return ['offers' => $offers, 'recommendations' => $recs];
    }

    /** An audit row for an act the system itself made, not a signed-in person. */
    private static function systemLog(string $action, string $entity, ?int $entityId, string $detail): void
    {
        try {
            Database::insert('audit_log', [
                'user_id' => null, 'actor' => 'System (automatic)', 'action' => $action,
                'entity' => $entity, 'entity_id' => $entityId, 'detail' => $detail, 'ip_address' => null,
            ]);
        } catch (Throwable $e) {
            /* auditing never takes the system down */
        }
    }
}
