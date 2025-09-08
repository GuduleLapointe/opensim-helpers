<?php
/**
 * Test INI Import Functionality in Installation Wizard
 * 
 * Tests the actual wizard page through HTTP requests like a real client
 */

require_once __DIR__ . '/bootstrap.php';

// Load the OpenSim_Ini class for parsing expected values
require_once dirname(__DIR__) . '/engine/class-ini.php';

echo "Testing INI Import Wizard functionality via HTTP..." . PHP_EOL;

// Test configuration
$test_robust_ini_path = getenv('ROBUST_INI') ?: dirname(__DIR__) . '/lib/0.9.3/dist-ini/Robust.HG.ini.example';

// Verify test INI file exists
$test->assert_true(file_exists($test_robust_ini_path), "Test INI file exists at " . $test_robust_ini_path);

// Start test server
$base_url = start_test_server();
if(!$test->assert_not_empty($base_url, "Test server started successfully")) {
    echo "   ❌ Could not start test server or access wizard URL, aborting tests." . PHP_EOL;
    exit($test->summary() ? 0 : 1);
}

$wizard_url = $base_url . '/install-wizard.php';
$headers = os_get_headers($wizard_url);
if ($headers === false || $test->assert_equals($headers[0] ?? '', 'HTTP/1.1 200 OK', "Wizard URL accessible at $wizard_url") === false) {
    echo "   ❌ Could not access $wizard_url, aborting tests." . PHP_EOL;
    exit($test->summary() ? 0 : 1);
}

$form_id = 'opensim_install_wizard';

echo PHP_EOL . "Fetching Installation Step 1 page..." . PHP_EOL;

// Fetch the initial wizard page
$content = os_file_get_contents($wizard_url);
if (!$test->assert_true($content !== false, "Step 1 Page fetched")) {
    echo "   Could not fetch $wizard_url" . PHP_EOL;
    exit($test->summary() ? 0 : 1);
}

if (!$test->assert_valid_form($content, $form_id, array(
    'config_method',
    'robust_ini[path]',
    'robust_ini[upload]'
), array(
    'step_slug' => 'initial_config'
))) {
    echo "   ❌ Step 1 form validation failed, aborting tests." . PHP_EOL;
    exit($test->summary() ? 0 : 1);
}

echo PHP_EOL;
echo "Sumbitting Step 1 with file path $test_robust_ini_path" . PHP_EOL;

// Submit form with file path method
$post_data = [
    'form_id' => $form_id,
    'step_slug' => 'initial_config',
    'config_method' => 'ini_import',
    'robust_ini[path]' => $test_robust_ini_path,
    'robust_ini[upload]' => ''
];

$post_query = http_build_query($post_data);
$headers = [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($post_query),
];

// Use os_file_get_contents with POST context
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $post_query,
    ],
]);

// $content = os_file_get_contents($wizard_url, false, $context);
$content = file_get_contents($wizard_url, false, $context);

// Parse the INI file we sent to extract expected values for validation

$ini = new OpenSim_Ini($test_robust_ini_path);
$config = $ini->get_config();

$login_uri = $config['GridInfoService']['login'] ?? null;
$public_port = $config['Const']['PublicPort'] ?? null;
$hostname = $config['Const']['BaseHostname'] ?? null;
if(empty($hostname)) {
    $hostname = parse_url($login_uri, PHP_URL_HOST);
}
if(empty($login_uri) && !empty($hostname) && !empty($public_port)) {
    $login_uri = "$hostname:$public_port";
}
$login_uri = OpenSim::sanitize_login_uri( $login_uri );
	
$test->assert_not_empty($login_uri, "Login URI $login_uri");
$test->assert_not_empty($hostname, "Hostname  $hostname");

$connection_string = $config['DatabaseService']['ConnectionString'] ?? '';
$db_credentials = connectionstring_to_array($connection_string);

$fields = array(
    'db_host' => $db_credentials['host'] ?? null,
    'db_port' => $db_credentials['port'] ?? null,
    'db_name' => $db_credentials['name'] ?? null,
    'db_user' => $db_credentials['user'] ?? null,
    'db_pass' => $db_credentials['pass'] ?? null,
    'console_host' => $hostname ?? null,
    'console_user' => $config['Network']['ConsoleUser'] ?? null,
    'console_pass' => $config['Network']['ConsolePass'] ?? null,
    'console_port' => $config['Network']['ConsolePort'] ?? null,
);

if(!$test->assert_valid_form($content, $form_id, array_keys($fields), array_filter($fields))) {
    echo "   ❌ Step 1 form validation failed, aborting tests." . PHP_EOL;
    exit($test->summary() ? 0 : 1);
}

// TODO: Test file upload method

// TODO: Test console credentials if provided (required same result as what the form reports)
// TODO: Test dababase credentials (required same result as what the form reports)

$test->summary();
