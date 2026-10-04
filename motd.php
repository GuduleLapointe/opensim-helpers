<?php
/**
 * motd.php
 *
 * The message of the day, as plain text: what Robust shows when a user logs in, if `MessageUrl` of its
 * `[LoginService]` is this script. Robust reads it when it starts, and uses its `WelcomeMessage` when the
 * script does not answer.
 *
 * The text is OPENSIM_MOTD (see config.example.php), `\n` for a new line, `<USERNAME>` for the name of the
 * avatar. Without one, a welcome to the grid.
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link        https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

require_once 'includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo defined('OPENSIM_MOTD') && OPENSIM_MOTD ? OPENSIM_MOTD : sprintf('Welcome to %s, <USERNAME>!', OPENSIM_GRID_NAME);
