<?php
/**
 * Market Register — consolidated into Trading Locations.
 *
 * This page was a shorter list of the same markets that locations.php shows,
 * with the same division join and none of the pitch, footfall or land-use
 * detail. Two screens for one register is how a system starts to look
 * cluttered, so the register now lives in one place and this redirects.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

header('Location: locations.php');
exit;