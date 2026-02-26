<?php
/**
 * WP Site Advisory Admin Dashboard Class
 *
 * @package WP_Site_Advisory
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Admin_Dashboard {

    /**
     * Render the dashboard page
     */
    public function render() {
        // Get scan results
        $scan_results = get_option('wsa_last_scan_results', array());
        $last_scan = get_option('wsa_last_scheduled_scan', '');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wsa-dashboard">
                <!-- Quick Stats Overview -->
                <div class="wsa-stats-grid">
                    <?php $this->render_stat_cards($scan_results, $last_scan); ?>
                </div>

                <!-- Action Buttons -->
                <div class="wsa-actions">
                    <button id="wsa-scan-site" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Scan Site Now', 'wp-site-advisory'); ?>
                    </button>
                    <button id="wsa-system-scan" class="button button-primary">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('System Scan', 'wp-site-advisory'); ?>
                    </button>
                    <?php if (wsa_is_pro_active()): ?>
                        <button id="wsa-get-recommendations" class="button button-secondary" <?php echo empty($scan_results) ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-lightbulb"></span>
                            <?php _e('Get AI Recommendations', 'wp-site-advisory'); ?>
                        </button>
                    <?php else: ?>
                        <button class="button button-secondary wsa-pro-feature-trigger" data-feature="ai_analysis" <?php echo empty($scan_results) ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Get AI Recommendations (Pro)', 'wp-site-advisory'); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-settings'); ?>" class="button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'wp-site-advisory'); ?>
                    </a>
                </div>

                <!-- Loading States -->
                <div id="wsa-loading" class="wsa-loading" style="display: none;">
                    <div class="wsa-spinner"></div>
                    <span id="wsa-loading-text"><?php _e('Processing...', 'wp-site-advisory'); ?></span>
                </div>

                <!-- Tabbed Interface -->
                <div class="wsa-tabs-container">
                    <nav class="wsa-tab-nav nav-tab-wrapper">
                        <a href="#wsa-overview" class="nav-tab nav-tab-active" data-tab="overview">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php _e('Overview', 'wp-site-advisory'); ?>
                        </a>
                        <a href="#wsa-plugins" class="nav-tab" data-tab="plugins">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <?php _e('Plugins', 'wp-site-advisory'); ?>
                        </a>
                        <a href="#wsa-theme" class="nav-tab" data-tab="theme">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php _e('Theme Analysis', 'wp-site-advisory'); ?>
                        </a>
                        <a href="#wsa-system" class="nav-tab" data-tab="system">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('System Security', 'wp-site-advisory'); ?>
                        </a>
                        <a href="#wsa-integrations" class="nav-tab" data-tab="integrations">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php _e('Integrations', 'wp-site-advisory'); ?>
                        </a>
                        <?php if (wsa_is_pro_active()): ?>
                        <a href="#wsa-ai-features" class="nav-tab" data-tab="ai-features">
                            <span class="dashicons dashicons-admin-network"></span>
                            <?php _e('AI Features', 'wp-site-advisory'); ?>
                        </a>
                        <?php endif; ?>
                    </nav>

                    <div class="wsa-tab-content">
                        <!-- Overview Tab -->
                        <div id="wsa-overview" class="wsa-tab-panel wsa-tab-panel-active">
                            <?php $this->render_overview_tab($scan_results); ?>
                        </div>

                        <!-- Plugins Tab -->
                        <div id="wsa-plugins" class="wsa-tab-panel">
                            <?php $this->render_plugins_tab($scan_results); ?>
                        </div>

                        <!-- Theme Analysis Tab -->
                        <div id="wsa-theme" class="wsa-tab-panel">
                            <?php $this->render_theme_tab($scan_results); ?>
                        </div>

                        <!-- System Security Tab -->
                        <div id="wsa-system" class="wsa-tab-panel">
                            <?php $this->render_system_tab(); ?>
                        </div>

                        <!-- Integrations Tab -->
                        <div id="wsa-integrations" class="wsa-tab-panel">
                            <?php $this->render_integrations_tab($scan_results); ?>
                        </div>

                        <!-- AI Features Tab -->
                        <?php if (wsa_is_pro_active()): ?>
                        <div id="wsa-ai-features" class="wsa-tab-panel">
                            <?php $this->render_ai_features_tab(); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AI Recommendations -->
                <div id="wsa-recommendations" class="wsa-recommendations" style="display: none;">
                    <h2><?php _e('AI Recommendations', 'wp-site-advisory'); ?></h2>
                    <div class="wsa-recommendations-content"></div>
                </div>

                <?php 
                // Show Pro features showcase if not Pro
                if (!wsa_is_pro_active()) {
                    WP_Site_Advisory_Pro_Helper::get_instance()->render_pro_showcase();
                    
                    // Add specific feature upgrade modals
                    echo wsa_upgrade_cta('ai_analysis', 'modal');
                    echo wsa_upgrade_cta('vulnerability_scan', 'modal');
                    echo wsa_upgrade_cta('advanced_scheduling', 'modal');
                    echo wsa_upgrade_cta('white_label', 'modal');
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render stat cards
     */
    private function render_stat_cards($scan_results, $last_scan) {
        $plugins_count = count(get_option('active_plugins', array()));
        $current_theme = wp_get_theme();
        
        // Calculate vulnerability count
        $vulnerability_count = 0;
        $theme_issues_count = 0;
        
        if (!empty($scan_results['plugins'])) {
            foreach ($scan_results['plugins'] as $plugin) {
                if (!empty($plugin['vulnerability_count'])) {
                    $vulnerability_count += $plugin['vulnerability_count'];
                }
            }
        }
        
        // Add theme security issues to total count
        if (!empty($scan_results['theme_analysis']['security_scan']['issues_found'])) {
            $theme_issues_count = $scan_results['theme_analysis']['security_scan']['issues_found'];
            $vulnerability_count += $theme_issues_count;
        }
        ?>
        <div class="wsa-stat-card wsa-clickable-stat" data-tab="plugins">
            <h3><?php _e('Active Plugins', 'wp-site-advisory'); ?></h3>
            <div class="wsa-stat-number">
                <?php echo $plugins_count; ?>
            </div>
        </div>
        <div class="wsa-stat-card wsa-clickable-stat" data-tab="theme">
            <h3><?php _e('Current Theme', 'wp-site-advisory'); ?></h3>
            <div class="wsa-stat-text">
                <?php 
                echo '<strong>' . esc_html($current_theme->get('Name')) . '</strong><br>';
                echo '<span style="font-size: 11px; color: #646970;">v' . esc_html($current_theme->get('Version')) . '</span>';
                
                // Show theme security status if available
                if ($theme_issues_count > 0) {
                    echo '<br><span class="wsa-status-disconnected" style="font-size: 11px;">' . sprintf(__('%d Security Issues', 'wp-site-advisory'), $theme_issues_count) . '</span>';
                } else if (!empty($scan_results['theme_analysis'])) {
                    echo '<br><span class="wsa-status-connected" style="font-size: 11px;">' . __('Secure', 'wp-site-advisory') . '</span>';
                }
                ?>
            </div>
        </div>
        <div class="wsa-stat-card wsa-clickable-stat <?php echo $vulnerability_count > 0 ? 'wsa-has-issues' : ''; ?>" data-tab="system">
            <h3><?php _e('Security Status', 'wp-site-advisory'); ?></h3>
            <div class="wsa-stat-text">
                <?php 
                if ($vulnerability_count > 0) {
                    echo '<span class="wsa-status-disconnected">' . sprintf(__('%d Issues', 'wp-site-advisory'), $vulnerability_count) . '</span>';
                } else {
                    echo '<span class="wsa-status-connected">' . __('Secure', 'wp-site-advisory') . '</span>';
                }
                ?>
            </div>
        </div>
        <div class="wsa-stat-card">
            <h3><?php _e('Last Scan', 'wp-site-advisory'); ?></h3>
            <div class="wsa-stat-text">
                <?php 
                if (!empty($last_scan)) {
                    echo esc_html(human_time_diff(strtotime($last_scan), current_time('timestamp')) . ' ago');
                } else {
                    _e('Never', 'wp-site-advisory');
                }
                ?>
            </div>
        </div>
        <div class="wsa-stat-card">
            <h3><?php _e('AI Status', 'wp-site-advisory'); ?></h3>
            <div class="wsa-stat-text">
                <?php 
                $api_key = get_option('wsa_openai_api_key', '');
                if (!empty($api_key)) {
                    echo '<span class="wsa-status-connected">' . __('Connected', 'wp-site-advisory') . '</span>';
                } else {
                    echo '<span class="wsa-status-disconnected">' . __('Not Configured', 'wp-site-advisory') . '</span>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Overview Tab
     */
    private function render_overview_tab($scan_results) {
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-overview-cards">
                <!-- Security Overview -->
                <div class="wsa-overview-card wsa-security-overview">
                    <div class="wsa-card-header">
                        <h3><span class="dashicons dashicons-shield-alt"></span> <?php _e('Security Overview', 'wp-site-advisory'); ?></h3>
                    </div>
                    <div class="wsa-card-body">
                        <?php $this->render_security_overview($scan_results); ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="wsa-overview-card wsa-recent-activity">
                    <div class="wsa-card-header">
                        <h3><span class="dashicons dashicons-clock"></span> <?php _e('Recent Activity', 'wp-site-advisory'); ?></h3>
                    </div>
                    <div class="wsa-card-body">
                        <?php $this->render_recent_activity(); ?>
                    </div>
                </div>

                <!-- Performance Analysis -->
                <div class="wsa-overview-card wsa-performance-analysis">
                    <div class="wsa-card-header">
                        <h3><span class="dashicons dashicons-performance"></span> <?php _e('Performance Analysis', 'wp-site-advisory'); ?></h3>
                    </div>
                    <div class="wsa-card-body">
                        <?php $this->render_performance_analysis_overview(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Plugins Tab
     */
    private function render_plugins_tab($scan_results) {
        ?>
        <div class="wsa-tab-panel-content">
            <?php 
            if (!empty($scan_results['plugins'])) {
                $this->render_plugins_section($scan_results['plugins']);
            } else {
                echo '<div class="wsa-empty-state">';
                echo '<span class="dashicons dashicons-admin-plugins"></span>';
                echo '<h3>' . __('No Plugin Data Available', 'wp-site-advisory') . '</h3>';
                echo '<p>' . __('Run a site scan to analyze your plugins for security issues and recommendations.', 'wp-site-advisory') . '</p>';
                echo '<button id="wsa-scan-plugins" class="button button-primary">' . __('Scan Plugins Now', 'wp-site-advisory') . '</button>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render Theme Tab
     */
    private function render_theme_tab($scan_results) {
        ?>
        <div class="wsa-tab-panel-content">
            <?php 
            if (!empty($scan_results['theme_analysis'])) {
                $this->render_theme_analysis_section($scan_results['theme_analysis']);
            } else {
                echo '<div class="wsa-empty-state">';
                echo '<span class="dashicons dashicons-admin-appearance"></span>';
                echo '<h3>' . __('No Theme Analysis Available', 'wp-site-advisory') . '</h3>';
                echo '<p>' . __('Run a site scan to analyze your theme for security issues and compatibility.', 'wp-site-advisory') . '</p>';
                echo '<button id="wsa-scan-theme" class="button button-primary">' . __('Scan Theme Now', 'wp-site-advisory') . '</button>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render System Tab
     */
    private function render_system_tab() {
        ?>
        <div class="wsa-tab-panel-content">
            <?php 
            $system_scan_results = get_option('wsa_system_scan_results', array());
            if (!empty($system_scan_results)) {
                $this->render_system_security_section($system_scan_results);
                $this->render_system_performance_section($system_scan_results);
                $this->render_inactive_items_section($system_scan_results);
            } else {
                echo '<div class="wsa-empty-state">';
                echo '<span class="dashicons dashicons-admin-tools"></span>';
                echo '<h3>' . __('No System Analysis Available', 'wp-site-advisory') . '</h3>';
                echo '<p>' . __('Run a system scan to check your WordPress installation security and performance.', 'wp-site-advisory') . '</p>';
                echo '<button id="wsa-scan-system" class="button button-primary">' . __('Scan System Now', 'wp-site-advisory') . '</button>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render Integrations Tab
     */
    private function render_integrations_tab($scan_results) {
        ?>
        <div class="wsa-tab-panel-content">
            <?php 
            if (!empty($scan_results['google_integrations'])) {
                $this->render_google_integrations_section($scan_results['google_integrations']);
            } else {
                echo '<div class="wsa-empty-state">';
                echo '<span class="dashicons dashicons-admin-links"></span>';
                echo '<h3>' . __('No Integration Data Available', 'wp-site-advisory') . '</h3>';
                echo '<p>' . __('Run a site scan to check for Google Analytics, Search Console, and other integrations.', 'wp-site-advisory') . '</p>';
                echo '<button id="wsa-scan-integrations" class="button button-primary">' . __('Scan Integrations Now', 'wp-site-advisory') . '</button>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render security overview for Overview tab
     */
    private function render_security_overview($scan_results) {
        $vulnerability_count = 0;
        $plugin_count = 0;
        $theme_issues = 0;

        if (!empty($scan_results['plugins'])) {
            foreach ($scan_results['plugins'] as $plugin) {
                $plugin_count++;
                if (!empty($plugin['vulnerability_count'])) {
                    $vulnerability_count += $plugin['vulnerability_count'];
                }
            }
        }

        if (!empty($scan_results['theme_analysis']['security_scan']['issues_found'])) {
            $theme_issues = $scan_results['theme_analysis']['security_scan']['issues_found'];
        }

        $total_issues = $vulnerability_count + $theme_issues;
        ?>
        <div class="wsa-security-metrics">
            <div class="wsa-metric wsa-clickable-metric" data-tab="plugins" role="button" tabindex="0" 
                 aria-label="<?php esc_attr_e('Click to view security issues details', 'wp-site-advisory'); ?>" 
                 title="<?php esc_attr_e('Click to view security issues', 'wp-site-advisory'); ?>">
                <div class="wsa-metric-number <?php echo $total_issues > 0 ? 'wsa-warning' : 'wsa-success'; ?>">
                    <?php echo $total_issues; ?>
                </div>
                <div class="wsa-metric-label"><?php _e('Security Issues', 'wp-site-advisory'); ?></div>
                <div class="wsa-metric-hint"><?php _e('Click to view', 'wp-site-advisory'); ?></div>
            </div>
            <div class="wsa-metric wsa-clickable-metric" data-tab="plugins" role="button" tabindex="0" 
                 aria-label="<?php esc_attr_e('Click to view plugins details', 'wp-site-advisory'); ?>" 
                 title="<?php esc_attr_e('Click to view scanned plugins', 'wp-site-advisory'); ?>">
                <div class="wsa-metric-number"><?php echo $plugin_count; ?></div>
                <div class="wsa-metric-label"><?php _e('Plugins Scanned', 'wp-site-advisory'); ?></div>
                <div class="wsa-metric-hint"><?php _e('Click to view', 'wp-site-advisory'); ?></div>
            </div>
            <div class="wsa-metric wsa-clickable-metric" data-tab="theme" role="button" tabindex="0" 
                 aria-label="<?php esc_attr_e('Click to view theme issues details', 'wp-site-advisory'); ?>" 
                 title="<?php esc_attr_e('Click to view theme issues', 'wp-site-advisory'); ?>">
                <div class="wsa-metric-number <?php echo $theme_issues > 0 ? 'wsa-warning' : 'wsa-success'; ?>">
                    <?php echo $theme_issues; ?>
                </div>
                <div class="wsa-metric-label"><?php _e('Theme Issues', 'wp-site-advisory'); ?></div>
                <div class="wsa-metric-hint"><?php _e('Click to view', 'wp-site-advisory'); ?></div>
            </div>
        </div>

        <?php if ($total_issues > 0): ?>
        <div class="wsa-security-alert">
            <span class="dashicons dashicons-warning"></span>
            <strong><?php _e('Action Required:', 'wp-site-advisory'); ?></strong>
            <?php printf(__('We found %d security issues that need your attention.', 'wp-site-advisory'), $total_issues); ?>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        $last_scan = get_option('wsa_last_scheduled_scan', '');
        $scan_results = get_option('wsa_last_scan_results', array());
        
        ?>
        <div class="wsa-activity-list">
            <?php if (!empty($last_scan)): ?>
            <div class="wsa-activity-item">
                <span class="dashicons dashicons-search"></span>
                <div class="wsa-activity-content">
                    <strong><?php _e('Last Site Scan', 'wp-site-advisory'); ?></strong>
                    <span class="wsa-activity-time"><?php echo human_time_diff(strtotime($last_scan), current_time('timestamp')) . ' ago'; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php 
            $recent_plugins = get_option('recently_activated', array());
            if (!empty($recent_plugins)): 
                $recent_plugin = array_keys($recent_plugins)[0];
            ?>
            <div class="wsa-activity-item">
                <span class="dashicons dashicons-admin-plugins"></span>
                <div class="wsa-activity-content">
                    <strong><?php _e('Plugin Activity', 'wp-site-advisory'); ?></strong>
                    <span class="wsa-activity-time"><?php printf(__('Recent plugin: %s', 'wp-site-advisory'), basename(dirname($recent_plugin))); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="wsa-activity-item">
                <span class="dashicons dashicons-wordpress"></span>
                <div class="wsa-activity-content">
                    <strong><?php _e('WordPress Version', 'wp-site-advisory'); ?></strong>
                    <span class="wsa-activity-time"><?php echo get_bloginfo('version'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render quick actions
     */
    private function render_quick_actions($scan_results) {
        ?>
        <div class="wsa-quick-actions-grid">
            <button class="wsa-quick-action" id="wsa-quick-scan">
                <span class="dashicons dashicons-search"></span>
                <span><?php _e('Quick Scan', 'wp-site-advisory'); ?></span>
            </button>
            
            <?php if (wsa_is_pro_active()): ?>
            <button class="wsa-quick-action" id="wsa-quick-ai-analysis">
                <span class="dashicons dashicons-lightbulb"></span>
                <span><?php _e('AI Analysis', 'wp-site-advisory'); ?></span>
            </button>
            <?php else: ?>
            <button class="wsa-quick-action wsa-pro-feature-trigger" data-feature="ai_analysis">
                <span class="dashicons dashicons-lock"></span>
                <span><?php _e('AI Analysis (Pro)', 'wp-site-advisory'); ?></span>
            </button>
            <?php endif; ?>

            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-settings'); ?>" class="wsa-quick-action">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php _e('Settings', 'wp-site-advisory'); ?></span>
            </a>

            <?php if (!empty($scan_results)): ?>
            <button class="wsa-quick-action" id="wsa-export-results">
                <span class="dashicons dashicons-download"></span>
                <span><?php _e('Export Results', 'wp-site-advisory'); ?></span>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render scan results
     */
    private function render_scan_results($scan_results) {
        if (empty($scan_results)) {
            echo '<div class="wsa-no-results">';
            echo '<p>' . __('No scan results available. Click "Scan Site Now" to get started.', 'wp-site-advisory') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="wsa-scan-results">';
        
        // System Security and Performance (render first for priority)
        $system_scan_results = get_option('wsa_system_scan_results', array());
        if (!empty($system_scan_results)) {
            $this->render_system_security_section($system_scan_results);
            $this->render_system_performance_section($system_scan_results);
            $this->render_inactive_items_section($system_scan_results);
        }
        
        // Plugin Results
        if (!empty($scan_results['plugins'])) {
            $this->render_plugins_section($scan_results['plugins']);
        }
        
        // Theme Analysis
        if (!empty($scan_results['theme_analysis'])) {
            $this->render_theme_analysis_section($scan_results['theme_analysis']);
        }
        
        // Google Integrations
        if (!empty($scan_results['google_integrations'])) {
            $this->render_google_integrations_section($scan_results['google_integrations']);
        }
        
        echo '</div>';
    }

    /**
     * Render plugins section
     */
    private function render_plugins_section($plugins) {
        // Ensure $plugins is an array and clean any invalid entries
        if (!is_array($plugins)) {
            $plugins = array();
        }
        
        // Clean and validate plugin data
        $plugins = array_filter($plugins, function($plugin) {
            if (!is_array($plugin)) {
                return false;
            }
            if (empty($plugin['name'])) {
                return false;
            }
            return true;
        });
        
        // Filter plugins that need updates for default view
        $plugins_needing_updates = array_filter($plugins, function($plugin) {
            return is_array($plugin) && (
                !empty($plugin['update_available']) || 
                !empty($plugin['vulnerabilities']) || 
                !empty($plugin['vulnerability_count'])
            );
        });
        
        $initial_display_count = 8; // Show 8 plugins initially
        ?>
        <div class="wsa-section wsa-plugins-section">
            <div class="wsa-section-header">
                <h2 id="wsa-plugins-section-title">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Plugin Analysis', 'wp-site-advisory'); ?>
                    <span class="wsa-count wsa-total-count">(<?php echo count($plugins); ?> total)</span>
                </h2>
                
                <div class="wsa-plugin-controls">
                    <div class="wsa-plugin-stats">
                        <span class="wsa-stat-item wsa-needs-attention-count">
                            <strong><?php echo count($plugins_needing_updates); ?></strong> need attention
                        </span>
                        <span class="wsa-stat-item wsa-secure-count">
                            <strong><?php echo count($plugins) - count($plugins_needing_updates); ?></strong> secure
                        </span>
                    </div>
                    
                    <div class="wsa-plugin-filters">
                        <div class="wsa-toggle-wrapper">
                            <label class="wsa-toggle-label">
                                <input type="checkbox" id="wsa-show-all-plugins" class="wsa-toggle-input">
                                <span class="wsa-toggle-slider"></span>
                                <span class="wsa-toggle-text"><?php _e('Show all plugins', 'wp-site-advisory'); ?></span>
                            </label>
                        </div>
                        
                        <button class="button wsa-view-toggle-btn" id="wsa-expand-plugins-view" data-expanded="false">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php _e('Show All', 'wp-site-advisory'); ?>
                            <span class="wsa-remaining-count">(+<?php echo max(0, count($plugins_needing_updates) - $initial_display_count); ?> more)</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="wsa-plugins-grid" data-initial-count="<?php echo $initial_display_count; ?>" data-total-count="<?php echo count($plugins); ?>">
                <?php 
                // Sort plugins to show those needing attention first
                // Ensure we have proper arrays before merging
                $plugins_needing_updates = is_array($plugins_needing_updates) ? $plugins_needing_updates : array();
                $plugins = is_array($plugins) ? $plugins : array();
                
                // Filter out any invalid plugins from both arrays
                $plugins_needing_updates = array_filter($plugins_needing_updates, function($plugin) {
                    return is_array($plugin) && isset($plugin['name']);
                });
                
                $plugins = array_filter($plugins, function($plugin) {
                    return is_array($plugin) && isset($plugin['name']);
                });
                
                $sorted_plugins = array_merge($plugins_needing_updates, array_diff_key($plugins, array_flip(array_keys($plugins_needing_updates))));
                
                foreach ($sorted_plugins as $index => $plugin) :
                    // Ensure $plugin is an array with required fields
                    if (!is_array($plugin) || empty($plugin['name'])) {
                        continue;
                    }
                    
                    // Ensure basic plugin fields have default values
                    $plugin = array_merge(array(
                        'name' => '',
                        'version' => '',
                        'description' => '',
                        'plugin_uri' => '',
                        'author' => '',
                        'size' => 0,
                        'update_available' => false,
                        'vulnerabilities' => array(),
                        'vulnerability_count' => 0,
                        'highest_severity' => 'low',
                        'security_risk' => array('level' => 'low'),
                        'recommendations' => array()
                    ), $plugin);
                    
                    // Ensure author is a string (might be an array)
                    if (is_array($plugin['author'])) {
                        $plugin['author'] = isset($plugin['author'][0]) ? $plugin['author'][0] : '';
                    } elseif (!is_string($plugin['author'])) {
                        $plugin['author'] = '';
                    }
                    
                    // Ensure other fields that might be arrays are properly converted
                    if (is_array($plugin['description'])) {
                        $plugin['description'] = implode(' ', $plugin['description']);
                    }
                    if (is_array($plugin['name'])) {
                        $plugin['name'] = isset($plugin['name'][0]) ? $plugin['name'][0] : '';
                    }
                    if (is_array($plugin['version'])) {
                        $plugin['version'] = isset($plugin['version'][0]) ? $plugin['version'][0] : '';
                    }
                    
                    $needs_attention = !empty($plugin['update_available']) || !empty($plugin['vulnerabilities']) || !empty($plugin['vulnerability_count']);
                    $plugin_classes = array('wsa-plugin-card');                    // Add display control classes
                    if ($index >= $initial_display_count) {
                        $plugin_classes[] = 'wsa-plugin-hidden';
                    }
                    
                    // Add filtering classes
                    if ($needs_attention) {
                        $plugin_classes[] = 'wsa-needs-attention';
                    } else {
                        $plugin_classes[] = 'wsa-up-to-date';
                    }
                    
                    // Determine vulnerability class for card styling
                    $has_vulnerabilities = !empty($plugin['vulnerabilities']) || !empty($plugin['vulnerability_count']);
                    
                    if ($has_vulnerabilities) {
                        $severity = $plugin['highest_severity'] ?? 'low';
                        $plugin_classes[] = 'wsa-vulnerability-' . $severity;
                    }
                ?>
                    <div class="<?php echo esc_attr(implode(' ', $plugin_classes)); ?>">
                        <div class="wsa-plugin-header">
                            <h3><?php echo esc_html($plugin['name']); ?></h3>
                            <div class="wsa-plugin-badges">
                                <span class="wsa-plugin-version">v<?php echo esc_html($plugin['version']); ?></span>
                                <?php if ($has_vulnerabilities) : ?>
                                    <span class="wsa-vulnerability-badge <?php echo esc_attr($plugin['highest_severity'] ?? 'medium'); ?>">
                                        <?php printf(__('%d Vuln', 'wp-site-advisory'), $plugin['vulnerability_count']); ?>
                                    </span>
                                <?php elseif (!empty($plugin['update_available'])) : ?>
                                    <span class="wsa-update-badge">
                                        <?php _e('Update', 'wp-site-advisory'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="wsa-plugin-details">
                            <?php if (!empty($plugin['description'])) : ?>
                                <p class="wsa-plugin-description">
                                    <?php echo esc_html(wp_trim_words($plugin['description'], 20)); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($has_vulnerabilities) : ?>
                                <div class="wsa-vulnerability-details">
                                    <h5><?php _e('Security Issues Found:', 'wp-site-advisory'); ?></h5>
                                    <?php foreach ($plugin['vulnerabilities'] as $source => $data) : ?>
                                        <?php if (!empty($data['vulnerabilities'])) : ?>
                                            <?php foreach (array_slice($data['vulnerabilities'], 0, 2) as $vuln) : ?>
                                                <div class="wsa-vulnerability-item">
                                                    <strong><?php echo esc_html($vuln['title']); ?></strong>
                                                    <span class="wsa-severity-<?php echo esc_attr($vuln['severity']); ?>">
                                                        (<?php echo esc_html(ucfirst($vuln['severity'])); ?>)
                                                    </span>
                                                    <?php if (!empty($vuln['fixed_in'])) : ?>
                                                        <small><?php printf(__('Fixed in: %s', 'wp-site-advisory'), esc_html($vuln['fixed_in'])); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($data['vulnerabilities']) > 2) : ?>
                                                <small><?php printf(__('+ %d more vulnerabilities', 'wp-site-advisory'), count($data['vulnerabilities']) - 2); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="wsa-plugin-meta">
                                <?php if (!empty($plugin['author']) && is_string($plugin['author'])) : ?>
                                    <span class="wsa-plugin-author">
                                        <?php printf(__('By: %s', 'wp-site-advisory'), esc_html($plugin['author'])); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($plugin['plugin_uri'])) : ?>
                                    <a href="<?php echo esc_url($plugin['plugin_uri']); ?>" 
                                       target="_blank" class="wsa-plugin-link">
                                        <?php _e('Plugin Page', 'wp-site-advisory'); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="wsa-plugin-status">
                            <div class="wsa-status-left">
                                <?php if ($has_vulnerabilities) : ?>
                                    <span class="wsa-status-critical">
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php printf(__('%d Security Issues', 'wp-site-advisory'), $plugin['vulnerability_count']); ?>
                                    </span>
                                <?php elseif (!empty($plugin['update_available'])) : ?>
                                    <span class="wsa-status-warning">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('Update Available', 'wp-site-advisory'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="wsa-status-ok">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Secure & Updated', 'wp-site-advisory'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($plugin['security_status'])) : ?>
                                <div class="wsa-security-score">
                                    <?php 
                                    $security_labels = array(
                                        'good' => __('Good', 'wp-site-advisory'),
                                        'warning' => __('Warning', 'wp-site-advisory'),
                                        'critical' => __('Critical', 'wp-site-advisory')
                                    );
                                    $security_label = $security_labels[$plugin['security_status']] ?? __('Unknown', 'wp-site-advisory');
                                    ?>
                                    <small><?php printf(__('Security: %s', 'wp-site-advisory'), $security_label); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Google integrations section
     */
    private function render_google_integrations_section($integrations) {
        ?>
        <div class="wsa-section">
            <h2>
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Google Integrations', 'wp-site-advisory'); ?>
            </h2>
            
            <div class="wsa-integrations-grid">
                <?php 
                $google_services = array(
                    'analytics' => array(
                        'name' => __('Google Analytics', 'wp-site-advisory'),
                        'description' => __('Web analytics and reporting', 'wp-site-advisory')
                    ),
                    'tag_manager' => array(
                        'name' => __('Google Tag Manager', 'wp-site-advisory'),
                        'description' => __('Tag management system', 'wp-site-advisory')
                    ),
                    'search_console' => array(
                        'name' => __('Google Search Console', 'wp-site-advisory'),
                        'description' => __('SEO and search performance', 'wp-site-advisory')
                    ),
                    'adsense' => array(
                        'name' => __('Google AdSense', 'wp-site-advisory'),
                        'description' => __('Advertisement platform', 'wp-site-advisory')
                    ),
                    'fonts' => array(
                        'name' => __('Google Fonts', 'wp-site-advisory'),
                        'description' => __('Web font service', 'wp-site-advisory')
                    ),
                    'maps' => array(
                        'name' => __('Google Maps', 'wp-site-advisory'),
                        'description' => __('Mapping and location services', 'wp-site-advisory')
                    ),
                    'recaptcha' => array(
                        'name' => __('Google reCAPTCHA', 'wp-site-advisory'),
                        'description' => __('Security and spam protection', 'wp-site-advisory')
                    ),
                    'youtube' => array(
                        'name' => __('YouTube', 'wp-site-advisory'),
                        'description' => __('Video embedding and content', 'wp-site-advisory')
                    )
                );
                
                foreach ($google_services as $service => $service_info) :
                    $is_detected = isset($integrations[$service]) && $integrations[$service];
                    $service_id = isset($integrations[$service . '_id']) ? $integrations[$service . '_id'] : '';
                ?>
                    <div class="wsa-integration-card <?php echo $is_detected ? 'wsa-detected' : 'wsa-missing'; ?>">
                        <div class="wsa-integration-header">
                            <div class="wsa-integration-icon">
                                <?php if ($is_detected) : ?>
                                    <span class="dashicons dashicons-yes-alt wsa-icon-success"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-minus wsa-icon-warning"></span>
                                <?php endif; ?>
                            </div>
                            <div class="wsa-integration-details">
                                <h4><?php echo esc_html($service_info['name']); ?></h4>
                                <p class="wsa-service-description"><?php echo esc_html($service_info['description']); ?></p>
                                <p class="wsa-integration-status">
                                    <?php if ($is_detected) : ?>
                                        <span class="wsa-status-detected"><?php _e('✓ Detected', 'wp-site-advisory'); ?></span>
                                        <?php if ($service_id) : ?>
                                            <br><small><?php printf(__('ID: %s', 'wp-site-advisory'), esc_html($service_id)); ?></small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="wsa-status-missing"><?php _e('Not Found', 'wp-site-advisory'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="wsa-integration-actions">
                            <?php if ($is_detected) : ?>
                                <button type="button" class="button button-small wsa-view-integration-details" 
                                        data-service="<?php echo esc_attr($service); ?>"
                                        data-name="<?php echo esc_attr($service_info['name']); ?>"
                                        data-id="<?php echo esc_attr($service_id); ?>"
                                        data-description="<?php echo esc_attr($service_info['description']); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('View Details', 'wp-site-advisory'); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" class="button button-small wsa-show-setup-guide" 
                                        data-service="<?php echo esc_attr($service); ?>"
                                        data-name="<?php echo esc_attr($service_info['name']); ?>">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <?php _e('Setup Guide', 'wp-site-advisory'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            $missing_count = count(array_filter($google_services, function($service, $key) use ($integrations) {
                return !isset($integrations[$key]) || !$integrations[$key];
            }, ARRAY_FILTER_USE_BOTH));
            
            if ($missing_count > 0) :
            ?>
                <div class="wsa-integration-notice">
                    <p>
                        <span class="dashicons dashicons-info"></span>
                        <?php printf(
                            _n(
                                '%d Google integration is missing. Click "Get AI Recommendations" for setup guidance.',
                                '%d Google integrations are missing. Click "Get AI Recommendations" for setup guidance.',
                                $missing_count,
                                'wp-site-advisory'
                            ),
                            $missing_count
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render system security section
     */
    private function render_system_security_section($system_scan) {
        if (empty($system_scan) || empty($system_scan['security_checks'])) {
            return;
        }
        
        $security_checks = $system_scan['security_checks'];
        $issues_count = $security_checks['issues_found'];
        $security_score = $security_checks['security_score'];
        ?>
        <div class="wsa-section">
            <div class="wsa-section-header">
                <h2>
                    <span class="dashicons dashicons-shield-alt"></span>
                    <?php _e('System Security', 'wp-site-advisory'); ?>
                    <span class="wsa-security-score wsa-security-<?php echo $security_score >= 80 ? 'good' : ($security_score >= 60 ? 'medium' : 'poor'); ?>">
                        <?php printf(__('Score: %d%%', 'wp-site-advisory'), $security_score); ?>
                    </span>
                </h2>
            </div>
            
            <div class="wsa-security-grid">
                <?php foreach ($security_checks['checks'] as $check_name => $check) : ?>
                    <div class="wsa-security-check wsa-check-<?php echo esc_attr($check['status']); ?>">
                        <div class="wsa-check-header">
                            <span class="wsa-check-icon">
                                <?php if ($check['status'] === 'good') : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                <?php endif; ?>
                            </span>
                            <span class="wsa-check-title"><?php echo esc_html($this->get_security_check_title($check_name)); ?></span>
                        </div>
                        <div class="wsa-check-message"><?php echo esc_html($check['message']); ?></div>
                        <?php if (!empty($check['recommendation'])) : ?>
                            <div class="wsa-check-recommendation">
                                <strong><?php _e('Recommendation:', 'wp-site-advisory'); ?></strong>
                                <?php echo esc_html($check['recommendation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($security_checks['recommendations'])) : ?>
                <div class="wsa-security-recommendations">
                    <h4><?php _e('Priority Actions', 'wp-site-advisory'); ?></h4>
                    <ul>
                        <?php foreach (array_slice($security_checks['recommendations'], 0, 3) as $rec) : ?>
                            <li class="wsa-priority-<?php echo esc_attr($rec['priority']); ?>">
                                <strong><?php echo esc_html($rec['title']); ?>:</strong>
                                <?php echo esc_html($rec['description']); ?>
                                <?php if (!empty($rec['action_url'])) : ?>
                                    <a href="<?php echo esc_url($rec['action_url']); ?>" class="button button-small">
                                        <?php _e('Fix This', 'wp-site-advisory'); ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Vulnerability Scanner Integration -->
            <?php $this->render_vulnerability_scanner_section(); ?>
        </div>
        <?php
    }
    
    /**
     * Render vulnerability scanner section within System Security
     */
    private function render_vulnerability_scanner_section() {
        ?>
        <div class="wsa-vulnerability-scanner">
            <div class="wsa-section-header">
                <h3>
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Vulnerability Scanner', 'wp-site-advisory'); ?>
                    <span class="wsa-pro-badge"><?php _e('PRO', 'wp-site-advisory'); ?></span>
                </h3>
            </div>
            
            <div class="wsa-vulnerability-content">
                <div class="wsa-vulnerability-actions">
                    <button id="wsa-run-vulnerability-scan" class="button button-primary">
                        <?php _e('Scan for Vulnerabilities', 'wp-site-advisory'); ?>
                    </button>
                    <button id="wsa-view-vulnerability-report" class="button button-secondary" style="display: none;">
                        <?php _e('View Report', 'wp-site-advisory'); ?>
                    </button>
                </div>
                
                <div id="wsa-vulnerability-results" class="wsa-scan-results" style="display: none;">
                    <!-- Results will be loaded here via AJAX -->
                </div>
                
                <div class="wsa-vulnerability-info">
                    <p><?php _e('Scan your WordPress installation for known security vulnerabilities using WPScan database.', 'wp-site-advisory'); ?></p>
                    <ul>
                        <li><?php _e('Check core WordPress version for vulnerabilities', 'wp-site-advisory'); ?></li>
                        <li><?php _e('Scan installed plugins for security issues', 'wp-site-advisory'); ?></li>
                        <li><?php _e('Analyze theme files for potential risks', 'wp-site-advisory'); ?></li>
                        <li><?php _e('Get detailed remediation recommendations', 'wp-site-advisory'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render performance analysis overview for Overview tab
     */
    private function render_performance_analysis_overview() {
        ?>
        <div class="wsa-performance-overview">
            <div class="wsa-performance-url-input">
                <label for="wsa-performance-url">
                    <?php _e('URL to Analyze:', 'wp-site-advisory'); ?>
                    <small>(<?php _e('Must be publicly accessible', 'wp-site-advisory'); ?>)</small>
                </label>
                <input type="url" id="wsa-performance-url" class="regular-text" 
                       value="<?php echo esc_url(home_url()); ?>" 
                       placeholder="<?php esc_attr_e('Enter a public URL...', 'wp-site-advisory'); ?>" />
                
                <?php if (strpos(home_url(), 'localhost') !== false || strpos(home_url(), '127.0.0.1') !== false): ?>
                    <div class="notice notice-warning inline" style="margin: 10px 0;">
                        <p>
                            <strong><?php _e('Local Development Detected:', 'wp-site-advisory'); ?></strong>
                            <?php _e('PageSpeed Insights cannot analyze localhost URLs. Please enter a public URL or deploy your site to test performance.', 'wp-site-advisory'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wsa-performance-actions">
                <button id="wsa-run-pagespeed-analysis" class="button button-primary">
                    <?php _e('Run PageSpeed Analysis', 'wp-site-advisory'); ?>
                </button>
                <button id="wsa-view-performance-report" class="button button-secondary" style="display: none;">
                    <?php _e('View Full Report', 'wp-site-advisory'); ?>
                </button>
                <button id="wsa-get-ai-insights" class="button button-secondary" style="display: none;">
                    <?php _e('Get AI Insights', 'wp-site-advisory'); ?>
                </button>
            </div>
            
            <div id="wsa-performance-results" class="wsa-scan-results" style="display: none;">
                <!-- Results will be loaded here via AJAX -->
            </div>
            
            <div class="wsa-performance-metrics" id="wsa-performance-metrics" style="display: none;">
                <div class="wsa-metric-grid">
                    <div class="wsa-metric">
                        <span class="wsa-metric-label"><?php _e('Performance Score', 'wp-site-advisory'); ?></span>
                        <span class="wsa-metric-value" id="wsa-perf-score">-</span>
                    </div>
                    <div class="wsa-metric">
                        <span class="wsa-metric-label"><?php _e('First Contentful Paint', 'wp-site-advisory'); ?></span>
                        <span class="wsa-metric-value" id="wsa-fcp">-</span>
                    </div>
                    <div class="wsa-metric">
                        <span class="wsa-metric-label"><?php _e('Largest Contentful Paint', 'wp-site-advisory'); ?></span>
                        <span class="wsa-metric-value" id="wsa-lcp">-</span>
                    </div>
                    <div class="wsa-metric">
                        <span class="wsa-metric-label"><?php _e('Total Blocking Time', 'wp-site-advisory'); ?></span>
                        <span class="wsa-metric-value" id="wsa-tbt">-</span>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Render system performance section
     */
    private function render_system_performance_section($system_scan) {
        if (empty($system_scan) || empty($system_scan['performance_checks'])) {
            return;
        }
        
        $performance_checks = $system_scan['performance_checks'];
        $issues_count = $performance_checks['issues_found'];
        ?>
        <div class="wsa-section">
            <div class="wsa-section-header">
                <h2>
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Performance Checks', 'wp-site-advisory'); ?>
                    <?php if ($issues_count > 0) : ?>
                        <span class="wsa-count wsa-issues-count"><?php printf(__('%d Issues', 'wp-site-advisory'), $issues_count); ?></span>
                    <?php else : ?>
                        <span class="wsa-count wsa-success-count"><?php _e('All Good', 'wp-site-advisory'); ?></span>
                    <?php endif; ?>
                </h2>
            </div>
            
            <div class="wsa-performance-grid">
                <?php foreach ($performance_checks['checks'] as $check_name => $check) : ?>
                    <div class="wsa-performance-check wsa-check-<?php echo esc_attr($check['status']); ?>">
                        <div class="wsa-check-header">
                            <span class="wsa-check-icon">
                                <?php if ($check['status'] === 'good') : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php elseif ($check['status'] === 'warning') : ?>
                                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-info" style="color: #72aee6;"></span>
                                <?php endif; ?>
                            </span>
                            <span class="wsa-check-title"><?php echo esc_html($this->get_performance_check_title($check_name)); ?></span>
                        </div>
                        <div class="wsa-check-message"><?php echo esc_html($check['message']); ?></div>
                        <?php if (!empty($check['recommendation'])) : ?>
                            <div class="wsa-check-recommendation">
                                <strong><?php _e('Recommendation:', 'wp-site-advisory'); ?></strong>
                                <?php echo esc_html($check['recommendation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render inactive items section
     */
    private function render_inactive_items_section($system_scan) {
        if (empty($system_scan)) {
            return;
        }
        
        $inactive_plugins = $system_scan['inactive_plugins'] ?? array();
        $inactive_themes = $system_scan['inactive_themes'] ?? array();
        $total_inactive = ($inactive_plugins['count'] ?? 0) + ($inactive_themes['count'] ?? 0);
        
        if ($total_inactive === 0) {
            return;
        }
        
        $total_size = ($inactive_plugins['total_size'] ?? 0) + ($inactive_themes['total_size'] ?? 0);
        $total_size_mb = $total_size > 0 ? round($total_size / 1024 / 1024, 2) : 0;
        ?>
        <div class="wsa-section">
            <div class="wsa-section-header">
                <h2>
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Inactive Items', 'wp-site-advisory'); ?>
                    <span class="wsa-count"><?php printf(__('%d Items (%s MB)', 'wp-site-advisory'), $total_inactive, $total_size_mb); ?></span>
                </h2>
            </div>
            
            <?php if (!empty($inactive_plugins['plugins'])) : ?>
                <div class="wsa-inactive-section">
                    <h4>
                        <?php _e('Inactive Plugins', 'wp-site-advisory'); ?>
                        <span class="wsa-count">(<?php echo $inactive_plugins['count']; ?>)</span>
                    </h4>
                    <div class="wsa-inactive-grid">
                        <?php foreach (array_slice($inactive_plugins['plugins'], 0, 6) as $plugin) : ?>
                            <div class="wsa-inactive-item">
                                <div class="wsa-item-header">
                                    <strong><?php echo esc_html($plugin['name']); ?></strong>
                                    <span class="wsa-item-version">v<?php echo esc_html($plugin['version']); ?></span>
                                </div>
                                <div class="wsa-item-details">
                                    <span class="wsa-item-size"><?php echo round($plugin['size'] / 1024 / 1024, 2); ?> MB</span>
                                    <?php if (isset($plugin['security_risk']['level']) && $plugin['security_risk']['level'] !== 'low') : ?>
                                        <span class="wsa-risk-<?php echo esc_attr($plugin['security_risk']['level']); ?>">
                                            <?php printf(__('Risk: %s', 'wp-site-advisory'), ucfirst($plugin['security_risk']['level'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($plugin['recommendations']) && is_array($plugin['recommendations']) && isset($plugin['recommendations'][0]['action'])) : ?>
                                    <div class="wsa-item-recommendation">
                                        <?php echo esc_html($plugin['recommendations'][0]['action']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($inactive_plugins['count'] > 6) : ?>
                        <p class="wsa-show-more">
                            <em><?php printf(__('... and %d more inactive plugins', 'wp-site-advisory'), $inactive_plugins['count'] - 6); ?></em>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($inactive_themes['themes'])) : ?>
                <div class="wsa-inactive-section">
                    <h4>
                        <?php _e('Inactive Themes', 'wp-site-advisory'); ?>
                        <span class="wsa-count">(<?php echo $inactive_themes['count']; ?>)</span>
                    </h4>
                    <div class="wsa-inactive-grid">
                        <?php foreach (array_slice($inactive_themes['themes'], 0, 6) as $theme) : ?>
                            <div class="wsa-inactive-item">
                                <div class="wsa-item-header">
                                    <strong><?php echo esc_html($theme['name']); ?></strong>
                                    <span class="wsa-item-version">v<?php echo esc_html($theme['version']); ?></span>
                                </div>
                                <div class="wsa-item-details">
                                    <span class="wsa-item-size"><?php echo round($theme['size'] / 1024 / 1024, 2); ?> MB</span>
                                    <?php if ($theme['is_child_theme']) : ?>
                                        <span class="wsa-child-theme"><?php _e('Child Theme', 'wp-site-advisory'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($inactive_themes['count'] > 6) : ?>
                        <p class="wsa-show-more">
                            <em><?php printf(__('... and %d more inactive themes', 'wp-site-advisory'), $inactive_themes['count'] - 6); ?></em>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($inactive_plugins['recommendations']) || !empty($inactive_themes['recommendations'])) : ?>
                <div class="wsa-cleanup-recommendations">
                    <h4><?php _e('Cleanup Recommendations', 'wp-site-advisory'); ?></h4>
                    <ul>
                        <?php 
                        $all_recs = array_merge(
                            $inactive_plugins['recommendations'] ?? array(),
                            $inactive_themes['recommendations'] ?? array()
                        );
                        foreach ($all_recs as $rec) : 
                        ?>
                            <li class="wsa-priority-<?php echo esc_attr($rec['priority']); ?>">
                                <strong><?php echo esc_html($rec['title']); ?>:</strong>
                                <?php echo esc_html($rec['description']); ?>
                                <?php if (!empty($rec['action_url'])) : ?>
                                    <a href="<?php echo esc_url($rec['action_url']); ?>" class="button button-small">
                                        <?php _e('Manage', 'wp-site-advisory'); ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get security check title
     */
    private function get_security_check_title($check_name) {
        $titles = array(
            'admin_username' => __('Admin Username Security', 'wp-site-advisory'),
            'ssl_enabled' => __('SSL/HTTPS Status', 'wp-site-advisory'),
            'wp_core_updated' => __('WordPress Core Updates', 'wp-site-advisory'),
            'file_permissions' => __('File Permissions', 'wp-site-advisory'),
            'debug_mode' => __('Debug Mode Status', 'wp-site-advisory'),
            'file_editing' => __('File Editing Security', 'wp-site-advisory')
        );
        
        return isset($titles[$check_name]) ? $titles[$check_name] : ucwords(str_replace('_', ' ', $check_name));
    }
    
    /**
     * Get performance check title
     */
    private function get_performance_check_title($check_name) {
        $titles = array(
            'database_size' => __('Database Size', 'wp-site-advisory'),
            'wp_cron' => __('WP Cron Status', 'wp-site-advisory'),
            'memory_limit' => __('PHP Memory Limit', 'wp-site-advisory'),
            'upload_max_filesize' => __('Upload Limit', 'wp-site-advisory'),
            'object_cache' => __('Object Cache', 'wp-site-advisory'),
            'gzip_compression' => __('GZIP Compression', 'wp-site-advisory')
        );
        
        return isset($titles[$check_name]) ? $titles[$check_name] : ucwords(str_replace('_', ' ', $check_name));
    }
    
    /**
     * Render theme analysis section
     */
    private function render_theme_analysis_section($theme_analysis) {
        if (empty($theme_analysis) || isset($theme_analysis['error'])) {
            return;
        }
        
        $theme_info = $theme_analysis['theme_info'];
        $security_scan = $theme_analysis['security_scan'];
        $editability = $theme_analysis['editability'];
        $update_info = $theme_analysis['update_available'];
        $recommendations = $theme_analysis['recommendations'];
        ?>
        <div class="wsa-section">
            <div class="wsa-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php _e('Theme Analysis', 'wp-site-advisory'); ?>
                    <span class="wsa-count"><?php echo esc_html($theme_info['name']); ?></span>
                </h2>
            </div>
            
            <div class="wsa-theme-analysis">
                <!-- Theme Information Card -->
                <div class="wsa-theme-info-card">
                    <div class="wsa-theme-header">
                        <h3><?php echo esc_html($theme_info['name']); ?></h3>
                        <div class="wsa-theme-badges">
                            <span class="wsa-theme-version">v<?php echo esc_html($theme_info['version']); ?></span>
                            <?php if ($theme_info['is_child_theme']) : ?>
                                <span class="wsa-child-theme-badge"><?php _e('Child Theme', 'wp-site-advisory'); ?></span>
                            <?php endif; ?>
                            <?php if ($update_info['update_available']) : ?>
                                <span class="wsa-update-badge">
                                    <?php _e('Update Available', 'wp-site-advisory'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="wsa-theme-details">
                        <?php if (!empty($theme_info['description'])) : ?>
                            <p class="wsa-theme-description">
                                <?php echo esc_html(wp_trim_words($theme_info['description'], 25)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="wsa-theme-meta">
                            <?php if (!empty($theme_info['author'])) : ?>
                                <span class="wsa-theme-author">
                                    <?php printf(__('By: %s', 'wp-site-advisory'), esc_html($theme_info['author'])); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($theme_info['theme_uri'])) : ?>
                                <a href="<?php echo esc_url($theme_info['theme_uri']); ?>" 
                                   target="_blank" class="wsa-theme-link">
                                    <?php _e('Theme Page', 'wp-site-advisory'); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($theme_info['is_child_theme'] && !empty($theme_info['parent_theme'])) : ?>
                            <div class="wsa-parent-theme-info">
                                <strong><?php _e('Parent Theme:', 'wp-site-advisory'); ?></strong>
                                <?php echo esc_html($theme_info['parent_theme']['name']); ?> 
                                v<?php echo esc_html($theme_info['parent_theme']['version']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Security Status -->
                    <div class="wsa-theme-status">
                        <div class="wsa-status-left">
                            <?php if ($security_scan['critical_issues'] > 0) : ?>
                                <span class="wsa-status-critical">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php printf(__('%d Critical Security Issues', 'wp-site-advisory'), $security_scan['critical_issues']); ?>
                                </span>
                            <?php elseif ($security_scan['medium_issues'] > 0) : ?>
                                <span class="wsa-status-warning">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php printf(__('%d Security Warnings', 'wp-site-advisory'), $security_scan['medium_issues']); ?>
                                </span>
                            <?php elseif ($update_info['update_available']) : ?>
                                <span class="wsa-status-warning">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Update Available', 'wp-site-advisory'); ?>
                                </span>
                            <?php else : ?>
                                <span class="wsa-status-ok">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Secure & Updated', 'wp-site-advisory'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="wsa-security-score">
                            <small><?php printf(__('Security Score: %d%%', 'wp-site-advisory'), $security_scan['security_score']); ?></small>
                        </div>
                    </div>
                </div>
                
                <!-- Security Scan Results -->
                <?php if ($security_scan['issues_found'] > 0) : ?>
                    <div class="wsa-theme-security-issues">
                        <h4><?php _e('Security Issues Found', 'wp-site-advisory'); ?></h4>
                        <div class="wsa-security-summary">
                            <span class="wsa-scan-stat">
                                <?php printf(__('Files Scanned: %d', 'wp-site-advisory'), $security_scan['files_scanned']); ?>
                            </span>
                            <span class="wsa-scan-stat">
                                <?php printf(__('Issues Found: %d', 'wp-site-advisory'), $security_scan['issues_found']); ?>
                            </span>
                            <span class="wsa-scan-stat">
                                <?php printf(__('Scan Time: %s seconds', 'wp-site-advisory'), $security_scan['scan_time']); ?>
                            </span>
                        </div>
                        
                        <div class="wsa-security-issues-list">
                            <?php foreach (array_slice($security_scan['issues'], 0, 5) as $issue) : ?>
                                <div class="wsa-security-issue wsa-severity-<?php echo esc_attr($issue['severity']); ?>">
                                    <div class="wsa-issue-header">
                                        <strong><?php echo esc_html($issue['description']); ?></strong>
                                        <span class="wsa-severity-badge wsa-severity-<?php echo esc_attr($issue['severity']); ?>">
                                            <?php echo esc_html(ucfirst($issue['severity'])); ?>
                                        </span>
                                    </div>
                                    <div class="wsa-issue-details">
                                        <small>
                                            <?php printf(__('File: %s', 'wp-site-advisory'), esc_html($issue['file'])); ?>
                                            <?php if (!empty($issue['line'])) : ?>
                                                <?php printf(__(' (Line %d)', 'wp-site-advisory'), $issue['line']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($issue['recommendation'])) : ?>
                                        <div class="wsa-issue-recommendation">
                                            <em><?php echo esc_html($issue['recommendation']); ?></em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($security_scan['issues']) > 5) : ?>
                                <div class="wsa-more-issues">
                                    <?php printf(__('+ %d more issues found', 'wp-site-advisory'), count($security_scan['issues']) - 5); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Theme Settings & Editability -->
                <div class="wsa-theme-settings">
                    <h4><?php _e('Theme Settings', 'wp-site-advisory'); ?></h4>
                    <div class="wsa-settings-grid">
                        <div class="wsa-setting-item">
                            <span class="wsa-setting-label"><?php _e('File Editing:', 'wp-site-advisory'); ?></span>
                            <span class="wsa-setting-value <?php echo $editability['is_editable'] ? 'wsa-enabled' : 'wsa-disabled'; ?>">
                                <?php echo $editability['is_editable'] ? __('Enabled', 'wp-site-advisory') : __('Disabled', 'wp-site-advisory'); ?>
                            </span>
                        </div>
                        <div class="wsa-setting-item">
                            <span class="wsa-setting-label"><?php _e('Directory Writable:', 'wp-site-advisory'); ?></span>
                            <span class="wsa-setting-value <?php echo $editability['theme_directory_writable'] ? 'wsa-enabled' : 'wsa-disabled'; ?>">
                                <?php echo $editability['theme_directory_writable'] ? __('Yes', 'wp-site-advisory') : __('No', 'wp-site-advisory'); ?>
                            </span>
                        </div>
                        <div class="wsa-setting-item">
                            <span class="wsa-setting-label"><?php _e('Update Available:', 'wp-site-advisory'); ?></span>
                            <span class="wsa-setting-value <?php echo $update_info['update_available'] ? 'wsa-enabled' : 'wsa-disabled'; ?>">
                                <?php echo $update_info['update_available'] ? __('Yes', 'wp-site-advisory') : __('No', 'wp-site-advisory'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Recommendations -->
                <?php if (!empty($recommendations)) : ?>
                    <div class="wsa-theme-recommendations">
                        <h4><?php _e('Recommendations', 'wp-site-advisory'); ?></h4>
                        <?php foreach ($recommendations as $recommendation) : ?>
                            <div class="wsa-recommendation wsa-priority-<?php echo esc_attr($recommendation['priority']); ?>">
                                <div class="wsa-recommendation-header">
                                    <strong><?php echo esc_html($recommendation['title']); ?></strong>
                                    <span class="wsa-priority-badge wsa-priority-<?php echo esc_attr($recommendation['priority']); ?>">
                                        <?php echo esc_html(ucfirst($recommendation['priority'])); ?>
                                    </span>
                                </div>
                                <p><?php echo esc_html($recommendation['description']); ?></p>
                                <?php if (!empty($recommendation['action_url'])) : ?>
                                    <a href="<?php echo esc_url($recommendation['action_url']); ?>" class="button button-secondary">
                                        <?php _e('Take Action', 'wp-site-advisory'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Scan Results Modal -->
        <div id="wsa-system-scan-modal" class="wsa-modal-overlay">
            <div class="wsa-system-scan-modal">
                <div class="wsa-modal-header">
                    <h3>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('System Scan Results', 'wp-site-advisory'); ?>
                    </h3>
                    <button class="wsa-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-site-advisory'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wsa-system-scan-modal-content" id="wsa-modal-system-scan-content">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <h4><?php _e('System Scan Results', 'wp-site-advisory'); ?></h4>
                        <p><?php _e('Your system scan results will appear here.', 'wp-site-advisory'); ?></p>
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-system-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wsa-run-new-system-scan">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Run New System Scan', 'wp-site-advisory'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Site Scan Results Modal -->
        <div id="wsa-site-scan-modal" class="wsa-modal-overlay">
            <div class="wsa-site-scan-modal">
                <div class="wsa-modal-header">
                    <h3>
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Site Scan Results', 'wp-site-advisory'); ?>
                    </h3>
                    <button class="wsa-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-site-advisory'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wsa-site-scan-modal-content" id="wsa-modal-site-scan-content">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-search"></span>
                        <h4><?php _e('Site Scan Results', 'wp-site-advisory'); ?></h4>
                        <p><?php _e('Your site scan results will appear here.', 'wp-site-advisory'); ?></p>
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-site-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wsa-run-new-site-scan">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Run New Site Scan', 'wp-site-advisory'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Features Tab
     */
    private function render_ai_features_tab() {
        ?>
        <div class="wsa-ai-features-content">
            <div class="wsa-section-header">
                <h2><?php _e('AI-Powered Features', 'wp-site-advisory'); ?></h2>
                <p class="description"><?php _e('Access and manage all AI-powered features for your WordPress site.', 'wp-site-advisory'); ?></p>
            </div>
            
            <div class="wsa-ai-dashboard-grid">
                
                <!-- AI Automated Optimizer -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('AI Automated Optimizer', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Automatically optimize your site\'s performance with AI-powered recommendations and fixes.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="optimizer-status">
                                <?php echo $this->get_ai_optimizer_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary wsa-ai-feature-btn" id="run-optimizer" data-feature="optimizer">
                                <?php _e('Run Optimization', 'wp-site-advisory'); ?>
                            </button>
                            <button class="button wsa-view-results-btn" data-feature="optimizer" style="<?php echo $this->has_ai_results('optimizer') ? '' : 'display:none;'; ?>">
                                <?php _e('View Results', 'wp-site-advisory'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-settings'); ?>" class="button">
                                <?php _e('Settings', 'wp-site-advisory'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI Content Analyzer -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-edit-page"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('AI Content Analyzer', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Analyze your content for SEO, accessibility, and quality improvements with AI insights.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="content-status">
                                <?php echo $this->get_ai_content_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary wsa-ai-feature-btn" id="analyze-content" data-feature="content">
                                <?php _e('Analyze Content', 'wp-site-advisory'); ?>
                            </button>
                            <button class="button wsa-view-results-btn" data-feature="content" style="<?php echo $this->has_ai_results('content') ? '' : 'display:none;'; ?>">
                                <?php _e('View Results', 'wp-site-advisory'); ?>
                            </button>
                            <a href="<?php echo admin_url('edit.php'); ?>" class="button">
                                <?php _e('Edit Posts', 'wp-site-advisory'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI Predictive Analytics -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('AI Predictive Analytics', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Get AI-powered predictions about traffic, performance trends, and security risks.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="analytics-status">
                                <?php echo $this->get_ai_analytics_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary wsa-ai-feature-btn" id="generate-predictions" data-feature="analytics">
                                <?php _e('Generate Predictions', 'wp-site-advisory'); ?>
                            </button>
                            <button class="button wsa-view-results-btn" data-feature="analytics" style="<?php echo $this->has_ai_results('analytics') ? '' : 'display:none;'; ?>">
                                <?php _e('View Results', 'wp-site-advisory'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced PageSpeed Analysis -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-dashboard"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('Enhanced PageSpeed Analysis', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Advanced PageSpeed analysis with AI-powered optimization recommendations.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="pagespeed-status">
                                <?php echo $this->get_ai_pagespeed_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary wsa-ai-feature-btn" id="run-pagespeed" data-feature="pagespeed">
                                <?php _e('Run Analysis', 'wp-site-advisory'); ?>
                            </button>
                            <button class="button wsa-view-results-btn" data-feature="pagespeed" style="<?php echo $this->has_ai_results('pagespeed') ? '' : 'display:none;'; ?>">
                                <?php _e('View Results', 'wp-site-advisory'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- AI White Label Reports -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('AI White Label Reports', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Generate professional reports with AI-powered insights for clients.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="reports-status">
                                <?php echo $this->get_ai_reports_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary wsa-ai-feature-btn" id="generate-report" data-feature="reports">
                                <?php _e('Generate Report', 'wp-site-advisory'); ?>
                            </button>
                            <button class="button wsa-view-results-btn" data-feature="reports" style="<?php echo $this->has_ai_results('reports') ? '' : 'display:none;'; ?>">
                                <?php _e('View Results', 'wp-site-advisory'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- AI Configuration -->
                <div class="wsa-ai-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php _e('AI Configuration', 'wp-site-advisory'); ?></h3>
                        <p><?php _e('Configure OpenAI API settings and AI feature preferences.', 'wp-site-advisory'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="ai-config-status">
                                <?php echo $this->get_ai_config_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-settings'); ?>" class="button button-primary">
                                <?php _e('Configure AI', 'wp-site-advisory'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Quick Stats -->
            <div class="wsa-ai-quick-stats">
                <h3><?php _e('AI Features Quick Stats', 'wp-site-advisory'); ?></h3>
                <div class="wsa-stats-grid">
                    <div class="stat-item">
                        <span class="stat-number" id="last-optimization"><?php echo get_option('wsa_pro_last_optimization', 'Never'); ?></span>
                        <span class="stat-label"><?php _e('Last Optimization', 'wp-site-advisory'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="content-analyzed"><?php echo get_option('wsa_pro_content_analyzed', 0); ?></span>
                        <span class="stat-label"><?php _e('Content Analyzed', 'wp-site-advisory'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="predictions-generated"><?php echo get_option('wsa_pro_predictions_generated', 0); ?></span>
                        <span class="stat-label"><?php _e('Predictions Generated', 'wp-site-advisory'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="reports-generated"><?php echo get_option('wsa_pro_reports_generated', 0); ?></span>
                        <span class="stat-label"><?php _e('Reports Generated', 'wp-site-advisory'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Recent AI Activity -->
            <div class="wsa-ai-recent-activity">
                <h3><?php _e('Recent AI Activity', 'wp-site-advisory'); ?></h3>
                <div id="recent-ai-activity-list">
                    <?php echo $this->get_recent_ai_activity(); ?>
                </div>
            </div>
            
            <!-- Debug Test Section -->
            <div class="wsa-debug-section" style="margin-top: 30px; padding: 20px; background: #f0f0f1; border-radius: 4px;">
                <h4><?php _e('Debug: Test Functions', 'wp-site-advisory'); ?></h4>
                <p><?php _e('Use these buttons to test AJAX communication and create sample results:', 'wp-site-advisory'); ?></p>
                <button class="button button-primary wsa-ai-feature-btn" id="test-ajax" data-feature="test">
                    <?php _e('Test AJAX Connection', 'wp-site-advisory'); ?>
                </button>
                <button class="button button-secondary" id="create-test-results" style="margin-left: 10px;">
                    <?php _e('Create Test Results', 'wp-site-advisory'); ?>
                </button>
                <div id="test-results" style="margin-top: 10px;"></div>
            </div>
            
        </div>
        
        <!-- AI Results Modal -->
        <div id="wsa-ai-results-modal" class="wsa-modal" style="display: none;">
            <div class="wsa-modal-content">
                <div class="wsa-modal-header">
                    <h3 id="wsa-modal-title"><?php _e('AI Analysis Results', 'wp-site-advisory'); ?></h3>
                    <span class="wsa-modal-close">&times;</span>
                </div>
                <div class="wsa-modal-body">
                    <div id="wsa-modal-results-container">
                        <!-- Results will be loaded here -->
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div id="wsa-modal-backdrop" class="wsa-modal-backdrop" style="display: none;"></div>
        
        <?php
    }

    /**
     * Get AI feature status methods
     */
    private function get_ai_optimizer_status() {
        if (class_exists('\WSA_Pro\Features\AI_Automated_Optimizer')) {
            return '<span class="status-indicator status-active">' . __('Active', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-inactive">' . __('Inactive', 'wp-site-advisory') . '</span>';
    }

    private function get_ai_content_status() {
        if (class_exists('\WSA_Pro\Features\AI_Content_Analyzer')) {
            return '<span class="status-indicator status-active">' . __('Active', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-inactive">' . __('Inactive', 'wp-site-advisory') . '</span>';
    }

    private function get_ai_analytics_status() {
        if (class_exists('\WSA_Pro\Features\AI_Predictive_Analytics')) {
            return '<span class="status-indicator status-active">' . __('Active', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-inactive">' . __('Inactive', 'wp-site-advisory') . '</span>';
    }

    private function get_ai_pagespeed_status() {
        if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
            return '<span class="status-indicator status-active">' . __('Active', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-inactive">' . __('Inactive', 'wp-site-advisory') . '</span>';
    }

    private function get_ai_reports_status() {
        if (class_exists('\WSA_Pro\Features\White_Label_Reports')) {
            return '<span class="status-indicator status-active">' . __('Active', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-inactive">' . __('Inactive', 'wp-site-advisory') . '</span>';
    }

    /**
     * Check if AI results exist for a feature
     */
    private function has_ai_results($feature) {
        $option_name = 'wsa_ai_results_' . $feature;
        $results = get_option($option_name);
        
        // Debug logging (only when WP_DEBUG is enabled)
        $has_results = !empty($results) && (
            (isset($results['html']) && !empty($results['html'])) ||
            (isset($results['raw_data']) && !empty($results['raw_data'])) ||
            (isset($results['success']) && $results['success'] === true)
        );
        
        return $has_results;
    }

    private function get_ai_config_status() {
        $openai_key = get_option('wsa_openai_api_key');
        if (!empty($openai_key)) {
            return '<span class="status-indicator status-active">' . __('Configured', 'wp-site-advisory') . '</span>';
        }
        return '<span class="status-indicator status-warning">' . __('Not Configured', 'wp-site-advisory') . '</span>';
    }

    private function get_recent_ai_activity() {
        $activities = get_option('wsa_ai_activity_log', array());
        
        if (empty($activities)) {
            return '<p>' . __('No recent AI activity found.', 'wp-site-advisory') . '</p>';
        }
        
        $html = '';
        foreach (array_slice($activities, -5) as $activity) {
            $time_ago = human_time_diff($activity['timestamp'], time()) . ' ago';
            $html .= sprintf(
                '<div class="activity-item">
                    <span class="activity-icon dashicons %s"></span>
                    <div class="activity-text">%s</div>
                    <div class="activity-time">%s</div>
                </div>',
                esc_attr($activity['icon']),
                esc_html($activity['message']),
                esc_html($time_ago)
            );
        }
        
        return $html;
    }
}