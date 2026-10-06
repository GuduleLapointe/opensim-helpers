<?php
/**
 * index.php: the single entry of the helpers, see OpenSim_Helpers_Router.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/classes/class-router.php';

$script = OpenSim_Helpers_Router::run();
if ($script !== null) {
    // Here, in the global scope: the scripts keep their state in global variables
    require $script;
}
