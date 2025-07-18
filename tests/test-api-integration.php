<?php
/**
 * Test suite for Ethicrawler Bot Detector API Integration
 */

class TestEthicrawlerAPIIntegration extends WP_UnitTestCase {
    
    private $plugin;
    
    public function setUp(): void {
        parent::setUp();
        $this->plugin = EthicrawlerBotDetector::get_instance();
    }
    
    /**
     * Test non-blocking API request setup
     */
    public function test_non_blocking_api_request() {
        // Set up test data
        update_option('ethicrawler_site_id', 'test-site-123');
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['REQUEST_URI'] = '/test-page/';
        
        // Capture the shutdown actions before our test
        global $wp_filter;
        $shutdown_actions_before = isset($wp_filter['shutdown']) ? count($wp_filter['shutdown']->callbacks) : 0;
        
        // Call the method that should register a shutdown action
        $this->call_private_method('log_bot_activity', ['GPTBot/1.0', '203.0.113.1', '/test-page/']);
        
        // Check that a shutdown action was added
        $shutdown_actions_after = isset($wp_filter['shutdown']) ? count($wp_filter['shutdown']->callbacks) : 0;
        $this->assertGreaterThan($shutdown_actions_before, $shutdown_actions_after, 'No shutdown action was registered for non-blocking API request');
        
        // Clean up
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']);
    }
    
    /**
     * Test retry scheduling
     */
    public function test_retry_scheduling() {
        // Mock a retry key
        $retry_key = 'ethicrawler_retry_' . md5('test-data');
        
        // Store test data in transient
        set_transient($retry_key . '_data', ['test' => 'data'], DAY_IN_SECONDS);
        
        // Call the schedule_retry method
        $this->call_private_method('schedule_retry', [$retry_key]);
        
        // Check that a retry was scheduled
        $scheduled = wp_next_scheduled('ethicrawler_retry_api_request', [$retry_key]);
        $this->assertNotFalse($scheduled, 'Retry was not scheduled');
        
        // Check that retry count was incremented
        $retry_count = get_transient($retry_key . '_count');
        $this->assertEquals(1, $retry_count, 'Retry count was not set correctly');
        
        // Clean up
        wp_clear_scheduled_hook('ethicrawler_retry_api_request', [$retry_key]);
        delete_transient($retry_key . '_data');
        delete_transient($retry_key . '_count');
    }
    
    /**
     * Test error logging
     */
    public function test_error_logging() {
        // Capture the shutdown actions before our test
        global $wp_filter;
        $shutdown_actions_before = isset($wp_filter['shutdown']) ? count($wp_filter['shutdown']->callbacks) : 0;
        
        // Call the error logging method
        $this->call_private_method('log_api_error', ['Test error message', ['test' => 'context']]);
        
        // Check that a shutdown action was added for non-blocking error logging
        $shutdown_actions_after = isset($wp_filter['shutdown']) ? count($wp_filter['shutdown']->callbacks) : 0;
        $this->assertGreaterThan($shutdown_actions_before, $shutdown_actions_after, 'No shutdown action was registered for error logging');
    }
    
    /**
     * Test error categorization
     */
    public function test_error_categorization() {
        $test_cases = [
            'Invalid backend URL configured' => 'configuration',
            'API request failed due to timeout' => 'timeout',
            'Retry attempt failed' => 'retry',
            'SSL certificate verification failed' => 'ssl',
            'Failed to resolve DNS' => 'dns',
            'HTTP error 500 received' => 'http',
            'Some other random error' => 'general'
        ];
        
        foreach ($test_cases as $message => $expected_category) {
            $category = $this->call_private_method('determine_error_category', [$message]);
            $this->assertEquals($expected_category, $category, "Error category for '$message' should be '$expected_category', got '$category'");
        }
    }
    
    /**
     * Test retry API request method
     */
    public function test_retry_api_request() {
        // Mock a retry key
        $retry_key = 'ethicrawler_retry_' . md5('test-data');
        
        // Store test data and count in transients
        $test_data = [
            'site_id' => 'test-site-123',
            'user_agent' => 'GPTBot/1.0',
            'ip_address' => '203.0.113.1',
            'path' => '/test-page/',
            'timestamp' => current_time('c', true)
        ];
        set_transient($retry_key . '_data', $test_data, DAY_IN_SECONDS);
        set_transient($retry_key . '_count', 1, DAY_IN_SECONDS);
        
        // We can't easily test the actual API call, but we can verify the method doesn't throw errors
        try {
            $this->plugin->retry_api_request($retry_key);
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (Exception $e) {
            $this->fail('Exception thrown during retry_api_request: ' . $e->getMessage());
        }
        
        // Clean up
        delete_transient($retry_key . '_data');
        delete_transient($retry_key . '_count');
    }
    
    /**
     * Helper method to call private methods for testing
     */
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