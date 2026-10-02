<?php
/**
 * config.php for helpers installed with the OpenSim kit.
 *
 * Nothing to edit here: the settings of the grid are read from the kit (`opensim.conf`, the Robust
 * config of the grid, its `helpers.ini`) by the engine, and defined as the constants the scripts
 * expect. The package installs it as `includes/config.php`; an installation made by hand keeps its
 * own `config.php` (see `config.example.php`).
 *
 * The grid is the one named by OPENSIM_GRID (set by the virtual host of the grid), else the only
 * grid of the profile. A constant defined before this file wins over the settings.
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link        https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

if (!defined('OPENSIM_ENGINE')) {
    define('OPENSIM_ENGINE', true);
}
require_once dirname(__DIR__) . '/vendor/autoload.php';

$kit_settings = OpenSim_Kit::settings();
if ($kit_settings === null) {
    error_log('opensim-helpers: no grid found in opensim.conf, give its nick in OPENSIM_GRID');
    http_response_code(503);
    die('Not properly configured');
}
OpenSim_Kit::define_constants($kit_settings);
unset($kit_settings);

if (OPENSIM_USE_UTC_TIME) {
    date_default_timezone_set('UTC');
}

require_once __DIR__ . '/databases.php';
require_once __DIR__ . '/functions.php';

$currency_addon = dirname(__DIR__) . '/addons/' . CURRENCY_PROVIDER . '.php';
if (file_exists($currency_addon)) {
    require_once $currency_addon;
}
