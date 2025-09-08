<?php
/**
 * Bootstrap file for OpenSimulator Helpers testing framework
 * Loads WordPress and sets up the testing environment
 */

// Start session FIRST to prevent warnings
if ( session_status() === PHP_SESSION_NONE ) {
	session_start();
}

try {
    // Load the helpers bootstrap (this defines OPENSIM_ENGINE_PATH)
    require_once dirname( __DIR__ ) . '/bootstrap.php';
} catch ( Exception $e ) {
    echo "ERROR: Exception loading bootstrap.php: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Load .env file if it exists for per-site configuration
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
	$env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($env_lines as $line) {
		if (strpos(trim($line), '#') === 0) {
			continue; // Skip comments
		}
		if (strpos($line, '=') !== false) {
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value, '"\''); // Remove quotes
			putenv("$key=$value");
			$_ENV[$key] = $value;
		}
	}
	echo "Loaded environment configuration from .env" . PHP_EOL;
}

// Simple test framework
class SimpleTest {
	private $tests_run = 0;
	private $tests_passed = 0;
	private $tests_failed = 0;
	private $failed_tests = array();

	public function assert_true( $condition, $message = '' ) {
		$this->tests_run++;
		if ( $condition ) {
			$this->tests_passed++;
			echo "✓ PASS: {$message}" . PHP_EOL;
			return true;
		} else {
			$this->tests_failed++;
			$this->failed_tests[] = $message;
			echo "✗ FAIL: {$message}" . PHP_EOL;
			return false;
		}
	}

	public function assert_false( $condition, $message = '' ) {
		return $this->assert_true( ! $condition, $message );
	}

