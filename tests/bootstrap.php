<?php
/**
 * PHPUnit bootstrap file for Ethicrawler Bot Detector tests
 */

// Define test environment
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

// WordPress test configuration
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/ethicrawler-bot-detector.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';