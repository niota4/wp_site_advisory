<?php
/**
 * WP Site Advisory Google Detector Class
 *
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Google_Detector {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize hooks if needed
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
     * Detect all Google integrations
     */
    public function detect_integrations() {
        $integrations = array(
            'analytics' => false,
            'analytics_id' => '',
            'tag_manager' => false,
            'tag_manager_id' => '',
            'search_console' => false,
            'search_console_id' => '',
            'adsense' => false,
            'adsense_id' => '',
            'fonts' => false,
            'maps' => false,
            'recaptcha' => false,
            'youtube' => false,
            'detection_methods' => array(),
        );

        // Get the current site's HTML content
        $homepage_content = $this->get_homepage_content();
        
        if (!$homepage_content) {
            return $integrations;
        }

        // Detect Google Analytics
        $analytics_result = $this->detect_google_analytics($homepage_content);
        $integrations['analytics'] = $analytics_result['detected'];
        $integrations['analytics_id'] = $analytics_result['id'];
        if ($analytics_result['detected']) {
            $integrations['detection_methods'][] = 'Google Analytics: ' . $analytics_result['method'];
        }

        // Detect Google Tag Manager
        $gtm_result = $this->detect_google_tag_manager($homepage_content);
        $integrations['tag_manager'] = $gtm_result['detected'];
        $integrations['tag_manager_id'] = $gtm_result['id'];
        if ($gtm_result['detected']) {
            $integrations['detection_methods'][] = 'Google Tag Manager: ' . $gtm_result['method'];
        }

        // Detect Google Search Console
        $gsc_result = $this->detect_google_search_console($homepage_content);
        $integrations['search_console'] = $gsc_result['detected'];
        $integrations['search_console_id'] = $gsc_result['id'];
        if ($gsc_result['detected']) {
            $integrations['detection_methods'][] = 'Google Search Console: ' . $gsc_result['method'];
        }

        // Detect Google AdSense
        $adsense_result = $this->detect_google_adsense($homepage_content);
        $integrations['adsense'] = $adsense_result['detected'];
        $integrations['adsense_id'] = $adsense_result['id'];
        if ($adsense_result['detected']) {
            $integrations['detection_methods'][] = 'Google AdSense: ' . $adsense_result['method'];
        }

        // Detect Google Fonts
        $integrations['fonts'] = $this->detect_google_fonts($homepage_content);
        if ($integrations['fonts']) {
            $integrations['detection_methods'][] = 'Google Fonts: Found in HTML/CSS';
        }

        // Detect Google Maps
        $integrations['maps'] = $this->detect_google_maps($homepage_content);
        if ($integrations['maps']) {
            $integrations['detection_methods'][] = 'Google Maps: Found in HTML/JavaScript';
        }

        // Detect Google reCAPTCHA
        $integrations['recaptcha'] = $this->detect_google_recaptcha($homepage_content);
        if ($integrations['recaptcha']) {
            $integrations['detection_methods'][] = 'Google reCAPTCHA: Found in forms or scripts';
        }

        // Detect YouTube embeds
        $integrations['youtube'] = $this->detect_youtube_embeds($homepage_content);
        if ($integrations['youtube']) {
            $integrations['detection_methods'][] = 'YouTube: Embedded videos found';
        }

        // Also check wp_head and wp_footer actions for tracking codes
        $this->detect_wordpress_integrations($integrations);

        return $integrations;
    }

    /**
     * Get homepage content for analysis
     */
    private function get_homepage_content() {
        $homepage_url = home_url();
        
        // Try to get content using wp_remote_get
        $response = wp_remote_get($homepage_url, array(
            'timeout' => 30,
            'user-agent' => 'WP SiteAdvisor Scanner',
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $content = wp_remote_retrieve_body($response);
        
        if (empty($content)) {
            return false;
        }

        return $content;
    }

    /**
     * Detect Google Analytics
     */
    private function detect_google_analytics($content) {
        $result = array(
            'detected' => false,
            'id' => '',
            'method' => ''
        );

        // Check for GA4 (gtag)
        if (preg_match('/gtag\([\'"]config[\'"],\s*[\'"]([GA]-[A-Z0-9-]+)[\'"]/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'GA4 (gtag.js)';
            return $result;
        }

        // Check for Universal Analytics
        if (preg_match('/ga\([\'"]create[\'"],\s*[\'"]([UA]-[0-9]+-[0-9]+)[\'"]/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'Universal Analytics (analytics.js)';
            return $result;
        }

        // Check for Google Analytics script inclusion
        if (preg_match('/googletagmanager\.com\/gtag\/js\?id=([GA]-[A-Z0-9-]+)/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'GA4 script inclusion';
            return $result;
        }

        // Check for Google Analytics in script tags
        if (strpos($content, 'google-analytics.com/analytics.js') !== false) {
            $result['detected'] = true;
            $result['method'] = 'Universal Analytics script inclusion';
            // Try to extract ID from ga('create', 'UA-XXXXXX-X')
            if (preg_match('/[\'"]UA-[0-9]+-[0-9]+[\'"]/', $content, $matches)) {
                $result['id'] = trim($matches[0], '"\'');
            }
            return $result;
        }

        return $result;
    }

    /**
     * Detect Google Tag Manager
     */
    private function detect_google_tag_manager($content) {
        $result = array(
            'detected' => false,
            'id' => '',
            'method' => ''
        );

        // Check for GTM container
        if (preg_match('/googletagmanager\.com\/gtm\.js\?id=([GTM]-[A-Z0-9]+)/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'GTM script inclusion';
            return $result;
        }

        // Check for GTM noscript
        if (preg_match('/googletagmanager\.com\/ns\.html\?id=([GTM]-[A-Z0-9]+)/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'GTM noscript fallback';
            return $result;
        }

        // Check for dataLayer
        if (strpos($content, 'dataLayer') !== false && strpos($content, 'googletagmanager') !== false) {
            $result['detected'] = true;
            $result['method'] = 'GTM dataLayer detected';
            return $result;
        }

        return $result;
    }

    /**
     * Detect Google Search Console
     */
    private function detect_google_search_console($content) {
        $result = array(
            'detected' => false,
            'id' => '',
            'method' => ''
        );

        // Check for Google Search Console verification meta tag
        if (preg_match('/<meta\s+name=[\'"]google-site-verification[\'"][^>]*content=[\'"]([^\'\"]+)[\'"]/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'Meta tag verification';
            return $result;
        }

        // Check for Search Console in Analytics or GTM (indirect detection)
        if ($this->detect_google_analytics($content)['detected'] || $this->detect_google_tag_manager($content)['detected']) {
            // If GA or GTM is present, GSC is likely connected
            $result['detected'] = true;
            $result['method'] = 'Likely connected via Analytics/GTM';
            return $result;
        }

        return $result;
    }

    /**
     * Detect Google AdSense
     */
    private function detect_google_adsense($content) {
        $result = array(
            'detected' => false,
            'id' => '',
            'method' => ''
        );

        // Check for AdSense script
        if (preg_match('/pagead2\.googlesyndication\.com\/pagead\/js\/adsbygoogle\.js\?client=(ca-pub-[0-9]+)/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = $matches[1];
            $result['method'] = 'AdSense script inclusion';
            return $result;
        }

        // Check for AdSense ads
        if (preg_match('/data-ad-client=[\'"]ca-pub-([0-9]+)[\'"]/', $content, $matches)) {
            $result['detected'] = true;
            $result['id'] = 'ca-pub-' . $matches[1];
            $result['method'] = 'AdSense ad unit';
            return $result;
        }

        // Check for googlesyndication domain
        if (strpos($content, 'googlesyndication.com') !== false) {
            $result['detected'] = true;
            $result['method'] = 'AdSense domain detected';
            return $result;
        }

        return $result;
    }

    /**
     * Detect Google Fonts
     */
    private function detect_google_fonts($content) {
        // Check for Google Fonts CSS
        if (strpos($content, 'fonts.googleapis.com') !== false) {
            return true;
        }

        // Check for Google Fonts API
        if (strpos($content, 'fonts.gstatic.com') !== false) {
            return true;
        }

        // Check for @import statements
        if (preg_match('/@import.*fonts\.googleapis\.com/', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Detect Google Maps
     */
    private function detect_google_maps($content) {
        // Check for Google Maps JavaScript API
        if (strpos($content, 'maps.googleapis.com/maps/api/js') !== false) {
            return true;
        }

        // Check for Google Maps embed
        if (strpos($content, 'maps.google.com/maps') !== false) {
            return true;
        }

        // Check for Maps JavaScript objects
        if (preg_match('/google\.maps\./', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Detect Google reCAPTCHA
     */
    private function detect_google_recaptcha($content) {
        // Check for reCAPTCHA API
        if (strpos($content, 'recaptcha/api.js') !== false) {
            return true;
        }

        // Check for reCAPTCHA class
        if (strpos($content, 'g-recaptcha') !== false) {
            return true;
        }

        // Check for reCAPTCHA v3
        if (strpos($content, 'grecaptcha') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Detect YouTube embeds
     */
    private function detect_youtube_embeds($content) {
        // Check for YouTube iframe embeds
        if (strpos($content, 'youtube.com/embed') !== false) {
            return true;
        }

        // Check for YouTube watch links
        if (strpos($content, 'youtube.com/watch') !== false) {
            return true;
        }

        // Check for YouTube API
        if (strpos($content, 'youtube.com/iframe_api') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Detect WordPress-specific integrations
     */
    private function detect_wordpress_integrations(&$integrations) {
        // Check for popular WordPress plugins that integrate with Google services
        $active_plugins = get_option('active_plugins', array());
        
        // Get all plugins data
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        foreach ($active_plugins as $plugin_file) {
            if (isset($all_plugins[$plugin_file])) {
                $plugin_name = strtolower($all_plugins[$plugin_file]['Name']);
                
                // Google Analytics plugins
                if (strpos($plugin_name, 'google analytics') !== false || 
                    strpos($plugin_name, 'analytify') !== false ||
                    strpos($plugin_name, 'monster insights') !== false ||
                    strpos($plugin_name, 'ga google analytics') !== false) {
                    
                    if (!$integrations['analytics']) {
                        $integrations['analytics'] = true;
                        $integrations['detection_methods'][] = 'WordPress Plugin: ' . $all_plugins[$plugin_file]['Name'];
                    }
                }

                // Google Tag Manager plugins
                if (strpos($plugin_name, 'tag manager') !== false ||
                    strpos($plugin_name, 'gtm') !== false) {
                    
                    if (!$integrations['tag_manager']) {
                        $integrations['tag_manager'] = true;
                        $integrations['detection_methods'][] = 'WordPress Plugin: ' . $all_plugins[$plugin_file]['Name'];
                    }
                }

                // SEO plugins (often include Search Console)
                if (strpos($plugin_name, 'yoast') !== false ||
                    strpos($plugin_name, 'rankmath') !== false ||
                    strpos($plugin_name, 'all in one seo') !== false) {
                    
                    if (!$integrations['search_console']) {
                        $integrations['search_console'] = true;
                        $integrations['detection_methods'][] = 'SEO Plugin: ' . $all_plugins[$plugin_file]['Name'];
                    }
                }
            }
        }

        // Check theme options for Google integrations
        $this->check_theme_integrations($integrations);
    }

    /**
     * Check theme options for Google integrations
     */
    private function check_theme_integrations(&$integrations) {
        // Get theme mods and options
        $theme_mods = get_theme_mods();
        $theme_options = get_option(get_option('stylesheet'), array());
        
        // Combine both arrays
        $all_options = array_merge($theme_mods, $theme_options);
        
        // Convert to string for searching
        $options_string = strtolower(serialize($all_options));
        
        // Check for Google Analytics IDs in theme options
        if (!$integrations['analytics']) {
            if (preg_match('/(ua-[0-9]+-[0-9]+|ga?-[a-z0-9-]+)/i', $options_string, $matches)) {
                $integrations['analytics'] = true;
                $integrations['analytics_id'] = $matches[1];
                $integrations['detection_methods'][] = 'Theme Options: Analytics ID found';
            }
        }

        // Check for Google Tag Manager IDs
        if (!$integrations['tag_manager']) {
            if (preg_match('/(gtm-[a-z0-9]+)/i', $options_string, $matches)) {
                $integrations['tag_manager'] = true;
                $integrations['tag_manager_id'] = $matches[1];
                $integrations['detection_methods'][] = 'Theme Options: GTM ID found';
            }
        }

        // Check for AdSense IDs
        if (!$integrations['adsense']) {
            if (preg_match('/(ca-pub-[0-9]+)/i', $options_string, $matches)) {
                $integrations['adsense'] = true;
                $integrations['adsense_id'] = $matches[1];
                $integrations['detection_methods'][] = 'Theme Options: AdSense ID found';
            }
        }
    }

    /**
     * Get recommendations for missing integrations
     */
    public function get_missing_integrations_recommendations($integrations) {
        $recommendations = array();

        if (!$integrations['analytics']) {
            $recommendations[] = array(
                'service' => 'Google Analytics',
                'priority' => 'high',
                'reason' => 'Essential for understanding your website traffic and user behavior',
                'setup_steps' => array(
                    'Create a Google Analytics account at analytics.google.com',
                    'Add your website as a property',
                    'Install the tracking code in your website header',
                    'Verify data collection after 24-48 hours'
                )
            );
        }

        if (!$integrations['search_console']) {
            $recommendations[] = array(
                'service' => 'Google Search Console',
                'priority' => 'high',
                'reason' => 'Critical for monitoring your website\'s search performance and SEO issues',
                'setup_steps' => array(
                    'Go to search.google.com/search-console',
                    'Add and verify your website property',
                    'Submit your sitemap',
                    'Monitor for crawl errors and search performance'
                )
            );
        }

        if (!$integrations['tag_manager']) {
            $recommendations[] = array(
                'service' => 'Google Tag Manager',
                'priority' => 'medium',
                'reason' => 'Simplifies tracking code management and allows for advanced tracking setups',
                'setup_steps' => array(
                    'Create a Google Tag Manager account',
                    'Create a container for your website',
                    'Install the GTM code in your website',
                    'Move your existing tracking codes to GTM'
                )
            );
        }

        return $recommendations;
    }

    /**
     * Generate a comprehensive integration report
     */
    public function generate_integration_report($integrations) {
        $report = array(
            'summary' => array(),
            'detected_services' => array(),
            'missing_services' => array(),
            'recommendations' => array(),
            'technical_details' => $integrations['detection_methods'] ?? array(),
        );

        $all_services = array(
            'analytics' => 'Google Analytics',
            'tag_manager' => 'Google Tag Manager', 
            'search_console' => 'Google Search Console',
            'adsense' => 'Google AdSense',
            'fonts' => 'Google Fonts',
            'maps' => 'Google Maps',
            'recaptcha' => 'Google reCAPTCHA',
            'youtube' => 'YouTube'
        );

        foreach ($all_services as $key => $service_name) {
            if ($integrations[$key]) {
                $report['detected_services'][] = array(
                    'name' => $service_name,
                    'id' => $integrations[$key . '_id'] ?? '',
                );
            } else {
                $report['missing_services'][] = $service_name;
            }
        }

        $report['summary'] = array(
            'total_services' => count($all_services),
            'detected_count' => count($report['detected_services']),
            'missing_count' => count($report['missing_services']),
            'integration_score' => round((count($report['detected_services']) / count($all_services)) * 100),
        );

        // Get recommendations for missing critical services
        $critical_services = array('analytics', 'search_console');
        $missing_critical = array_filter($critical_services, function($service) use ($integrations) {
            return !$integrations[$service];
        });

        if (!empty($missing_critical)) {
            $report['recommendations'] = $this->get_missing_integrations_recommendations($integrations);
        }

        return $report;
    }
}