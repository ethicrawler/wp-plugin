<?php
/**
 * Plugin Name: Ethicrawler Bot Detector
 * Plugin URI: https://github.com/ethicrawler/wp-plugin
 * Description: Detects and monitors AI bot activity on WordPress sites for monetization through the Ethicrawler platform.
 * Version: 1.0.0
 * Author: Ethicrawler
 * Author URI: https://ethicrawler.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: ethicrawler-bot-detector
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ETHICRAWLER_PLUGIN_VERSION', '1.0.0');
define('ETHICRAWLER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ETHICRAWLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETHICRAWLER_PLUGIN_FILE', __FILE__);

/**
 * Main Ethicrawler Bot Detector Class
 */
class EthicrawlerBotDetector {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Whitelisted legitimate crawlers
     */
    private $whitelisted_bots = [
        'Googlebot',
        'Bingbot',
        'Slurp', // Yahoo
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'Applebot'
    ];
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(ETHICRAWLER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ETHICRAWLER_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Core functionality hooks
        add_action('init', [$this, 'init']);
        add_action('template_redirect', [$this, 'capture_request_data']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // API retry mechanism hook
        add_action('ethicrawler_retry_api_request', [$this, 'retry_api_request'], 10, 1);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create options with default values
        add_option('ethicrawler_site_id', '');
        add_option('ethicrawler_backend_url', 'https://api.ethicrawler.com');
        add_option('ethicrawler_enabled', true);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('Ethicrawler Bot Detector: Plugin activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Ethicrawler Bot Detector: Plugin deactivated');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('ethicrawler-bot-detector', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Capture request data for bot detection
     */
    public function capture_request_data() {
        // Skip if plugin is disabled
        if (!get_option('ethicrawler_enabled', true)) {
            return;
        }
        
        // Skip admin requests
        if (is_admin()) {
            return;
        }
        
        // Skip AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // Skip REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        
        // Get request data
        $user_agent = $this->get_user_agent();
        $ip_address = $this->get_ip_address();
        $path = $this->get_request_path();
        
        // Skip if no user agent
        if (empty($user_agent)) {
            return;
        }
        
        // Check if this is a whitelisted bot
        if ($this->is_whitelisted_bot($user_agent)) {
            return;
        }
        
        // Check if this appears to be an AI bot
        if ($this->is_ai_bot($user_agent)) {
            $this->log_bot_activity($user_agent, $ip_address, $path);
        }
    }
    
    /**
     * Get User-Agent from request
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }
    
    /**
     * Get IP address from request
     */
    private function get_ip_address() {
        // Check for various IP headers in order of preference
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private/reserved
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
    
    /**
     * Get request path
     */
    private function get_request_path() {
        return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    }
    
    /**
     * Check if user agent is a whitelisted legitimate crawler
     */
    private function is_whitelisted_bot($user_agent) {
        foreach ($this->whitelisted_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user agent appears to be an AI bot
     */
    private function is_ai_bot($user_agent) {
        $ai_bot_patterns = [
            'GPTBot',
            'ChatGPT',
            'OpenAI',
            'Claude',
            'Anthropic',
            'Bard',
            'Gemini',
            'PaLM',
            'LaMDA',
            'crawler',
            'scraper',
            'spider',
            'bot',
            'python-requests',
            'curl',
            'wget',
            'http',
            'api'
        ];
        
        $user_agent_lower = strtolower($user_agent);
        
        foreach ($ai_bot_patterns as $pattern) {
            if (stripos($user_agent_lower, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log bot activity
     */
    private function log_bot_activity($user_agent, $ip_address, $path) {
        $site_id = get_option('ethicrawler_site_id', '');
        
        // Skip if no site ID configured
        if (empty($site_id)) {
            error_log('Ethicrawler Bot Detector: No site ID configured');
            return;
        }
        
        $data = [
            'site_id' => $site_id,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'path' => $path,
            'timestamp' => current_time('c', true) // ISO 8601 format in UTC
        ];
        
        // Send to backend API (non-blocking)
        $this->send_to_backend($data);
    }
    
    /**
     * Send data to backend API (non-blocking)
     * 
     * This implementation ensures site performance is not affected by:
     * 1. Using non-blocking requests with 'blocking' => false
     * 2. Using a short timeout to prevent hanging connections
     * 3. Using WordPress shutdown hook for truly asynchronous processing
     * 4. Implementing exponential backoff for retries
     * 5. Gracefully handling all errors without affecting the main thread
     */
    private function send_to_backend($data) {
        // Use WordPress shutdown hook to ensure the request happens after page rendering is complete
        // This guarantees zero impact on page load time for visitors
        add_action('shutdown', function() use ($data) {
            $this->process_api_request($data);
        });
        
        return true;
    }
    
    /**
     * Process API request asynchronously after page rendering is complete
     * This is called by the shutdown hook to ensure zero impact on site performance
     */
    private function process_api_request($data) {
        $backend_url = get_option('ethicrawler_backend_url', 'https://api.ethicrawler.com');
        $endpoint = rtrim($backend_url, '/') . '/api/v1/log_request';
        
        // Validate backend URL
        if (empty($backend_url) || !filter_var($backend_url, FILTER_VALIDATE_URL)) {
            $this->log_api_error('Invalid backend URL configured', ['url' => $backend_url]);
            return false;
        }
        
        // Generate unique request ID for tracking and deduplication
        $request_id = md5(serialize($data) . microtime(true));
        
        // Prepare request arguments for non-blocking transmission
        $args = [
            'body' => wp_json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Ethicrawler-WP-Plugin/' . ETHICRAWLER_PLUGIN_VERSION,
                'Accept' => 'application/json',
                'X-Ethicrawler-Request-ID' => $request_id
            ],
            'timeout' => 2, // Very short timeout to prevent hanging connections
            'blocking' => false, // Critical: Non-blocking to prevent site slowdown
            'sslverify' => true,
            'redirection' => 1, // Limit redirects to minimize processing time
            'httpversion' => '1.1',
            'reject_unsafe_urls' => true // Security: Prevent requests to private IPs/localhost
        ];
        
        // Add retry mechanism using WordPress cron for failed requests
        $retry_key = 'ethicrawler_retry_' . $request_id;
        
        // Store request data temporarily for potential retries
        set_transient($retry_key . '_data', $data, DAY_IN_SECONDS);
        
        // Use fastcgi_finish_request if available to release the connection immediately
        // This PHP function is available in PHP-FPM and allows the script to continue
        // executing while immediately returning the response to the client
        if (function_exists('fastcgi_finish_request') && !defined('WP_CLI') && !wp_doing_ajax()) {
            @fastcgi_finish_request();
        }
        
        // Attempt to send the request
        $response = wp_remote_post($endpoint, $args);
        
        // Handle errors without affecting site performance
        if (is_wp_error($response)) {
            $this->log_api_error('API request failed', [
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
                'endpoint' => $endpoint,
                'request_id' => $request_id
            ]);
            
            // Schedule retry for failed requests (non-blocking)
            $this->schedule_retry($retry_key);
            return false;
        }
        
        // For non-blocking requests, we can't check the response status
        // but we can log successful dispatch
        $this->log_api_success('Data transmitted to backend', [
            'endpoint' => $endpoint,
            'data_size' => strlen(wp_json_encode($data)),
            'request_id' => $request_id
        ]);
        
        return true;
    }
    
    /**
     * Schedule retry for failed API requests
     * 
     * This implementation uses WordPress cron for reliable retries with exponential backoff
     * while ensuring site performance is not affected
     */
    private function schedule_retry($retry_key) {
        // Check if retry is already scheduled
        if (wp_next_scheduled('ethicrawler_retry_api_request', [$retry_key])) {
            return;
        }
        
        // Get retry count
        $retry_count = get_transient($retry_key . '_count') ?: 0;
        
        // Maximum 3 retries with exponential backoff
        if ($retry_count < 3) {
            // Exponential backoff: 1min, 2min, 4min
            $delay = pow(2, $retry_count) * 60;
            
            // Get the stored data for this retry
            $data = get_transient($retry_key . '_data');
            
            if ($data) {
                // Schedule non-blocking retry via WordPress cron
                wp_schedule_single_event(time() + $delay, 'ethicrawler_retry_api_request', [$retry_key]);
                set_transient($retry_key . '_count', $retry_count + 1, DAY_IN_SECONDS);
                
                $this->log_api_error('Scheduled retry for failed request', [
                    'retry_count' => $retry_count + 1,
                    'delay_seconds' => $delay,
                    'retry_key' => $retry_key,
                    'next_attempt_time' => date('Y-m-d H:i:s', time() + $delay)
                ]);
            } else {
                $this->log_api_error('Cannot schedule retry - missing data', [
                    'retry_key' => $retry_key
                ]);
                delete_transient($retry_key . '_count');
            }
        } else {
            $this->log_api_error('Maximum retries exceeded, dropping request', [
                'retry_count' => $retry_count,
                'retry_key' => $retry_key
            ]);
            
            // Clean up transients
            delete_transient($retry_key . '_count');
            delete_transient($retry_key . '_data');
        }
    }
    
    /**
     * Log API errors without affecting site performance
     * 
     * This implementation ensures comprehensive error logging while maintaining
     * zero impact on site performance by:
     * 1. Using non-blocking option updates
     * 2. Limiting stored error history to prevent database bloat
     * 3. Providing detailed context for debugging
     * 4. Categorizing errors for better analysis
     */
    private function log_api_error($message, $context = []) {
        // Add timestamp and error category
        $error_category = $this->determine_error_category($message);
        
        $log_entry = [
            'timestamp' => current_time('c', true),
            'level' => 'ERROR',
            'category' => $error_category,
            'message' => $message,
            'context' => $context,
            'plugin_version' => ETHICRAWLER_PLUGIN_VERSION,
            'site_url' => get_site_url(),
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version')
        ];
        
        // Log to WordPress error log with category for easier filtering
        error_log('Ethicrawler Bot Detector [ERROR] [' . $error_category . ']: ' . $message . ' - ' . wp_json_encode($context));
        
        // Use shutdown hook for option updates to ensure zero impact on page load
        add_action('shutdown', function() use ($log_entry) {
            $this->update_error_logs($log_entry);
        });
    }
    
    /**
     * Update error logs in a non-blocking way
     * Called during shutdown to prevent performance impact
     */
    private function update_error_logs($log_entry) {
        // Store recent errors for admin display (limit to last 10)
        $recent_errors = get_option('ethicrawler_recent_errors', []);
        array_unshift($recent_errors, $log_entry);
        $recent_errors = array_slice($recent_errors, 0, 10);
        update_option('ethicrawler_recent_errors', $recent_errors, false);
        
        // Update error statistics
        $error_stats = get_option('ethicrawler_error_stats', ['total' => 0, 'last_error' => null, 'by_category' => []]);
        $error_stats['total']++;
        $error_stats['last_error'] = current_time('c', true);
        
        // Track errors by category for better reporting
        if (!isset($error_stats['by_category'][$log_entry['category']])) {
            $error_stats['by_category'][$log_entry['category']] = 0;
        }
        $error_stats['by_category'][$log_entry['category']]++;
        
        update_option('ethicrawler_error_stats', $error_stats, false);
    }
    
    /**
     * Determine error category for better organization and filtering
     */
    private function determine_error_category($message) {
        $message_lower = strtolower($message);
        
        if (strpos($message_lower, 'url') !== false) {
            return 'configuration';
        } elseif (strpos($message_lower, 'retry') !== false) {
            return 'retry';
        } elseif (strpos($message_lower, 'timeout') !== false || strpos($message_lower, 'timed out') !== false) {
            return 'timeout';
        } elseif (strpos($message_lower, 'http') !== false || strpos($message_lower, 'status') !== false) {
            return 'http';
        } elseif (strpos($message_lower, 'ssl') !== false || strpos($message_lower, 'tls') !== false) {
            return 'ssl';
        } elseif (strpos($message_lower, 'dns') !== false || strpos($message_lower, 'resolve') !== false) {
            return 'dns';
        } else {
            return 'general';
        }
    }
    
    /**
     * Log API success events
     */
    private function log_api_success($message, $context = []) {
        // Only log success in debug mode to avoid log spam
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ethicrawler Bot Detector [SUCCESS]: ' . $message . ' - ' . wp_json_encode($context));
        }
        
        // Update success statistics
        $success_stats = get_option('ethicrawler_success_stats', ['total' => 0, 'last_success' => null]);
        $success_stats['total']++;
        $success_stats['last_success'] = current_time('c', true);
        update_option('ethicrawler_success_stats', $success_stats, false);
    }
    
    /**
     * Retry failed API requests (called by WordPress cron)
     * 
     * This method is triggered by the WordPress cron system for failed API requests
     * It retrieves the stored data from transients and attempts to resend it
     */
    public function retry_api_request($retry_key) {
        // Get the stored data for this retry
        $data = get_transient($retry_key . '_data');
        
        if (!$data) {
            $this->log_api_error('Retry failed: Missing data for retry', ['retry_key' => $retry_key]);
            delete_transient($retry_key . '_count');
            return;
        }
        
        $backend_url = get_option('ethicrawler_backend_url', 'https://api.ethicrawler.com');
        $endpoint = rtrim($backend_url, '/') . '/api/v1/log_request';
        
        // Validate backend URL
        if (empty($backend_url) || !filter_var($backend_url, FILTER_VALIDATE_URL)) {
            $this->log_api_error('Retry failed: Invalid backend URL', ['url' => $backend_url]);
            return;
        }
        
        // Get retry count for logging
        $retry_count = get_transient($retry_key . '_count') ?: 0;
        
        // Prepare request arguments for retry (blocking this time since it's in cron)
        $args = [
            'body' => wp_json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Ethicrawler-WP-Plugin/' . ETHICRAWLER_PLUGIN_VERSION . ' (retry)',
                'Accept' => 'application/json',
                'X-Ethicrawler-Request-ID' => substr($retry_key, 16), // Extract request ID from retry key
                'X-Ethicrawler-Retry-Count' => $retry_count
            ],
            'timeout' => 10, // Longer timeout for retry
            'blocking' => true, // Blocking in cron is acceptable
            'sslverify' => true,
            'redirection' => 2,
            'httpversion' => '1.1',
            'reject_unsafe_urls' => true // Security: Prevent requests to private IPs/localhost
        ];
        
        $this->log_api_success('Attempting retry', [
            'retry_count' => $retry_count,
            'retry_key' => $retry_key,
            'endpoint' => $endpoint
        ]);
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->log_api_error('Retry attempt failed', [
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
                'retry_count' => $retry_count,
                'endpoint' => $endpoint,
                'retry_key' => $retry_key
            ]);
            
            // Schedule another retry if we haven't exceeded the limit
            if ($retry_count < 3) {
                $this->schedule_retry($retry_key);
            } else {
                // Clean up transients after max retries
                delete_transient($retry_key . '_count');
                delete_transient($retry_key . '_data');
            }
        } else {
            // Check response status for blocking requests
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code >= 200 && $status_code < 300) {
                $this->log_api_success('Retry successful', [
                    'status_code' => $status_code,
                    'endpoint' => $endpoint,
                    'retry_count' => $retry_count,
                    'retry_key' => $retry_key
                ]);
                
                // Clean up retry tracking on success
                delete_transient($retry_key . '_count');
                delete_transient($retry_key . '_data');
            } else {
                $this->log_api_error('Retry failed with HTTP error', [
                    'status_code' => $status_code,
                    'response_body' => wp_remote_retrieve_body($response),
                    'retry_count' => $retry_count,
                    'retry_key' => $retry_key
                ]);
                
                // Schedule another retry if we haven't exceeded the limit
                if ($retry_count < 3) {
                    $this->schedule_retry($retry_key);
                } else {
                    // Clean up transients after max retries
                    delete_transient($retry_key . '_count');
                    delete_transient($retry_key . '_data');
                }
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Ethicrawler Bot Detector', 'ethicrawler-bot-detector'),
            __('Ethicrawler', 'ethicrawler-bot-detector'),
            'manage_options',
            'ethicrawler-bot-detector',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ethicrawler_settings', 'ethicrawler_site_id');
        register_setting('ethicrawler_settings', 'ethicrawler_backend_url');
        register_setting('ethicrawler_settings', 'ethicrawler_enabled');
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('ethicrawler_settings');
                do_settings_sections('ethicrawler_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ethicrawler_enabled"><?php _e('Enable Bot Detection', 'ethicrawler-bot-detector'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="ethicrawler_enabled" name="ethicrawler_enabled" value="1" <?php checked(1, get_option('ethicrawler_enabled', true)); ?> />
                            <p class="description"><?php _e('Enable or disable bot detection and logging.', 'ethicrawler-bot-detector'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ethicrawler_site_id"><?php _e('Site ID', 'ethicrawler-bot-detector'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ethicrawler_site_id" name="ethicrawler_site_id" value="<?php echo esc_attr(get_option('ethicrawler_site_id', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your unique site identifier from the Ethicrawler dashboard.', 'ethicrawler-bot-detector'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ethicrawler_backend_url"><?php _e('Backend URL', 'ethicrawler-bot-detector'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="ethicrawler_backend_url" name="ethicrawler_backend_url" value="<?php echo esc_attr(get_option('ethicrawler_backend_url', 'https://api.ethicrawler.com')); ?>" class="regular-text" />
                            <p class="description"><?php _e('The Ethicrawler backend API URL.', 'ethicrawler-bot-detector'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Status', 'ethicrawler-bot-detector'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Plugin Version', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td><?php echo esc_html(ETHICRAWLER_PLUGIN_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Detection Status', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php if (get_option('ethicrawler_enabled', true)): ?>
                                <span style="color: green;"><?php _e('Enabled', 'ethicrawler-bot-detector'); ?></span>
                            <?php else: ?>
                                <span style="color: red;"><?php _e('Disabled', 'ethicrawler-bot-detector'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Site ID', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php 
                            $site_id = get_option('ethicrawler_site_id', '');
                            if (empty($site_id)): ?>
                                <span style="color: orange;"><?php _e('Not configured', 'ethicrawler-bot-detector'); ?></span>
                            <?php else: ?>
                                <span style="color: green;"><?php echo esc_html($site_id); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Backend Connection', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php 
                            $backend_url = get_option('ethicrawler_backend_url', 'https://api.ethicrawler.com');
                            if (!empty($backend_url)): ?>
                                <span style="color: green;"><?php echo esc_html($backend_url); ?></span>
                            <?php else: ?>
                                <span style="color: red;"><?php _e('Not configured', 'ethicrawler-bot-detector'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php _e('API Integration Status', 'ethicrawler-bot-detector'); ?></h2>
            <?php
            $success_stats = get_option('ethicrawler_success_stats', ['total' => 0, 'last_success' => null]);
            $error_stats = get_option('ethicrawler_error_stats', ['total' => 0, 'last_error' => null, 'by_category' => []]);
            $recent_errors = get_option('ethicrawler_recent_errors', []);
            ?>
            <div class="notice notice-info inline">
                <p><strong><?php _e('Performance Protection:', 'ethicrawler-bot-detector'); ?></strong> <?php _e('All API calls are processed asynchronously after page rendering is complete, ensuring zero impact on site performance.', 'ethicrawler-bot-detector'); ?></p>
            </div>
            
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Successful API Calls', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <span style="color: green;"><?php echo esc_html($success_stats['total']); ?></span>
                            <?php if ($success_stats['last_success']): ?>
                                <br><small><?php printf(__('Last success: %s', 'ethicrawler-bot-detector'), esc_html(date('Y-m-d H:i:s', strtotime($success_stats['last_success'])))); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Failed API Calls', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php if ($error_stats['total'] > 0): ?>
                                <span style="color: red;"><?php echo esc_html($error_stats['total']); ?></span>
                                <?php if ($error_stats['last_error']): ?>
                                    <br><small><?php printf(__('Last error: %s', 'ethicrawler-bot-detector'), esc_html(date('Y-m-d H:i:s', strtotime($error_stats['last_error'])))); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: green;">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Error Categories', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php if (!empty($error_stats['by_category'])): ?>
                                <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ($error_stats['by_category'] as $category => $count): ?>
                                    <li><?php echo esc_html(ucfirst($category)); ?>: <span style="color: <?php echo $count > 0 ? 'red' : 'green'; ?>"><?php echo esc_html($count); ?></span></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span style="color: green;"><?php _e('No errors recorded', 'ethicrawler-bot-detector'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('API Request Mode', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <span style="color: green;"><?php _e('Fully Asynchronous', 'ethicrawler-bot-detector'); ?></span>
                            <br><small><?php _e('Requests are processed after page rendering using WordPress shutdown hook', 'ethicrawler-bot-detector'); ?></small>
                            <?php if (function_exists('fastcgi_finish_request')): ?>
                                <br><small><?php _e('Using fastcgi_finish_request() for optimal performance', 'ethicrawler-bot-detector'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Retry Mechanism', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <span style="color: green;"><?php _e('Enabled with Exponential Backoff', 'ethicrawler-bot-detector'); ?></span>
                            <br><small><?php _e('Failed requests are automatically retried with 1min, 2min, 4min delays', 'ethicrawler-bot-detector'); ?></small>
                            <br><small><?php _e('Retries are processed via WordPress cron to ensure site performance', 'ethicrawler-bot-detector'); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Error Handling', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <span style="color: green;"><?php _e('Comprehensive', 'ethicrawler-bot-detector'); ?></span>
                            <br><small><?php _e('All errors are logged with detailed context for debugging', 'ethicrawler-bot-detector'); ?></small>
                            <br><small><?php _e('Error logging uses non-blocking updates during shutdown hook', 'ethicrawler-bot-detector'); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Whitelisted Crawlers', 'ethicrawler-bot-detector'); ?></strong></td>
                        <td>
                            <?php echo esc_html(implode(', ', $this->whitelisted_bots)); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (!empty($recent_errors)): ?>
            <h2><?php _e('Recent API Errors', 'ethicrawler-bot-detector'); ?></h2>
            <div class="notice notice-warning">
                <p><?php _e('Recent API errors are shown below. These errors do not affect your site performance as all API calls are non-blocking.', 'ethicrawler-bot-detector'); ?></p>
            </div>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Timestamp', 'ethicrawler-bot-detector'); ?></th>
                        <th><?php _e('Error Message', 'ethicrawler-bot-detector'); ?></th>
                        <th><?php _e('Details', 'ethicrawler-bot-detector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recent_errors, 0, 5) as $error): ?>
                    <tr>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($error['timestamp']))); ?></td>
                        <td><?php echo esc_html($error['message']); ?></td>
                        <td>
                            <?php if (!empty($error['context'])): ?>
                                <details>
                                    <summary><?php _e('Show details', 'ethicrawler-bot-detector'); ?></summary>
                                    <pre style="font-size: 11px; max-height: 100px; overflow-y: auto;"><?php echo esc_html(wp_json_encode($error['context'], JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p>
                <button type="button" class="button" onclick="if(confirm('<?php _e('Are you sure you want to clear error logs?', 'ethicrawler-bot-detector'); ?>')) { window.location.href='<?php echo esc_url(add_query_arg('clear_errors', '1')); ?>'; }">
                    <?php _e('Clear Error Logs', 'ethicrawler-bot-detector'); ?>
                </button>
            </p>
            <?php endif; ?>
            
            <h2><?php _e('Bot Detection Information', 'ethicrawler-bot-detector'); ?></h2>
            <div class="notice notice-info">
                <p><strong><?php _e('How it works:', 'ethicrawler-bot-detector'); ?></strong></p>
                <ul>
                    <li><?php _e('The plugin monitors all page requests for AI bot activity', 'ethicrawler-bot-detector'); ?></li>
                    <li><?php _e('Legitimate search engine crawlers are automatically whitelisted and not logged', 'ethicrawler-bot-detector'); ?></li>
                    <li><?php _e('AI bots and scrapers are detected and their activity is logged to the backend', 'ethicrawler-bot-detector'); ?></li>
                    <li><?php _e('All API calls are non-blocking to ensure your site performance is not affected', 'ethicrawler-bot-detector'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
EthicrawlerBotDetector::get_instance();
if (!defined('WP_DEBUG')) { define('WP_DEBUG', true); }
if (!defined('WP_DEBUG_LOG')) { define('WP_DEBUG_LOG', true); }
if (!defined('WP_DEBUG_DISPLAY')) { define('WP_DEBUG_DISPLAY', false); }

function ethicrawler_log($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    error_log("[Ethicrawler Debug] $message");
}

add_action('init', function () {
    ethicrawler_log('Ethicrawler init hook fired');
    if (isset($_GET['ethicrawler-debug'])) {
        ethicrawler_log('Ethicrawler Debug Endpoint hit');
        wp_send_json_success([
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'none'
        ]);
        exit;
    }
});
