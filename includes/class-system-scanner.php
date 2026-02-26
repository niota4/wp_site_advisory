<?php
/**
 * WP Site Advisory System Scanner Class
 * 
 * Handles security checks, performance checks, and inactive plugin/theme detection
 * 
 * @package WP_Site_Advisory
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_System_Scanner {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Constructor intentionally empty
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
     * Perform comprehensive system scan
     * 
     * @return array System scan results
     */
    public function scan_system() {
        file_put_contents('/Applications/MAMP/htdocs/wpsiteadvisor/wp-content/debug-scan.log', 
            date('Y-m-d H:i:s') . " - System scanner scan_system method called\n", FILE_APPEND);
            
        // Check user capability
        if (!current_user_can('manage_options')) {
            file_put_contents('/Applications/MAMP/htdocs/wpsiteadvisor/wp-content/debug-scan.log', 
                date('Y-m-d H:i:s') . " - Insufficient permissions in scan_system\n", FILE_APPEND);
            return array('error' => 'Insufficient permissions');
        }
        
        try {
            $results = array('last_scanned' => current_time('mysql'));
            
            // Safe execution of each scan component
            try {
                $results['inactive_plugins'] = $this->detect_inactive_plugins();
            } catch (Exception $e) {
                $results['inactive_plugins'] = array('error' => 'Inactive plugins scan failed');
            }
            
            try {
                $results['inactive_themes'] = $this->detect_inactive_themes();
            } catch (Exception $e) {
                $results['inactive_themes'] = array('error' => 'Inactive themes scan failed');
            }
            
            try {
                $results['security_checks'] = $this->perform_security_checks();
            } catch (Exception $e) {
                $results['security_checks'] = array('error' => 'Security checks failed');
            }
            
            try {
                $results['performance_checks'] = $this->perform_performance_checks();
            } catch (Exception $e) {
                $results['performance_checks'] = array('error' => 'Performance checks failed');
            }
            
            return $results;
        } catch (Exception $e) {
            return array('error' => 'System scan failed: ' . $e->getMessage());
        } catch (Error $e) {
            return array('error' => 'System scan failed: Fatal error occurred');
        }
    }
    
    /**
     * Detect inactive plugins and recommend removal
     * 
     * @return array Inactive plugins information
     */
    private function detect_inactive_plugins() {
        // Include plugin.php if not already loaded
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $inactive_plugins = array();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (!in_array($plugin_file, $active_plugins)) {
                $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
                
                $inactive_plugins[] = array(
                    'file' => $plugin_file,
                    'name' => sanitize_text_field($plugin_data['Name']),
                    'version' => sanitize_text_field($plugin_data['Version']),
                    'author' => sanitize_text_field($plugin_data['Author']),
                    'description' => sanitize_textarea_field($plugin_data['Description']),
                    'size' => is_dir($plugin_dir) ? $this->get_directory_size($plugin_dir) : 0,
                    'last_modified' => is_dir($plugin_dir) ? date('Y-m-d H:i:s', filemtime($plugin_dir)) : '',
                    'security_risk' => $this->assess_inactive_plugin_risk($plugin_file, $plugin_dir),
                    'recommendations' => $this->get_inactive_plugin_recommendations($plugin_data)
                );
            }
        }
        
        return array(
            'count' => count($inactive_plugins),
            'plugins' => $inactive_plugins,
            'total_size' => array_sum(array_column($inactive_plugins, 'size')),
            'recommendations' => $this->generate_inactive_plugins_recommendations(count($inactive_plugins))
        );
    }
    
    /**
     * Detect inactive themes
     * 
     * @return array Inactive themes information
     */
    private function detect_inactive_themes() {
        $all_themes = wp_get_themes();
        $current_theme = get_stylesheet();
        $parent_theme = get_template();
        $inactive_themes = array();
        
        foreach ($all_themes as $theme_slug => $theme) {
            // Skip if it's the current theme or parent theme
            if ($theme_slug === $current_theme || $theme_slug === $parent_theme) {
                continue;
            }
            
            $theme_dir = $theme->get_stylesheet_directory();
            
            $inactive_themes[] = array(
                'slug' => $theme_slug,
                'name' => sanitize_text_field($theme->get('Name')),
                'version' => sanitize_text_field($theme->get('Version')),
                'author' => sanitize_text_field($theme->get('Author')),
                'description' => sanitize_textarea_field($theme->get('Description')),
                'size' => is_dir($theme_dir) ? $this->get_directory_size($theme_dir) : 0,
                'last_modified' => is_dir($theme_dir) ? date('Y-m-d H:i:s', filemtime($theme_dir)) : '',
                'is_child_theme' => $theme->parent() ? true : false,
                'security_risk' => $this->assess_inactive_theme_risk($theme_dir),
                'recommendations' => $this->get_inactive_theme_recommendations($theme)
            );
        }
        
        return array(
            'count' => count($inactive_themes),
            'themes' => $inactive_themes,
            'total_size' => array_sum(array_column($inactive_themes, 'size')),
            'recommendations' => $this->generate_inactive_themes_recommendations(count($inactive_themes))
        );
    }
    
    /**
     * Perform basic security checks
     * 
     * @return array Security check results
     */
    private function perform_security_checks() {
        $checks = array(
            'admin_username' => $this->check_admin_username(),
            'ssl_enabled' => $this->check_ssl_status(),
            'wp_core_updated' => $this->check_wp_core_updates(),
            'file_permissions' => $this->check_file_permissions(),
            'debug_mode' => $this->check_debug_mode(),
            'file_editing' => $this->check_file_editing_disabled()
        );
        
        // Calculate overall security score
        $security_score = $this->calculate_security_score($checks);
        
        return array(
            'checks' => $checks,
            'security_score' => $security_score,
            'recommendations' => $this->generate_security_recommendations($checks),
            'issues_found' => count(array_filter($checks, function($check) {
                return isset($check['status']) && $check['status'] === 'warning';
            }))
        );
    }
    
    /**
     * Perform basic performance checks
     * 
     * @return array Performance check results
     */
    private function perform_performance_checks() {
        $checks = array(
            'database_size' => $this->check_database_size(),
            'wp_cron' => $this->check_wp_cron_status(),
            'memory_limit' => $this->check_memory_limit(),
            'upload_max_filesize' => $this->check_upload_limits(),
            'object_cache' => $this->check_object_cache(),
            'gzip_compression' => $this->check_gzip_compression()
        );
        
        return array(
            'checks' => $checks,
            'recommendations' => $this->generate_performance_recommendations($checks),
            'issues_found' => count(array_filter($checks, function($check) {
                return isset($check['status']) && $check['status'] === 'warning';
            }))
        );
    }
    
    /**
     * Check if admin username is "admin"
     */
    private function check_admin_username() {
        $admin_user = get_user_by('login', 'admin');
        
        return array(
            'status' => $admin_user ? 'warning' : 'good',
            'message' => $admin_user ? 
                __('Admin username "admin" detected - security risk', 'wp-site-advisory') :
                __('Admin username is not "admin" - good', 'wp-site-advisory'),
            'value' => $admin_user ? 'admin' : 'secure',
            'recommendation' => $admin_user ? 
                __('Change the admin username to something more secure', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check SSL status
     */
    private function check_ssl_status() {
        $is_ssl = is_ssl();
        $site_url_ssl = strpos(get_site_url(), 'https://') === 0;
        
        return array(
            'status' => ($is_ssl && $site_url_ssl) ? 'good' : 'warning',
            'message' => ($is_ssl && $site_url_ssl) ? 
                __('Site is running over SSL/HTTPS - secure', 'wp-site-advisory') :
                __('Site is not fully using SSL/HTTPS - security risk', 'wp-site-advisory'),
            'value' => ($is_ssl && $site_url_ssl) ? 'enabled' : 'disabled',
            'recommendation' => ($is_ssl && $site_url_ssl) ? '' :
                __('Enable SSL certificate and update site URLs to use HTTPS', 'wp-site-advisory')
        );
    }
    
    /**
     * Check WordPress core updates
     */
    private function check_wp_core_updates() {
        $core_updates = get_core_updates();
        $needs_update = false;
        
        if (!empty($core_updates)) {
            foreach ($core_updates as $update) {
                if ($update->response === 'upgrade') {
                    $needs_update = true;
                    break;
                }
            }
        }
        
        return array(
            'status' => $needs_update ? 'warning' : 'good',
            'message' => $needs_update ? 
                __('WordPress core update available', 'wp-site-advisory') :
                __('WordPress core is up to date', 'wp-site-advisory'),
            'current_version' => get_bloginfo('version'),
            'latest_version' => $needs_update ? $core_updates[0]->version : get_bloginfo('version'),
            'recommendation' => $needs_update ? 
                __('Update WordPress core to the latest version for security fixes', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        $wp_config_perms = substr(sprintf('%o', fileperms(ABSPATH . 'wp-config.php')), -3);
        $secure_perms = in_array($wp_config_perms, array('644', '640', '600'));
        
        return array(
            'status' => $secure_perms ? 'good' : 'warning',
            'message' => $secure_perms ? 
                __('File permissions are secure', 'wp-site-advisory') :
                sprintf(__('wp-config.php has permissions %s - should be 644 or more restrictive', 'wp-site-advisory'), $wp_config_perms),
            'wp_config_perms' => $wp_config_perms,
            'recommendation' => $secure_perms ? '' :
                __('Set wp-config.php file permissions to 644 or 640', 'wp-site-advisory')
        );
    }
    
    /**
     * Check debug mode
     */
    private function check_debug_mode() {
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        
        return array(
            'status' => $debug_enabled ? 'warning' : 'good',
            'message' => $debug_enabled ? 
                __('Debug mode is enabled - should be disabled in production', 'wp-site-advisory') :
                __('Debug mode is disabled - good for production', 'wp-site-advisory'),
            'value' => $debug_enabled ? 'enabled' : 'disabled',
            'recommendation' => $debug_enabled ? 
                __('Disable WP_DEBUG in wp-config.php for production sites', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check file editing status
     */
    private function check_file_editing_disabled() {
        $file_editing_disabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        
        return array(
            'status' => $file_editing_disabled ? 'good' : 'warning',
            'message' => $file_editing_disabled ? 
                __('File editing is disabled - secure', 'wp-site-advisory') :
                __('File editing is enabled - security risk', 'wp-site-advisory'),
            'value' => $file_editing_disabled ? 'disabled' : 'enabled',
            'recommendation' => $file_editing_disabled ? '' :
                __('Add define("DISALLOW_FILE_EDIT", true); to wp-config.php', 'wp-site-advisory')
        );
    }
    
    /**
     * Check database size
     */
    private function check_database_size() {
        global $wpdb;
        
        $size_query = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                table_schema as 'database_name',
                sum( data_length + index_length ) / 1024 / 1024 as 'size_mb'
            FROM information_schema.TABLES 
            WHERE table_schema = %s 
            GROUP BY table_schema",
            DB_NAME
        ));
        
        $size_mb = !empty($size_query) ? round($size_query[0]->size_mb, 2) : 0;
        
        return array(
            'status' => ($size_mb > 500) ? 'warning' : 'good',
            'message' => sprintf(__('Database size: %s MB', 'wp-site-advisory'), $size_mb),
            'size_mb' => $size_mb,
            'recommendation' => ($size_mb > 500) ? 
                __('Consider database optimization - size is getting large', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check WP Cron status
     */
    private function check_wp_cron_status() {
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        // Check if there are scheduled events
        $cron_array = _get_cron_array();
        $scheduled_events = !empty($cron_array) ? count($cron_array) : 0;
        
        return array(
            'status' => $cron_disabled ? 'warning' : 'good',
            'message' => $cron_disabled ? 
                __('WP Cron is disabled', 'wp-site-advisory') :
                sprintf(__('WP Cron is enabled with %d scheduled events', 'wp-site-advisory'), $scheduled_events),
            'is_disabled' => $cron_disabled,
            'scheduled_events' => $scheduled_events,
            'recommendation' => $cron_disabled ? 
                __('WP Cron is disabled - ensure server cron is configured if needed', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_limit_mb = $memory_limit / 1024 / 1024;
        
        return array(
            'status' => ($memory_limit_mb < 128) ? 'warning' : 'good',
            'message' => sprintf(__('PHP Memory Limit: %dM', 'wp-site-advisory'), $memory_limit_mb),
            'limit_mb' => $memory_limit_mb,
            'recommendation' => ($memory_limit_mb < 128) ? 
                __('Increase PHP memory limit to at least 128M', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check upload limits
     */
    private function check_upload_limits() {
        $upload_max = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
        $upload_max_mb = $upload_max / 1024 / 1024;
        
        return array(
            'status' => ($upload_max_mb < 32) ? 'warning' : 'good',
            'message' => sprintf(__('Upload Max Filesize: %dM', 'wp-site-advisory'), $upload_max_mb),
            'limit_mb' => $upload_max_mb,
            'recommendation' => ($upload_max_mb < 32) ? 
                __('Consider increasing upload_max_filesize for better media handling', 'wp-site-advisory') : ''
        );
    }
    
    /**
     * Check object cache
     */
    private function check_object_cache() {
        $object_cache_enabled = wp_using_ext_object_cache();
        
        return array(
            'status' => $object_cache_enabled ? 'good' : 'info',
            'message' => $object_cache_enabled ? 
                __('External object cache is enabled', 'wp-site-advisory') :
                __('Using default object cache (database)', 'wp-site-advisory'),
            'is_external' => $object_cache_enabled,
            'recommendation' => $object_cache_enabled ? '' :
                __('Consider implementing Redis or Memcached for better performance', 'wp-site-advisory')
        );
    }
    
    /**
     * Check GZIP compression
     */
    private function check_gzip_compression() {
        $gzip_enabled = false;
        
        // Check if output buffering with compression is enabled
        if (function_exists('gzencode')) {
            $gzip_enabled = in_array('ob_gzhandler', ob_list_handlers()) || 
                           (ini_get('zlib.output_compression') && ini_get('zlib.output_compression') !== 'Off');
        }
        
        return array(
            'status' => $gzip_enabled ? 'good' : 'info',
            'message' => $gzip_enabled ? 
                __('GZIP compression is enabled', 'wp-site-advisory') :
                __('GZIP compression not detected', 'wp-site-advisory'),
            'is_enabled' => $gzip_enabled,
            'recommendation' => $gzip_enabled ? '' :
                __('Enable GZIP compression on your server for faster loading', 'wp-site-advisory')
        );
    }
    
    /**
     * Calculate overall security score
     */
    private function calculate_security_score($checks) {
        $total_checks = count($checks);
        $passed_checks = count(array_filter($checks, function($check) {
            return isset($check['status']) && $check['status'] === 'good';
        }));
        
        return round(($passed_checks / $total_checks) * 100);
    }
    
    /**
     * Get directory size in bytes
     */
    private function get_directory_size($dir) {
        $size = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Assess security risk of inactive plugin
     */
    private function assess_inactive_plugin_risk($plugin_file, $plugin_dir) {
        $risk_level = 'low';
        $risk_factors = array();
        
        // Check for known vulnerable plugins (simplified)
        $known_risky_plugins = array(
            'duplicator/duplicator.php' => 'high',
            'wp-file-manager/file_folder_manager.php' => 'high',
            'easy-wp-smtp/easy-wp-smtp.php' => 'medium'
        );
        
        if (isset($known_risky_plugins[$plugin_file])) {
            return array(
                'level' => $known_risky_plugins[$plugin_file],
                'factors' => array(__('Known security vulnerabilities reported', 'wp-site-advisory'))
            );
        }
        
        // Check for old plugins (not updated in 2+ years)
        if (is_dir($plugin_dir)) {
            $last_modified = filemtime($plugin_dir);
            if ($last_modified < (time() - (2 * YEAR_IN_SECONDS))) {
                $risk_level = 'medium';
                $risk_factors[] = __('Plugin has not been updated in 2+ years', 'wp-site-advisory');
            }
        }
        
        return array(
            'level' => $risk_level,
            'factors' => $risk_factors
        );
    }
    
    /**
     * Assess security risk of inactive theme
     */
    private function assess_inactive_theme_risk($theme_dir) {
        $risk_level = 'low';
        $risk_factors = array();
        
        if (is_dir($theme_dir)) {
            $last_modified = filemtime($theme_dir);
            if ($last_modified < (time() - (2 * YEAR_IN_SECONDS))) {
                $risk_level = 'medium';
                $risk_factors[] = __('Theme has not been updated in 2+ years', 'wp-site-advisory');
            }
        }
        
        return array(
            'level' => $risk_level,
            'factors' => $risk_factors
        );
    }
    
    /**
     * Get recommendations for inactive plugin
     */
    private function get_inactive_plugin_recommendations($plugin_data) {
        $recommendations = array();
        
        $recommendations[] = array(
            'priority' => 'medium',
            'action' => __('Remove if not needed', 'wp-site-advisory'),
            'reason' => __('Inactive plugins can pose security risks and consume disk space', 'wp-site-advisory')
        );
        
        return $recommendations;
    }
    
    /**
     * Get recommendations for inactive theme
     */
    private function get_inactive_theme_recommendations($theme) {
        $recommendations = array();
        
        // Don't recommend removing Twenty themes as they're fallback themes
        if (!preg_match('/^twenty/', $theme->get_stylesheet())) {
            $recommendations[] = array(
                'priority' => 'low',
                'action' => __('Consider removing', 'wp-site-advisory'),
                'reason' => __('Inactive themes consume disk space and can pose security risks', 'wp-site-advisory')
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Generate recommendations for inactive plugins
     */
    private function generate_inactive_plugins_recommendations($count) {
        if ($count === 0) {
            return array();
        }
        
        return array(
            array(
                'priority' => 'medium',
                'title' => sprintf(__('Review %d Inactive Plugins', 'wp-site-advisory'), $count),
                'description' => __('Inactive plugins should be removed to reduce security risks and free up disk space.', 'wp-site-advisory'),
                'action_url' => admin_url('plugins.php')
            )
        );
    }
    
    /**
     * Generate recommendations for inactive themes
     */
    private function generate_inactive_themes_recommendations($count) {
        if ($count === 0) {
            return array();
        }
        
        return array(
            array(
                'priority' => 'low',
                'title' => sprintf(__('Review %d Inactive Themes', 'wp-site-advisory'), $count),
                'description' => __('Consider removing unused themes to free up disk space and reduce potential security risks.', 'wp-site-advisory'),
                'action_url' => admin_url('themes.php')
            )
        );
    }
    
    /**
     * Generate security recommendations
     */
    private function generate_security_recommendations($checks) {
        $recommendations = array();
        
        foreach ($checks as $check_name => $check) {
            if (isset($check['status']) && $check['status'] === 'warning' && !empty($check['recommendation'])) {
                $priority = ($check_name === 'admin_username' || $check_name === 'ssl_enabled') ? 'high' : 'medium';
                
                $recommendations[] = array(
                    'priority' => $priority,
                    'title' => $this->get_security_check_title($check_name),
                    'description' => $check['recommendation'],
                    'action_url' => $this->get_security_check_action_url($check_name)
                );
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Generate performance recommendations
     */
    private function generate_performance_recommendations($checks) {
        $recommendations = array();
        
        foreach ($checks as $check_name => $check) {
            if (isset($check['status']) && $check['status'] === 'warning' && !empty($check['recommendation'])) {
                $recommendations[] = array(
                    'priority' => 'medium',
                    'title' => $this->get_performance_check_title($check_name),
                    'description' => $check['recommendation'],
                    'action_url' => null
                );
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get security check title
     */
    private function get_security_check_title($check_name) {
        $titles = array(
            'admin_username' => __('Change Admin Username', 'wp-site-advisory'),
            'ssl_enabled' => __('Enable SSL/HTTPS', 'wp-site-advisory'),
            'wp_core_updated' => __('Update WordPress Core', 'wp-site-advisory'),
            'file_permissions' => __('Fix File Permissions', 'wp-site-advisory'),
            'debug_mode' => __('Disable Debug Mode', 'wp-site-advisory'),
            'file_editing' => __('Disable File Editing', 'wp-site-advisory')
        );
        
        return isset($titles[$check_name]) ? $titles[$check_name] : ucwords(str_replace('_', ' ', $check_name));
    }
    
    /**
     * Get performance check title
     */
    private function get_performance_check_title($check_name) {
        $titles = array(
            'database_size' => __('Optimize Database', 'wp-site-advisory'),
            'wp_cron' => __('Check WP Cron Setup', 'wp-site-advisory'),
            'memory_limit' => __('Increase Memory Limit', 'wp-site-advisory'),
            'upload_max_filesize' => __('Increase Upload Limit', 'wp-site-advisory'),
            'object_cache' => __('Implement Object Cache', 'wp-site-advisory'),
            'gzip_compression' => __('Enable GZIP Compression', 'wp-site-advisory')
        );
        
        return isset($titles[$check_name]) ? $titles[$check_name] : ucwords(str_replace('_', ' ', $check_name));
    }
    
    /**
     * Get security check action URL
     */
    private function get_security_check_action_url($check_name) {
        $urls = array(
            'admin_username' => admin_url('users.php'),
            'ssl_enabled' => admin_url('options-general.php'),
            'wp_core_updated' => admin_url('update-core.php'),
            'file_permissions' => null,
            'debug_mode' => null,
            'file_editing' => null
        );
        
        return isset($urls[$check_name]) ? $urls[$check_name] : null;
    }
    
    /**
     * Generate weekly summary report
     * 
     * @return array Weekly summary data
     */
    public function generate_weekly_summary() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            return array('error' => 'Insufficient permissions');
        }
        
        // Get latest scan results
        $scan_results = get_option('wsa_last_scan_results', array());
        $system_scan = $this->scan_system();
        
        $summary = array(
            'report_date' => current_time('mysql'),
            'site_info' => array(
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'active_theme' => wp_get_theme()->get('Name')
            ),
            'summary_stats' => array(
                'total_plugins' => count(get_option('active_plugins', array())),
                'plugins_needing_updates' => 0,
                'inactive_plugins' => $system_scan['inactive_plugins']['count'],
                'inactive_themes' => $system_scan['inactive_themes']['count'],
                'security_score' => $system_scan['security_checks']['security_score'],
                'security_issues' => $system_scan['security_checks']['issues_found'],
                'performance_issues' => $system_scan['performance_checks']['issues_found']
            ),
            'key_recommendations' => $this->get_top_recommendations($scan_results, $system_scan),
            'next_actions' => $this->get_next_actions($scan_results, $system_scan),
            'scan_summary' => array(
                'last_full_scan' => isset($scan_results['timestamp']) ? $scan_results['timestamp'] : '',
                'scans_this_week' => $this->count_recent_scans(7)
            )
        );
        
        // Count plugins needing updates
        if (!empty($scan_results['plugins'])) {
            foreach ($scan_results['plugins'] as $plugin) {
                if (!empty($plugin['update_available'])) {
                    $summary['summary_stats']['plugins_needing_updates']++;
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * Get top recommendations for weekly summary
     */
    private function get_top_recommendations($scan_results, $system_scan) {
        $recommendations = array();
        
        // Security recommendations
        foreach ($system_scan['security_checks']['recommendations'] as $rec) {
            if ($rec['priority'] === 'high') {
                $recommendations[] = $rec;
            }
        }
        
        // Inactive plugins recommendation
        if ($system_scan['inactive_plugins']['count'] > 0) {
            $recommendations = array_merge($recommendations, $system_scan['inactive_plugins']['recommendations']);
        }
        
        // Limit to top 5
        return array_slice($recommendations, 0, 5);
    }
    
    /**
     * Get next actions for weekly summary
     */
    private function get_next_actions($scan_results, $system_scan) {
        $actions = array();
        
        // Plugin updates
        if (!empty($scan_results['plugins'])) {
            $update_count = 0;
            foreach ($scan_results['plugins'] as $plugin) {
                if (!empty($plugin['update_available'])) {
                    $update_count++;
                }
            }
            
            if ($update_count > 0) {
                $actions[] = array(
                    'action' => sprintf(__('Update %d plugins', 'wp-site-advisory'), $update_count),
                    'priority' => 'high',
                    'url' => admin_url('plugins.php')
                );
            }
        }
        
        // Security actions
        if ($system_scan['security_checks']['issues_found'] > 0) {
            $actions[] = array(
                'action' => sprintf(__('Address %d security issues', 'wp-site-advisory'), $system_scan['security_checks']['issues_found']),
                'priority' => 'high',
                'url' => admin_url('admin.php?page=wp-site-advisory')
            );
        }
        
        // Cleanup actions
        if ($system_scan['inactive_plugins']['count'] > 0 || $system_scan['inactive_themes']['count'] > 0) {
            $total_inactive = $system_scan['inactive_plugins']['count'] + $system_scan['inactive_themes']['count'];
            $actions[] = array(
                'action' => sprintf(__('Review %d inactive items', 'wp-site-advisory'), $total_inactive),
                'priority' => 'medium',
                'url' => admin_url('admin.php?page=wp-site-advisory')
            );
        }
        
        return $actions;
    }
    
    /**
     * Count recent scans
     */
    private function count_recent_scans($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wsa_scan_history';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return 0;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return intval($count);
    }
}