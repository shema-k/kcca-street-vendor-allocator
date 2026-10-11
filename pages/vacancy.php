<?php
/**
 * Vacant Space Finder — consolidated into Trading Locations.
 *
 * A filtered view of the same markets, reachable from the register with
 * one click. The trader-facing version of the same question is the stall
 * map, which shows the actual free pitches by position.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

header('Location: locations.php');
exit;