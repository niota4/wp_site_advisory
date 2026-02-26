<?php
/**
 * Plugin Name: WP SiteAdvisor
 * Plugin URI: https://wpsiteadvisor.com
 * Description: AI-powered WordPress plugin that scans installed plugins, detects conflicts, identifies missing integrations like Google Analytics/Search Console, and provides actionable recommendations. Includes a dashboard in WP admin and AI integration via OpenAI API.
 * Version: 2.4.0
 * Author: WP SiteAdvisor
 * Author URI: https://wpsiteadvisor.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-advisory
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_SITE_ADVISORY_VERSION', '2.4.0');
define('WP_SITE_ADVISORY_PATH', plugin_dir_path(__FILE__));
define('WP_SITE_ADVISORY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SITE_ADVISORY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SITE_ADVISORY_PLUGIN_FILE', __FILE__);
define('WP_SITE_ADVISORY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WP_Site_Advisory Class
 */
class WP_Site_Advisory {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    public $version = '2.4.0';

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Plugin activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin after WordPress is loaded
        add_action('init', array($this, 'init'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Ajax handlers
        add_action('wp_ajax_wsa_scan_site', array($this, 'ajax_scan_site'));
        // Only register AI recommendations if Pro plugin is not active
        if (!class_exists('WSA_Pro_AI_Analyzer')) {
            add_action('wp_ajax_wsa_get_ai_recommendations', array($this, 'ajax_get_ai_recommendations'));
        }
        add_action('wp_ajax_wsa_remove_api_key', array($this, 'ajax_remove_api_key'));
        add_action('wp_ajax_wsa_refresh_usage', array($this, 'ajax_refresh_usage'));
        add_action('wp_ajax_wsa_scan_system', array($this, 'ajax_scan_system'));
        add_action('wp_ajax_wsa_get_system_data', array($this, 'ajax_get_system_data'));
        add_action('wp_ajax_wsa_get_scan_progress', array($this, 'ajax_get_scan_progress'));
        
        // Pro feature AJAX handlers
        add_action('wp_ajax_wsa_track_pro_attempt', array($this, 'ajax_track_pro_attempt'));
        add_action('wp_ajax_wsa_dismiss_pro_notice', array($this, 'ajax_dismiss_pro_notice'));
        
        // Only register AI AJAX handlers if Pro plugin is not active
        if (!class_exists('WSA_Pro') || !wsa_is_pro_active()) {
            add_action('wp_ajax_wsa_run_ai_analysis', array($this, 'ajax_run_ai_analysis'));
            add_action('wp_ajax_wsa_get_ai_status', array($this, 'ajax_get_ai_status'));
        }
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Cron hooks
        add_action('wsa_scheduled_scan', array($this, 'perform_scheduled_scan'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Schedule cron job
        if (!wp_next_scheduled('wsa_scheduled_scan')) {
            wp_schedule_event(time(), 'daily', 'wsa_scheduled_scan');
        }
        
        // Set default options
        $this->set_default_options();
        
        // Update plugin version for Pro compatibility
        update_option('wsa_plugin_version', WP_SITE_ADVISORY_VERSION);
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('wsa_scheduled_scan');
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain('wp-site-advisory', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Update plugin version for Pro compatibility
        $current_version = get_option('wsa_plugin_version', '1.0.0');
        if (version_compare($current_version, WP_SITE_ADVISORY_VERSION, '<')) {
            update_option('wsa_plugin_version', WP_SITE_ADVISORY_VERSION);
        }
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        $this->init_components();
    }

    /**
     * Include required files
     */
    private function include_files() {
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-plugin-scanner.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-theme-scanner.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-google-detector.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-openai-handler.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-openai-usage.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-system-scanner.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-weekly-report.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-pro-helper.php';
        require_once WP_SITE_ADVISORY_PLUGIN_DIR . 'includes/class-branding.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize Pro helper first
        WP_Site_Advisory_Pro_Helper::get_instance();
        
        // Initialize scanner
        WP_Site_Advisory_Plugin_Scanner::get_instance();
        
        // Initialize Google detector
        WP_Site_Advisory_Google_Detector::get_instance();
        
        // Initialize OpenAI handler
        WP_Site_Advisory_OpenAI_Handler::get_instance();
        
        // Initialize system scanner
        WP_Site_Advisory_System_Scanner::get_instance();
        
        // Initialize weekly report
        WP_Site_Advisory_Weekly_Report::get_instance();
        
        // Initialize branding system
        if (class_exists('WP_Site_Advisory_Branding')) {
            WP_Site_Advisory_Branding::get_instance();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('WP SiteAdvisor', 'wp-site-advisory'),
            __('WP SiteAdvisor', 'wp-site-advisory'),
            'manage_options',
            'wp-site-advisory',
            array($this, 'admin_page_dashboard'),
            'dashicons-chart-line',
            30
        );
        
        // Settings submenu (only if unified settings aren't available)
        if (!class_exists('WP_Site_Advisory_Unified_Settings')) {
            add_submenu_page(
                'wp-site-advisory',
                __('Settings', 'wp-site-advisory'),
                __('Settings', 'wp-site-advisory'),
                'manage_options',
                'wp-site-advisory-settings',
                array($this, 'admin_page_settings')
            );
        }
        
        // Pro upgrade submenu (only if Pro is not active)
        if (!wsa_is_pro_active()) {
            add_submenu_page(
                'wp-site-advisory',
                __('Upgrade to Pro', 'wp-site-advisory'),
                '<span style="color: #0073aa;">🔒 ' . __('Upgrade to Pro', 'wp-site-advisory') . '</span>',
                'manage_options',
                'wp-site-advisory-upgrade',
                array($this, 'admin_page_upgrade')
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'wp-site-advisory') === false) {
            return;
        }

        // Enqueue CSS (use minified version in production)
        $css_file = defined('WP_DEBUG') && WP_DEBUG ? 'admin.css' : 'admin.min.css';
        wp_enqueue_style(
            'wp-site-advisory-admin',
            WP_SITE_ADVISORY_PLUGIN_URL . 'assets/css/' . $css_file,
            array(),
            WP_SITE_ADVISORY_VERSION
        );
        
        // Enqueue Pro upgrade CSS
        wp_enqueue_style(
            'wp-site-advisory-pro-upgrade',
            WP_SITE_ADVISORY_PLUGIN_URL . 'assets/css/pro-upgrade.css',
            array('wp-site-advisory-admin'),
            WP_SITE_ADVISORY_VERSION
        );

        // Enqueue JavaScript (use minified version in production)
        $js_file = defined('WP_DEBUG') && WP_DEBUG ? 'admin.js' : 'admin.min.js';
        wp_enqueue_script(
            'wp-site-advisory-admin',
            WP_SITE_ADVISORY_PLUGIN_URL . 'assets/js/' . $js_file,
            array('jquery'),
            WP_SITE_ADVISORY_VERSION,
            true
        );
        
        // Enqueue Pro upgrade JavaScript
        wp_enqueue_script(
            'wp-site-advisory-pro-upgrade',
            WP_SITE_ADVISORY_PLUGIN_URL . 'assets/js/pro-upgrade.js',
            array('jquery', 'wp-site-advisory-admin'),
            WP_SITE_ADVISORY_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wp-site-advisory-admin', 'wsa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_admin_nonce'),
            'has_pro' => defined('WP_SITE_ADVISORY_PRO_VERSION'),
            'strings' => array(
                'scanning' => __('Scanning...', 'wp-site-advisory'),
                'getting_recommendations' => __('Getting AI recommendations...', 'wp-site-advisory'),
                'error' => __('An error occurred. Please try again.', 'wp-site-advisory'),
                'all_plugins_text' => __('Active Plugins', 'wp-site-advisory'),
                'filtered_plugins_text' => __('Plugins Needing Attention', 'wp-site-advisory'),
            )
        ));
        
        // Localize Pro upgrade script
        wp_localize_script('wp-site-advisory-pro-upgrade', 'wsa_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_pro_nonce'),
            'pro_active' => wsa_is_pro_active()
        ));
        
        // Pass Pro status to frontend
        wp_add_inline_script('wp-site-advisory-pro-upgrade', 
            'window.wsa_pro_active = ' . (wsa_is_pro_active() ? 'true' : 'false') . ';'
        );
    }

    /**
     * Dashboard admin page
     */
    public function admin_page_dashboard() {
        $dashboard = new WP_Site_Advisory_Admin_Dashboard();
        $dashboard->render();
    }

    /**
     * Settings admin page
     */
    public function admin_page_settings() {
        $settings = new WP_Site_Advisory_Settings();
        $settings->render();
    }

    /**
     * Pro upgrade admin page
     */
    public function admin_page_upgrade() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wsa-upgrade-page">
                <div class="wsa-upgrade-header">
                    <h2><?php _e('Unlock the Full Power of WP SiteAdvisor', 'wp-site-advisory'); ?></h2>
                    <p class="wsa-upgrade-subtitle"><?php _e('Get advanced features, AI-powered analysis, and priority support', 'wp-site-advisory'); ?></p>
                </div>

                <?php 
                WP_Site_Advisory_Pro_Helper::get_instance()->render_pro_showcase();
                
                // Add comparison table
                $this->render_comparison_table();
                ?>
                
                <div class="wsa-upgrade-testimonials">
                    <h3><?php _e('What Our Pro Users Say', 'wp-site-advisory'); ?></h3>
                    <div class="wsa-testimonials-grid">
                        <blockquote>
                            <p>"WP SiteAdvisor Pro saved me hours of manual security audits. The AI recommendations are spot-on!"</p>
                            <cite>— Sarah M., Web Developer</cite>
                        </blockquote>
                        <blockquote>
                            <p>"Perfect for managing multiple client sites. The white-label reports are professional and detailed."</p>
                            <cite>— Mike R., Agency Owner</cite>
                        </blockquote>
                        <blockquote>
                            <p>"The vulnerability scanning caught issues I would have missed. Worth every penny."</p>
                            <cite>— Jessica L., Site Owner</cite>
                        </blockquote>
                    </div>
                </div>

                <div class="wsa-upgrade-final-cta">
                    <h3><?php _e('Ready for AI Automation?', 'wp-site-advisory'); ?></h3>
                    <p><?php _e('Join thousands of WordPress professionals using AI to optimize their sites automatically', 'wp-site-advisory'); ?></p>
                    <a href="<?php echo esc_url(WP_Site_Advisory_Pro_Helper::get_instance()->get_upgrade_url('upgrade_page')); ?>" 
                       class="button button-primary button-hero" target="_blank">
                        <?php _e('Activate AI Pro Features', 'wp-site-advisory'); ?>
                    </a>
                    <p class="wsa-pro-benefits"><?php _e('Instant activation • AI-powered automation • Professional support', 'wp-site-advisory'); ?></p>
                </div>
            </div>
        </div>

        <style>
        .wsa-upgrade-page {
            max-width: 1000px;
            margin: 0 auto;
        }
        .wsa-upgrade-header {
            text-align: center;
            margin: 40px 0;
        }
        .wsa-upgrade-header h2 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }
        .wsa-upgrade-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
        }
        .wsa-testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        .wsa-testimonials-grid blockquote {
            background: #f8f9fa;
            padding: 25px;
            border-left: 4px solid #0073aa;
            font-style: italic;
            margin: 0;
        }
        .wsa-testimonials-grid cite {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #666;
        }
        .wsa-upgrade-final-cta {
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa, #fff);
            padding: 50px;
            border-radius: 0px;
            margin: 50px 0;
            border: 2px dashed #0073aa;
        }
        .wsa-money-back {
            color: #666;
            font-size: 14px;
            margin-top: 15px;
        }
        </style>
        <?php
    }

    /**
     * Render comparison table
     */
    private function render_comparison_table() {
        ?>
        <div class="wsa-comparison-table">
            <h3><?php _e('Free vs Pro Features', 'wp-site-advisory'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Feature', 'wp-site-advisory'); ?></th>
                        <th><?php _e('Free', 'wp-site-advisory'); ?></th>
                        <th><?php _e('Pro', 'wp-site-advisory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Plugin & Theme Scanning', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('Basic Security Checks', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('Google Integrations Detection', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('AI-Powered Analysis', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('Advanced Vulnerability Scanning', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('One-Click Fixes', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('White-Label Reports', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('Advanced Scheduling', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <td><?php _e('Priority Support', 'wp-site-advisory'); ?></td>
                        <td><span class="dashicons dashicons-minus" style="color: #dc3232;"></span></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <style>
        .wsa-comparison-table {
            margin: 40px 0;
        }
        .wsa-comparison-table h3 {
            text-align: center;
            margin-bottom: 20px;
        }
        .wsa-comparison-table table {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .wsa-comparison-table th {
            background: #0073aa;
            color: white;
            text-align: center;
            padding: 15px;
        }
        .wsa-comparison-table td {
            padding: 12px 15px;
            text-align: center;
        }
        .wsa-comparison-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .wsa-comparison-table td:first-child {
            text-align: left;
            font-weight: 500;
        }
        </style>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings() {
        $settings = new WP_Site_Advisory_Settings();
        $settings->register();
    }

    /**
     * AJAX handler for site scan
     */
    public function ajax_scan_site() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory'));
        }

        $scanner = WP_Site_Advisory_Plugin_Scanner::get_instance();
        $theme_scanner = new WP_Site_Advisory_Theme_Scanner();
        $google_detector = WP_Site_Advisory_Google_Detector::get_instance();
        
        $result = array(
            'plugins' => $scanner->scan_plugins(),
            'theme_analysis' => $theme_scanner->scan_theme(),
            'google_integrations' => $google_detector->detect_integrations(),
            'timestamp' => current_time('mysql')
        );
        
        // Store scan results
        update_option('wsa_last_scan_results', $result);
        
        wp_send_json_success($result);
    }

    /**
     * AJAX handler for AI recommendations
     */
    public function ajax_get_ai_recommendations() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory'));
        }

        $openai_handler = WP_Site_Advisory_OpenAI_Handler::get_instance();
        $scan_data = get_option('wsa_last_scan_results', array());
        
        if (empty($scan_data)) {
            wp_send_json_error(array('message' => __('Please run a site scan first.', 'wp-site-advisory')));
        }
        
        $recommendations = $openai_handler->get_recommendations($scan_data);
        
        if (is_wp_error($recommendations)) {
            wp_send_json_error(array('message' => $recommendations->get_error_message()));
        }
        
        wp_send_json_success($recommendations);
    }

    /**
     * AJAX handler for removing API key
     */
    public function ajax_remove_api_key() {
        // Check nonce
        if (!check_ajax_referer('wsa_remove_api_key', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-site-advisory')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-site-advisory')));
        }

        // Remove the API key
        $result = delete_option('wsa_openai_api_key');
        
        // Verify it was actually removed
        $new_key = get_option('wsa_openai_api_key', '');
        
        if (empty($new_key)) {
            wp_send_json_success(array('message' => __('API key removed successfully.', 'wp-site-advisory')));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove API key.', 'wp-site-advisory')));
        }
    }

    /**
     * AJAX handler for refreshing OpenAI usage data
     */
    public function ajax_refresh_usage() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_refresh_usage')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-site-advisory')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to refresh usage data.', 'wp-site-advisory')));
        }
        
        // Get fresh usage data
        $usage_handler = new WP_Site_Advisory_OpenAI_Usage();
        $usage_data = $usage_handler->refresh_usage_data();
        $display_data = $usage_handler->format_for_display($usage_data);
        
        if ($display_data['error']) {
            wp_send_json_error(array(
                'message' => $display_data['message'],
                'code' => $display_data['code']
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Usage data refreshed successfully.', 'wp-site-advisory'),
            'data' => $display_data,
            'updated_at' => current_time('Y-m-d H:i:s')
        ));
    }

    /**
     * AJAX handler for system scan
     */
    public function ajax_scan_system() {
        // Force immediate output to check if function is called
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wp-site-advisory'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wp-site-advisory'));
        }
        
        try {
            // Initialize progress tracking
            $scan_id = uniqid('scan_');
            update_option('wsa_scan_progress_' . $scan_id, array(
                'status' => 'Starting system scan...',
                'progress' => 0,
                'step' => 1,
                'total_steps' => 6
            ));
            
            // Update progress
            $this->update_scan_progress($scan_id, 'Checking security configurations...', 1, 6);
            
            // Perform system scan
            $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
            
            // Update progress during scan
            $this->update_scan_progress($scan_id, 'Scanning file permissions...', 2, 6);
            
            $results = $system_scanner->scan_system();
            
            $this->update_scan_progress($scan_id, 'Analyzing performance metrics...', 4, 6);
            
            if (isset($results['error'])) {
                wp_send_json_error($results['error']);
            }
            
            $this->update_scan_progress($scan_id, 'Finalizing scan results...', 5, 6);
            
            // Store results
            update_option('wsa_system_scan_results', $results);
            
            $this->update_scan_progress($scan_id, 'Scan completed successfully!', 6, 6);
            
            // Add scan_id to results for frontend tracking
            $results['scan_id'] = $scan_id;
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
        } catch (Error $e) {
            wp_send_json_error('Fatal error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler to get system data
     */
    public function ajax_get_system_data() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wp-site-advisory'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wp-site-advisory'));
        }
        
        // Get stored system scan results
        $results = get_option('wsa_system_scan_results', array());
        
        if (empty($results)) {
            // If no stored results, perform a new scan
            $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
            $results = $system_scanner->scan_system();
            
            if (!isset($results['error'])) {
                update_option('wsa_system_scan_results', $results);
            }
        }
        
        wp_send_json_success($results);
    }

    /**
     * AJAX handler to track Pro feature attempts
     */
    public function ajax_track_pro_attempt() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wp-site-advisory'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wp-site-advisory'));
        }
        
        $feature = sanitize_text_field($_POST['feature']);
        
        // Track the Pro feature attempt
        wsa_track_pro_attempt($feature);
        
        wp_send_json_success(array(
            'message' => __('Pro feature attempt tracked.', 'wp-site-advisory'),
            'feature' => $feature
        ));
    }

    /**
     * AJAX handler to dismiss Pro notices
     */
    public function ajax_dismiss_pro_notice() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wp-site-advisory'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wp-site-advisory'));
        }
        
        $feature = sanitize_text_field($_POST['feature']);
        $user_id = get_current_user_id();
        
        // Store dismissed notice to prevent showing again for a while
        $dismissed_notices = get_user_meta($user_id, 'wsa_dismissed_pro_notices', true);
        
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = array();
        }
        
        $dismissed_notices[$feature] = time();
        
        // Keep only recent dismissals (last 7 days)
        $week_ago = time() - (7 * 24 * 60 * 60);
        $dismissed_notices = array_filter($dismissed_notices, function($timestamp) use ($week_ago) {
            return $timestamp > $week_ago;
        });
        
        update_user_meta($user_id, 'wsa_dismissed_pro_notices', $dismissed_notices);
        
        wp_send_json_success(array(
            'message' => __('Pro notice dismissed.', 'wp-site-advisory'),
            'feature' => $feature
        ));
    }

    /**
     * Perform scheduled scan
     */
    public function perform_scheduled_scan() {
        $scanner = WP_Site_Advisory_Plugin_Scanner::get_instance();
        $theme_scanner = new WP_Site_Advisory_Theme_Scanner();
        $google_detector = WP_Site_Advisory_Google_Detector::get_instance();
        
        $result = array(
            'plugins' => $scanner->scan_plugins(),
            'theme_analysis' => $theme_scanner->scan_theme(),
            'google_integrations' => $google_detector->detect_integrations(),
            'timestamp' => current_time('mysql')
        );
        
        // Store scan results
        update_option('wsa_last_scan_results', $result);
        update_option('wsa_last_scheduled_scan', current_time('mysql'));
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for scan history
        $table_name = $wpdb->prefix . 'wsa_scan_history';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            scan_type varchar(50) NOT NULL,
            scan_data longtext NOT NULL,
            recommendations longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'wsa_openai_api_key' => '',
            'wsa_scan_frequency' => 'daily',
            'wsa_enable_notifications' => 1,
            'wsa_notification_email' => get_option('admin_email'),
            'wsa_weekly_reports' => 0,
            'wsa_report_day' => 'monday',
            'wsa_report_time' => '09:00',
            'wsa_report_recipients' => get_option('admin_email'),
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Update scan progress for real-time feedback
     */
    private function update_scan_progress($scan_id, $status, $step, $total_steps) {
        $progress = array(
            'status' => $status,
            'progress' => round(($step / $total_steps) * 100),
            'step' => $step,
            'total_steps' => $total_steps,
            'timestamp' => current_time('mysql')
        );
        
        update_option('wsa_scan_progress_' . $scan_id, $progress);
        
        // Force output buffer flush for real-time updates
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * AJAX handler to get scan progress
     */
    public function ajax_get_scan_progress() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wp-site-advisory'));
        }

        $scan_id = sanitize_text_field($_POST['scan_id']);
        $progress = get_option('wsa_scan_progress_' . $scan_id, false);
        
        if (!$progress) {
            wp_send_json_error(__('Scan progress not found.', 'wp-site-advisory'));
        }
        
        wp_send_json_success($progress);
    }

    /**
     * AJAX handler for AI analysis - delegates to Pro plugin if available
     */
    public function ajax_run_ai_analysis() {
        // Check permissions first
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check if Pro plugin is available and delegate
        if (class_exists('WSA_Pro') && wsa_is_pro_active()) {
            $wsa_pro = WSA_Pro::get_instance();
            
            // Check if AI Dashboard exists and has the method
            if (isset($wsa_pro->ai_dashboard) && method_exists($wsa_pro->ai_dashboard, 'ajax_run_ai_analysis')) {
                $wsa_pro->ai_dashboard->ajax_run_ai_analysis();
                return;
            }
        }

        // If Pro plugin not available, return appropriate message
        wp_send_json_error(array(
            'message' => 'AI features are available in WP SiteAdvisor Pro. Please upgrade to access advanced AI-powered analysis.',
            'upgrade_required' => true
        ));
    }

    /**
     * AJAX handler for AI status - delegates to Pro plugin if available  
     */
    public function ajax_get_ai_status() {
        // Check permissions first
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check if Pro plugin is available and delegate
        if (class_exists('WSA_Pro') && wsa_is_pro_active()) {
            $wsa_pro = WSA_Pro::get_instance();
            
            // Check if AI Dashboard exists and has the method
            if (isset($wsa_pro->ai_dashboard) && method_exists($wsa_pro->ai_dashboard, 'ajax_get_ai_status')) {
                $wsa_pro->ai_dashboard->ajax_get_ai_status();
                return;
            }
        }

        // If Pro plugin not available, return basic status
        wp_send_json_success(array(
            'statuses' => array(
                'optimizer' => '<span class="status-indicator status-inactive">Pro Required</span>',
                'content' => '<span class="status-indicator status-inactive">Pro Required</span>',
                'analytics' => '<span class="status-indicator status-inactive">Pro Required</span>',
                'pagespeed' => '<span class="status-indicator status-inactive">Pro Required</span>',
                'reports' => '<span class="status-indicator status-inactive">Pro Required</span>',
                'ai-config' => '<span class="status-indicator status-inactive">Pro Required</span>'
            ),
            'stats' => array(
                'last-optimization' => 'Pro Required',
                'content-analyzed' => 'Pro Required',
                'predictions-generated' => 'Pro Required',
                'reports-generated' => 'Pro Required'
            )
        ));
    }
}

