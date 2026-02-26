<?php
/**
 * Theme Scanner Class
 * 
 * Handles theme detection, version checking, security scanning, and analysis
 * 
 * @package WP_Site_Advisory
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Theme_Scanner {
    
    /**
     * Current theme object
     */
    private $current_theme;
    
    /**
     * Theme security issues found
     */
    private $security_issues;
    
    /**
     * Risky PHP functions to scan for
     */
    private $risky_functions = array(
        'eval',
        'base64_decode',
        'base64_encode',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fwrite',
        'create_function',
        'assert',
        'preg_replace',  // when used with /e modifier
        'call_user_func',
        'call_user_func_array'
    );
    
    /**
     * File patterns that indicate potential security risks
     */
    private $risky_patterns = array(
        '/include\s*\(\s*\$[^)]+\)/',           // Dynamic includes
        '/require\s*\(\s*\$[^)]+\)/',          // Dynamic requires
        '/include_once\s*\(\s*\$[^)]+\)/',     // Dynamic include_once
        '/require_once\s*\(\s*\$[^)]+\)/',     // Dynamic require_once
        '/\$_(?:GET|POST|REQUEST|COOKIE)\[.*?\].*?(?:include|require)/', // Direct user input in includes
        '/echo\s+\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/', // Unescaped output
        '/print\s+\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/', // Unescaped print
        '/\?\>\s*<\?php/',                     // Multiple PHP tags (potential code injection)
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->current_theme = wp_get_theme();
        $this->security_issues = array();
    }
    
    /**
     * Scan the current theme
     * 
     * @return array Theme analysis results
     */
    public function scan_theme() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            return array('error' => 'Insufficient permissions');
        }
        
        $results = array(
            'theme_info' => $this->get_theme_info(),
            'editability' => $this->check_editability(),
            'update_available' => $this->check_for_updates(),
            'security_scan' => $this->perform_security_scan(),
            'recommendations' => $this->generate_recommendations(),
            'last_scanned' => current_time('mysql')
        );
        
        return $results;
    }
    
    /**
     * Get current theme information
     * 
     * @return array Theme information
     */
    private function get_theme_info() {
        $theme_data = array(
            'name' => sanitize_text_field($this->current_theme->get('Name')),
            'version' => sanitize_text_field($this->current_theme->get('Version')),
            'author' => sanitize_text_field($this->current_theme->get('Author')),
            'description' => sanitize_textarea_field($this->current_theme->get('Description')),
            'theme_uri' => esc_url($this->current_theme->get('ThemeURI')),
            'template' => sanitize_text_field($this->current_theme->get_template()),
            'stylesheet' => sanitize_text_field($this->current_theme->get_stylesheet()),
            'parent_theme' => null,
            'is_child_theme' => false
        );
        
        // Check if it's a child theme
        if ($this->current_theme->parent()) {
            $theme_data['is_child_theme'] = true;
            $parent_theme = $this->current_theme->parent();
            $theme_data['parent_theme'] = array(
                'name' => sanitize_text_field($parent_theme->get('Name')),
                'version' => sanitize_text_field($parent_theme->get('Version')),
                'template' => sanitize_text_field($parent_theme->get_template())
            );
        }
        
        return $theme_data;
    }
    
    /**
     * Check if theme files are editable
     * 
     * @return array Editability information
     */
    private function check_editability() {
        $editability = array(
            'is_editable' => false,
            'file_editing_disabled' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            'user_can_edit' => current_user_can('edit_themes'),
            'theme_directory_writable' => false,
            'risks' => array()
        );
        
        // Check if theme directory is writable
        $theme_root = get_theme_root($this->current_theme->get_stylesheet());
        $theme_dir = $theme_root . '/' . $this->current_theme->get_stylesheet();
        
        if (is_dir($theme_dir) && is_writable($theme_dir)) {
            $editability['theme_directory_writable'] = true;
        }
        
        // Determine overall editability
        $editability['is_editable'] = (
            !$editability['file_editing_disabled'] &&
            $editability['user_can_edit'] &&
            $editability['theme_directory_writable']
        );
        
        // Assess risks
        if ($editability['is_editable']) {
            $editability['risks'][] = __('Theme files can be edited directly from WordPress admin', 'wp-site-advisory');
            $editability['risks'][] = __('Malicious code injection is possible if admin account is compromised', 'wp-site-advisory');
        }
        
        return $editability;
    }
    
    /**
     * Check for theme updates
     * 
     * @return array Update information
     */
    private function check_for_updates() {
        $update_info = array(
            'update_available' => false,
            'current_version' => $this->current_theme->get('Version'),
            'latest_version' => null,
            'update_uri' => null,
            'is_wp_org_theme' => false
        );
        
        // Force refresh of update transients
        wp_clean_themes_cache();
        
        // Check for updates
        $update_themes = get_site_transient('update_themes');
        $stylesheet = $this->current_theme->get_stylesheet();
        
        if (isset($update_themes->response[$stylesheet])) {
            $update_info['update_available'] = true;
            $update_info['latest_version'] = $update_themes->response[$stylesheet]['new_version'];
            $update_info['update_uri'] = admin_url('update-core.php');
            $update_info['is_wp_org_theme'] = true;
        }
        
        return $update_info;
    }
    
    /**
     * Perform comprehensive security scan of theme files
     * 
     * @return array Security scan results
     */
    private function perform_security_scan() {
        $scan_results = array(
            'files_scanned' => 0,
            'issues_found' => 0,
            'critical_issues' => 0,
            'medium_issues' => 0,
            'low_issues' => 0,
            'security_score' => 100,
            'issues' => array(),
            'scan_time' => 0
        );
        
        $start_time = microtime(true);
        
        // Get theme files
        $theme_files = $this->get_theme_files();
        
        foreach ($theme_files as $file_path) {
            $scan_results['files_scanned']++;
            $this->scan_file_for_vulnerabilities($file_path, $scan_results);
        }
        
        // Calculate security score
        $scan_results['security_score'] = max(0, 100 - ($scan_results['critical_issues'] * 20) - ($scan_results['medium_issues'] * 10) - ($scan_results['low_issues'] * 5));
        
        $scan_results['scan_time'] = round(microtime(true) - $start_time, 2);
        
        return $scan_results;
    }
    
    /**
     * Get all PHP files in the current theme
     * 
     * @return array Array of file paths
     */
    private function get_theme_files() {
        $theme_root = get_theme_root($this->current_theme->get_stylesheet());
        $theme_dir = $theme_root . '/' . $this->current_theme->get_stylesheet();
        $files = array();
        
        if (!is_dir($theme_dir)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }
        
        return $files;
    }
    
    /**
     * Scan individual file for security vulnerabilities
     * 
     * @param string $file_path Path to the file
     * @param array &$scan_results Reference to scan results array
     */
    private function scan_file_for_vulnerabilities($file_path, &$scan_results) {
        if (!is_readable($file_path)) {
            return;
        }
        
        $content = file_get_contents($file_path);
        if ($content === false) {
            return;
        }
        
        $relative_path = str_replace(WP_CONTENT_DIR, '', $file_path);
        
        // Scan for risky functions
        $this->scan_risky_functions($content, $relative_path, $scan_results);
        
        // Scan for risky patterns
        $this->scan_risky_patterns($content, $relative_path, $scan_results);
        
        // Scan for unescaped output
        $this->scan_unescaped_output($content, $relative_path, $scan_results);
    }
    
    /**
     * Scan for risky PHP functions
     * 
     * @param string $content File content
     * @param string $file_path Relative file path
     * @param array &$scan_results Reference to scan results
     */
    private function scan_risky_functions($content, $file_path, &$scan_results) {
        foreach ($this->risky_functions as $function) {
            $pattern = '/\b' . preg_quote($function, '/') . '\s*\(/i';
            
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line_number = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $severity = $this->get_function_severity($function);
                    
                    $scan_results['issues'][] = array(
                        'type' => 'risky_function',
                        'severity' => $severity,
                        'function' => $function,
                        'file' => $file_path,
                        'line' => $line_number,
                        'description' => sprintf(__('Risky function "%s" found', 'wp-site-advisory'), $function),
                        'recommendation' => $this->get_function_recommendation($function)
                    );
                    
                    $scan_results['issues_found']++;
                    $scan_results[$severity . '_issues']++;
                }
            }
        }
    }
    
    /**
     * Scan for risky patterns
     * 
     * @param string $content File content
     * @param string $file_path Relative file path
     * @param array &$scan_results Reference to scan results
     */
    private function scan_risky_patterns($content, $file_path, &$scan_results) {
        foreach ($this->risky_patterns as $pattern) {
            // Validate regex pattern before using it
            if (@preg_match($pattern, '') === false) {
                continue;
            }
            
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line_number = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $scan_results['issues'][] = array(
                        'type' => 'risky_pattern',
                        'severity' => 'critical',
                        'pattern' => $pattern,
                        'file' => $file_path,
                        'line' => $line_number,
                        'description' => __('Potentially dangerous code pattern detected', 'wp-site-advisory'),
                        'recommendation' => __('Review and sanitize user input handling', 'wp-site-advisory')
                    );
                    
                    $scan_results['issues_found']++;
                    $scan_results['critical_issues']++;
                }
            }
        }
    }
    
    /**
     * Scan for unescaped output (basic detection)
     * 
     * @param string $content File content
     * @param string $file_path Relative file path
     * @param array &$scan_results Reference to scan results
     */
    private function scan_unescaped_output($content, $file_path, &$scan_results) {
        // Look for common unescaped output patterns
        $output_patterns = array(
            '/echo\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*;/' => 'medium',
            '/print\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*;/' => 'medium',
            '/<\?=\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*\?>/' => 'medium'
        );
        
        foreach ($output_patterns as $pattern => $severity) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    // Skip if it's already escaped (basic check)
                    $context = substr($content, max(0, $match[1] - 50), 100);
                    if (preg_match('/esc_html|esc_attr|wp_kses|sanitize_/', $context)) {
                        continue;
                    }
                    
                    $line_number = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $scan_results['issues'][] = array(
                        'type' => 'unescaped_output',
                        'severity' => $severity,
                        'file' => $file_path,
                        'line' => $line_number,
                        'code' => trim($match[0]),
                        'description' => __('Potentially unescaped output detected', 'wp-site-advisory'),
                        'recommendation' => __('Use WordPress escaping functions like esc_html(), esc_attr(), etc.', 'wp-site-advisory')
                    );
                    
                    $scan_results['issues_found']++;
                    $scan_results[$severity . '_issues']++;
                }
            }
        }
    }
    
    /**
     * Get severity level for a function
     * 
     * @param string $function Function name
     * @return string Severity level
     */
    private function get_function_severity($function) {
        $critical_functions = array('eval', 'exec', 'shell_exec', 'system', 'passthru', 'create_function', 'assert');
        $medium_functions = array('base64_decode', 'base64_encode', 'file_get_contents', 'file_put_contents', 'fopen', 'fwrite');
        
        if (in_array($function, $critical_functions)) {
            return 'critical';
        } elseif (in_array($function, $medium_functions)) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Get recommendation for a function
     * 
     * @param string $function Function name
     * @return string Recommendation
     */
    private function get_function_recommendation($function) {
        $recommendations = array(
            'eval' => __('Avoid eval(). Use alternative approaches or sanitize input thoroughly.', 'wp-site-advisory'),
            'base64_decode' => __('Ensure base64_decode() input is validated and from trusted sources.', 'wp-site-advisory'),
            'exec' => __('Avoid system commands. Use WordPress APIs when possible.', 'wp-site-advisory'),
            'shell_exec' => __('Avoid shell commands. Use WordPress APIs when possible.', 'wp-site-advisory'),
            'system' => __('Avoid system commands. Use WordPress APIs when possible.', 'wp-site-advisory'),
            'file_get_contents' => __('Use wp_remote_get() for URLs and validate file paths for local files.', 'wp-site-advisory'),
            'file_put_contents' => __('Use WordPress filesystem APIs and validate file paths.', 'wp-site-advisory')
        );
        
        return isset($recommendations[$function]) ? $recommendations[$function] : __('Review usage and ensure proper input validation.', 'wp-site-advisory');
    }
    
    /**
     * Generate security recommendations based on scan results
     * 
     * @return array Array of recommendations
     */
    private function generate_recommendations() {
        $recommendations = array();
        
        // Theme update recommendation
        $update_info = $this->check_for_updates();
        if ($update_info['update_available']) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => __('Update Theme', 'wp-site-advisory'),
                'description' => sprintf(
                    __('Your theme has an update available (v%s → v%s). Update to get security fixes and new features.', 'wp-site-advisory'),
                    $update_info['current_version'],
                    $update_info['latest_version']
                ),
                'action_url' => admin_url('update-core.php')
            );
        }
        
        // File editing recommendation
        $editability = $this->check_editability();
        if ($editability['is_editable']) {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => __('Disable File Editing', 'wp-site-advisory'),
                'description' => __('Consider disabling theme file editing by adding define("DISALLOW_FILE_EDIT", true); to wp-config.php', 'wp-site-advisory'),
                'action_url' => null
            );
        }
        
        // Child theme recommendation
        $theme_info = $this->get_theme_info();
        if (!$theme_info['is_child_theme'] && $theme_info['template'] !== $theme_info['stylesheet']) {
            $recommendations[] = array(
                'priority' => 'low',
                'title' => __('Use Child Theme', 'wp-site-advisory'),
                'description' => __('Consider using a child theme to preserve customizations during theme updates.', 'wp-site-advisory'),
                'action_url' => admin_url('themes.php')
            );
        }
        
        return $recommendations;
    }
}