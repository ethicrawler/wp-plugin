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
     * Send data to backend API
     */
    private function send_to_backend($data) {
        $backend_url = get_option('ethicrawler_backend_url', 'https://api.ethicrawler.com');
        $endpoint = rtrim($backend_url, '/') . '/api/v1/log_request';
        
        $args = [
            'body' => wp_json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Ethicrawler-WP-Plugin/' . ETHICRAWLER_PLUGIN_VERSION
            ],
            'timeout' => 5,
            'blocking' => false, // Non-blocking to prevent site slowdown
            'sslverify' => true
        ];
        
        $response = wp_remote_post($endpoint, $args);
        
        // Log errors for debugging (only in non-blocking mode for monitoring)
        if (is_wp_error($response)) {
            error_log('Ethicrawler Bot Detector: API request failed - ' . $response->get_error_message());
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
                </tbody>
            </table>
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
