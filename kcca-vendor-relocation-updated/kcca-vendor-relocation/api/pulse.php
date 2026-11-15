<?php
/**
 * GET /api/pulse.php
 *
 * A tiny change signal for the live-update script. It returns a token that
 * changes whenever data changes anywhere in the system (any recorded action,
 * or any status change on the main registers), plus the counts shown as
 * badges in the navigation rail. It carries no records, only a fingerprint.
 *
 * Written without PHP 8 features so it runs on older PHP as well.
 */

require_once dirname(__FILE__) . '/bootstrap.php';

if (!Auth::check()) {
    jsonOut(array('ok' => false, 'error' => 'Not signed in'), 401);
}

/* Count and a fingerprint of each row's status, so a status change (an offer
   accepted, a notice served) alters the token even when no row is added. */
$parts = array(
    'audit'        => 'SELECT COALESCE(MAX(id),0) FROM audit_log',
    'allocations'  => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM allocations',
    'vendors'      => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM vendors',
    'spaces'       => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM market_spaces',
    'zones'        => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM trade_zones',
    'enforcement'  => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM enforcement_actions',
    'recs'         => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM recommendations',
    'requests'     => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM stall_requests',
    'notices'      => 'SELECT COUNT(*) FROM notices',
    'complaints'   => 'SELECT CONCAT(COUNT(*),":",COALESCE(SUM(CRC32(status)),0)) FROM trader_complaints',
);

$seen = array();
foreach ($parts as $key => $sql) {
    try {
        $seen[$key] = (string) Database::value($sql);
    } catch (Exception $e) {
        $seen[$key] = 'n/a';
    } catch (Throwable $e) {
        $seen[$key] = 'n/a';
    }
}

/* The same counts the navigation rail shows as badges. */
$pillSql = array(
    'enforcement'     => "SELECT COUNT(*) FROM enforcement_actions WHERE status IN ('planned','in-progress')",
    'recommendations' => "SELECT COUNT(*) FROM recommendations WHERE status = 'proposed'",
    'allocations'     => "SELECT COUNT(*) FROM allocations WHERE status IN ('offered','proposed')",
    'relocation'      => "SELECT COUNT(*) FROM vendors WHERE status IN ('surveyed','notified') AND current_zone_id IS NOT NULL",
);
$pills = array();
foreach ($pillSql as $key => $sql) {
    try {
        $pills[$key] = (int) Database::value($sql);
    } catch (Exception $e) {
        $pills[$key] = 0;
    } catch (Throwable $e) {
        $pills[$key] = 0;
    }
}

jsonOut(array(
    'ok'    => true,
    'token' => md5(json_encode($seen)),
    'pills' => $pills,
    'at'    => date('H:i:s'),
));
