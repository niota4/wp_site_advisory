<?php
/**
 * WP Site Advisory Settings Class
 *
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Settings {

    /**
     * Settings page slug
     */
    private $page_slug = 'wp-site-advisory-settings';

    /**
     * Settings group
     */
    private $settings_group = 'wsa_settings';

    /**
     * Register settings
     */
    public function register() {
        // Register setting sections
        add_settings_section(
            'wsa_openai_section',
            __('OpenAI API Configuration', 'wp-site-advisory'),
            array($this, 'openai_section_callback'),
            $this->page_slug
        );

        add_settings_section(
            'wsa_openai_usage_section',
            __('OpenAI Usage & Billing', 'wp-site-advisory'),
            array($this, 'openai_usage_section_callback'),
            $this->page_slug
        );

        add_settings_section(
            'wsa_scan_section',
            __('Scan Settings', 'wp-site-advisory'),
            array($this, 'scan_section_callback'),
            $this->page_slug
        );

        add_settings_section(
            'wsa_notifications_section',
            __('Notification Settings', 'wp-site-advisory'),
            array($this, 'notifications_section_callback'),
            $this->page_slug
        );

        add_settings_section(
            'wsa_reports_section',
            __('Weekly Reports', 'wp-site-advisory'),
            array($this, 'reports_section_callback'),
            $this->page_slug
        );

        // Register individual settings
        $this->register_openai_settings();
        $this->register_scan_settings();
        $this->register_notification_settings();
        $this->register_reports_settings();
    }

    /**
     * Register OpenAI settings
     */
    private function register_openai_settings() {
        register_setting(
            $this->settings_group,
            'wsa_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key'),
                'default' => ''
            )
        );

        add_settings_field(
            'wsa_openai_api_key',
            __('OpenAI API Key', 'wp-site-advisory'),
            array($this, 'api_key_field_callback'),
            $this->page_slug,
            'wsa_openai_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-3.5-turbo'
            )
        );

        add_settings_field(
            'wsa_openai_model',
            __('OpenAI Model', 'wp-site-advisory'),
            array($this, 'openai_model_field_callback'),
            $this->page_slug,
            'wsa_openai_section'
        );
    }

    /**
     * Register scan settings
     */
    private function register_scan_settings() {
        register_setting(
            $this->settings_group,
            'wsa_scan_frequency',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_scan_frequency'),
                'default' => 'daily'
            )
        );

        add_settings_field(
            'wsa_scan_frequency',
            __('Automatic Scan Frequency', 'wp-site-advisory'),
            array($this, 'scan_frequency_field_callback'),
            $this->page_slug,
            'wsa_scan_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_enable_auto_scan',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            )
        );

        add_settings_field(
            'wsa_enable_auto_scan',
            __('Enable Automatic Scanning', 'wp-site-advisory'),
            array($this, 'enable_auto_scan_field_callback'),
            $this->page_slug,
            'wsa_scan_section'
        );
    }

    /**
     * Register notification settings
     */
    private function register_notification_settings() {
        register_setting(
            $this->settings_group,
            'wsa_enable_notifications',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            )
        );

        add_settings_field(
            'wsa_enable_notifications',
            __('Enable Email Notifications', 'wp-site-advisory'),
            array($this, 'enable_notifications_field_callback'),
            $this->page_slug,
            'wsa_notifications_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_notification_email',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => get_option('admin_email')
            )
        );

        add_settings_field(
            'wsa_notification_email',
            __('Notification Email', 'wp-site-advisory'),
            array($this, 'notification_email_field_callback'),
            $this->page_slug,
            'wsa_notifications_section'
        );
    }

    /**
     * Register weekly reports settings
     */
    private function register_reports_settings() {
        register_setting(
            $this->settings_group,
            'wsa_weekly_reports',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        add_settings_field(
            'wsa_weekly_reports',
            __('Enable Weekly Reports', 'wp-site-advisory'),
            array($this, 'weekly_reports_field_callback'),
            $this->page_slug,
            'wsa_reports_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_report_day',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_report_day'),
                'default' => 'monday'
            )
        );

        add_settings_field(
            'wsa_report_day',
            __('Report Day', 'wp-site-advisory'),
            array($this, 'report_day_field_callback'),
            $this->page_slug,
            'wsa_reports_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_report_time',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_report_time'),
                'default' => '09:00'
            )
        );

        add_settings_field(
            'wsa_report_time',
            __('Report Time', 'wp-site-advisory'),
            array($this, 'report_time_field_callback'),
            $this->page_slug,
            'wsa_reports_section'
        );

        register_setting(
            $this->settings_group,
            'wsa_report_recipients',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_report_recipients'),
                'default' => get_option('admin_email')
            )
        );

        add_settings_field(
            'wsa_report_recipients',
            __('Report Recipients', 'wp-site-advisory'),
            array($this, 'report_recipients_field_callback'),
            $this->page_slug,
            'wsa_reports_section'
        );
    }

    /**
     * Render settings page
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>

            <!-- API Key Test Section -->
            <div class="wsa-test-api">
                <h2><?php _e('Test API Connection', 'wp-site-advisory'); ?></h2>
                <button id="wsa-test-api" class="button button-secondary">
                    <?php _e('Test OpenAI Connection', 'wp-site-advisory'); ?>
                </button>
                <div id="wsa-api-test-result"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Section callbacks
     */
    public function openai_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings to enable AI-powered recommendations.', 'wp-site-advisory') . '</p>';
    }

    public function scan_section_callback() {
        echo '<p>' . __('Configure how often WP SiteAdvisor should scan your site automatically.', 'wp-site-advisory') . '</p>';
    }

    public function notifications_section_callback() {
        echo '<p>' . __('Configure email notifications for scan results and recommendations.', 'wp-site-advisory') . '</p>';
    }

    public function reports_section_callback() {
        echo '<p>' . __('Configure automated weekly summary reports to stay informed about your site\'s health.', 'wp-site-advisory') . '</p>';
        
        // Show next scheduled report if enabled
        $reports_enabled = get_option('wsa_weekly_reports', false);
        if ($reports_enabled) {
            $weekly_report = WP_Site_Advisory_Weekly_Report::get_instance();
            $next_report = $weekly_report->get_next_report_time();
            if ($next_report) {
                echo '<p class="description" style="color: #00a32a;"><strong>' . 
                     sprintf(__('Next report scheduled: %s', 'wp-site-advisory'), date('F j, Y \a\t g:i A', strtotime($next_report))) . 
                     '</strong></p>';
            }
        }
    }

    public function openai_usage_section_callback() {
        echo '<p>' . __('Monitor your OpenAI API usage and token consumption for AI-powered features.', 'wp-site-advisory') . '</p>';
        
        // Display the usage dashboard
        $this->display_openai_usage_dashboard();
    }

    /**
     * Field callbacks
     */
    public function api_key_field_callback() {
        $value = get_option('wsa_openai_api_key', '');
        $has_key = !empty($value);
        $display_value = $has_key ? '' : ''; // Always empty for security
        $masked_display = $has_key ? 'sk-proj-...' . substr($value, -4) : '';
        
        ?>
        <div class="wsa-api-key-field">
            <?php if ($has_key): ?>
                <div class="wsa-api-key-status">
                    <span class="wsa-key-indicator">
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('API Key configured:', 'wp-site-advisory'); ?>
                        <code><?php echo esc_html($masked_display); ?></code>
                    </span>
                    <button type="button" id="wsa-change-api-key" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Change Key', 'wp-site-advisory'); ?>
                    </button>
                    <button type="button" id="wsa-remove-api-key" class="button button-secondary" style="margin-left: 5px; color: #d63638;">
                        <?php _e('Remove Key', 'wp-site-advisory'); ?>
                    </button>
                </div>
                <div class="wsa-api-key-input" style="display: none; margin-top: 10px;">
                    <input type="password" 
                           id="wsa_openai_api_key" 
                           name="wsa_openai_api_key" 
                           value="<?php echo esc_attr($display_value); ?>" 
                           placeholder="<?php _e('Enter new API key...', 'wp-site-advisory'); ?>"
                           class="regular-text" />
                    <button type="button" id="wsa-cancel-change" class="button button-secondary" style="margin-left: 5px;">
                        <?php _e('Cancel', 'wp-site-advisory'); ?>
                    </button>
                </div>
            <?php else: ?>
                <input type="password" 
                       id="wsa_openai_api_key" 
                       name="wsa_openai_api_key" 
                       value="<?php echo esc_attr($display_value); ?>" 
                       placeholder="<?php _e('Enter your OpenAI API key...', 'wp-site-advisory'); ?>"
                       class="regular-text" />
            <?php endif; ?>
        </div>
        
        <p class="description">
            <?php _e('Enter your OpenAI API key. Get one from', 'wp-site-advisory'); ?>
            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.
        </p>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Show input field when changing key
            $('#wsa-change-api-key').click(function() {
                $('.wsa-api-key-status').hide();
                $('.wsa-api-key-input').show();
                $('#wsa_openai_api_key').focus();
            });
            
            // Cancel changing key
            $('#wsa-cancel-change').click(function() {
                $('.wsa-api-key-input').hide();
                $('.wsa-api-key-status').show();
                $('#wsa_openai_api_key').val('');
            });
            
            // Remove API key
            $('#wsa-remove-api-key').click(function() {
                if (confirm('<?php _e('Are you sure you want to remove the API key? AI recommendations will be disabled.', 'wp-site-advisory'); ?>')) {
                    
                    // Use AJAX to remove the API key
                    $.post(wsa_ajax.ajax_url, {
                        action: 'wsa_remove_api_key',
                        nonce: '<?php echo wp_create_nonce('wsa_remove_api_key'); ?>'
                    }, function(response) {
                        if (response.success) {
                            // Reload the page to show the updated state
                            window.location.reload();
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error occurred'));
                        }
                    }).fail(function(xhr, status, error) {
                        alert('<?php _e('Network error. Please try again.', 'wp-site-advisory'); ?>');
                    });
                }
            });

            // Handle OpenAI usage refresh
            $(document).on('click', '#wsa-refresh-usage', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $dashboard = $('.wsa-usage-dashboard');
                var originalText = $button.text();
                var nonce = $button.data('nonce');
                
                // Show loading state
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt spinning"></span> <?php _e("Refreshing...", "wp-site-advisory"); ?>');
                $dashboard.addClass('wsa-loading');
                
                // Make AJAX request
                $.post(wsa_ajax.ajax_url, {
                    action: 'wsa_refresh_usage',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        // Show success message
                        var $notice = $('<div class="notice notice-success is-dismissible inline"><p><?php _e("Usage data refreshed successfully!", "wp-site-advisory"); ?></p></div>');
                        $dashboard.prepend($notice);
                        
                        // Reload the page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        var errorMessage = response.data.message || '<?php _e("Failed to refresh usage data", "wp-site-advisory"); ?>';
                        var $notice = $('<div class="notice notice-error is-dismissible inline"><p>' + errorMessage + '</p></div>');
                        $dashboard.prepend($notice);
                    }
                }).fail(function(xhr, status, error) {
                    // Show network error
                    var $notice = $('<div class="notice notice-error is-dismissible inline"><p><?php _e("Network error. Please check your connection and try again.", "wp-site-advisory"); ?></p></div>');
                    $dashboard.prepend($notice);
                }).always(function() {
                    // Reset button state
                    $button.prop('disabled', false).html(originalText);
                    $dashboard.removeClass('wsa-loading');
                    
                    // Auto-dismiss notices after 5 seconds
                    setTimeout(function() {
                        $('.notice.is-dismissible').fadeOut();
                    }, 5000);
                });
            });
        });
        </script>
        
        <style>
        .wsa-usage-dashboard {
            margin: 20px 0;
        }
        
        .wsa-usage-dashboard.wsa-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .spinning {
            animation: rotation 1s infinite linear;
        }
        
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(359deg); }
        }
        </style>
        <?php
    }

    public function openai_model_field_callback() {
        $value = get_option('wsa_openai_model', 'gpt-3.5-turbo');
        $models = array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Recommended)',
            'gpt-4' => 'GPT-4 (Higher cost, better quality)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
        );
        
        ?>
        <select id="wsa_openai_model" name="wsa_openai_model">
            <?php foreach ($models as $model_id => $model_name) : ?>
                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($value, $model_id); ?>>
                    <?php echo esc_html($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Choose the OpenAI model to use for generating recommendations.', 'wp-site-advisory'); ?>
        </p>
        <?php
    }

    public function scan_frequency_field_callback() {
        $value = get_option('wsa_scan_frequency', 'daily');
        $frequencies = array(
            'hourly' => __('Every Hour', 'wp-site-advisory'),
            'twicedaily' => __('Twice Daily', 'wp-site-advisory'),
            'daily' => __('Daily', 'wp-site-advisory'),
            'weekly' => __('Weekly', 'wp-site-advisory'),
        );
        
        ?>
        <select id="wsa_scan_frequency" name="wsa_scan_frequency">
            <?php foreach ($frequencies as $freq_id => $freq_name) : ?>
                <option value="<?php echo esc_attr($freq_id); ?>" <?php selected($value, $freq_id); ?>>
                    <?php echo esc_html($freq_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('How often should WP SiteAdvisor automatically scan your site?', 'wp-site-advisory'); ?>
        </p>
        <?php
    }

    public function enable_auto_scan_field_callback() {
        $value = get_option('wsa_enable_auto_scan', true);
        
        ?>
        <input type="checkbox" 
               id="wsa_enable_auto_scan" 
               name="wsa_enable_auto_scan" 
               value="1" 
               <?php checked($value, true); ?> />
        <label for="wsa_enable_auto_scan">
            <?php _e('Enable automatic scanning based on the frequency above', 'wp-site-advisory'); ?>
        </label>
        <?php
    }

    public function enable_notifications_field_callback() {
        $value = get_option('wsa_enable_notifications', true);
        
        ?>
        <input type="checkbox" 
               id="wsa_enable_notifications" 
               name="wsa_enable_notifications" 
               value="1" 
               <?php checked($value, true); ?> />
        <label for="wsa_enable_notifications">
            <?php _e('Send email notifications when issues are detected', 'wp-site-advisory'); ?>
        </label>
        <?php
    }

    public function notification_email_field_callback() {
        $value = get_option('wsa_notification_email', get_option('admin_email'));
        
        ?>
        <input type="email" 
               id="wsa_notification_email" 
               name="wsa_notification_email" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Email address to receive notifications about scan results and recommendations.', 'wp-site-advisory'); ?>
        </p>
        <?php
    }

    public function weekly_reports_field_callback() {
        $value = get_option('wsa_weekly_reports', false);
        
        ?>
        <input type="checkbox" 
               id="wsa_weekly_reports" 
               name="wsa_weekly_reports" 
               value="1" 
               <?php checked(1, $value); ?> />
        <label for="wsa_weekly_reports">
            <?php _e('Send weekly summary reports via email', 'wp-site-advisory'); ?>
        </label>
        <p class="description">
            <?php _e('Automatically email a comprehensive weekly report with security score, issues found, and recommendations.', 'wp-site-advisory'); ?>
        </p>
        
        <div id="wsa-test-report" style="margin-top: 15px;">
            <button type="button" id="wsa-send-test-report" class="button button-secondary">
                <?php _e('Send Test Report', 'wp-site-advisory'); ?>
            </button>
            <input type="email" id="wsa-test-email" placeholder="<?php esc_attr_e('Test email address', 'wp-site-advisory'); ?>" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="margin-left: 10px;" />
            <span id="wsa-test-report-result" style="margin-left: 10px;"></span>
        </div>
        <?php
    }

    public function report_day_field_callback() {
        $value = get_option('wsa_report_day', 'monday');
        
        $days = array(
            'monday' => __('Monday', 'wp-site-advisory'),
            'tuesday' => __('Tuesday', 'wp-site-advisory'),
            'wednesday' => __('Wednesday', 'wp-site-advisory'),
            'thursday' => __('Thursday', 'wp-site-advisory'),
            'friday' => __('Friday', 'wp-site-advisory'),
            'saturday' => __('Saturday', 'wp-site-advisory'),
            'sunday' => __('Sunday', 'wp-site-advisory')
        );
        
        ?>
        <select id="wsa_report_day" name="wsa_report_day">
            <?php foreach ($days as $day => $label) : ?>
                <option value="<?php echo esc_attr($day); ?>" <?php selected($value, $day); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Day of the week to send the weekly report.', 'wp-site-advisory'); ?>
        </p>
        <?php
    }

    public function report_time_field_callback() {
        $value = get_option('wsa_report_time', '09:00');
        
        ?>
        <input type="time" 
               id="wsa_report_time" 
               name="wsa_report_time" 
               value="<?php echo esc_attr($value); ?>" />
        <p class="description">
            <?php _e('Time to send the weekly report (server time).', 'wp-site-advisory'); ?>
        </p>
        <?php
    }

    public function report_recipients_field_callback() {
        $value = get_option('wsa_report_recipients', get_option('admin_email'));
        
        ?>
        <textarea id="wsa_report_recipients" 
                  name="wsa_report_recipients" 
                  rows="3" 
                  cols="50" 
                  class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Email addresses to receive weekly reports (comma-separated for multiple recipients).', 'wp-site-advisory'); ?><br>
            <?php _e('Example: admin@example.com, manager@example.com', 'wp-site-advisory'); ?>
        </p>
        
        <div style="margin-top: 10px;">
            <a href="#" id="wsa-preview-report" class="button button-secondary">
                <?php _e('Preview Report', 'wp-site-advisory'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Sanitization callbacks
     */
    public function sanitize_api_key($value) {
        $value = sanitize_text_field($value);
        
        // If the field is empty, keep the existing key 
        if (empty($value)) {
            return get_option('wsa_openai_api_key', '');
        }
        
        // Don't save if it's the masked value
        if (strpos($value, '*') !== false || strpos($value, 'sk-proj-...') !== false) {
            return get_option('wsa_openai_api_key', '');
        }
        
        // Basic validation for OpenAI API key format (updated pattern for newer keys)
        if (!empty($value) && !preg_match('/^sk-[a-zA-Z0-9\-_]{20,}$/', $value)) {
            add_settings_error(
                'wsa_openai_api_key',
                'invalid-api-key',
                __('Invalid OpenAI API key format. Please check your key.', 'wp-site-advisory')
            );
            return get_option('wsa_openai_api_key', '');
        }
        
        // Add success message when API key is saved (only once per request)
        static $success_message_shown = false;
        if (!$success_message_shown) {
            add_settings_error(
                'wsa_openai_api_key',
                'api-key-saved',
                __('API key saved successfully!', 'wp-site-advisory'),
                'updated'
            );
            $success_message_shown = true;
        }
        
        return $value;
    }

    public function sanitize_scan_frequency($value) {
        $allowed_frequencies = array('hourly', 'twicedaily', 'daily', 'weekly');
        
        if (!in_array($value, $allowed_frequencies)) {
            $value = 'daily';
        }
        
        // Handle cron rescheduling
        $old_frequency = get_option('wsa_scan_frequency', 'daily');
        if ($old_frequency !== $value) {
            // Clear existing scheduled event
            wp_clear_scheduled_hook('wsa_scheduled_scan');
            
            // Schedule new event if auto scan is enabled
            if (get_option('wsa_enable_auto_scan', true)) {
                wp_schedule_event(time(), $value, 'wsa_scheduled_scan');
            }
        }
        
        return $value;
    }

    public function sanitize_report_day($value) {
        $allowed_days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        
        if (!in_array($value, $allowed_days)) {
            $value = 'monday';
        }
        
        // Reschedule weekly reports if day changed
        $old_day = get_option('wsa_report_day', 'monday');
        if ($old_day !== $value && get_option('wsa_weekly_reports', false)) {
            $weekly_report = WP_Site_Advisory_Weekly_Report::get_instance();
            $weekly_report->schedule_weekly_reports();
        }
        
        return $value;
    }

    public function sanitize_report_time($value) {
        // Validate time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            $value = '09:00';
        }
        
        // Reschedule weekly reports if time changed
        $old_time = get_option('wsa_report_time', '09:00');
        if ($old_time !== $value && get_option('wsa_weekly_reports', false)) {
            $weekly_report = WP_Site_Advisory_Weekly_Report::get_instance();
            $weekly_report->schedule_weekly_reports();
        }
        
        return $value;
    }

    public function sanitize_report_recipients($value) {
        $recipients = array_map('trim', explode(',', $value));
        $valid_recipients = array();
        
        foreach ($recipients as $email) {
            if (is_email($email)) {
                $valid_recipients[] = sanitize_email($email);
            }
        }
        
        // If no valid emails, use admin email
        if (empty($valid_recipients)) {
            $valid_recipients[] = get_option('admin_email');
        }
        
        return implode(', ', $valid_recipients);
    }

    /**
     * Display OpenAI Usage Dashboard
     */
    private function display_openai_usage_dashboard() {
        // Check if API key is configured
        $api_key = get_option('wsa_openai_api_key', '');
        
        if (empty($api_key)) {
            $this->display_no_api_key_message();
            return;
        }
        
        // Get usage handler instance
        $usage_handler = new WP_Site_Advisory_OpenAI_Usage();
        $usage_data = $usage_handler->get_usage_data();
        $display_data = $usage_handler->format_for_display($usage_data);
        
        if ($display_data['error']) {
            $this->display_usage_error($display_data);
            return;
        }
        
        // Display usage dashboard
        $this->render_usage_dashboard($display_data);
    }
    
    /**
     * Display message when no API key is configured
     */
    private function display_no_api_key_message() {
        ?>
        <div class="wsa-usage-dashboard wsa-no-api-key">
            <div class="notice notice-info inline">
                <p>
                    <strong><?php _e('OpenAI API Key Required', 'wp-site-advisory'); ?></strong><br>
                    <?php _e('Please configure your OpenAI API key in the configuration section above to view usage statistics.', 'wp-site-advisory'); ?>
                </p>
                <p>
                    <a href="#wsa_openai_section" class="button button-secondary">
                        <?php _e('Configure API Key', 'wp-site-advisory'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display usage error message
     */
    private function display_usage_error($display_data) {
        $error_code = $display_data['code'];
        $error_message = $display_data['message'];
        
        // Determine error type and appropriate action
        $is_api_key_error = in_array($error_code, array('invalid_api_key', 'no_api_key'));
        $notice_class = $is_api_key_error ? 'notice-error' : 'notice-warning';
        ?>
        <div class="wsa-usage-dashboard wsa-usage-error">
            <div class="notice <?php echo esc_attr($notice_class); ?> inline">
                <p>
                    <strong><?php _e('Usage Data Unavailable', 'wp-site-advisory'); ?></strong><br>
                    <?php echo esc_html($error_message); ?>
                </p>
                <p class="wsa-usage-actions">
                    <?php if ($is_api_key_error): ?>
                        <a href="#wsa_openai_section" class="button button-primary">
                            <?php _e('Update API Key', 'wp-site-advisory'); ?>
                        </a>
                    <?php else: ?>
                        <button type="button" class="button button-secondary" id="wsa-refresh-usage" data-nonce="<?php echo wp_create_nonce('wsa_refresh_usage'); ?>">
                            <?php _e('Try Again', 'wp-site-advisory'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <a href="https://platform.openai.com/account/billing" target="_blank" class="button button-secondary">
                        <?php _e('Visit OpenAI Billing', 'wp-site-advisory'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the full usage dashboard
     */
    private function render_usage_dashboard($display_data) {
        $raw_data = $display_data['raw_data'];
        ?>
        <div class="wsa-usage-dashboard">
            <!-- Usage Summary Cards -->
            <div class="wsa-usage-summary">
                <div class="wsa-usage-cards">
                    <div class="wsa-usage-card wsa-usage-tokens">
                        <div class="wsa-card-header">
                            <h4><?php _e('Total Tokens Used', 'wp-site-advisory'); ?></h4>
                            <span class="wsa-card-period"><?php echo esc_html($display_data['period']); ?></span>
                        </div>
                        <div class="wsa-card-value">
                            <span class="wsa-big-number"><?php echo esc_html($display_data['total_tokens']); ?></span>
                            <span class="wsa-trend wsa-trend-<?php echo esc_attr($display_data['trend']); ?>">
                                <?php 
                                switch ($display_data['trend']) {
                                    case 'up': echo '↗'; break;
                                    case 'down': echo '↘'; break;
                                    default: echo '→';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="wsa-card-subtitle">
                            <?php printf(__('Daily average: %s tokens', 'wp-site-advisory'), esc_html($display_data['daily_average'])); ?>
                        </div>
                    </div>
                    
                    <div class="wsa-usage-card wsa-usage-cost">
                        <div class="wsa-card-header">
                            <h4><?php _e('Estimated Cost', 'wp-site-advisory'); ?></h4>
                            <span class="wsa-card-period"><?php echo esc_html($display_data['period']); ?></span>
                        </div>
                        <div class="wsa-card-value">
                            <span class="wsa-big-number"><?php echo esc_html($display_data['total_cost']); ?></span>
                        </div>
                        <div class="wsa-card-subtitle">
                            <?php printf(__('Primary model: %s', 'wp-site-advisory'), esc_html($display_data['top_model'])); ?>
                        </div>
                    </div>
                    
                    <div class="wsa-usage-card wsa-usage-status">
                        <div class="wsa-card-header">
                            <h4><?php _e('API Status', 'wp-site-advisory'); ?></h4>
                            <span class="wsa-status-indicator wsa-status-active"></span>
                        </div>
                        <div class="wsa-card-value">
                            <span class="wsa-status-text"><?php _e('Active', 'wp-site-advisory'); ?></span>
                        </div>
                        <div class="wsa-card-subtitle">
                            <?php printf(__('Last updated: %s', 'wp-site-advisory'), esc_html(date('M j, g:i A', strtotime($display_data['last_updated'])))); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Progress Bar (if we have a plan limit) -->
            <?php $this->render_usage_progress($raw_data); ?>
            
            <!-- Model Breakdown -->
            <?php if (!empty($raw_data['models'])): ?>
            <div class="wsa-usage-breakdown">
                <h4><?php _e('Usage by Model', 'wp-site-advisory'); ?></h4>
                <div class="wsa-model-list">
                    <?php foreach ($raw_data['models'] as $model => $model_data): ?>
                        <div class="wsa-model-item">
                            <div class="wsa-model-info">
                                <span class="wsa-model-name"><?php echo esc_html($model); ?></span>
                                <span class="wsa-model-tokens"><?php echo number_format($model_data['tokens']); ?> tokens</span>
                            </div>
                            <div class="wsa-model-cost">
                                <?php echo '$' . number_format($model_data['cost'], 3); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="wsa-usage-actions">
                <button type="button" class="button button-secondary" id="wsa-refresh-usage" data-nonce="<?php echo wp_create_nonce('wsa_refresh_usage'); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh Usage Data', 'wp-site-advisory'); ?>
                </button>
                
                <a href="https://platform.openai.com/account/billing" target="_blank" class="button button-primary">
                    <span class="dashicons dashicons-external"></span>
                    <?php _e('Manage Billing & Top Up', 'wp-site-advisory'); ?>
                </a>
                
                <a href="https://platform.openai.com/account/usage" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php _e('View Detailed Usage', 'wp-site-advisory'); ?>
                </a>
            </div>
            
            <!-- Help Text -->
            <div class="wsa-usage-help">
                <p class="description">
                    <?php _e('Usage data is cached for 1 hour. Click "Refresh Usage Data" to get the latest information. To increase your token balance or modify billing settings, visit your OpenAI billing page.', 'wp-site-advisory'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render usage progress bar (if applicable)
     */
    private function render_usage_progress($raw_data) {
        // This would show progress against a plan limit
        // For now, we'll show a simple monthly projection
        if (isset($raw_data['analytics']['projected_monthly_cost'])) {
            $projected_cost = $raw_data['analytics']['projected_monthly_cost'];
            $current_cost = $raw_data['cost']['total_cost'];
            
            // Estimate percentage through month
            $current_day = date('j');
            $days_in_month = date('t');
            $month_progress = ($current_day / $days_in_month) * 100;
            
            ?>
            <div class="wsa-usage-progress">
                <div class="wsa-progress-header">
                    <h4><?php _e('Monthly Projection', 'wp-site-advisory'); ?></h4>
                    <span class="wsa-progress-label">
                        <?php printf(__('$%s projected for %s', 'wp-site-advisory'), 
                             number_format($projected_cost, 2), 
                             date('F Y')); ?>
                    </span>
                </div>
                <div class="wsa-progress-bar">
                    <div class="wsa-progress-fill" style="width: <?php echo min(100, $month_progress); ?>%"></div>
                </div>
                <div class="wsa-progress-info">
                    <span><?php printf(__('Current: $%s', 'wp-site-advisory'), number_format($current_cost, 2)); ?></span>
                    <span><?php printf(__('Day %d of %d', 'wp-site-advisory'), $current_day, $days_in_month); ?></span>
                </div>
            </div>
            <?php
        }
    }
}