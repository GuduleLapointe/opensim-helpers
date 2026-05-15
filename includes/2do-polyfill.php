<?php
/**
 * 2do-polyfill for config.php
 *
 * Define constants expected by helper scripts, reading them from Laravel settings,
 * accessible through settings($key) defined in app/Helpers/settings.php.
 *
 * This file must be included from within the Laravel app context.
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link        https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

if (!defined("APP_NAME")) {
    // Not called from Laravel
    http_response_code(503);
    die("Not properly configured");
}

use App\Settings\HelpersSettings;

require_once "functions.php";

define("OPENSIM_USE_UTC_TIME", settings("helpers.use_utc_time", true));

$credentials = settings("helpers.credentials");

// --- Search Settings (grid-independant services) ---

/**
 * Search database credentials and settings.
 * Needed if you enable search in OpenSim server.
 *
 * A dedicated database is:
 *   - strongly recommended if the search engine is shared by several grids
 *   - recommended and more efficient for large and/or hypergrid-enabled grids
 *   - optional for closed grids and standalone simulators
 * These are recommendations, the Robust database can safely be used instead.
 */

$search_db = $credentials["search_db"] ?? [];

define("SEARCH_DB_HOST", $search_db["hostname"] ?? null);
define("SEARCH_DB_NAME", $search_db["prefix"] ?? null);
define("SEARCH_DB_USER", $search_db["user"] ?? null);
define("SEARCH_DB_PASS", $search_db["password"] ?? null);
define("SEARCH_TABLE_EVENTS", "events"); // TODO: expose as setting if needed

/**
 * Other registrars to forward hosts registrations.
 * Deprecated since OpenSim 0.9.x, use DATA_SRV_* instead in OpenSim.ini
 */
define("SEARCH_REGISTRARS", []); // Deprecated

define(
    "HYPEVENTS_URL",
    preg_replace(
        ':/$:',
        "",
        settings("helpers.events_url", "https://2do.directory/events"),
    ),
);

// --- Grid settings (Robust/Standalone OpenSim server) ---

define("OPENSIM_GRID_NAME", settings("helpers.grid_name"));
define("OPENSIM_LOGIN_URI", settings("helpers.login_uri"));
define("OPENSIM_MAIL_SENDER", settings("helpers.mail_sender"));
// define('OPENSIM_GRID_LOGO_URL', settings("helpers.grid_logo_url"));

$robust_db = $credentials["robust_db"] ?? [];
$opensim_db = $credentials["opensim_db"] ?? $robust_db;
$offline_db = $credentials["offline_db"] ?? $robust_db;
$currency_db = $credentials["currency_db"] ?? $robust_db;

/**
 * Main database.
 * For grids, use Robust database credentials.
 * For standalone simulators, use OpenSim database credentials.
 *
 * Access to grid/simulator database is required
 *   - to enable classifieds in search
 *   - to enable offline messages forwarding
 *   - to enable economy
 *
 * It is not necessary for a multi-grid search engine).
 * In this case search will only provide results for places, land
 * for sale and events.
 */

// --- Main/Robust DB ---
define("ROBUST_DB", true); // TODO: expose as setting if needed
define("ROBUST_DB_HOST", $robust_db["hostname"] ?? null);
define("ROBUST_DB_NAME", $robust_db["prefix"] ?? null);
define("ROBUST_DB_USER", $robust_db["user"] ?? null);
define("ROBUST_DB_PASS", $robust_db["password"] ?? null);

// --- Standalone/Region DB ---
define("OPENSIM_DB", true); // TODO: expose as setting if needed
define("OPENSIM_DB_HOST", $opensim_db["hostname"] ?? null);
define("OPENSIM_DB_NAME", $opensim_db["prefix"] ?? null);
define("OPENSIM_DB_USER", $opensim_db["user"] ?? null);
define("OPENSIM_DB_PASS", $opensim_db["password"] ?? null);

// --- Currency DB ---
define("CURRENCY_DB_HOST", $currency_db["hostname"] ?? null);
define("CURRENCY_DB_NAME", $currency_db["prefix"] ?? null);
define("CURRENCY_DB_USER", $currency_db["user"] ?? null);
define("CURRENCY_DB_PASS", $currency_db["password"] ?? null);
define("CURRENCY_MONEY_TBL", "balances"); // TODO: expose as setting if needed
define("CURRENCY_TRANSACTION_TBL", "transactions"); // TODO: expose as setting if needed

// --- Money Server ---
define(
    "CURRENCY_USE_MONEYSERVER",
    settings("helpers.currency_use_moneyserver", false),
);
define("CURRENCY_SCRIPT_KEY", settings("helpers.currency_script_key", null));
define("CURRENCY_RATE", settings("helpers.currency_rate", null));
define("CURRENCY_RATE_PER", settings("helpers.currency_rate_per", null));
define("CURRENCY_PROVIDER", settings("helpers.currency_provider", null));
define("CURRENCY_HELPER_URL", settings("helpers.currency_helper_url", null));

/**
 * OffLine messages forwarding
 */
define("OFFLINE_DB_HOST", $offline_db["hostname"] ?? null);
define("OFFLINE_DB_NAME", $offline_db["prefix"] ?? null);
define("OFFLINE_DB_USER", $offline_db["user"] ?? null);
define("OFFLINE_DB_PASS", $offline_db["password"] ?? null);
define("OFFLINE_MESSAGE_TBL", "im_offline"); // TODO: expose as setting if needed

/**
 * Mute list database.
 * Deprecated since OpenSim server >= 0.9.x)
 */
// define('MUTE_DB_HOST', ...);
// define('MUTE_DB_NAME', ...);
// define('MUTE_DB_USER', ...);
// define('MUTE_DB_PASS', ...);
// define('MUTE_LIST_TBL', ...);

// --- Custom config ---
// define('MY_CONSTANT_NAME', settings('helpers.my_constant_name'));

/**
 * DO NOT MAKE CHANGES BELOW THIS
 */
if (OPENSIM_USE_UTC_TIME) {
    date_default_timezone_set("UTC");
}

require_once "databases.php";

$currency_addon = dirname(__DIR__) . "/addons/" . CURRENCY_PROVIDER . ".php";
if (file_exists($currency_addon)) {
    require_once $currency_addon;
}
