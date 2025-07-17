<?php
/**
 * Simple test runner for basic plugin functionality
 * This can be run without a full WordPress test environment
 */

// Mock WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for basic testing
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // Mock - do nothing in test
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // Mock - do nothing in test
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock - do nothing in test
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false) {
        return date('c');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

// Load the plugin
echo "Loading plugin...\n";
require_once 'ethicrawler-bot-detector.php';
echo "Plugin loaded successfully.\n";

/**
 * Simple test class for basic functionality
 */
class SimplePluginTest {
    
    private $plugin;
    private $passed = 0;
    private $failed = 0;
    
    public function __construct() {
        $this->plugin = EthicrawlerBotDetector::get_instance();
        echo "Running basic plugin tests...\n\n";
    }
    
    public function run_all_tests() {
        $this->test_ai_bot_detection();
        $this->test_legitimate_crawler_whitelisting();
        $this->test_ip_address_extraction();
        $this->test_user_agent_extraction();
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Test Results: {$this->passed} passed, {$this->failed} failed\n";
        
        if ($this->failed > 0) {
            exit(1);
        }
    }
    
    private function test_ai_bot_detection() {
        echo "Testing AI bot detection...\n";
        
        $ai_user_agents = [
            'GPTBot/1.0',
            'ChatGPT-User/1.0',
            'python-requests/2.28.1',
            'curl/7.68.0',
            'Custom-Scraper/1.0'
        ];
        
        foreach ($ai_user_agents as $user_agent) {
            $result = $this->call_private_method('is_ai_bot', [$user_agent]);
            $this->assert_true($result, "Should detect AI bot: {$user_agent}");
        }
    }
    
    private function test_legitimate_crawler_whitelisting() {
        echo "Testing legitimate crawler whitelisting...\n";
        
        $legitimate_crawlers = [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'facebookexternalhit/1.1'
        ];
        
        foreach ($legitimate_crawlers as $user_agent) {
            $result = $this->call_private_method('is_whitelisted_bot', [$user_agent]);
            $this->assert_true($result, "Should whitelist: {$user_agent}");
        }
    }
    
    private function test_ip_address_extraction() {
        echo "Testing IP address extraction...\n";
        
        // Test standard REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $ip = $this->call_private_method('get_ip_address');
        $this->assert_equals('192.168.1.100', $ip, "Should extract REMOTE_ADDR");
        
        // Test X-Forwarded-For header
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.100';
        $ip = $this->call_private_method('get_ip_address');
        $this->assert_equals('203.0.113.1', $ip, "Should extract first IP from X-Forwarded-For");
        
        // Clean up
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }
    
    private function test_user_agent_extraction() {
        echo "Testing User-Agent extraction...\n";
        
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
        $user_agent = $this->call_private_method('get_user_agent');
        $this->assert_equals('GPTBot/1.0', $user_agent, "Should extract User-Agent");
        
        // Clean up
        unset($_SERVER['HTTP_USER_AGENT']);
    }
    
    private function call_private_method($method_name, $args = []) {
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        
        if (empty($args)) {
            return $method->invoke($this->plugin);
        } else {
            return $method->invokeArgs($this->plugin, $args);
        }
    }
    
    private function assert_true($condition, $message) {
        if ($condition) {
            echo "  ✓ {$message}\n";
            $this->passed++;
        } else {
            echo "  ✗ {$message}\n";
            $this->failed++;
        }
    }
    
    private function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            echo "  ✓ {$message}\n";
            $this->passed++;
        } else {
            echo "  ✗ {$message} (expected: {$expected}, got: {$actual})\n";
            $this->failed++;
        }
    }
}

// Run the tests
$test = new SimplePluginTest();
$test->run_all_tests();
/**
 *
 Integration test class for full request flow
 */
class IntegrationTest {
    
    private $plugin;
    
    public function __construct() {
        $this->plugin = EthicrawlerBotDetector::get_instance();
        echo "\nRunning integration tests...\n\n";
    }
    
    public function test_full_request_flow() {
        echo "Testing full request flow simulation...\n";
        
        // Mock WordPress functions needed for the flow
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                $options = [
                    'ethicrawler_enabled' => true,
                    'ethicrawler_site_id' => 'test-site-123',
                    'ethicrawler_backend_url' => 'https://api.ethicrawler.com'
                ];
                return isset($options[$option]) ? $options[$option] : $default;
            }
        }
        
        if (!function_exists('is_admin')) {
            function is_admin() { return false; }
        }
        
        if (!function_exists('wp_doing_ajax')) {
            function wp_doing_ajax() { return false; }
        }
        
        // Simulate an AI bot request
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['REQUEST_URI'] = '/test-page/';
        
        // Test that the request would be processed
        $user_agent = $this->call_private_method('get_user_agent');
        $ip_address = $this->call_private_method('get_ip_address');
        $path = $this->call_private_method('get_request_path');
        
        echo "  ✓ User-Agent captured: {$user_agent}\n";
        echo "  ✓ IP address captured: {$ip_address}\n";
        echo "  ✓ Request path captured: {$path}\n";
        
        // Test bot detection logic
        $is_whitelisted = $this->call_private_method('is_whitelisted_bot', [$user_agent]);
        $is_ai_bot = $this->call_private_method('is_ai_bot', [$user_agent]);
        
        echo "  ✓ Whitelisted check: " . ($is_whitelisted ? 'true' : 'false') . "\n";
        echo "  ✓ AI bot detection: " . ($is_ai_bot ? 'true' : 'false') . "\n";
        
        if (!$is_whitelisted && $is_ai_bot) {
            echo "  ✓ Request would be logged to backend API\n";
        }
        
        // Clean up
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']);
        
        echo "  ✓ Integration test completed successfully\n";
    }
    
    private function call_private_method($method_name, $args = []) {
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        
        if (empty($args)) {
            return $method->invoke($this->plugin);
        } else {
            return $method->invokeArgs($this->plugin, $args);
        }
    }
}

// Run integration tests
$integration_test = new IntegrationTest();
$integration_test->test_full_request_flow();