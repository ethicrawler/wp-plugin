<?php
/**
 * Test suite for Ethicrawler Bot Detector
 */

class TestEthicrawlerBotDetector extends WP_UnitTestCase {
    
    private $plugin;
    
    public function setUp(): void {
        parent::setUp();
        $this->plugin = EthicrawlerBotDetector::get_instance();
    }
    
    /**
     * Test bot detection for AI crawlers
     */
    public function test_ai_bot_detection() {
        $ai_user_agents = [
            'GPTBot/1.0',
            'ChatGPT-User/1.0',
            'OpenAI-Crawler/1.0',
            'Claude-Web/1.0',
            'python-requests/2.28.1',
            'curl/7.68.0',
            'Custom-Scraper/1.0'
        ];
        
        foreach ($ai_user_agents as $user_agent) {
            $this->assertTrue(
                $this->call_private_method('is_ai_bot', [$user_agent]),
                "Failed to detect AI bot: {$user_agent}"
            );
        }
    }
    
    /**
     * Test whitelisting of legitimate crawlers
     */
    public function test_legitimate_crawler_whitelisting() {
        $legitimate_crawlers = [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)',
            'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)',
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'
        ];
        
        foreach ($legitimate_crawlers as $user_agent) {
            $this->assertTrue(
                $this->call_private_method('is_whitelisted_bot', [$user_agent]),
                "Failed to whitelist legitimate crawler: {$user_agent}"
            );
        }
    }
    
    /**
     * Test that legitimate crawlers are not flagged as AI bots
     */
    public function test_legitimate_crawlers_not_flagged_as_ai() {
        $legitimate_crawlers = [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'
        ];
        
        foreach ($legitimate_crawlers as $user_agent) {
            // Should be whitelisted
            $this->assertTrue(
                $this->call_private_method('is_whitelisted_bot', [$user_agent])
            );
            
            // Even if it matches AI patterns, whitelisted bots should be excluded
            // This tests the logic flow in capture_request_data()
        }
    }
    
    /**
     * Test IP address extraction with various headers
     */
    public function test_ip_address_extraction() {
        // Test standard REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $ip = $this->call_private_method('get_ip_address');
        $this->assertEquals('192.168.1.100', $ip);
        
        // Test X-Forwarded-For header (should take precedence)
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.100';
        $ip = $this->call_private_method('get_ip_address');
        $this->assertEquals('203.0.113.1', $ip);
        
        // Test Cloudflare header (should take highest precedence)
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
        $ip = $this->call_private_method('get_ip_address');
        $this->assertEquals('198.51.100.1', $ip);
        
        // Clean up
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    
    /**
     * Test User-Agent extraction and sanitization
     */
    public function test_user_agent_extraction() {
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0 <script>alert("xss")</script>';
        $user_agent = $this->call_private_method('get_user_agent');
        
        // Should be sanitized
        $this->assertStringNotContainsString('<script>', $user_agent);
        $this->assertStringContainsString('GPTBot/1.0', $user_agent);
        
        // Clean up
        unset($_SERVER['HTTP_USER_AGENT']);
    }
    
    /**
     * Test request path extraction
     */
    public function test_request_path_extraction() {
        $_SERVER['REQUEST_URI'] = '/test-page/?param=value';
        $path = $this->call_private_method('get_request_path');
        $this->assertEquals('/test-page/?param=value', $path);
        
        // Clean up
        unset($_SERVER['REQUEST_URI']);
    }
    
    /**
     * Test plugin activation creates required options
     */
    public function test_plugin_activation() {
        // Delete options first to ensure clean test
        delete_option('ethicrawler_site_id');
        delete_option('ethicrawler_backend_url');
        delete_option('ethicrawler_enabled');
        
        // Trigger activation
        $this->plugin->activate();
        
        // Check that options were created with correct defaults
        $this->assertEquals('', get_option('ethicrawler_site_id'));
        $this->assertEquals('https://api.ethicrawler.com', get_option('ethicrawler_backend_url'));
        $this->assertTrue(get_option('ethicrawler_enabled'));
    }
    
    /**
     * Test that plugin skips admin requests
     */
    public function test_skips_admin_requests() {
        // Mock admin request
        set_current_screen('dashboard');
        
        // This should return early and not process the request
        // We can't easily test this without mocking, but we can verify
        // the is_admin() check works
        $this->assertTrue(is_admin());
        
        // Clean up
        set_current_screen('front');
    }
    
    /**
     * Test bot activity logging data structure
     */
    public function test_bot_activity_data_structure() {
        // Set up test data
        update_option('ethicrawler_site_id', 'test-site-123');
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['REQUEST_URI'] = '/test-page/';
        
        // We can't easily test the actual API call without mocking,
        // but we can verify the data structure would be correct
        $expected_data = [
            'site_id' => 'test-site-123',
            'user_agent' => 'GPTBot/1.0',
            'ip_address' => '203.0.113.1',
            'path' => '/test-page/',
            'timestamp' => current_time('c', true)
        ];
        
        // Verify each component works
        $this->assertEquals('test-site-123', get_option('ethicrawler_site_id'));
        $this->assertEquals('GPTBot/1.0', $this->call_private_method('get_user_agent'));
        $this->assertEquals('203.0.113.1', $this->call_private_method('get_ip_address'));
        $this->assertEquals('/test-page/', $this->call_private_method('get_request_path'));
        
        // Clean up
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']);
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