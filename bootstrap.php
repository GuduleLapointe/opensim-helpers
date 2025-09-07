<?php
/**
 * Helpers Bootstrap
 * 
 * Handles direct requests to helper scripts without WordPress.
 * This loads the engine and provides the API endpoints.
 */

// Define helper mode
define('OPENSIM_HELPERS', true);
if(!defined('OPENSIM_HELPERS_PATH')) {
    // Define the path to helpers directory
    define('OPENSIM_HELPERS_PATH', __DIR__);
}

if(!defined('OPENSIM_CONFIG_DIR')) {
    // Define the path to config directory
    define('OPENSIM_CONFIG_DIR', OPENSIM_HELPERS_PATH . '/config');
}

require_once OPENSIM_HELPERS_PATH . '/engine/bootstrap.php';
set_helpers_locale();

// Helpers autoloader for optional classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'OpenSim_Helpers_') === 0 || strpos($class, 'Helpers_') === 0) {
        $class_name = str_replace('OpenSim_', '', $class);
        $file = OPENSIM_HELPERS_PATH . '/classes/class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            error_log('[ERROR] Helper class '. $class . ' missing, file not found: ' . $file);
        }

    }
});

// Load ONLY essential dependencies that are always needed
// Move to autoloader (only loaded when used):
// require_once OPENSIM_HELPERS_PATH . '/includes/class-api.php';
// require_once OPENSIM_HELPERS_PATH . '/includes/class-search-helper.php';
// require_once OPENSIM_HELPERS_PATH . '/includes/class-profile-helper.php';

// Deprecation notice: setting will be handled by Engine_Settings in next release.
if( file_exists( OPENSIM_HELPERS_PATH . '/includes/config.php' ) ) {
    // Load configuration if exists
    try {
        @require_once OPENSIM_HELPERS_PATH . '/includes/config.php';
    } catch (Throwable $e) {
        // Handle error if config file fails to load, but don't die.
        // error_log('[ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}

require_once OPENSIM_HELPERS_PATH . '/classes/class-helpers.php';

// Move to autoloader (only loaded when used):
// require_once OPENSIM_HELPERS_PATH . '/includes/helpers-migration-v2to3.php';
