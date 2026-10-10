<?php
/**
 * KCCA VRSP — Application configuration.
 *
 * Copy config.local.php.example over this file (or set the environment
 * variables) to point at your own MariaDB / MySQL server.
 */

declare(strict_types=1);

define('APP_NAME',    'KCCA Vendor Relocation & Space Allocation System');
define('APP_SHORT',   'KCCA VRSP');
define('APP_VERSION', '1.0.0');
define('APP_BASENAME', 'kcca-vendor-relocation');

/* ---------------------------------------------------------------------
 * Database
 * ------------------------------------------------------------------ */
/* The bundled MariaDB lives in ~/.kcca-vrsp/db, outside the project, so it
   is not committed and is not wiped when /tmp is cleared. db.sh starts it.
   Point these at your own server with the environment variables if you
   prefer:  KCCA_DB_HOST=localhost KCCA_DB_PORT=3306 ./run.sh          */
define('DB_HOST',    getenv('KCCA_DB_HOST') ?: '127.0.0.1');
define('DB_PORT',    getenv('KCCA_DB_PORT') ?: '3307');
define('DB_SOCKET',  getenv('KCCA_DB_SOCKET') ?: getenv('HOME') . '/.kcca-vrsp/db/run/mysqld.sock');
define('DB_NAME', getenv('KCCA_DB_NAME') ?: 'kcca_vendor_relocation');
define('DB_USER', getenv('KCCA_DB_USER') ?: 'kcca');
define('DB_PASS', getenv('KCCA_DB_PASS') ?: 'kcca_dev_2026');

/* ---------------------------------------------------------------------
 * Session / security
 * ------------------------------------------------------------------ */
define('SESSION_NAME', 'KCCA_VRSP_SESS');
define('CSRF_TOKEN_KEY', 'kcca_vrsp_csrf');

/* ---------------------------------------------------------------------
 * Operational settings of the allocation engine
 * These are policy settings, not measurements: a deployment may change
 * them. Weights are relative and are normalised before scoring.
 * ------------------------------------------------------------------ */
const RELOCATION_WEIGHTS = [
    'proximity'     => 0.24,  // how far the vendor must travel from their old spot
    'trade_fit'     => 0.22,  // does the market section suit the goods being sold
    'equity'        => 0.20,  // vulnerability: single parents, disability, low capital
    'viability'     => 0.15,  // is the market filling up; preserve headroom elsewhere
    'affordability' => 0.11,  // rent against the vendor's turnover
    'facilities'    => 0.08,  // water, power, toilets, storage, cold chain
];

const ZONE_SCORING_WEIGHTS = [
    'obstruction'    => 0.26,  // how much of the footpath / carriageway is taken
    'vendor_load'    => 0.20,  // how many traders are affected by clearing
    'traffic_impact' => 0.16,  // effect on traffic flow and emergency access
    'duration'       => 0.12,  // how long the unlawful occupation has persisted
    'strategic'      => 0.12,  // presence on a priority / strategic corridor
    'public_access'  => 0.08,  // near a hospital, school, civic or worship asset
    'complaints'     => 0.06,  // public complaints in the last 30 days
];

/* ---------------------------------------------------------------------
 * Paths
 * ------------------------------------------------------------------ */
define('ROOT_PATH',    dirname(__DIR__));
define('ASSETS_URL',   'assets');
define('APP_URL',      '');           // set if hosted in a sub-directory

/**
 * Relative path from the current page back to the application root.
 *
 * Pages live at different depths (pages/, auth/, and the root itself), so
 * a script that posts to "api/vendor_save.php" would resolve against
 * whichever page is open. Prepending APP_REL makes those paths correct
 * everywhere without hard-coding "../".
 */
if (!defined('APP_REL')) {
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $depth     = trim($scriptDir, '/') === '' ? 0 : count(explode('/', trim($scriptDir, '/')));
    define('APP_REL', str_repeat('../', $depth));
}

date_default_timezone_set('Africa/Kampala');