<?php
/**
 * What every script of the helpers loads first: their libraries, their settings (the config of the grid, or
 * includes/config.php which only defines constants), then what they need to run, the database and the functions.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

if (!defined('OPENSIM_ENGINE')) {
    define('OPENSIM_ENGINE', true);
}

// The libraries first: a config of the former kind, which loads the database and the functions itself, needs them
require_once dirname(__DIR__) . '/vendor/autoload.php';

// The settings: constants in includes/config.php (the historic way), else the config of the grid in /etc/opensim/grids
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once dirname(__DIR__) . '/classes/class-grid-config.php';
    $problem = OpenSim_Helpers_GridConfig::load();
    // Without settings the helpers cannot know the grid: say it, to the log and to the caller
    if ($problem !== null) {
        error_log("opensim-helpers: $problem");
        http_response_code(503);
        die('Not properly configured');
    }
    unset($problem);
}

// What a config can leave out (a constant it defines wins)
$defaults = [
    'OPENSIM_GRID_NAME' => 'OpenSimulator grid',
    'OPENSIM_MAIL_SENDER' => 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
    'OPENSIM_USE_UTC_TIME' => true,
    'OPENSIM_DB' => defined('OPENSIM_DB_HOST'),
    'HYPEVENTS_URL' => 'https://2do.directory/events',
    'SEARCH_TABLE_EVENTS' => 'events',
    'CURRENCY_PROVIDER' => null,
    'CURRENCY_USE_MONEYSERVER' => false,
    'CURRENCY_SCRIPT_KEY' => null,
    'CURRENCY_HELPER_URL' => null,
    'CURRENCY_MONEY_TBL' => 'balances',
    'CURRENCY_TRANSACTION_TBL' => 'transactions',
    'OFFLINE_MESSAGE_TBL' => 'im_offline',
];
foreach ($defaults as $name => $value) {
    defined($name) || define($name, $value);
}

// A service uses the main database unless the config gives it another
foreach (['SEARCH', 'CURRENCY', 'OFFLINE'] as $service) {
    foreach (['HOST', 'NAME', 'USER', 'PASS'] as $key) {
        if (!defined("{$service}_DB_$key") && defined("OPENSIM_DB_$key")) {
            define("{$service}_DB_$key", constant("OPENSIM_DB_$key"));
        }
    }
}

// The one thing the helpers cannot guess: at least one database
$has_database = false;
foreach (['OPENSIM', 'SEARCH', 'CURRENCY', 'OFFLINE'] as $service) {
    $has_database =
        $has_database ||
        (defined("{$service}_DB_HOST") &&
            constant("{$service}_DB_HOST") !== null &&
            defined("{$service}_DB_NAME") &&
            constant("{$service}_DB_NAME") !== null &&
            defined("{$service}_DB_USER") &&
            defined("{$service}_DB_PASS"));
}
if (!$has_database) {
    error_log('opensim-helpers: no database in the config, define at least OPENSIM_DB_HOST, _NAME, _USER and _PASS');
    http_response_code(503);
    die('Not properly configured');
}
unset($defaults, $name, $value, $service, $key, $has_database);

if (OPENSIM_USE_UTC_TIME) {
    date_default_timezone_set('UTC');
}

require_once __DIR__ . '/databases.php';
require_once __DIR__ . '/functions.php';

$currency_addon = dirname(__DIR__) . '/addons/' . CURRENCY_PROVIDER . '.php';
if (is_file($currency_addon)) {
    require_once $currency_addon;
}
unset($currency_addon);
