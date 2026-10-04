<?php
/**
 * What every script of the helpers loads first: their libraries, their settings (includes/config.php, which only defines
 * constants), then what they need to run, the database and the functions.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

if (!defined('OPENSIM_ENGINE')) {
    define('OPENSIM_ENGINE', true);
}

// The libraries first: a config of the former kind, which loads the database and the functions itself, needs them
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Without settings the helpers cannot know the grid: say it, to the log and to the caller
if (!is_file(__DIR__ . '/config.php')) {
    error_log('opensim-helpers: includes/config.php is missing, copy includes/config.example.php and edit it');
    http_response_code(503);
    die('Not properly configured');
}
require_once __DIR__ . '/config.php';

// What a config can leave out
defined('OPENSIM_USE_UTC_TIME') || define('OPENSIM_USE_UTC_TIME', true);
defined('CURRENCY_PROVIDER') || define('CURRENCY_PROVIDER', null);
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