	public function assert_equals( $expected, $actual, $message = '' ) {
		$this->tests_run++;
		if ( $expected === $actual ) {
			$this->tests_passed++;
			echo "✓ PASS: {$message} (" . var_export($expected, true) . ")" . PHP_EOL;
			return true;
		} else {
			$this->tests_failed++;
			$error_details = "(expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")";
			$this->failed_tests[] = $message . " " . $error_details;
			echo "✗ FAIL: {$message} {$error_details}" . PHP_EOL;
			return false;
		}
	}

	public function assert_not_empty( $value, $message = '' ) {
		$this->tests_run++;
		if ( ! empty( $value ) ) {
			$this->tests_passed++;
			echo "✓ PASS: {$message}" . PHP_EOL;
			return true;
		} else {
			$this->tests_failed++;
			$this->failed_tests[] = $message . " (value was empty)";
			echo "✗ FAIL: {$message} (value was empty)" . PHP_EOL;
			return false;
		}
	}

    /**
     * Check if text contains a specific string (case-insensitive)
     * @param string $haystack The text to search in
     * @param string $needle The text to search for
     * @return bool True if found, false otherwise
     */
    public function assert_contains($haystack, $needle, $message = '') {
        $this->tests_run++;
        if (stripos($haystack, $needle) !== false) {
            $this->tests_passed++;
            echo "✓ PASS: {$message}" . PHP_EOL;
            return true;
        } else {
            $this->tests_failed++;
            $this->failed_tests[] = $message . " (did not contain expected string)";
            echo "✗ FAIL: {$message} (did not contain expected string)" . PHP_EOL;
            return false;
        }
    }

    /**
     * Check if a string matches any of the given patterns
     * Supports both string and regex patterns (regex patterns start with '/')
     * 
     * @param string $text The text to search in
     * @param array $patterns Array of patterns (strings or regex patterns starting with '/')
     * @return bool True if any pattern matches
     */
    public function assert_matches_any_pattern($text, $patterns, $message = '') {
        $this->tests_run++;
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '/') === 0) {
                // Regex pattern
                if (preg_match($pattern, $text)) {
                    $this->tests_passed++;
                    echo "✓ PASS: {$message}" . PHP_EOL;
                    return true;
                }
            } else {
                // Simple substring match (case-insensitive)
                if (stripos($text, $pattern) !== false) {
                    $this->tests_passed++;
                    echo "✓ PASS: {$message}" . PHP_EOL;
                    return true;
                }
            }
        }
        $this->tests_failed++;
        $this->failed_tests[] = $message . " (did not match any expected patterns)";
        echo "✗ FAIL: {$message} (did not match any expected patterns)" . PHP_EOL;
        return false;
    }

    public function assert_valid_form( $content, $expected_form_id, $expected_fields, $expected_values) {
        // Parse the HTML to verify it's the initial config step
        $parsed_html = SimpleTest::parse_html($content);
        if (!$this->assert_true($parsed_html !== false, "  HTML parsed")) {
            return false;
        }

        $xpath = new DOMXPath($parsed_html);

        // Extract and display any error messages from the page
        $error_elements = $xpath->query('//div[contains(@class, "alert-danger")]');
        if (!$this->assert_equals(0, $error_elements->length, "  Error messages")) {
            $errors = array();
            foreach ($error_elements as $error_element) {
                $error_text = trim($error_element->textContent);
                if (!empty($error_text)) {
                    echo "    - " . $error_text . PHP_EOL;
                }
            }
            return false;
        }

        // Check for config_method field with ini_import option
        $form_id = $xpath->query('//form')->item(0)->id;

        if(!$this->assert_equals($expected_form_id, $form_id, "  Form ID")) {
            return false;
        }

        foreach($expected_values as $field_name => $expected_value) {
            $field = $xpath->query(sprintf('//input[@name="%s"] | //select[@name="%s"]', $field_name, $field_name))->item(0);
            if($field === null) {
                continue; // Already reported missing
            }
            $actual_value = '';
            if($field->tagName === 'input') {
                $actual_value = $field->getAttribute('value') ?? '';
            } elseif($field->tagName === 'select') {
                $selected_option = $xpath->query('.//option[@selected]', $field)->item(0);
                if($selected_option) {
                    $actual_value = $selected_option->getAttribute('value') ?? '';
                }
            }
            if(!$this->assert_equals($expected_value, $actual_value, "  Field '$field_name' value")) {
                $invalid[] = $field_name;
            }
        }
        if(!empty($invalid)) {
            echo "   ❌ Field value mismatches: " . implode(', ', $invalid) . ", aborting tests." . PHP_EOL;
            return false;
        }

        $missing = [];
        foreach ($expected_fields as $field_name) {
            $field = $xpath->query(sprintf('//input[@name="%s"] | //select[@name="%s"]', $field_name, $field_name))->item(0);
            if($field === null) {
                $missing[] = $field_name;
                continue;
            }
        }

        if(!$this->assert_true(empty($missing), "  Required fields" . (!empty($missing) ? ' (missing: ' . implode(', ', $missing) . ')' : ''))) {
            echo "   ❌ Missing fields: " . implode(', ', $missing) . ", aborting tests." . PHP_EOL;
            exit($this->summary() ? 0 : 1);
        }

        return true;
    }

    public function get_stats() {
		return array(
			'run' => $this->tests_run,
			'passed' => $this->tests_passed,
			'failed' => $this->tests_failed,
			'failed_tests' => $this->failed_tests
		);
	}

	public function summary() {
		echo "\n" . str_repeat( '=', 50 ) . PHP_EOL;
		echo "Test Summary:" . PHP_EOL;
		echo "  Total tests: {$this->tests_run}" . PHP_EOL;
		echo "  Passed: {$this->tests_passed}" . PHP_EOL;
		echo "  Failed: {$this->tests_failed}" . PHP_EOL;
		
		if ( $this->tests_failed > 0 ) {
			echo "\nFailed Tests:" . PHP_EOL;
			foreach ( $this->failed_tests as $i => $failed_test ) {
				echo "  " . ($i + 1) . ". {$failed_test}" . PHP_EOL;
			}
			echo "\n  Status: FAILED" . PHP_EOL;
			return false;
		} else {
			echo "  Status: ALL PASSED" . PHP_EOL;
			return true;
		}
	}

    /**
     * DOM Analysis Helper Functions
     * These functions provide reliable HTML content analysis using DOMDocument
     * instead of unreliable string matching.
     */

    /**
     * Parse HTML content into a DOMDocument with error suppression
     * @param string $html_content The HTML content to parse
     * @return DOMDocument|false The parsed document or false on error
     */
    public static function parse_html($html_content) {
        if (empty($html_content)) {
            return false;
        }
        
        $doc = new DOMDocument();
        // Suppress warnings for malformed HTML
        $old_setting = libxml_use_internal_errors(true);
        $success = $doc->loadHTML($html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($old_setting);
        
        return $success ? $doc : false;
    }

    /**
     * Extract title content from HTML
     * @param string $html_content The HTML content
     * @return string|false The title content or false if not found
     */
    public static function get_html_title($html_content) {
        $doc = testing_parse_html($html_content);
        if (!$doc) {
            return false;
        }
        
        $xpath = new DOMXPath($doc);
        $title_nodes = $xpath->query('//title');
        
        return $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : false;
    }

    /**
     * Extract main content from HTML, excluding navigation, header, footer, sidebar elements
     * Uses multiple strategies to find the actual main content container
     * @param string $html_content The HTML content to extract from
     * @return string The main content text
     */
    public static function get_main_content($html_content) {
        $parsed_html = testing_parse_html($html_content);
        if (!$parsed_html) {
            return '';
        }

        $xpath = new DOMXPath($parsed_html);
        
        // Try multiple main content selectors in order of preference
        $main_selectors = array(
            '//main',                                    // Standard HTML5 main element
            '//div[@id="main-content"]',                // Divi theme
            '//div[@id="content"]',                     // Common theme pattern
            '//div[@class="content"]',                  // Common theme pattern
            '//div[contains(@class, "main-content")]',  // Flexible main-content class
            '//div[contains(@class, "site-content")]',  // WordPress theme pattern
            '//div[contains(@class, "entry-content")]', // Post/page content
            '//article',                                // Article elements
            '//div[@role="main"]'                       // ARIA main role
        );
        
        foreach ($main_selectors as $selector) {
            $main_nodes = $xpath->query($selector);
            if ($main_nodes->length > 0) {
                // Found main content container, extract text while excluding secondary elements
                $main_container = $main_nodes->item(0);
                
                // Remove navigation, header, footer, sidebar elements from the main container
                $exclude_selectors = array(
                    './/nav', './/header', './/footer', 
                    './/*[contains(@class, "sidebar")]', 
                    './/*[contains(@class, "navigation")]',
                    './/*[contains(@class, "nav")]',
                    './/*[contains(@class, "widget")]',
                    './/*[contains(@class, "menu")]'
                );
                
                foreach ($exclude_selectors as $exclude_selector) {
                    $exclude_nodes = $xpath->query($exclude_selector, $main_container);
                    foreach ($exclude_nodes as $exclude_node) {
                        if ($exclude_node->parentNode) {
                            $exclude_node->parentNode->removeChild($exclude_node);
                        }
                    }
                }
                
                return trim($main_container->textContent);
            }
        }
        
        // Fallback: get body content and exclude known secondary elements
        $body_nodes = $xpath->query('//body');
        if ($body_nodes->length === 0) {
            return strip_tags($html_content);
        }

        $body = $body_nodes->item(0);
        
        // Remove common secondary elements from body
        $exclude_selectors = array(
            './/nav', './/header', './/footer', 
            './/*[contains(@class, "sidebar")]', 
            './/*[contains(@class, "navigation")]',
            './/*[contains(@class, "nav")]',
            './/*[contains(@class, "widget")]',
            './/*[contains(@class, "menu")]',
            './/*[@role="navigation"]',
            './/*[@role="banner"]',
            './/*[@role="contentinfo"]'
        );
        
        foreach ($exclude_selectors as $exclude_selector) {
            $exclude_nodes = $xpath->query($exclude_selector, $body);
            foreach ($exclude_nodes as $exclude_node) {
                if ($exclude_node->parentNode) {
                    $exclude_node->parentNode->removeChild($exclude_node);
                }
            }
        }
        
        return trim($body->textContent);
    }

    /**
     * Analyze HTML content for basic page elements
     * Generic function that extracts common HTML elements without any domain-specific logic
     * @param string $html_content The HTML content to analyze
     * @return array Analysis results with basic page elements
     */
    public static function analyze_html_content($html_content) {
        $parsed_html = testing_parse_html($html_content);
        if (!$parsed_html) {
            return array(
                'success' => false,
                'error' => 'Failed to parse HTML'
            );
        }

        $head_title = testing_get_html_title($html_content);
        $main_content = testing_get_main_content($html_content);
        
        // Get page title and all headings
        $xpath = new DOMXPath($parsed_html);
        
        // Find page title (first h1 in the document)
        $page_title = null;
        $headings = $xpath->query('//h1');
        if ($headings->length > 0) {
            $page_title = trim($headings->item(0)->textContent);
        }
        
        // Get all headings for analysis
        $all_headings = array();
        $heading_nodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        foreach ($heading_nodes as $heading) {
            $all_headings[] = array(
                'tag' => $heading->tagName,
                'text' => trim($heading->textContent),
                'class' => $heading->getAttribute('class')
            );
        }

        return array(
            'success' => true,
            'head_title' => $head_title,
            'page_title' => $page_title,
            'all_headings' => $all_headings,
            'main_content' => $main_content,
            'content_preview' => substr($main_content, 0, 200),
            'html_length' => strlen($html_content)
        );
    }
}