/**
 * Initialize the plugin
 */
function wp_site_advisory_init() {
    return WP_Site_Advisory::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'wp_site_advisory_init');

/**
 * Global helper function to check if Pro version is active and licensed
 * 
 * @return bool True if Pro is active and licensed, false otherwise
 */
function wsa_is_pro_active() {
    // Check if Pro plugin is active
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $pro_plugin_file = 'wp-site-advisory-pro/wp-site-advisory-pro.php';
    
    if (!is_plugin_active($pro_plugin_file)) {
        return false;
    }
    
    // Check if Pro license validation function exists and returns valid license
    if (function_exists('wsa_pro_is_license_active')) {
        return wsa_pro_is_license_active();
    }
    
    return false;
}

/**
 * Plugin uninstall hook
 */
function wp_site_advisory_uninstall() {
    global $wpdb;
    
    // Remove options
    delete_option('wsa_openai_api_key');
    delete_option('wsa_scan_frequency');
    delete_option('wsa_enable_notifications');
    delete_option('wsa_notification_email');
    delete_option('wsa_last_scan_results');
    delete_option('wsa_last_scheduled_scan');
    
    // Remove tables
    $table_name = $wpdb->prefix . 'wsa_scan_history';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('wsa_scheduled_scan');
}

register_uninstall_hook(__FILE__, 'wp_site_advisory_uninstall');