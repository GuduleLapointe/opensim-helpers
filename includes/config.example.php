<?php
/**
 * config.example.php
 *
 * Helpers configuration
 * Rename this file as "config.php" before editing.
 *
 * @package		magicoli/opensim-helpers
 * @author 		Gudule Lapointe <gudule@speculoos.world>
 * @link 			https://github.com/magicoli/opensim-helpers
 * @license		AGPLv3
 */

/**
 * Grid info
 */
define('OPENSIM_GRID_NAME', 'Your Grid');
define('OPENSIM_LOGIN_URI', 'http://yourgrid.org:8002');
define('OPENSIM_MAIL_SENDER', "no-reply@{$_SERVER['SERVER_NAME']}");
// define('OPENSIM_GRID_LOGO_URL', "http://yougrid.org/logo.png");
/**
 * Routes: the URL of each service or page. index.php, the single entry of the helpers, serves what the web server
 * has no file for. The scripts also answer under any prefix (/helpers/query.php). Pages: @home, @splash (the page
 * the viewer shows before the login).
 */
// define('OPENSIM_ROUTES', ['/search' => 'query.php', '/guide' => 'guide.php', '/welcome' => '@splash']);
// define('OPENSIM_TEMPLATES_DIR', '/path/to/your/templates'); // Twig templates that replace the default ones

// define('OPENSIM_MOTD', 'Welcome to Your Grid, <USERNAME>!'); // message of the day, see motd.php

define('HYPEVENTS_URL', preg_replace(':/$:', '', 'https://2do.directory/events'));

/**
 * Main database.
 * For grids, use Robust database credentials.
 * For standalone simulators, use OpenSim database credentials.
 *
 * Access to OpenSim database is required
 *   - for search including classifieds
 *   - for offline messages processing
 *   - for economy
 * It is not required if only search is needed, without classifieds (e.g. to for
 * a multi-grid search engine). In this case search will only provide results
 * for places, land for sale and events.
 */
define('OPENSIM_DB', true); // Set to false for search only, see above
define('OPENSIM_DB_HOST', 'localhost');
define('OPENSIM_DB_NAME', 'opensim');
define('OPENSIM_DB_USER', 'opensim');
define('OPENSIM_DB_PASS', 'password');
define('SEARCH_TABLE_EVENTS', 'events');

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
define('SEARCH_DB_HOST', OPENSIM_DB_HOST);
define('SEARCH_DB_NAME', OPENSIM_DB_NAME);
define('SEARCH_DB_USER', OPENSIM_DB_USER);
define('SEARCH_DB_PASS', OPENSIM_DB_PASS);

/**
 * Other registrars to forward hosts registrations.
 *
 * This method is not needed as with current OpenSim server (0.9.x) which allow
 * specifying multiple registrars, but could be used in the future to implement
 * peer to peer information sharing.
 *
 * @var array
 */
define('SEARCH_REGISTRARS', [
    // 'http://2do.directory/helpers/register.php',
    // 'http://metaverseink.com/cgi-bin/register.py',
]);

/**
 * Currency database credentials and settings.
 * Needed if currency is enabled on OpenSim server.
 * A dedicated database is recommended, but not mandatory.
 */
define('CURRENCY_DB_HOST', OPENSIM_DB_HOST);
define('CURRENCY_DB_NAME', OPENSIM_DB_NAME);
define('CURRENCY_DB_USER', OPENSIM_DB_USER);
define('CURRENCY_DB_PASS', OPENSIM_DB_PASS);
define('CURRENCY_MONEY_TBL', 'balances');
define('CURRENCY_TRANSACTION_TBL', 'transactions');

/**
 * Money Server settings.
 */
define('CURRENCY_USE_MONEYSERVER', false);
define('CURRENCY_SCRIPT_KEY', '123456789');
define('CURRENCY_RATE', 10); // amount in dollar...
define('CURRENCY_RATE_PER', 1000); // ... for this amount in virtual currency
define('CURRENCY_PROVIDER', null); // NULL, 'podex' or 'gloebit'
define('CURRENCY_HELPER_URL', 'http://yourgrid.org/helpers/');
// if (!defined('CURRENCY_HELPER_PATH')) define('CURRENCY_HELPER_PATH', dirname(__DIR__));

/**
 * Timezone settings. Leave commented if included in a larger project. You
 * dont want to mess up with timezone randomly in the middle of a process!
 */
define('OPENSIM_USE_UTC_TIME', true);
if (OPENSIM_USE_UTC_TIME) {
    date_default_timezone_set('UTC');
}

/**
 * OffLine messages DB credentials.
 *
 * @var [type]
 */
define('OFFLINE_DB_HOST', OPENSIM_DB_HOST);
define('OFFLINE_DB_NAME', OPENSIM_DB_NAME);
define('OFFLINE_DB_USER', OPENSIM_DB_USER);
define('OFFLINE_DB_PASS', OPENSIM_DB_PASS);
define('OFFLINE_MESSAGE_TBL', 'im_offline'); // Same DB as Offline Module V2?

/**
 * Mute list database.
 * mute is now handled by OpenSim server (0.9.x), so we shouldn't need this
 */
// define('MUTE_DB_HOST', OPENSIM_DB_HOST);
// define('MUTE_DB_NAME', OPENSIM_DB_NAME);
// define('MUTE_DB_USER', OPENSIM_DB_USER);
// define('MUTE_DB_PASS', OPENSIM_DB_PASS);
// define('MUTE_LIST_TBL', 'mute_list');

/**
 * Additional custom config
 * (e.g. define custom values for addons here)
 */
// define('MY_CONSTANT_NAME', 'my value');

/**
 * DO NOT MAKE CHANGES BELOW THIS
 * Add your custom values above.
 */
require_once 'databases.php';
require_once 'functions.php';

$currency_addon = dirname(__DIR__) . '/addons/' . CURRENCY_PROVIDER . '.php';
if (file_exists($currency_addon)) {
    require_once $currency_addon;
}
