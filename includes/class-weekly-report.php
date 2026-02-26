<?php
/**
 * WP Site Advisory Weekly Report Class
 * 
 * Handles weekly summary report generation and email delivery
 * 
 * @package WP_Site_Advisory
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Weekly_Report {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'schedule_weekly_reports'));
        add_action('wsa_send_weekly_report', array($this, 'send_weekly_report'));
        
        // Only register AJAX handlers if Pro plugin with white-label reports is not active
        if (!class_exists('\WSA_Pro\Features\White_Label_Reports')) {
            add_action('wp_ajax_wsa_send_test_report', array($this, 'send_test_report'));
        }
        add_action('wp_ajax_wsa_preview_weekly_report', array($this, 'preview_weekly_report'));
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
     * Schedule weekly reports if enabled
     */
    public function schedule_weekly_reports() {
        $settings = get_option('wsa_settings', array());
        $reports_enabled = isset($settings['weekly_reports']) && $settings['weekly_reports'] === '1';
        
        // Clear existing schedule
        if (wp_next_scheduled('wsa_send_weekly_report')) {
            wp_clear_scheduled_hook('wsa_send_weekly_report');
        }
        
        // Schedule new weekly report if enabled
        if ($reports_enabled) {
            $send_day = isset($settings['report_day']) ? $settings['report_day'] : 'monday';
            $send_time = isset($settings['report_time']) ? $settings['report_time'] : '09:00';
            
            $next_send = $this->calculate_next_send_time($send_day, $send_time);
            wp_schedule_event($next_send, 'weekly', 'wsa_send_weekly_report');
        }
    }
    
    /**
     * Calculate next send time based on day and time preferences
     */
    private function calculate_next_send_time($day, $time) {
        $days_map = array(
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        );
        
        $target_day = isset($days_map[$day]) ? $days_map[$day] : 1; // Default to Monday
        $time_parts = explode(':', $time);
        $hour = intval($time_parts[0]);
        $minute = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
        
        // Get current time
        $now = current_time('timestamp');
        $current_day = intval(date('w', $now)); // 0 = Sunday, 1 = Monday, etc.
        
        // Calculate days until target day
        $days_until = ($target_day - $current_day + 7) % 7;
        if ($days_until === 0) {
            // If it's the same day, check if time has passed
            $current_hour = intval(date('H', $now));
            $current_minute = intval(date('i', $now));
            
            if ($hour < $current_hour || ($hour === $current_hour && $minute <= $current_minute)) {
                // Time has passed, schedule for next week
                $days_until = 7;
            }
        }
        
        return mktime($hour, $minute, 0, date('n', $now), date('j', $now) + $days_until, date('Y', $now));
    }
    
    /**
     * Send weekly report via email
     */
    public function send_weekly_report() {
        // Check if reports are still enabled
        $settings = get_option('wsa_settings', array());
        if (!isset($settings['weekly_reports']) || $settings['weekly_reports'] !== '1') {
            return;
        }
        
        $recipients = $this->get_report_recipients();
        if (empty($recipients)) {
            return;
        }
        
        // Generate report data
        $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
        $weekly_summary = $system_scanner->generate_weekly_summary();
        
        if (isset($weekly_summary['error'])) {
            return;
        }
        
        // Generate email content
        $email_subject = $this->generate_email_subject($weekly_summary);
        $email_content = $this->generate_email_content($weekly_summary);
        
        // Send emails
        foreach ($recipients as $recipient) {
            if (is_email($recipient)) {
                wp_mail(
                    $recipient,
                    $email_subject,
                    $email_content,
                    array('Content-Type: text/html; charset=UTF-8')
                );
            }
        }
        
        // Log the report sending
        $this->log_report_sent($weekly_summary);
    }
    
    /**
     * Get report recipients
     */
    private function get_report_recipients() {
        $settings = get_option('wsa_settings', array());
        $recipients = array();
        
        // Default to admin email
        if (empty($settings['report_recipients'])) {
            $recipients[] = get_option('admin_email');
        } else {
            $recipient_emails = array_map('trim', explode(',', $settings['report_recipients']));
            foreach ($recipient_emails as $email) {
                if (is_email($email)) {
                    $recipients[] = $email;
                }
            }
        }
        
        return $recipients;
    }
    
    /**
     * Generate email subject
     */
    private function generate_email_subject($summary) {
        $site_name = $summary['site_info']['site_name'];
        $security_score = $summary['summary_stats']['security_score'];
        $total_issues = $summary['summary_stats']['security_issues'] + $summary['summary_stats']['performance_issues'];
        
        if ($total_issues > 0) {
            return sprintf(__('[%s] Weekly Site Report - %d Issues Found (Security Score: %d%%)', 'wp-site-advisory'), 
                $site_name, $total_issues, $security_score);
        } else {
            return sprintf(__('[%s] Weekly Site Report - All Good! (Security Score: %d%%)', 'wp-site-advisory'), 
                $site_name, $security_score);
        }
    }
    
    /**
     * Generate HTML email content
     */
    private function generate_email_content($summary) {
        $template_path = WP_SITE_ADVISORY_PATH . 'templates/email-weekly-report.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback to inline HTML if template doesn't exist
        return $this->generate_inline_email_content($summary);
    }
    
    /**
     * Generate inline email content as fallback
     */
    private function generate_inline_email_content($summary) {
        $site_name = esc_html($summary['site_info']['site_name']);
        $site_url = esc_url($summary['site_info']['site_url']);
        $security_score = $summary['summary_stats']['security_score'];
        $total_issues = $summary['summary_stats']['security_issues'] + $summary['summary_stats']['performance_issues'];
        
        $html = '<!DOCTYPE html>';
        $html .= '<html><head><meta charset="UTF-8"><title>Weekly Site Report</title></head>';
        $html .= '<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        // Header
        $html .= '<div style="background-color: #0073aa; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $html .= '<h1 style="margin: 0; font-size: 24px;">📊 Weekly Site Report</h1>';
        $html .= '<p style="margin: 10px 0 0 0; opacity: 0.9;">For ' . $site_name . '</p>';
        $html .= '</div>';
        
        // Summary Stats
        $html .= '<div style="background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $html .= '<h2 style="margin-top: 0; color: #0073aa;">📈 Summary Statistics</h2>';
        
        $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">';
        $html .= '<div style="text-align: center; padding: 15px; background: white; border-radius: 3px;">';
        $html .= '<div style="font-size: 24px; font-weight: bold; color: ' . ($security_score >= 80 ? '#28a745' : ($security_score >= 60 ? '#ffc107' : '#dc3545')) . ';">' . $security_score . '%</div>';
        $html .= '<div style="font-size: 14px; color: #666;">Security Score</div>';
        $html .= '</div>';
        $html .= '<div style="text-align: center; padding: 15px; background: white; border-radius: 3px;">';
        $html .= '<div style="font-size: 24px; font-weight: bold; color: ' . ($total_issues === 0 ? '#28a745' : '#dc3545') . ';">' . $total_issues . '</div>';
        $html .= '<div style="font-size: 14px; color: #666;">Total Issues</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $stats = $summary['summary_stats'];
        $html .= '<ul style="list-style: none; padding: 0;">';
        $html .= '<li style="padding: 5px 0; border-bottom: 1px solid #eee;"><strong>Active Plugins:</strong> ' . $stats['total_plugins'] . '</li>';
        $html .= '<li style="padding: 5px 0; border-bottom: 1px solid #eee;"><strong>Plugins Needing Updates:</strong> ' . $stats['plugins_needing_updates'] . '</li>';
        $html .= '<li style="padding: 5px 0; border-bottom: 1px solid #eee;"><strong>Inactive Plugins:</strong> ' . $stats['inactive_plugins'] . '</li>';
        $html .= '<li style="padding: 5px 0; border-bottom: 1px solid #eee;"><strong>Inactive Themes:</strong> ' . $stats['inactive_themes'] . '</li>';
        $html .= '<li style="padding: 5px 0;"><strong>WordPress Version:</strong> ' . esc_html($summary['site_info']['wordpress_version']) . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Key Recommendations
        if (!empty($summary['key_recommendations'])) {
            $html .= '<div style="background-color: #fff3cd; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">';
            $html .= '<h2 style="margin-top: 0; color: #856404;">⚠️ Key Recommendations</h2>';
            $html .= '<ul>';
            
            foreach (array_slice($summary['key_recommendations'], 0, 5) as $rec) {
                $priority_color = $rec['priority'] === 'high' ? '#dc3545' : ($rec['priority'] === 'medium' ? '#ffc107' : '#17a2b8');
                $html .= '<li style="margin-bottom: 10px;">';
                $html .= '<span style="background-color: ' . $priority_color . '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px; text-transform: uppercase;">' . esc_html($rec['priority']) . '</span> ';
                $html .= '<strong>' . esc_html($rec['title']) . '</strong><br>';
                $html .= '<span style="color: #666;">' . esc_html($rec['description']) . '</span>';
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Next Actions
        if (!empty($summary['next_actions'])) {
            $html .= '<div style="background-color: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745;">';
            $html .= '<h2 style="margin-top: 0; color: #155724;">✅ Next Actions</h2>';
            $html .= '<ul>';
            
            foreach ($summary['next_actions'] as $action) {
                $html .= '<li style="margin-bottom: 10px;">';
                $html .= '<strong>' . esc_html($action['action']) . '</strong>';
                if (!empty($action['url'])) {
                    $html .= ' <a href="' . esc_url($action['url']) . '" style="color: #0073aa; text-decoration: none;">→ Take Action</a>';
                }
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Footer
        $html .= '<div style="text-align: center; padding: 20px; border-top: 1px solid #eee; margin-top: 20px; color: #666; font-size: 14px;">';
        $html .= '<p>This report was generated by <strong>WP Site Advisory</strong> on ' . date('F j, Y \a\t g:i A', current_time('timestamp')) . '</p>';
        $html .= '<p><a href="' . admin_url('admin.php?page=wp-site-advisory') . '" style="color: #0073aa;">View Full Dashboard</a> | ';
        $html .= '<a href="' . admin_url('admin.php?page=wp-site-advisory-settings') . '" style="color: #0073aa;">Manage Report Settings</a></p>';
        $html .= '<p style="font-size: 12px;">Site: <a href="' . $site_url . '" style="color: #0073aa;">' . $site_name . '</a></p>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Log report sending
     */
    private function log_report_sent($summary) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wsa_report_history';
        
        // Create table if it doesn't exist
        $this->create_report_history_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'report_type' => 'weekly',
                'recipients_count' => count($this->get_report_recipients()),
                'security_score' => $summary['summary_stats']['security_score'],
                'total_issues' => $summary['summary_stats']['security_issues'] + $summary['summary_stats']['performance_issues'],
                'report_data' => wp_json_encode($summary),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Create report history table
     */
    private function create_report_history_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wsa_report_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            report_type varchar(20) NOT NULL DEFAULT 'weekly',
            recipients_count int(11) NOT NULL DEFAULT 0,
            security_score int(3) NOT NULL DEFAULT 0,
            total_issues int(11) NOT NULL DEFAULT 0,
            report_data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_report_type (report_type),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Send test report via AJAX
     */
    public function send_test_report() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-site-advisory'));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory'));
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        
        if (!is_email($test_email)) {
            wp_send_json_error(__('Invalid email address', 'wp-site-advisory'));
        }
        
        // Generate test report
        $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
        $weekly_summary = $system_scanner->generate_weekly_summary();
        
        if (isset($weekly_summary['error'])) {
            wp_send_json_error($weekly_summary['error']);
        }
        
        // Generate email content
        $email_subject = '[TEST] ' . $this->generate_email_subject($weekly_summary);
        $email_content = $this->generate_email_content($weekly_summary);
        
        // Send test email
        $sent = wp_mail(
            $test_email,
            $email_subject,
            $email_content,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        if ($sent) {
            wp_send_json_success(__('Test report sent successfully!', 'wp-site-advisory'));
        } else {
            wp_send_json_error(__('Failed to send test report. Please check your email settings.', 'wp-site-advisory'));
        }
    }
    
    /**
     * Preview weekly report via AJAX
     */
    public function preview_weekly_report() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'wsa_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-site-advisory'));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory'));
        }
        
        // Generate report
        $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
        $weekly_summary = $system_scanner->generate_weekly_summary();
        
        if (isset($weekly_summary['error'])) {
            echo '<p style="color: red;">Error: ' . esc_html($weekly_summary['error']) . '</p>';
            exit;
        }
        
        // Output HTML content
        echo $this->generate_email_content($weekly_summary);
        exit;
    }
    
    /**
     * Get report history
     */
    public function get_report_history($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wsa_report_history';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
        
        return $results ? $results : array();
    }
    
    /**
     * Get next scheduled report time
     */
    public function get_next_report_time() {
        $next_scheduled = wp_next_scheduled('wsa_send_weekly_report');
        return $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : false;
    }
    
    /**
     * Manual trigger for weekly report (for testing)
     */
    public function trigger_weekly_report() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $this->send_weekly_report();
        return true;
    }
}