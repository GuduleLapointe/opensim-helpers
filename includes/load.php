<?php
/**
 * What the helpers need once their settings are defined: the autoloader of their libraries (the engine, Twig...), the
 * database and the functions. The last line of includes/config.php loads it (see config.example.php); a config made by
 * something else (the OpenSim kit does) can do the same, or load these files itself.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

if (!defined('OPENSIM_ENGINE')) {
    define('OPENSIM_ENGINE', true);
}
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/databases.php';
require_once __DIR__ . '/functions.php';

$currency_addon = dirname(__DIR__) . '/addons/' . CURRENCY_PROVIDER . '.php';
if (is_file($currency_addon)) {
    require_once $currency_addon;
}
unset($currency_addon);
