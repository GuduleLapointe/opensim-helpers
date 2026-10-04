<?php
/**
 * index.php: the single entry of the helpers, see OpenSim_Helpers_Router.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/classes/class-router.php';

OpenSim_Helpers_Router::run();
