# W4OS Plugin Testing

This directory contains the testing environment, using a simple PHP test runner approach.

## Running Tests

To run all tests:

```bash
./tests/run-tests.php   # Abort remaining tests if a required test group fails
```

To run individual test groups:

```bash
php ./tests/test-00-dependencies-required.php
php ./tests/test-02-some-test.php               
php ./tests/test-some-other-test-.php
```

## Test Structure

- **`bootstrap.php`** - Loads WordPress and provides SimpleTest framework
- **`run-tests.php`** - Main test runner that executes all test-*.php files in order with requirement checking
- **`test-00-dependencies-required.php`** - System dependency tests (PHP extensions, server requirements) - REQUIRED

*Tests with "-required" suffix must pass for subsequent tests to run. Optional tests like `test-profile.php` will run regardless of other test outcomes.*

## Test Approach

- **No PHPUnit required** - Uses plain PHP with a simple test framework
- **Tests against live WordPress** - Uses your actual WordPress installation instead of a separate test environment
- **Environment-aware** - Tests with all your plugins, configuration, and OpenSim setup intact
- **Requirement-based execution** - Tests with "-required" suffix must pass before subsequent tests run
