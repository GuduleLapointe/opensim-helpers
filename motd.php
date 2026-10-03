<?php
/**
 * motd.php
 *
 * The message of the day, as plain text: what Robust shows when a user logs in, if `MessageUrl` of its
 * `[LoginService]` is this script (the OpenSim kit sets it). Robust reads it when it starts, and uses its
 * `WelcomeMessage` when the script does not answer.
 *
 * The text is `motd` of `[Helpers]` in the `helpers.ini` of the grid, `\n` for a new line, `<USERNAME>` for
 * the name of the avatar.
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link        https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

define('OPENSIM_ENGINE', true);
require_once __DIR__ . '/vendor/autoload.php';

$settings = OpenSim_Kit::settings();
if ($settings === null) {
    http_response_code(503);
    die('Not properly configured');
}

header('Content-Type: text/plain; charset=utf-8');
echo $settings['options']['motd'] ?? sprintf('Welcome to %s, <USERNAME>!', $settings['grid_name']);
