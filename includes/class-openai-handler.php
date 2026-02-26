<?php
/**
 * WP Site Advisory OpenAI Handler Class
 *
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_OpenAI_Handler {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * OpenAI API endpoint
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize hooks if needed
        add_action('wp_ajax_wsa_test_openai_connection', array($this, 'ajax_test_connection'));
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
     * Get AI recommendations based on scan data
     */
    public function get_recommendations($scan_data) {
        // Check if Pro version is available for AI analysis
        if (!wsa_is_pro_active()) {
            return wsa_disable_pro_feature('ai_analysis');
        }
        
        $api_key = get_option('wsa_openai_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured.', 'wp-site-advisory'));
        }

        // Prepare the context for OpenAI
        $context = $this->prepare_context($scan_data);
        
        // Create the prompt
        $prompt = $this->create_recommendation_prompt($context);
        
        // Make API request
        $response = $this->make_openai_request($prompt, $api_key);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Parse and structure the response
        $recommendations = $this->parse_ai_response($response);
        
        // Store recommendations for later use
        update_option('wsa_last_ai_recommendations', $recommendations);
        
        return $recommendations;
    }

    /**
     * Prepare context data for OpenAI
     */
    private function prepare_context($scan_data) {
        $context = array(
            'site_info' => array(
                'url' => home_url(),
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'active_theme' => wp_get_theme()->get('Name'),
                'scan_timestamp' => $scan_data['timestamp'] ?? current_time('mysql'),
            ),
            'plugins' => array(
                'total_active' => count($scan_data['plugins'] ?? array()),
                'plugins_with_updates' => 0,
                'outdated_plugins' => array(),
                'security_issues' => array(),
                'compatibility_issues' => array(),
            ),
            'google_integrations' => $scan_data['google_integrations'] ?? array(),
            'issues' => array(),
            'recommendations_needed' => array(),
        );

        // Analyze plugins
        if (!empty($scan_data['plugins'])) {
            foreach ($scan_data['plugins'] as $plugin) {
                if (!empty($plugin['update_available'])) {
                    $context['plugins']['plugins_with_updates']++;
                }

                if (!empty($plugin['potential_issues'])) {
                    foreach ($plugin['potential_issues'] as $issue) {
                        $context['issues'][] = array(
                            'plugin' => $plugin['name'],
                            'type' => $issue['type'],
                            'severity' => $issue['severity'],
                            'message' => $issue['message']
                        );

                        if ($issue['type'] === 'outdated') {
                            $context['plugins']['outdated_plugins'][] = $plugin['name'];
                        }
                        
                        if ($issue['severity'] === 'high') {
                            if ($issue['type'] === 'php_compatibility' || $issue['type'] === 'wp_compatibility') {
                                $context['plugins']['compatibility_issues'][] = array(
                                    'plugin' => $plugin['name'],
                                    'issue' => $issue['message']
                                );
                            }
                        }
                    }
                }

                if (isset($plugin['security_status']) && $plugin['security_status'] === 'warning') {
                    $context['plugins']['security_issues'][] = $plugin['name'];
                }
            }
        }

        // Analyze Google integrations
        $missing_integrations = array();
        $google_services = array('analytics', 'search_console', 'tag_manager');
        
        foreach ($google_services as $service) {
            if (empty($context['google_integrations'][$service])) {
                $missing_integrations[] = $service;
            }
        }

        if (!empty($missing_integrations)) {
            $context['recommendations_needed']['google_integrations'] = $missing_integrations;
        }
        
        // Analyze theme
        if (!empty($scan_data['theme_analysis'])) {
            $theme_analysis = $scan_data['theme_analysis'];
            $context['theme'] = array(
                'name' => $theme_analysis['theme_info']['name'] ?? '',
                'version' => $theme_analysis['theme_info']['version'] ?? '',
                'is_child_theme' => $theme_analysis['theme_info']['is_child_theme'] ?? false,
                'update_available' => $theme_analysis['update_available']['update_available'] ?? false,
                'is_editable' => $theme_analysis['editability']['is_editable'] ?? false,
                'security_score' => $theme_analysis['security_scan']['security_score'] ?? 100,
                'security_issues' => $theme_analysis['security_scan']['issues_found'] ?? 0,
                'critical_issues' => $theme_analysis['security_scan']['critical_issues'] ?? 0,
                'recommendations' => $theme_analysis['recommendations'] ?? array()
            );
            
            // Add theme-specific recommendations needed
            if ($context['theme']['update_available']) {
                $context['recommendations_needed']['theme_update'] = true;
            }
            
            if ($context['theme']['security_issues'] > 0) {
                $context['recommendations_needed']['theme_security'] = $context['theme']['security_issues'];
            }
            
            if ($context['theme']['is_editable']) {
                $context['recommendations_needed']['theme_editing'] = true;
            }
        }

        return $context;
    }

    /**
     * Create the recommendation prompt for OpenAI
     */
    private function create_recommendation_prompt($context) {
        $site_name = $context['site_info']['name'];
        $site_url = $context['site_info']['url'];
        $total_plugins = $context['plugins']['total_active'];
        $plugins_with_updates = $context['plugins']['plugins_with_updates'];
        $issues_count = count($context['issues']);

        $prompt = "You are a WordPress expert consultant analyzing a website called '{$site_name}' ({$site_url}). ";
        $prompt .= "The site is running WordPress {$context['site_info']['wordpress_version']} with PHP {$context['site_info']['php_version']} ";
        $prompt .= "and has {$total_plugins} active plugins.\n\n";

        $prompt .= "SCAN RESULTS ANALYSIS:\n";

        // Plugin issues
        if ($issues_count > 0) {
            $prompt .= "Plugin Issues Found ({$issues_count} total):\n";
            foreach ($context['issues'] as $issue) {
                $prompt .= "- {$issue['plugin']}: {$issue['message']} (Severity: {$issue['severity']})\n";
            }
            $prompt .= "\n";
        }

        if ($plugins_with_updates > 0) {
            $prompt .= "Plugins needing updates: {$plugins_with_updates}\n";
        }

        // Theme analysis
        if (!empty($context['theme'])) {
            $theme = $context['theme'];
            $prompt .= "\nTheme Analysis:\n";
            $prompt .= "- Current Theme: {$theme['name']} v{$theme['version']}\n";
            $prompt .= "- Child Theme: " . ($theme['is_child_theme'] ? 'YES' : 'NO') . "\n";
            $prompt .= "- Update Available: " . ($theme['update_available'] ? 'YES' : 'NO') . "\n";
            $prompt .= "- File Editing Enabled: " . ($theme['is_editable'] ? 'YES (Security Risk)' : 'NO') . "\n";
            $prompt .= "- Security Score: {$theme['security_score']}%\n";
            
            if ($theme['security_issues'] > 0) {
                $prompt .= "- Security Issues Found: {$theme['security_issues']} ({$theme['critical_issues']} critical)\n";
            }
            $prompt .= "\n";
        }

        // Google integrations
        $prompt .= "Google Integrations Status:\n";
        $google_services = array('analytics', 'search_console', 'tag_manager', 'adsense');
        foreach ($google_services as $service) {
            $status = !empty($context['google_integrations'][$service]) ? 'INSTALLED' : 'MISSING';
            $service_name = ucwords(str_replace('_', ' ', $service));
            $prompt .= "- Google {$service_name}: {$status}\n";
        }
        $prompt .= "\n";

        // Request specific recommendations
        $prompt .= "Please provide actionable recommendations in the following JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "priority_score": 1-10,';
        $prompt .= '  "overall_health": "excellent|good|needs_attention|critical",';
        $prompt .= '  "summary": "Brief overall assessment",';
        $prompt .= '  "recommendations": [';
        $prompt .= '    {';
        $prompt .= '      "category": "security|performance|seo|maintenance|integration",';
        $prompt .= '      "priority": "critical|high|medium|low",';
        $prompt .= '      "title": "Brief recommendation title",';
        $prompt .= '      "description": "Detailed explanation and benefits",';
        $prompt .= '      "action_steps": ["Step 1", "Step 2", "Step 3"],';
        $prompt .= '      "estimated_time": "15 minutes|1 hour|etc",';
        $prompt .= '      "difficulty": "beginner|intermediate|advanced"';
        $prompt .= '    }';
        $prompt .= '  ],';
        $prompt .= '  "quick_wins": ["Easy fixes that provide immediate value"],';
        $prompt .= '  "long_term_goals": ["Strategic improvements for ongoing success"]';
        $prompt .= "}\n\n";

        $prompt .= "Focus on:\n";
        $prompt .= "1. Critical security and compatibility issues\n";
        $prompt .= "2. Missing essential integrations (Google Analytics, Search Console)\n";
        $prompt .= "3. Plugin maintenance and updates\n";
        $prompt .= "4. Performance optimizations\n";
        $prompt .= "5. SEO improvements\n\n";

        $prompt .= "Provide practical, WordPress-specific advice that a site owner can implement. ";
        $prompt .= "Prioritize recommendations by impact and ease of implementation. ";
        $prompt .= "Be specific about steps and tools when possible.";

        return $prompt;
    }

    /**
     * Make OpenAI API request
     */
    private function make_openai_request($prompt, $api_key) {
        $model = get_option('wsa_openai_model', 'gpt-3.5-turbo');
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert WordPress consultant and technical advisor. Provide clear, actionable recommendations in valid JSON format.'
                ),
                array(
                    'role' => 'user', 
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2000,
            'temperature' => 0.7,
        );

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', 
                sprintf(__('OpenAI API request failed: %s', 'wp-site-advisory'), $response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? __('Unknown API error', 'wp-site-advisory');
            
            return new WP_Error('api_error', 
                sprintf(__('OpenAI API error (Code %d): %s', 'wp-site-advisory'), $response_code, $error_message)
            );
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', __('Invalid JSON response from OpenAI API', 'wp-site-advisory'));
        }

        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error('empty_response', __('Empty response from OpenAI API', 'wp-site-advisory'));
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parse AI response and structure the data
     */
    private function parse_ai_response($response) {
        // Try to extract JSON from the response
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_content = substr($response, $json_start, $json_end - $json_start + 1);
            $parsed = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                // Validate and structure the response
                return $this->validate_ai_response($parsed);
            }
        }

        // If JSON parsing fails, create a basic structure
        return array(
            'priority_score' => 5,
            'overall_health' => 'needs_attention',
            'summary' => 'AI analysis could not be fully parsed. Please review the raw response.',
            'raw_response' => $response,
            'recommendations' => array(),
            'quick_wins' => array(),
            'long_term_goals' => array(),
            'generated_at' => current_time('mysql'),
        );
    }

    /**
     * Validate and structure AI response
     */
    private function validate_ai_response($response) {
        $validated = array(
            'priority_score' => intval($response['priority_score'] ?? 5),
            'overall_health' => sanitize_text_field($response['overall_health'] ?? 'needs_attention'),
            'summary' => sanitize_textarea_field($response['summary'] ?? 'No summary provided'),
            'recommendations' => array(),
            'quick_wins' => array(),
            'long_term_goals' => array(),
            'generated_at' => current_time('mysql'),
        );

        // Validate recommendations
        if (!empty($response['recommendations']) && is_array($response['recommendations'])) {
            foreach ($response['recommendations'] as $rec) {
                if (is_array($rec)) {
                    $validated_rec = array(
                        'category' => sanitize_text_field($rec['category'] ?? 'general'),
                        'priority' => sanitize_text_field($rec['priority'] ?? 'medium'),
                        'title' => sanitize_text_field($rec['title'] ?? 'Recommendation'),
                        'description' => sanitize_textarea_field($rec['description'] ?? ''),
                        'action_steps' => array(),
                        'estimated_time' => sanitize_text_field($rec['estimated_time'] ?? ''),
                        'difficulty' => sanitize_text_field($rec['difficulty'] ?? 'intermediate'),
                    );

                    if (!empty($rec['action_steps']) && is_array($rec['action_steps'])) {
                        foreach ($rec['action_steps'] as $step) {
                            $validated_rec['action_steps'][] = sanitize_text_field($step);
                        }
                    }

                    $validated['recommendations'][] = $validated_rec;
                }
            }
        }

        // Validate quick wins
        if (!empty($response['quick_wins']) && is_array($response['quick_wins'])) {
            foreach ($response['quick_wins'] as $win) {
                $validated['quick_wins'][] = sanitize_text_field($win);
            }
        }

        // Validate long term goals
        if (!empty($response['long_term_goals']) && is_array($response['long_term_goals'])) {
            foreach ($response['long_term_goals'] as $goal) {
                $validated['long_term_goals'][] = sanitize_text_field($goal);
            }
        }

        return $validated;
    }

    /**
     * Test OpenAI API connection
     */
    public function test_api_connection($api_key = '') {
        if (empty($api_key)) {
            $api_key = get_option('wsa_openai_api_key', '');
        }

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key is required for testing', 'wp-site-advisory'));
        }

        $test_prompt = 'Please respond with a simple JSON object: {"status": "working", "message": "API connection successful"}';
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $test_prompt
                )
            ),
            'max_tokens' => 100,
            'temperature' => 0,
        );

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => __('OpenAI API connection successful!', 'wp-site-advisory'),
            );
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? __('Unknown API error', 'wp-site-advisory');
            
            return new WP_Error('api_error', 
                sprintf(__('API connection failed (Code %d): %s', 'wp-site-advisory'), $response_code, $error_message)
            );
        }
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wsa_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory'));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $result = $this->test_api_connection($api_key);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Get usage statistics
     */
    public function get_usage_stats() {
        $stats = get_option('wsa_openai_usage_stats', array(
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'last_request' => '',
            'monthly_usage' => array(),
        ));

        return $stats;
    }

    /**
     * Update usage statistics
     */
    private function update_usage_stats($success = true) {
        $stats = $this->get_usage_stats();
        $current_month = date('Y-m');

        $stats['total_requests']++;
        $stats['last_request'] = current_time('mysql');

        if ($success) {
            $stats['successful_requests']++;
        } else {
            $stats['failed_requests']++;
        }

        // Track monthly usage
        if (!isset($stats['monthly_usage'][$current_month])) {
            $stats['monthly_usage'][$current_month] = 0;
        }
        $stats['monthly_usage'][$current_month]++;

        // Keep only last 12 months of data
        if (count($stats['monthly_usage']) > 12) {
            $stats['monthly_usage'] = array_slice($stats['monthly_usage'], -12, null, true);
        }

        update_option('wsa_openai_usage_stats', $stats);
    }
}