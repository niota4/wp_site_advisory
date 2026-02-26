<?php
/**
 * WP Site Advisory Pro Helper Functions
 * 
 * Handles Pro version detection and upgrade CTAs
 * 
 * @package WP_Site_Advisory
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Pro_Helper {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Pro plugin slug
     */
    const PRO_PLUGIN_SLUG = 'wp-site-advisory-pro/wp-site-advisory-pro.php';
    
    /**
     * Upgrade URL
     */
    const UPGRADE_URL = 'https://wpsiteadvisor.com/upgrade';
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_init', array($this, 'init_pro_hooks'));
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
     * Initialize Pro-related hooks
     */
    public function init_pro_hooks() {
        // Add upgrade notices for Pro features
        if (!$this->is_pro_active()) {
            add_action('admin_notices', array($this, 'show_pro_notices'));
        }
    }
    
    /**
     * Check if Pro version is installed
     */
    public function is_pro_installed() {
        $plugins = get_plugins();
        return isset($plugins[self::PRO_PLUGIN_SLUG]);
    }
    
    /**
     * Check if Pro version is active
     */
    public function is_pro_active() {
        return is_plugin_active(self::PRO_PLUGIN_SLUG);
    }
    
    /**
     * Check if Pro version exists and has a specific feature
     */
    public function has_pro_feature($feature) {
        if (!$this->is_pro_active()) {
            return false;
        }
        
        // Check if specific Pro feature exists
        switch ($feature) {
            case 'ai_analysis':
                return function_exists('wsa_pro_ai_analysis');
            case 'vulnerability_scan':
                return function_exists('wsa_pro_vulnerability_scan');
            case 'one_click_fixes':
                return function_exists('wsa_pro_one_click_fixes');
            case 'white_label':
                return function_exists('wsa_pro_white_label');
            case 'advanced_scheduling':
                return function_exists('wsa_pro_advanced_scheduling');
            case 'custom_branding':
                return function_exists('wsa_pro_custom_branding');
            default:
                return false;
        }
    }
    
    /**
     * Get Pro features list
     */
    public function get_pro_features() {
        return array(
            'ai_optimizer' => array(
                'title' => __('AI Automated Optimizer', 'wp-site-advisory'),
                'description' => __('AI-powered automatic optimization with database cleanup, image compression, and performance tuning', 'wp-site-advisory'),
                'icon' => 'dashicons-performance',
                'benefit' => __('Boost site speed by 40-60% with zero manual work')
            ),
            'ai_detective' => array(
                'title' => __('AI Site Detective', 'wp-site-advisory'),
                'description' => __('Advanced AI analysis that investigates security vulnerabilities, performance bottlenecks, and SEO issues', 'wp-site-advisory'),
                'icon' => 'dashicons-search',
                'benefit' => __('Uncover hidden issues before they impact your site')
            ),
            'ai_content_analyzer' => array(
                'title' => __('AI Content Analyzer', 'wp-site-advisory'),
                'description' => __('Real-time AI content optimization with SEO scoring, readability analysis, and accessibility improvements', 'wp-site-advisory'),
                'icon' => 'dashicons-edit-page',
                'benefit' => __('Improve content quality and search rankings automatically')
            ),
            'predictive_analytics' => array(
                'title' => __('Predictive Analytics', 'wp-site-advisory'),
                'description' => __('AI forecasts future performance issues, traffic patterns, and security risks before they happen', 'wp-site-advisory'),
                'icon' => 'dashicons-chart-area',
                'benefit' => __('Prevent problems before they affect your visitors')
            ),
            'ai_chatbot' => array(
                'title' => __('AI Support Chatbot', 'wp-site-advisory'),
                'description' => __('Intelligent AI assistant that provides instant answers about your site health and optimization recommendations', 'wp-site-advisory'),
                'icon' => 'dashicons-format-chat',
                'benefit' => __('Get expert advice 24/7 from your personal AI assistant')
            ),
            'openai_usage_tracking' => array(
                'title' => __('OpenAI Usage & Billing', 'wp-site-advisory'),
                'description' => __('Complete visibility into AI API usage with cost tracking, optimization tips, and budget management', 'wp-site-advisory'),
                'icon' => 'dashicons-chart-line',
                'benefit' => __('Monitor and optimize your AI costs with detailed analytics')
            ),
            'vulnerability_scan' => array(
                'title' => __('Advanced Vulnerability Scanning', 'wp-site-advisory'),
                'description' => __('Deep security scans using WPScan database and real-time threat intelligence with AI-powered risk assessment', 'wp-site-advisory'),
                'icon' => 'dashicons-shield-alt',
                'benefit' => __('Stay protected with military-grade security monitoring')
            ),
            'white_label' => array(
                'title' => __('White-Label Agency Tools', 'wp-site-advisory'),
                'description' => __('Custom branding, automated client reports, and multi-site management with AI insights dashboard', 'wp-site-advisory'),
                'icon' => 'dashicons-groups',
                'benefit' => __('Scale your agency with professional AI-powered reports')
            )
        );
    }
    
    /**
     * Generate upgrade CTA for a specific feature
     */
    public function upgrade_cta($feature_name, $context = 'button') {
        if ($this->is_pro_active()) {
            return '';
        }
        
        $features = $this->get_pro_features();
        $feature = isset($features[$feature_name]) ? $features[$feature_name] : null;
        
        if (!$feature) {
            $feature = array(
                'title' => ucwords(str_replace('_', ' ', $feature_name)),
                'description' => sprintf(__('%s is a Pro feature', 'wp-site-advisory'), ucwords(str_replace('_', ' ', $feature_name)))
            );
        }
        
        switch ($context) {
            case 'notice':
                return $this->render_upgrade_notice($feature_name, $feature);
            case 'inline':
                return $this->render_inline_upgrade($feature_name, $feature);
            case 'modal':
                return $this->render_modal_upgrade($feature_name, $feature);
            case 'button':
            default:
                return $this->render_button_upgrade($feature_name, $feature);
        }
    }
    
    /**
     * Render button upgrade CTA
     */
    private function render_button_upgrade($feature_name, $feature) {
        ob_start();
        ?>
        <div class="wsa-upgrade-cta wsa-upgrade-button">
            <span class="wsa-pro-badge">🔒 PRO</span>
            <strong><?php echo esc_html($feature['title']); ?></strong>
            <p><?php echo esc_html($feature['description']); ?></p>
            <a href="<?php echo esc_url(self::UPGRADE_URL . '?feature=' . $feature_name); ?>" 
               class="button button-primary wsa-upgrade-btn" 
               target="_blank">
                <?php _e('Upgrade to Pro', 'wp-site-advisory'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render inline upgrade CTA
     */
    private function render_inline_upgrade($feature_name, $feature) {
        ob_start();
        ?>
        <span class="wsa-upgrade-inline">
            🔒 <strong><?php echo esc_html($feature['title']); ?></strong> - 
            <a href="<?php echo esc_url(self::UPGRADE_URL . '?feature=' . $feature_name); ?>" 
               target="_blank" class="wsa-upgrade-link">
                <?php _e('Available in Pro', 'wp-site-advisory'); ?>
            </a>
        </span>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render admin notice upgrade CTA
     */
    private function render_upgrade_notice($feature_name, $feature) {
        ob_start();
        ?>
        <div class="notice notice-info is-dismissible wsa-pro-notice" data-feature="<?php echo esc_attr($feature_name); ?>">
            <p>
                <span class="dashicons dashicons-lock" style="color: #0073aa;"></span>
                <strong><?php echo esc_html($feature['title']); ?></strong> <?php _e('is a Pro feature.', 'wp-site-advisory'); ?>
                <a href="<?php echo esc_url(self::UPGRADE_URL . '?feature=' . $feature_name); ?>" 
                   target="_blank" class="button button-secondary" style="margin-left: 10px;">
                    <?php _e('Upgrade to WP SiteAdvisor Pro', 'wp-site-advisory'); ?>
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render modal upgrade CTA
     */
    private function render_modal_upgrade($feature_name, $feature) {
        ob_start();
        ?>
        <div class="wsa-upgrade-modal" id="wsa-upgrade-modal-<?php echo esc_attr($feature_name); ?>">
            <div class="wsa-modal-content">
                <div class="wsa-modal-header">
                    <span class="wsa-pro-badge-large">🔒 PRO FEATURE</span>
                    <button class="wsa-modal-close">&times;</button>
                </div>
                <div class="wsa-modal-body">
                    <h3><?php echo esc_html($feature['title']); ?></h3>
                    <p><?php echo esc_html($feature['description']); ?></p>
                    <?php if (isset($feature['benefit'])): ?>
                        <div class="wsa-feature-benefit">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html($feature['benefit']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="wsa-modal-footer">
                    <a href="<?php echo esc_url(self::UPGRADE_URL . '?feature=' . $feature_name); ?>" 
                       class="button button-primary button-hero" 
                       target="_blank">
                        <?php _e('Upgrade to Pro Now', 'wp-site-advisory'); ?>
                    </a>
                    <button class="button wsa-modal-close">
                        <?php _e('Maybe Later', 'wp-site-advisory'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Show Pro notices for features being used
     */
    public function show_pro_notices() {
        $current_screen = get_current_screen();
        
        // Only show on WP SiteAdvisor pages
        if (!$current_screen || strpos($current_screen->id, 'wp-site-advisory') === false) {
            return;
        }
        
        // Check if user recently tried to use Pro features
        $attempted_features = get_transient('wsa_attempted_pro_features_' . get_current_user_id());
        
        if ($attempted_features && is_array($attempted_features)) {
            foreach ($attempted_features as $feature) {
                echo $this->upgrade_cta($feature, 'notice');
            }
            // Clear the transient after showing
            delete_transient('wsa_attempted_pro_features_' . get_current_user_id());
        }
    }
    
    /**
     * Track when user attempts to use Pro feature
     */
    public function track_pro_attempt($feature_name) {
        if ($this->is_pro_active()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $attempted_features = get_transient('wsa_attempted_pro_features_' . $user_id);
        
        if (!is_array($attempted_features)) {
            $attempted_features = array();
        }
        
        if (!in_array($feature_name, $attempted_features)) {
            $attempted_features[] = $feature_name;
            set_transient('wsa_attempted_pro_features_' . $user_id, $attempted_features, 300); // 5 minutes
        }
    }
    
    /**
     * Disable Pro feature and show upgrade message
     */
    public function disable_pro_feature($feature_name, $return_message = true) {
        $this->track_pro_attempt($feature_name);
        
        if ($return_message) {
            $features = $this->get_pro_features();
            $feature = isset($features[$feature_name]) ? $features[$feature_name] : array(
                'title' => ucwords(str_replace('_', ' ', $feature_name))
            );
            
            return array(
                'success' => false,
                'data' => array(
                    'message' => sprintf(
                        __('%s is a Pro feature. %sUpgrade now%s to unlock it.', 'wp-site-advisory'),
                        $feature['title'],
                        '<a href="' . esc_url(self::UPGRADE_URL . '?feature=' . $feature_name) . '" target="_blank">',
                        '</a>'
                    ),
                    'pro_feature' => true,
                    'feature_name' => $feature_name,
                    'upgrade_url' => self::UPGRADE_URL . '?feature=' . $feature_name
                )
            );
        }
        
        return false;
    }
    
    /**
     * Get upgrade URL for specific feature
     */
    public function get_upgrade_url($feature_name = '') {
        $url = self::UPGRADE_URL;
        
        if ($feature_name) {
            $url .= '?feature=' . urlencode($feature_name);
        }
        
        // Add UTM parameters for tracking
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'utm_source=plugin&utm_medium=upgrade_cta&utm_campaign=wp_siteadvisor_free';
        
        return $url;
    }
    
    /**
     * Render Pro features showcase section
     */
    public function render_pro_showcase() {
        if ($this->is_pro_active()) {
            return;
        }
        
        $features = $this->get_pro_features();
        
        ?>
        <div class="wsa-pro-showcase">
            <div class="wsa-pro-header">
                <h2>
                    <?php 
                    // Use branded logo if available, fallback to icon
                    if (class_exists('WP_Site_Advisory_Branding')) {
                        echo WP_Site_Advisory_Branding::render_logo('medium');
                        echo ' <span style="color: #0073aa;">PRO</span>';
                    } else {
                        ?>
                        <span class="dashicons dashicons-superhero-alt" style="color: #0073aa;"></span>
                        <?php
                    }
                    ?>
                    <?php _e('Supercharge with AI', 'wp-site-advisory'); ?>
                </h2>
                <p><?php _e('Transform your WordPress management with cutting-edge AI automation and intelligence:', 'wp-site-advisory'); ?></p>
            </div>
            
            <div class="wsa-pro-features-grid">
                <?php foreach ($features as $feature_key => $feature): 
                    $is_ai_feature = in_array($feature_key, ['ai_optimizer', 'ai_detective', 'ai_content_analyzer', 'predictive_analytics', 'ai_chatbot', 'openai_usage_tracking']);
                ?>
                    <div class="wsa-pro-feature-card" <?php echo $is_ai_feature ? 'data-ai="true"' : ''; ?>>
                        <div class="wsa-feature-icon">
                            <span class="dashicons <?php echo esc_attr($feature['icon']); ?>"></span>
                        </div>
                        <h3><?php echo esc_html($feature['title']); ?></h3>
                        <p><?php echo esc_html($feature['description']); ?></p>
                        <?php if (isset($feature['benefit'])): ?>
                            <div class="wsa-feature-benefit">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html($feature['benefit']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="wsa-pro-cta">
                <a href="<?php echo esc_url($this->get_upgrade_url('showcase')); ?>" 
                   class="button button-primary button-hero" 
                   target="_blank">
                    <?php _e('Unlock AI-Powered WordPress Optimization', 'wp-site-advisory'); ?>
                </a>
                <p class="wsa-pro-guarantee">
                    <?php _e('Instant access • Enterprise-grade AI • Professional support', 'wp-site-advisory'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}

/**
 * Global helper functions
 */

/**
 * Generate upgrade CTA
 */
function wsa_upgrade_cta($feature_name, $context = 'button') {
    return WP_Site_Advisory_Pro_Helper::get_instance()->upgrade_cta($feature_name, $context);
}

/**
 * Track Pro feature attempt
 */
function wsa_track_pro_attempt($feature_name) {
    WP_Site_Advisory_Pro_Helper::get_instance()->track_pro_attempt($feature_name);
}

/**
 * Disable Pro feature gracefully
 */
function wsa_disable_pro_feature($feature_name, $return_message = true) {
    return WP_Site_Advisory_Pro_Helper::get_instance()->disable_pro_feature($feature_name, $return_message);
}