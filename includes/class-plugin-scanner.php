<?php
/**
 * WP Site Advisory Plugin Scanner Class
 *
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Plugin_Scanner {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into plugin activation/deactivation for real-time updates
        add_action('activated_plugin', array($this, 'clear_plugin_cache'));
        add_action('deactivated_plugin', array($this, 'clear_plugin_cache'));
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
     * Scan all active plugins and return detailed information
     */
    public function scan_plugins() {
        $plugins_data = array();
        
        // Get all active plugins
        $active_plugins = get_option('active_plugins', array());
        
        // Include plugin.php if not already loaded
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Get all installed plugins data
        $all_plugins = get_plugins();
        
        // Get plugin update information
        $update_plugins = get_site_transient('update_plugins');
        
        foreach ($active_plugins as $plugin_file) {
            if (isset($all_plugins[$plugin_file])) {
                $plugin_data = $all_plugins[$plugin_file];
                
                // Gather comprehensive plugin information
                $plugin_info = array(
                    'file' => $plugin_file,
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'description' => $plugin_data['Description'],
                    'author' => $plugin_data['Author'],
                    'author_uri' => $plugin_data['AuthorURI'],
                    'plugin_uri' => $plugin_data['PluginURI'],
                    'text_domain' => $plugin_data['TextDomain'],
                    'domain_path' => $plugin_data['DomainPath'],
                    'network' => $plugin_data['Network'],
                    'requires_wp' => $plugin_data['RequiresWP'],
                    'requires_php' => $plugin_data['RequiresPHP'],
                    'update_available' => false,
                    'new_version' => '',
                    'last_updated' => '',
                    'active_installs' => '',
                    'compatibility' => array(),
                    'potential_issues' => array(),
                    'security_status' => 'unknown',
                );
                
                // Check for updates
                if (isset($update_plugins->response[$plugin_file])) {
                    $plugin_info['update_available'] = true;
                    $plugin_info['new_version'] = $update_plugins->response[$plugin_file]->new_version;
                }
                
                // Get additional plugin metadata
                $plugin_info = $this->get_plugin_metadata($plugin_info, $plugin_file);
                
                // Analyze potential issues
                $plugin_info['potential_issues'] = $this->analyze_plugin_issues($plugin_info);
                
                // Check security status
                $plugin_info['security_status'] = $this->check_plugin_security($plugin_info);
                
                $plugins_data[] = $plugin_info;
            }
        }
        
        // Sort plugins by name
        usort($plugins_data, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $plugins_data;
    }

    /**
     * Get additional plugin metadata
     */
    private function get_plugin_metadata($plugin_info, $plugin_file) {
        // Get plugin directory path
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
        
        // Check if plugin has its own directory
        if (is_dir($plugin_dir)) {
            // Count plugin files
            $plugin_info['file_count'] = $this->count_plugin_files($plugin_dir);
            
            // Check for common plugin patterns
            $plugin_info['has_settings'] = $this->has_settings_page($plugin_file);
            $plugin_info['has_widgets'] = $this->has_widgets($plugin_dir);
            $plugin_info['has_shortcodes'] = $this->has_shortcodes($plugin_dir);
            $plugin_info['has_rest_api'] = $this->has_rest_api($plugin_dir);
            $plugin_info['has_ajax'] = $this->has_ajax($plugin_dir);
            $plugin_info['has_cron'] = $this->has_cron_jobs($plugin_dir);
            
            // Get plugin size
            $plugin_info['size'] = $this->get_plugin_size($plugin_dir);
        }
        
        // Try to get plugin info from WordPress.org API
        $wp_org_info = $this->get_wordpress_org_info($plugin_file);
        if ($wp_org_info) {
            $plugin_info['last_updated'] = $wp_org_info['last_updated'] ?? '';
            $plugin_info['active_installs'] = $wp_org_info['active_installs'] ?? '';
            $plugin_info['tested_up_to'] = $wp_org_info['tested'] ?? '';
            $plugin_info['support_threads'] = $wp_org_info['support_threads'] ?? 0;
            $plugin_info['support_threads_resolved'] = $wp_org_info['support_threads_resolved'] ?? 0;
        }
        
        return $plugin_info;
    }

    /**
     * Count plugin files
     */
    private function count_plugin_files($plugin_dir) {
        if (!is_dir($plugin_dir)) {
            return 0;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Check if plugin has settings page
     */
    private function has_settings_page($plugin_file) {
        global $admin_page_hooks;
        
        // This is a simplified check - in practice, you'd need more sophisticated detection
        $plugin_slug = dirname($plugin_file);
        
        // Check if plugin is registered in admin menu
        foreach ($admin_page_hooks as $hook => $page) {
            if (strpos($hook, $plugin_slug) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if plugin directory contains widget files
     */
    private function has_widgets($plugin_dir) {
        return $this->directory_contains_pattern($plugin_dir, array(
            'class.*widget',
            'widget.*class',
            'extends.*wp_widget',
            'wp_widget'
        ));
    }

    /**
     * Check if plugin has shortcodes
     */
    private function has_shortcodes($plugin_dir) {
        return $this->directory_contains_pattern($plugin_dir, array(
            'add_shortcode',
            'do_shortcode',
            'shortcode_atts'
        ));
    }

    /**
     * Check if plugin uses REST API
     */
    private function has_rest_api($plugin_dir) {
        return $this->directory_contains_pattern($plugin_dir, array(
            'register_rest_route',
            'WP_REST_',
            'rest_api_init'
        ));
    }

    /**
     * Check if plugin uses AJAX
     */
    private function has_ajax($plugin_dir) {
        return $this->directory_contains_pattern($plugin_dir, array(
            'wp_ajax_',
            'admin-ajax.php',
            'wp_enqueue_script.*ajax'
        ));
    }

    /**
     * Check if plugin has cron jobs
     */
    private function has_cron_jobs($plugin_dir) {
        return $this->directory_contains_pattern($plugin_dir, array(
            'wp_schedule_event',
            'wp_cron',
            'cron_schedules'
        ));
    }

    /**
     * Search directory for patterns
     */
    private function directory_contains_pattern($dir, $patterns) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                
                foreach ($patterns as $pattern) {
                    if (preg_match('/' . preg_quote($pattern, '/') . '/i', $content)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Get plugin directory size
     */
    private function get_plugin_size($plugin_dir) {
        if (!is_dir($plugin_dir)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    /**
     * Get plugin information from WordPress.org API
     */
    private function get_wordpress_org_info($plugin_file) {
        $plugin_slug = dirname($plugin_file);
        
        // Skip if it's a single file plugin
        if ($plugin_slug === '.') {
            return false;
        }
        
        $cache_key = 'wsa_wp_org_' . md5($plugin_slug);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $api_url = "https://api.wordpress.org/plugins/info/1.0/{$plugin_slug}.json";
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'user-agent' => 'WP SiteAdvisor Plugin'
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Cache for 6 hours
        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);
        
        return $data;
    }

    /**
     * Analyze potential plugin issues
     */
    private function analyze_plugin_issues($plugin_info) {
        $issues = array();
        
        // Check for outdated plugins
        if ($plugin_info['update_available']) {
            $issues[] = array(
                'type' => 'update_available',
                'severity' => 'medium',
                'message' => sprintf(
                    __('Update available: %s → %s', 'wp-site-advisory'),
                    $plugin_info['version'],
                    $plugin_info['new_version']
                )
            );
        }
        
        // Check WordPress compatibility
        if (!empty($plugin_info['requires_wp'])) {
            $current_wp_version = get_bloginfo('version');
            if (version_compare($current_wp_version, $plugin_info['requires_wp'], '<')) {
                $issues[] = array(
                    'type' => 'wp_compatibility',
                    'severity' => 'high',
                    'message' => sprintf(
                        __('Requires WordPress %s or higher (current: %s)', 'wp-site-advisory'),
                        $plugin_info['requires_wp'],
                        $current_wp_version
                    )
                );
            }
        }
        
        // Check PHP compatibility
        if (!empty($plugin_info['requires_php'])) {
            $current_php_version = PHP_VERSION;
            if (version_compare($current_php_version, $plugin_info['requires_php'], '<')) {
                $issues[] = array(
                    'type' => 'php_compatibility',
                    'severity' => 'high',
                    'message' => sprintf(
                        __('Requires PHP %s or higher (current: %s)', 'wp-site-advisory'),
                        $plugin_info['requires_php'],
                        $current_php_version
                    )
                );
            }
        }
        
        // Check if plugin hasn't been updated in a while
        if (!empty($plugin_info['last_updated'])) {
            $last_updated = strtotime($plugin_info['last_updated']);
            $months_old = (time() - $last_updated) / (30 * 24 * 60 * 60);
            
            if ($months_old > 24) {
                $issues[] = array(
                    'type' => 'outdated',
                    'severity' => 'medium',
                    'message' => __('Plugin hasn\'t been updated in over 2 years', 'wp-site-advisory')
                );
            } elseif ($months_old > 12) {
                $issues[] = array(
                    'type' => 'outdated',
                    'severity' => 'low',
                    'message' => __('Plugin hasn\'t been updated in over a year', 'wp-site-advisory')
                );
            }
        }
        
        // Check support status
        if (isset($plugin_info['support_threads']) && isset($plugin_info['support_threads_resolved'])) {
            $support_ratio = $plugin_info['support_threads'] > 0 
                ? $plugin_info['support_threads_resolved'] / $plugin_info['support_threads'] 
                : 1;
            
            if ($support_ratio < 0.5) {
                $issues[] = array(
                    'type' => 'poor_support',
                    'severity' => 'medium',
                    'message' => __('Plugin has poor support forum response rate', 'wp-site-advisory')
                );
            }
        }
        
        return $issues;
    }

    /**
     * Check plugin security status
     */
    private function check_plugin_security($plugin_info) {
        // This is a simplified security check
        // In a production environment, you'd integrate with security APIs
        
        $security_score = 'good';
        
        // Check for common security red flags
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_info['file']);
        
        if (is_dir($plugin_dir)) {
            // Check for eval() usage
            if ($this->directory_contains_pattern($plugin_dir, array('eval\\('))) {
                $security_score = 'warning';
            }
            
            // Check for $_GET/$_POST without sanitization
            if ($this->directory_contains_pattern($plugin_dir, array('\\$_GET\\[', '\\$_POST\\['))) {
                // This is a very basic check - in reality, you'd need more sophisticated analysis
                $security_score = 'warning';
            }
        }
        
        // Check if plugin has known vulnerabilities (simplified)
        if ($this->has_known_vulnerabilities($plugin_info)) {
            $security_score = 'critical';
        }
        
        return $security_score;
    }

    /**
     * Check for known vulnerabilities
     */
    private function has_known_vulnerabilities($plugin_info) {
        // Check multiple vulnerability databases
        $vulnerability_sources = array(
            'wpscan' => $this->check_wpscan_vulnerabilities($plugin_info),
            'wordfence' => $this->check_wordfence_vulnerabilities($plugin_info),
            'wpvulndb' => $this->check_wpvulndb_vulnerabilities($plugin_info),
            'cvss' => $this->check_cvss_vulnerabilities($plugin_info)
        );
        
        // Store vulnerability details
        $vulnerabilities = array();
        foreach ($vulnerability_sources as $source => $result) {
            if ($result && !empty($result['vulnerabilities'])) {
                $vulnerabilities[$source] = $result;
            }
        }
        
        if (!empty($vulnerabilities)) {
            $plugin_info['vulnerabilities'] = $vulnerabilities;
            $plugin_info['vulnerability_count'] = $this->count_vulnerabilities($vulnerabilities);
            $plugin_info['highest_severity'] = $this->get_highest_severity($vulnerabilities);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check WPScan vulnerability database
     */
    private function check_wpscan_vulnerabilities($plugin_info) {
        $plugin_slug = $this->get_plugin_slug($plugin_info['file']);
        $cache_key = 'wsa_wpscan_' . md5($plugin_slug . $plugin_info['version']);
        
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // WPScan API endpoint (requires API token for detailed info)
        $api_url = "https://wpscan.com/api/v3/plugins/{$plugin_slug}";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WP SiteAdvisor Plugin Scanner'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return false;
        }
        
        $result = $this->parse_wpscan_data($data, $plugin_info['version']);
        
        // Cache for 6 hours
        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Check Wordfence vulnerability database
     */
    private function check_wordfence_vulnerabilities($plugin_info) {
        $plugin_slug = $this->get_plugin_slug($plugin_info['file']);
        $cache_key = 'wsa_wordfence_' . md5($plugin_slug . $plugin_info['version']);
        
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Wordfence Intelligence API
        $api_url = "https://www.wordfence.com/api/intelligence/v2/vulnerabilities/plugin/{$plugin_slug}";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WP SiteAdvisor Plugin Scanner'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        $result = $this->parse_wordfence_data($data, $plugin_info['version']);
        
        // Cache for 6 hours
        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Check WPVulnDB
     */
    private function check_wpvulndb_vulnerabilities($plugin_info) {
        $plugin_slug = $this->get_plugin_slug($plugin_info['file']);
        
        // This is a simplified check - in production you'd integrate with a real vulnerability database
        $known_vulnerable_plugins = array(
            'duplicator' => array(
                'versions' => array('< 1.3.26'),
                'severity' => 'high',
                'description' => 'Path Traversal vulnerability'
            ),
            'wp-file-manager' => array(
                'versions' => array('< 6.9'),
                'severity' => 'critical',
                'description' => 'Remote Code Execution vulnerability'
            ),
            'easy-wp-smtp' => array(
                'versions' => array('< 1.4.2'),
                'severity' => 'medium',
                'description' => 'Authentication bypass vulnerability'
            ),
        );
        
        if (isset($known_vulnerable_plugins[$plugin_slug])) {
            $vuln = $known_vulnerable_plugins[$plugin_slug];
            if ($this->version_is_vulnerable($plugin_info['version'], $vuln['versions'])) {
                return array(
                    'vulnerabilities' => array(
                        array(
                            'id' => 'wpvulndb_' . $plugin_slug,
                            'title' => $vuln['description'],
                            'severity' => $vuln['severity'],
                            'affected_versions' => $vuln['versions'],
                            'fixed_in' => 'Latest version',
                            'published' => date('Y-m-d'),
                        )
                    )
                );
            }
        }
        
        return false;
    }
    
    /**
     * Check CVSS vulnerability scores
     */
    private function check_cvss_vulnerabilities($plugin_info) {
        // This would integrate with CVE databases
        // For now, return a simplified check based on plugin age and update frequency
        
        $last_updated = $plugin_info['last_updated'] ?? '';
        if (!empty($last_updated)) {
            $last_update_time = strtotime($last_updated);
            $months_old = (time() - $last_update_time) / (30 * 24 * 60 * 60);
            
            // Flag plugins that haven't been updated in over 3 years as potential security risks
            if ($months_old > 36) {
                return array(
                    'vulnerabilities' => array(
                        array(
                            'id' => 'stale_plugin_' . $plugin_info['file'],
                            'title' => 'Plugin appears abandoned - potential security risk',
                            'severity' => 'medium',
                            'description' => 'Plugin has not been updated in over 3 years, which may indicate security vulnerabilities.',
                            'published' => date('Y-m-d'),
                        )
                    )
                );
            }
        }
        
        return false;
    }
    
    /**
     * Parse WPScan API data
     */
    private function parse_wpscan_data($data, $current_version) {
        $vulnerabilities = array();
        
        if (isset($data['vulnerabilities']) && is_array($data['vulnerabilities'])) {
            foreach ($data['vulnerabilities'] as $vuln) {
                if ($this->version_affects_current($current_version, $vuln)) {
                    $vulnerabilities[] = array(
                        'id' => $vuln['id'] ?? 'unknown',
                        'title' => $vuln['title'] ?? 'Unknown vulnerability',
                        'severity' => $this->normalize_severity($vuln['severity'] ?? 'medium'),
                        'cvss_score' => $vuln['cvss']['score'] ?? null,
                        'published' => $vuln['published_date'] ?? '',
                        'fixed_in' => $vuln['fixed_in'] ?? 'Unknown',
                        'source' => 'WPScan'
                    );
                }
            }
        }
        
        return !empty($vulnerabilities) ? array('vulnerabilities' => $vulnerabilities) : false;
    }
    
    /**
     * Parse Wordfence API data
     */
    private function parse_wordfence_data($data, $current_version) {
        // Similar parsing logic for Wordfence data
        $vulnerabilities = array();
        
        if (isset($data['threats']) && is_array($data['threats'])) {
            foreach ($data['threats'] as $threat) {
                if ($this->version_affects_current($current_version, $threat)) {
                    $vulnerabilities[] = array(
                        'id' => $threat['id'] ?? 'unknown',
                        'title' => $threat['title'] ?? 'Unknown threat',
                        'severity' => $this->normalize_severity($threat['severity'] ?? 'medium'),
                        'published' => $threat['published'] ?? '',
                        'source' => 'Wordfence'
                    );
                }
            }
        }
        
        return !empty($vulnerabilities) ? array('vulnerabilities' => $vulnerabilities) : false;
    }
    
    /**
     * Check if current version is affected by vulnerability
     */
    private function version_affects_current($current_version, $vulnerability_data) {
        // Simplified version checking - in production, use proper version comparison
        $affected_versions = $vulnerability_data['affected_versions'] ?? array();
        
        foreach ($affected_versions as $version_range) {
            if ($this->version_is_vulnerable($current_version, array($version_range))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if version is vulnerable based on version ranges
     */
    private function version_is_vulnerable($current_version, $vulnerable_ranges) {
        foreach ($vulnerable_ranges as $range) {
            if (strpos($range, '<') === 0) {
                // Less than version check
                $target_version = trim(str_replace('<', '', $range));
                if (version_compare($current_version, $target_version, '<')) {
                    return true;
                }
            } elseif (strpos($range, '<=') === 0) {
                // Less than or equal version check
                $target_version = trim(str_replace('<=', '', $range));
                if (version_compare($current_version, $target_version, '<=')) {
                    return true;
                }
            } elseif ($range === $current_version) {
                // Exact version match
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Normalize severity levels
     */
    private function normalize_severity($severity) {
        $severity = strtolower($severity);
        $severity_map = array(
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            'info' => 'low',
            'informational' => 'low'
        );
        
        return $severity_map[$severity] ?? 'medium';
    }
    
    /**
     * Count total vulnerabilities
     */
    private function count_vulnerabilities($vulnerabilities) {
        $count = 0;
        foreach ($vulnerabilities as $source => $data) {
            if (isset($data['vulnerabilities'])) {
                $count += count($data['vulnerabilities']);
            }
        }
        return $count;
    }
    
    /**
     * Get highest severity from vulnerabilities
     */
    private function get_highest_severity($vulnerabilities) {
        $severity_levels = array('low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4);
        $highest = 0;
        $highest_severity = 'low';
        
        foreach ($vulnerabilities as $source => $data) {
            if (isset($data['vulnerabilities'])) {
                foreach ($data['vulnerabilities'] as $vuln) {
                    $severity = $vuln['severity'] ?? 'low';
                    if (isset($severity_levels[$severity]) && $severity_levels[$severity] > $highest) {
                        $highest = $severity_levels[$severity];
                        $highest_severity = $severity;
                    }
                }
            }
        }
        
        return $highest_severity;
    }
    
    /**
     * Get plugin slug from file path
     */
    private function get_plugin_slug($plugin_file) {
        $parts = explode('/', $plugin_file);
        return isset($parts[0]) ? $parts[0] : basename($plugin_file, '.php');
    }

    /**
     * Clear plugin cache when plugins are activated/deactivated
     */
    public function clear_plugin_cache() {
        delete_option('wsa_last_scan_results');
        delete_transient('wsa_plugins_cache');
    }

    /**
     * Get plugin recommendations based on scan results
     */
    public function get_plugin_recommendations($plugins_data) {
        $recommendations = array();
        
        foreach ($plugins_data as $plugin) {
            if (!empty($plugin['potential_issues'])) {
                foreach ($plugin['potential_issues'] as $issue) {
                    $recommendations[] = array(
                        'plugin' => $plugin['name'],
                        'type' => $issue['type'],
                        'severity' => $issue['severity'],
                        'message' => $issue['message'],
                        'suggestion' => $this->get_issue_suggestion($issue['type'])
                    );
                }
            }
        }
        
        return $recommendations;
    }

    /**
     * Get suggestion for specific issue type
     */
    private function get_issue_suggestion($issue_type) {
        $suggestions = array(
            'update_available' => __('Consider updating the plugin to get the latest features and security fixes.', 'wp-site-advisory'),
            'wp_compatibility' => __('Update WordPress or find an alternative plugin that supports your WordPress version.', 'wp-site-advisory'),
            'php_compatibility' => __('Update PHP version or find an alternative plugin that supports your PHP version.', 'wp-site-advisory'),
            'outdated' => __('Consider finding a more actively maintained alternative or check if the plugin still meets your needs.', 'wp-site-advisory'),
            'poor_support' => __('Monitor this plugin closely and consider alternatives with better support.', 'wp-site-advisory'),
        );
        
        return $suggestions[$issue_type] ?? __('Review this issue and take appropriate action.', 'wp-site-advisory');
    }
}