/**
 * Test Server Management Functions
 */

// Global variables to track server state
$_test_server_pid = null;
$_test_server_port = null;
$_test_server_base_url = null;

/**
 * Find the first available port starting from a given port
 */
function find_available_port($start_port = 8080) {
    for ($port = $start_port; $port <= 65535; $port++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if (!$socket) {
            return $port; // Port is available
        }
        fclose($socket);
    }
    return false; // No available port found
}

/**
 * Start a PHP test server for testing
 */
function start_test_server($document_root = null) {
    global $_test_server_pid, $_test_server_port, $_test_server_base_url;
    
    if ($_test_server_pid !== null) {
        echo "Test server already running on port $_test_server_port" . PHP_EOL;
        return $_test_server_base_url;
    }
    
    if ($document_root === null) {
        $document_root = dirname(__DIR__); // Default to helpers root
    }
    
    $_test_server_port = find_available_port(8080);
    if ($_test_server_port === false) {
        throw new Exception("No available port found for test server");
    }
    
    $_test_server_base_url = "http://127.0.0.1:$_test_server_port";
    
    echo "Starting test server on port $_test_server_port..." . PHP_EOL;
    
    // Start PHP built-in server in background
    $command = sprintf(
        'php -S 127.0.0.1:%d -t %s > /dev/null 2>&1 & echo $!',
        $_test_server_port,
        escapeshellarg($document_root)
    );
    
    $output = shell_exec($command);
    $_test_server_pid = (int)trim($output);
    
    if ($_test_server_pid <= 0) {
        throw new Exception("Failed to start test server");
    }
    
    // Wait a moment for server to start
    usleep(500000); // 0.5 seconds
    
    // Test if server is responding
    $test_url = $_test_server_base_url . '/index.php';
    $response = @file_get_contents($test_url);
    if ($response === false) {
        stop_test_server();
        throw new Exception("Test server started but not responding at $test_url");
    }
    
    echo "Test server started successfully at $_test_server_base_url" . PHP_EOL;
    return $_test_server_base_url;
}

