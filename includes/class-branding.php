<?php
/**
 * WP Site Advisory Branding Helper
 * 
 * Manages logos, icons, and branding elements throughout the plugin
 * 
 * @package WP_Site_Advisory
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Branding {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Base URL for plugin assets
     */
    private $assets_url;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->assets_url = WP_SITE_ADVISORY_PLUGIN_URL . 'assets/images/';
        add_action('admin_head', array($this, 'add_admin_favicon'));
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
     * Get plugin icon URL
     */
    public function get_icon_url($size = 'default') {
        $icons = array(
            'small' => 'wsa-icon.svg',
            'medium' => 'wsa-icon.svg', 
            'large' => 'wsa-icon.svg',
            'default' => 'wsa-icon.svg'
        );
        
        $icon_file = isset($icons[$size]) ? $icons[$size] : $icons['default'];
        return $this->assets_url . $icon_file;
    }
    
    /**
     * Get plugin logo URL
     */
    public function get_logo_url($size = 'default') {
        $logos = array(
            'small' => 'wsa-logo.svg',
            'medium' => 'wsa-logo.svg',
            'large' => 'wsa-logo.svg',
            'default' => 'wsa-logo.svg'
        );
        
        $logo_file = isset($logos[$size]) ? $logos[$size] : $logos['default'];
        return $this->assets_url . $logo_file;
    }
    
    /**
     * Render plugin icon HTML
     */
    public static function render_icon($size = 'default', $attributes = array()) {
        $instance = self::get_instance();
        $icon_url = $instance->get_icon_url($size);
        $alt_text = __('WP Site Advisor', 'wp-site-advisory');
        
        $default_attributes = array(
            'src' => $icon_url,
            'alt' => $alt_text,
            'class' => 'wsa-icon wsa-icon-' . $size
        );
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
        
        return sprintf('<img%s />', $attr_string);
    }
    
    /**
     * Render plugin logo HTML
     */
    public static function render_logo($size = 'default', $attributes = array()) {
        $instance = self::get_instance();
        $logo_url = $instance->get_logo_url($size);
        $alt_text = __('WP Site Advisor', 'wp-site-advisory');
        
        $default_attributes = array(
            'src' => $logo_url,
            'alt' => $alt_text,
            'class' => 'wsa-logo wsa-logo-' . $size
        );
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
        
        return sprintf('<img%s />', $attr_string);
    }
    
    /**
     * Get brand colors
     */
    public static function get_brand_colors() {
        return array(
            'primary' => '#0073aa',     // WordPress blue (matches your logo)
            'secondary' => '#666666',   // Gray from logo
            'accent' => '#005a87',      // Darker blue
            'light' => '#f0f6fc',       // Light blue
            'white' => '#ffffff',
            'text' => '#23282d'
        );
    }
    
    /**
     * Render branded header for admin pages
     */
    public static function render_admin_header($title = '', $subtitle = '') {
        $logo = self::render_logo('default', array('style' => 'height: 40px; width: auto;'));
        ?>
        <div class="wsa-admin-header">
            <div class="wsa-header-logo">
                <?php echo $logo; ?>
            </div>
            <?php if ($title): ?>
            <div class="wsa-header-content">
                <h1 class="wsa-header-title"><?php echo esc_html($title); ?></h1>
                <?php if ($subtitle): ?>
                    <p class="wsa-header-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add favicon to admin pages
     */
    public function add_admin_favicon() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'wp-site-advisory') !== false) {
            $favicon_url = $this->get_icon_url('small');
            echo '<link rel="icon" type="image/png" href="' . esc_url($favicon_url) . '" />';
        }
    }
    
    /**
     * Render Pro badge with branding
     */
    public static function render_pro_badge($text = 'PRO') {
        $colors = self::get_brand_colors();
        ?>
        <span class="wsa-pro-badge" style="background: linear-gradient(135deg, <?php echo $colors['primary']; ?>, <?php echo $colors['accent']; ?>);">
            <?php echo esc_html($text); ?>
        </span>
        <?php
    }
    
    /**
     * Get CSS for brand styling
     */
    public static function get_brand_css() {
        $colors = self::get_brand_colors();
        
        return "
        .wsa-admin-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #e1e1e1;
            margin-bottom: 20px;
        }
        
        .wsa-header-logo img {
            max-height: 40px;
            width: auto;
        }
        
        .wsa-header-title {
            margin: 0 0 5px 0;
            color: {$colors['text']};
            font-size: 24px;
            font-weight: 400;
        }
        
        .wsa-header-subtitle {
            margin: 0;
            color: {$colors['secondary']};
            font-size: 14px;
        }
        
        .wsa-icon,
        .wsa-logo {
            max-width: 100%;
            height: auto;
        }
        
        .wsa-pro-badge {
            display: inline-block;
            background: linear-gradient(135deg, {$colors['primary']}, {$colors['accent']});
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 115, 170, 0.3);
        }
        
        .wsa-branded-button {
            background: linear-gradient(135deg, {$colors['primary']}, {$colors['accent']});
            border-color: {$colors['primary']};
            color: white;
        }
        
        .wsa-branded-button:hover {
            background: linear-gradient(135deg, {$colors['accent']}, {$colors['primary']});
            border-color: {$colors['accent']};
            color: white;
        }
        ";
    }
}

/**
 * Global helper functions for branding
 */

/**
 * Get branding instance
 */
function wsa_branding() {
    return WP_Site_Advisory_Branding::get_instance();
}

/**
 * Render WP Site Advisor icon
 */
function wsa_icon($size = 'default', $attributes = array()) {
    return WP_Site_Advisory_Branding::render_icon($size, $attributes);
}

/**
 * Render WP Site Advisor logo  
 */
function wsa_logo($size = 'default', $attributes = array()) {
    return WP_Site_Advisory_Branding::render_logo($size, $attributes);
}

/**
 * Render branded admin header
 */
function wsa_admin_header($title = '', $subtitle = '') {
    WP_Site_Advisory_Branding::render_admin_header($title, $subtitle);
}

/**
 * Get brand colors array
 */
function wsa_brand_colors() {
    return WP_Site_Advisory_Branding::get_brand_colors();
}