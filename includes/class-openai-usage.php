<?php
/**
 * OpenAI Usage API Handler
 * 
 * Handles fetching and caching OpenAI token usage and billing information
 * 
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_OpenAI_Usage {
    
    /**
     * Cache key for usage data
     */
    const USAGE_CACHE_KEY = 'wsa_openai_usage_data';
    
    /**
     * Cache expiration time (1 hour)
     */
    const CACHE_EXPIRATION = 3600;
    
    /**
     * OpenAI Usage API endpoint
     */
    const USAGE_API_URL = 'https://api.openai.com/v1/usage';
    
    /**
     * Get cached or fresh usage data
     * 
     * @return array|WP_Error Usage data array or error object
     */
    public function get_usage_data() {
        // Try to get cached data first
        $cached_data = get_transient(self::USAGE_CACHE_KEY);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Fetch fresh data from OpenAI API
        $fresh_data = $this->fetch_usage_from_api();
        
        // Cache the data if successful
        if (!is_wp_error($fresh_data)) {
            set_transient(self::USAGE_CACHE_KEY, $fresh_data, self::CACHE_EXPIRATION);
        }
        
        return $fresh_data;
    }
    
    /**
     * Force refresh usage data (bypass cache)
     * 
     * @return array|WP_Error Fresh usage data or error
     */
    public function refresh_usage_data() {
        // Clear existing cache
        delete_transient(self::USAGE_CACHE_KEY);
        
        // Fetch and cache fresh data
        return $this->get_usage_data();
    }
    
    /**
     * Fetch usage data from OpenAI API
     * 
     * @return array|WP_Error Usage data or error object
     */
    private function fetch_usage_from_api() {
        // Get stored API key (hashed/encrypted)
        $api_key = get_option('wsa_openai_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error(
                'no_api_key',
                __('OpenAI API key is not configured. Please add your API key in the OpenAI Configuration section.', 'wp-site-advisory')
            );
        }
        
        // Calculate date range (last 30 days)
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Prepare API request
        $request_url = add_query_arg(array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ), self::USAGE_API_URL);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WP-Site-Advisory/' . WP_SITE_ADVISORY_VERSION
        );
        
        // Make API request with timeout and error handling
        $response = wp_remote_get($request_url, array(
            'headers' => $headers,
            'timeout' => 15,
            'sslverify' => true
        ));
        
        // Check for HTTP errors
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                sprintf(
                    __('Failed to connect to OpenAI API: %s', 'wp-site-advisory'),
                    $response->get_error_message()
                )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle different HTTP response codes
        switch ($response_code) {
            case 200:
                // Success - parse response
                $data = json_decode($response_body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error(
                        'invalid_response',
                        __('OpenAI API returned invalid JSON response.', 'wp-site-advisory')
                    );
                }
                
                return $this->process_usage_data($data, $start_date, $end_date);
                
            case 401:
                return new WP_Error(
                    'invalid_api_key',
                    __('OpenAI API key is invalid or expired. Please update your API key.', 'wp-site-advisory')
                );
                
            case 429:
                return new WP_Error(
                    'rate_limited',
                    __('OpenAI API rate limit exceeded. Please try again in a few minutes.', 'wp-site-advisory')
                );
                
            case 500:
            case 502:
            case 503:
                return new WP_Error(
                    'server_error',
                    __('OpenAI API is currently unavailable. Please try again later.', 'wp-site-advisory')
                );
                
            default:
                return new WP_Error(
                    'unexpected_response',
                    sprintf(
                        __('OpenAI API returned unexpected response code: %d', 'wp-site-advisory'),
                        $response_code
                    )
                );
        }
    }
    
    /**
     * Process raw usage data from OpenAI API
     * 
     * @param array $raw_data Raw API response data
     * @param string $start_date Start date for the period
     * @param string $end_date End date for the period
     * @return array Processed usage data
     */
    private function process_usage_data($raw_data, $start_date, $end_date) {
        // Initialize processed data structure
        $processed = array(
            'period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'days' => (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60) + 1
            ),
            'usage' => array(
                'total_tokens' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_requests' => 0
            ),
            'cost' => array(
                'total_cost' => 0.00,
                'prompt_cost' => 0.00,
                'completion_cost' => 0.00,
                'currency' => 'USD'
            ),
            'models' => array(),
            'daily_usage' => array(),
            'last_updated' => current_time('Y-m-d H:i:s')
        );
        
        // Process daily usage data if available
        if (isset($raw_data['data']) && is_array($raw_data['data'])) {
            foreach ($raw_data['data'] as $day_data) {
                $date = $day_data['date'] ?? '';
                $usage = $day_data['usage'] ?? array();
                
                if (!empty($date) && !empty($usage)) {
                    // Aggregate daily totals
                    foreach ($usage as $model_usage) {
                        $model = $model_usage['model'] ?? 'unknown';
                        $tokens = intval($model_usage['tokens'] ?? 0);
                        $requests = intval($model_usage['requests'] ?? 0);
                        
                        // Add to totals
                        $processed['usage']['total_tokens'] += $tokens;
                        $processed['usage']['total_requests'] += $requests;
                        
                        // Track per-model usage
                        if (!isset($processed['models'][$model])) {
                            $processed['models'][$model] = array(
                                'tokens' => 0,
                                'requests' => 0,
                                'cost' => 0.00
                            );
                        }
                        
                        $processed['models'][$model]['tokens'] += $tokens;
                        $processed['models'][$model]['requests'] += $requests;
                        
                        // Calculate cost based on model pricing
                        $model_cost = $this->calculate_model_cost($model, $tokens);
                        $processed['models'][$model]['cost'] += $model_cost;
                        $processed['cost']['total_cost'] += $model_cost;
                    }
                    
                    // Store daily breakdown
                    $processed['daily_usage'][$date] = $usage;
                }
            }
        }
        
        // Add percentage and trend calculations
        $processed['analytics'] = $this->calculate_usage_analytics($processed);
        
        return $processed;
    }
    
    /**
     * Calculate cost for specific model and token usage
     * 
     * @param string $model Model name
     * @param int $tokens Number of tokens
     * @return float Cost in USD
     */
    private function calculate_model_cost($model, $tokens) {
        // OpenAI pricing as of 2025 (per 1K tokens)
        // Note: These should be updated regularly or fetched from API
        $pricing = array(
            'gpt-4-turbo' => array('input' => 0.01, 'output' => 0.03),
            'gpt-4' => array('input' => 0.03, 'output' => 0.06),
            'gpt-3.5-turbo' => array('input' => 0.001, 'output' => 0.002),
            'text-embedding-ada-002' => array('input' => 0.0001, 'output' => 0),
            'whisper-1' => array('input' => 0.006, 'output' => 0),
            'tts-1' => array('input' => 0.015, 'output' => 0),
            'dall-e-3' => array('input' => 0.04, 'output' => 0) // per image, not token
        );
        
        // Default pricing if model not found
        $default_rate = 0.002;
        
        // Find matching pricing
        $rate = $default_rate;
        foreach ($pricing as $model_pattern => $rates) {
            if (strpos(strtolower($model), strtolower($model_pattern)) !== false) {
                // Use input rate as default (most usage is input tokens)
                $rate = $rates['input'];
                break;
            }
        }
        
        // Calculate cost (rate is per 1K tokens)
        return ($tokens / 1000) * $rate;
    }
    
    /**
     * Calculate usage analytics and trends
     * 
     * @param array $usage_data Processed usage data
     * @return array Analytics data
     */
    private function calculate_usage_analytics($usage_data) {
        $analytics = array(
            'daily_average' => 0,
            'peak_day' => null,
            'trend' => 'stable', // up, down, stable
            'efficiency_score' => 0,
            'projected_monthly_cost' => 0
        );
        
        if (!empty($usage_data['daily_usage'])) {
            $daily_totals = array();
            
            foreach ($usage_data['daily_usage'] as $date => $day_usage) {
                $daily_total = 0;
                foreach ($day_usage as $model_usage) {
                    $daily_total += intval($model_usage['tokens'] ?? 0);
                }
                $daily_totals[$date] = $daily_total;
            }
            
            if (!empty($daily_totals)) {
                // Calculate daily average
                $analytics['daily_average'] = array_sum($daily_totals) / count($daily_totals);
                
                // Find peak day
                $max_usage = max($daily_totals);
                $analytics['peak_day'] = array(
                    'date' => array_search($max_usage, $daily_totals),
                    'tokens' => $max_usage
                );
                
                // Calculate trend (simple: compare first half vs second half)
                $half_point = floor(count($daily_totals) / 2);
                $first_half_avg = array_sum(array_slice($daily_totals, 0, $half_point)) / $half_point;
                $second_half_avg = array_sum(array_slice($daily_totals, -$half_point)) / $half_point;
                
                $change_percent = (($second_half_avg - $first_half_avg) / $first_half_avg) * 100;
                
                if ($change_percent > 15) {
                    $analytics['trend'] = 'up';
                } elseif ($change_percent < -15) {
                    $analytics['trend'] = 'down';
                } else {
                    $analytics['trend'] = 'stable';
                }
                
                // Project monthly cost
                $analytics['projected_monthly_cost'] = ($analytics['daily_average'] / 1000) * 
                                                      $this->calculate_model_cost('gpt-3.5-turbo', 1000) * 30;
            }
        }
        
        return $analytics;
    }
    
    /**
     * Format usage data for display
     * 
     * @param array $usage_data Raw usage data
     * @return array Formatted display data
     */
    public function format_for_display($usage_data) {
        if (is_wp_error($usage_data)) {
            return array(
                'error' => true,
                'message' => $usage_data->get_error_message(),
                'code' => $usage_data->get_error_code()
            );
        }
        
        return array(
            'error' => false,
            'period' => sprintf(
                __('%d days (%s to %s)', 'wp-site-advisory'),
                $usage_data['period']['days'],
                date('M j', strtotime($usage_data['period']['start_date'])),
                date('M j, Y', strtotime($usage_data['period']['end_date']))
            ),
            'total_tokens' => number_format($usage_data['usage']['total_tokens']),
            'total_cost' => '$' . number_format($usage_data['cost']['total_cost'], 2),
            'daily_average' => number_format($usage_data['analytics']['daily_average']),
            'trend' => $usage_data['analytics']['trend'],
            'top_model' => $this->get_top_model($usage_data['models']),
            'last_updated' => $usage_data['last_updated'],
            'raw_data' => $usage_data
        );
    }
    
    /**
     * Get the most used model
     * 
     * @param array $models Model usage data
     * @return string Top model name
     */
    private function get_top_model($models) {
        if (empty($models)) {
            return __('No usage data', 'wp-site-advisory');
        }
        
        $top_model = '';
        $max_tokens = 0;
        
        foreach ($models as $model => $data) {
            if ($data['tokens'] > $max_tokens) {
                $max_tokens = $data['tokens'];
                $top_model = $model;
            }
        }
        
        return $top_model ?: __('Unknown', 'wp-site-advisory');
    }
    
    /**
     * Clear cached usage data
     */
    public function clear_cache() {
        delete_transient(self::USAGE_CACHE_KEY);
    }
}