/**
 * Stop the test server
 */
function stop_test_server() {
    global $_test_server_pid, $_test_server_port, $_test_server_base_url;
    
    if ($_test_server_pid !== null) {
        echo "Stopping test server (PID: $_test_server_pid)..." . PHP_EOL;
        
        // Kill the server process
        $kill_command = "kill $_test_server_pid 2>/dev/null";
        shell_exec($kill_command);
        
        // Wait a moment for graceful shutdown
        usleep(200000); // 0.2 seconds
        
        // Force kill if still running
        $force_kill_command = "kill -9 $_test_server_pid 2>/dev/null";
        shell_exec($force_kill_command);
        
        $_test_server_pid = null;
        $_test_server_port = null;
        $_test_server_base_url = null;
        
        echo "Test server stopped" . PHP_EOL;
    }
}

/**
 * Get the current test server base URL
 */
function get_test_server_url() {
    global $_test_server_base_url;
    return $_test_server_base_url;
}

/**
 * Cleanup function to ensure server is stopped
 */
function cleanup_test_server() {
    stop_test_server();
}

// Register cleanup handlers
register_shutdown_function('cleanup_test_server');

// Set up exception handler to ensure cleanup
set_exception_handler(function($exception) {
    echo "Uncaught exception: " . $exception->getMessage() . PHP_EOL;
    cleanup_test_server();
    exit(1);
});

// Handle SIGINT (Ctrl+C) and SIGTERM if available
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() {
        echo PHP_EOL . "Received interrupt signal, cleaning up..." . PHP_EOL;
        cleanup_test_server();
        exit(0);
    });
    
    pcntl_signal(SIGTERM, function() {
        echo PHP_EOL . "Received termination signal, cleaning up..." . PHP_EOL;
        cleanup_test_server();
        exit(0);
    });
}

// Global test instance
$test = new SimpleTest();
