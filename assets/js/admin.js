/**
 * WP SiteAdvisor Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        WSAAdmin.init();
    });

    // Main admin object
    window.WSAAdmin = {
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.checkElementsExist();
            this.initializePluginFilter();
        },

        // Bind event handlers
        bindEvents: function() {
            // Tab navigation
            this.initTabs();
            
            // Keyboard navigation for tabs
            $(document).on('keydown', '.wsa-tab-nav .nav-tab', this.handleTabKeyboard);
            
            // Quick action buttons
            $(document).on('click', '#wsa-quick-scan', this.scanSite);
            $(document).on('click', '#wsa-quick-ai-analysis', this.getAIRecommendations);
            $(document).on('click', '#wsa-scan-plugins', this.scanSite);
            $(document).on('click', '#wsa-scan-theme', this.scanSite);
            $(document).on('click', '#wsa-scan-system', this.systemScan);
            $(document).on('click', '#wsa-scan-integrations', this.scanSite);
            
            // Check if system scan button exists
            if ($('#wsa-system-scan').length) {
            } else {
            }
            
            // Scan site button
            $(document).on('click', '#wsa-scan-site', this.scanSite);
            
            // System scan button
            $(document).on('click', '#wsa-system-scan', this.systemScan);
            
            
            // Get AI recommendations button
            $(document).on('click', '#wsa-get-recommendations', this.getAIRecommendations);
            
            // Test API connection button
            $(document).on('click', '#wsa-test-api', this.testAPIConnection);
            
            // Plugin filter toggle
            $(document).on('change', '#wsa-show-all-plugins', this.togglePluginFilter);
            
            // Plugin expansion toggle
            $(document).on('click', '#wsa-expand-plugins-view', this.togglePluginExpansion);
            
            // Clickable statistics navigation
            $(document).on('click', '.wsa-clickable-stat', this.handleStatClick);
            
            // Clickable security overview metrics
            $(document).on('click', '.wsa-clickable-metric', this.handleStatClick);
            $(document).on('keydown', '.wsa-clickable-metric', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            // Integration detail buttons (modal)
            $(document).on('click', '.wsa-view-details-btn', this.handleViewIntegrationDetails);
            $(document).on('click', '.wsa-setup-guide-btn', this.handleShowSetupGuide);
            
            // Integration detail buttons (dashboard)
            $(document).on('click', '.wsa-view-integration-details', this.handleViewIntegrationDetailsDashboard);
            $(document).on('click', '.wsa-show-setup-guide', this.handleShowSetupGuideDashboard);
            
            // Weekly report buttons
            $(document).on('click', '#wsa-send-test-report', this.sendTestReport);
            $(document).on('click', '#wsa-preview-report', this.previewReport);
            
            // System scan modal events
            // Modal event handlers
            $(document).on('click', '#wsa-system-scan-modal .wsa-modal-close, #wsa-system-modal-close-btn', this.closeSystemScanModal);
            $(document).on('click', '#wsa-run-new-system-scan', this.runNewSystemScan);
            
            // Site scan modal event handlers
            $(document).on('click', '#wsa-site-scan-modal .wsa-modal-close, #wsa-site-modal-close-btn', this.closeSiteScanModal);
            $(document).on('click', '#wsa-run-new-site-scan', this.runNewSiteScan);
            
            // AI Results modal handlers
            $(document).on('click', '#wsa-ai-results-modal .wsa-modal-close, #wsa-modal-close-btn, #wsa-modal-backdrop', this.hideModal.bind(this));
            $(document).on('click', '.wsa-view-results-btn', this.handleViewResults.bind(this));
            
            // AI Features event handlers
            $(document).on('click', '.wsa-ai-feature-btn', this.handleAIFeatureAction);
            
            // Create test results handler
            $(document).on('click', '#create-test-results', this.handleCreateTestResults.bind(this));
            
            // Handle AJAX errors globally
            $(document).ajaxError(this.handleAjaxError);
        },

        // Check if required elements exist
        checkElementsExist: function() {
            // Add any initialization that depends on specific elements
            if ($('#wsa-scan-site').length) {
                this.initializeDashboard();
            }
            
            // Initialize AI features if Pro is active and we're on a dashboard with AI features
            if ((typeof wsa_ajax !== 'undefined' && wsa_ajax.has_pro) && 
                ($('.wsa-ai-dashboard-card').length > 0 || $('.wsa-view-results-btn').length > 0)) {
                // Delay initialization to ensure DOM is fully ready
                var self = this;
                setTimeout(function() {
                    self.initViewResultsButtons();
                }, 100);
            }
        },

                // Toggle plugin filter
        togglePluginFilter: function(e) {
            var $toggle = $(e.target);
            var $pluginsGrid = $('.wsa-plugins-grid');
            var $totalCount = $('.wsa-total-count');
            var $filteredCount = $('.wsa-filtered-count');
            var isShowingAll = $toggle.is(':checked');
            
            if (isShowingAll) {
                // Show all plugins
                $pluginsGrid.addClass('wsa-show-all');
                $totalCount.show();
                $filteredCount.hide();
            } else {
                // Show only plugins that need attention
                $pluginsGrid.removeClass('wsa-show-all');
                $totalCount.hide();
                $filteredCount.show();
            }
            
            // Update the plugins section header text only
            var $sectionHeader = $('#wsa-plugins-section-title');
            var pluginText = isShowingAll ? 
                wsa_ajax.strings.all_plugins_text : 
                wsa_ajax.strings.filtered_plugins_text;
            
            // Update the main text while preserving the icon and count
            var $icon = $sectionHeader.find('.dashicons');
            var $count = $sectionHeader.find('.wsa-count:visible');
            var iconHtml = $icon.length ? $icon[0].outerHTML : '';
            var countHtml = $count.length ? $count[0].outerHTML : '';
            
            $sectionHeader.html(iconHtml + ' ' + pluginText + ' ' + countHtml);
        },

        // Initialize plugin filter to default state
        initializePluginFilter: function() {
            // Set initial state - show only plugins needing attention by default
            var $pluginsGrid = $('.wsa-plugins-grid');
            var $toggle = $('#wsa-show-all-plugins');
            var $totalCount = $('.wsa-total-count');
            var $filteredCount = $('.wsa-filtered-count');
            
            if ($toggle.length && $pluginsGrid.length) {
                // Ensure toggle is unchecked (show filtered view by default)
                $toggle.prop('checked', false);
                
                // Apply default filtering state
                $pluginsGrid.removeClass('wsa-show-all');
                $totalCount.hide();
                $filteredCount.show();
                
                // Add smooth transitions
                $pluginsGrid.find('.wsa-plugin-card').css('transition', 'opacity 0.3s ease, transform 0.3s ease');
            }
            
            // Initialize expand/collapse functionality
            this.initializeExpandCollapse();
        },

        // Initialize expand/collapse functionality for plugins
        initializeExpandCollapse: function() {
            var self = this;
            
            // Bind expand/collapse button click
            $(document).on('click', '#wsa-expand-plugins-view', function(e) {
                e.preventDefault();
                self.togglePluginExpansion();
            });
            
            // Initialize view state
            this.updateExpandCollapseState();
        },

        // Toggle plugin expansion (show all vs limited)
        togglePluginExpansion: function() {
            var $button = $('#wsa-expand-plugins-view');
            var $grid = $('.wsa-plugins-grid');
            var isExpanded = $button.data('expanded') === true || $button.data('expanded') === 'true';
            
            if (isExpanded) {
                // Collapse view - hide extra plugins
                $grid.find('.wsa-plugin-card').each(function(index) {
                    var initialCount = parseInt($grid.data('initial-count')) || 8;
                    if (index >= initialCount) {
                        $(this).addClass('wsa-plugin-hidden').slideUp(300);
                    }
                });
                
                $button.data('expanded', false);
                this.updateExpandCollapseButton(false);
            } else {
                // Expand view - show all plugins
                $grid.find('.wsa-plugin-card.wsa-plugin-hidden').removeClass('wsa-plugin-hidden').slideDown(300);
                
                $button.data('expanded', true);
                this.updateExpandCollapseButton(true);
            }
        },

        // Update expand/collapse button appearance
        updateExpandCollapseButton: function(isExpanded) {
            var $button = $('#wsa-expand-plugins-view');
            var $icon = $button.find('.dashicons');
            var $text = $button.contents().not($icon).not('.wsa-remaining-count');
            var $remainingCount = $button.find('.wsa-remaining-count');
            
            if (isExpanded) {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $button.contents().filter(function() {
                    return this.nodeType === 3; // Text nodes
                }).last().replaceWith(' Show Less ');
                $remainingCount.hide();
            } else {
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $button.contents().filter(function() {
                    return this.nodeType === 3; // Text nodes
                }).last().replaceWith(' Show All ');
                $remainingCount.show();
            }
        },

        // Update expand/collapse state based on current view
        updateExpandCollapseState: function() {
            var $grid = $('.wsa-plugins-grid');
            var $button = $('#wsa-expand-plugins-view');
            var initialCount = parseInt($grid.data('initial-count')) || 8;
            var totalVisible = $grid.find('.wsa-plugin-card:visible').length;
            var remainingCount = Math.max(0, totalVisible - initialCount);
            
            // Update remaining count
            $button.find('.wsa-remaining-count').text('(+' + remainingCount + ' more)');
            
            // Hide button if no additional items to show
            if (remainingCount <= 0) {
                $button.hide();
            } else {
                $button.show();
            }
        },

        // Handle clickable statistics navigation
        handleStatClick: function(e) {
            e.preventDefault();
            
            var $stat = $(e.currentTarget);
            var targetTab = $stat.data('tab');
            
            if (targetTab) {
                // Switch to the target tab using the same logic as initTabs
                var $targetTabButton = $('.wsa-tab-nav .nav-tab[data-tab="' + targetTab + '"]');
                var $tabNav = $targetTabButton.closest('.wsa-tab-nav');
                var $tabContent = $tabNav.siblings('.wsa-tab-content');
                
                if ($targetTabButton.length && $tabContent.length) {
                    // Update tab navigation (same as initTabs)
                    $tabNav.find('.nav-tab').removeClass('nav-tab-active');
                    $targetTabButton.addClass('nav-tab-active');
                    
                    // Update tab panels (same as initTabs)
                    $tabContent.find('.wsa-tab-panel').removeClass('wsa-tab-panel-active');
                    $tabContent.find('#wsa-' + targetTab).addClass('wsa-tab-panel-active');
                    
                    // Store active tab in localStorage for persistence
                    localStorage.setItem('wsa_active_tab', targetTab);
                    
                    // Trigger custom event for Pro plugin integration
                    $(document).trigger('wsa:tabChanged', [targetTab]);
                    
                    // Scroll to the target section if needed
                    var $targetPanel = $('#wsa-' + targetTab);
                    if ($targetPanel.length) {
                        $('html, body').animate({
                            scrollTop: $targetPanel.offset().top - 50
                        }, 300);
                    }
                    
                    // Add visual feedback
                    $stat.addClass('wsa-stat-clicked');
                    setTimeout(function() {
                        $stat.removeClass('wsa-stat-clicked');
                    }, 200);
                }
            }
        },

        // Handle view integration details button
        handleViewIntegrationDetails: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var serviceKey = $button.data('service');
            var integrationData = $button.data('integration');
            
            // Show detailed modal for this integration
            this.showIntegrationDetailsModal(serviceKey, integrationData);
        },

        // Handle setup guide button
        handleShowSetupGuide: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var serviceKey = $button.data('service');
            var serviceName = $button.data('service-name');
            
            // Show setup guide modal for this integration
            this.showIntegrationSetupModal(serviceKey, serviceName);
        },

        // Handle view integration details from dashboard
        handleViewIntegrationDetailsDashboard: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var serviceKey = $button.data('service');
            var integrationData = {
                name: $button.data('name'),
                detected: true,
                id: $button.data('id'),
                description: $button.data('description')
            };
            
            // Show detailed modal for this integration
            this.showIntegrationDetailsModal(serviceKey, integrationData);
        },

        // Handle setup guide button from dashboard
        handleShowSetupGuideDashboard: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var serviceKey = $button.data('service');
            var serviceName = $button.data('name');
            
            // Show setup guide modal for this integration
            this.showIntegrationSetupModal(serviceKey, serviceName);
        },

        // Show integration details modal
        showIntegrationDetailsModal: function(serviceKey, integrationData) {
            var modalContent = this.buildIntegrationDetailsHTML(serviceKey, integrationData);
            this.showGenericModal('Integration Details', modalContent);
        },

        // Show integration setup guide modal
        showIntegrationSetupModal: function(serviceKey, serviceName) {
            var modalContent = this.buildSetupGuideHTML(serviceKey, serviceName);
            this.showGenericModal('Setup Guide: ' + serviceName, modalContent);
        },

        // Build integration details HTML
        buildIntegrationDetailsHTML: function(serviceKey, data) {
            var html = '<div class="wsa-integration-details-content">';
            
            html += '<div class="wsa-detail-section">';
            html += '<h4><span class="dashicons dashicons-yes-alt"></span> Detection Status</h4>';
            html += '<p><strong>Status:</strong> <span class="wsa-status-detected">✓ Detected and Active</span></p>';
            
            if (data.id) {
                html += '<p><strong>Service ID:</strong> <code>' + data.id + '</code></p>';
            }
            
            html += '</div>';
            
            // Add service-specific information
            html += '<div class="wsa-detail-section">';
            html += '<h4><span class="dashicons dashicons-info"></span> Service Information</h4>';
            html += '<p><strong>Service:</strong> ' + data.name + '</p>';
            html += '<p><strong>Description:</strong> ' + data.description + '</p>';
            
            // Add service-specific details based on the service type
            switch(serviceKey) {
                case 'analytics':
                    html += '<p><strong>Implementation:</strong> Google Analytics tracking code detected</p>';
                    html += '<p><strong>Tracking:</strong> Page views, events, and conversions are being recorded</p>';
                    break;
                case 'tag_manager':
                    html += '<p><strong>Implementation:</strong> Google Tag Manager container detected</p>';
                    html += '<p><strong>Management:</strong> Tags are managed through GTM interface</p>';
                    break;
                case 'search_console':
                    html += '<p><strong>Verification:</strong> Site ownership verified with Google Search Console</p>';
                    html += '<p><strong>Data:</strong> Search performance data available in GSC</p>';
                    break;
                case 'adsense':
                    html += '<p><strong>Implementation:</strong> AdSense ad units detected</p>';
                    html += '<p><strong>Monetization:</strong> Site is configured for ad revenue</p>';
                    break;
            }
            html += '</div>';
            
            html += '<div class="wsa-detail-section">';
            html += '<h4><span class="dashicons dashicons-admin-tools"></span> Next Steps</h4>';
            html += '<ul>';
            html += '<li>✓ Integration is working correctly</li>';
            html += '<li>Monitor performance in Google Analytics/Console</li>';
            html += '<li>Consider AI recommendations for optimization</li>';
            html += '</ul>';
            html += '</div>';
            
            html += '</div>';
            return html;
        },

        // Build setup guide HTML
        buildSetupGuideHTML: function(serviceKey, serviceName) {
            var html = '<div class="wsa-setup-guide-content">';
            
            html += '<div class="wsa-setup-intro">';
            html += '<p>This service was not detected on your website. Follow the steps below to set it up:</p>';
            html += '</div>';
            
            // Add service-specific setup instructions
            html += '<div class="wsa-setup-steps">';
            html += '<h4><span class="dashicons dashicons-list-view"></span> Setup Instructions</h4>';
            
            switch(serviceKey) {
                case 'analytics':
                    html += '<ol>';
                    html += '<li>Visit <a href="https://analytics.google.com" target="_blank">Google Analytics</a></li>';
                    html += '<li>Create a new property for your website</li>';
                    html += '<li>Copy the tracking ID (GA4 Measurement ID)</li>';
                    html += '<li>Add the tracking code to your website header</li>';
                    html += '<li>Verify tracking is working in the Analytics dashboard</li>';
                    html += '</ol>';
                    break;
                case 'tag_manager':
                    html += '<ol>';
                    html += '<li>Visit <a href="https://tagmanager.google.com" target="_blank">Google Tag Manager</a></li>';
                    html += '<li>Create a new container for your website</li>';
                    html += '<li>Copy the container ID (GTM-XXXXXXX)</li>';
                    html += '<li>Add GTM code to your website head and body</li>';
                    html += '<li>Configure tags through the GTM interface</li>';
                    html += '</ol>';
                    break;
                case 'search_console':
                    html += '<ol>';
                    html += '<li>Visit <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>';
                    html += '<li>Add your website as a property</li>';
                    html += '<li>Verify ownership using HTML tag or DNS method</li>';
                    html += '<li>Submit your sitemap</li>';
                    html += '<li>Monitor search performance and indexing</li>';
                    html += '</ol>';
                    break;
                default:
                    html += '<ol>';
                    html += '<li>Visit the official ' + serviceName + ' website</li>';
                    html += '<li>Create an account and follow setup instructions</li>';
                    html += '<li>Add the required code to your website</li>';
                    html += '<li>Verify the integration is working</li>';
                    html += '</ol>';
            }
            
            html += '</div>';
            
            html += '<div class="wsa-setup-help">';
            html += '<h4><span class="dashicons dashicons-sos"></span> Need Help?</h4>';
            html += '<p>Consider upgrading to <strong>WP SiteAdvisor Pro</strong> for AI-powered setup guidance and automated integration recommendations.</p>';
            html += '</div>';
            
            html += '</div>';
            return html;
        },

        // Show generic modal (used for integration details and setup guides)
        showGenericModal: function(title, content) {
            // Remove any existing modal
            $('.wsa-generic-modal').remove();
            
            var modalHtml = '<div class="wsa-generic-modal wsa-modal-overlay">';
            modalHtml += '<div class="wsa-modal-dialog">';
            modalHtml += '<div class="wsa-modal-header">';
            modalHtml += '<h3>' + title + '</h3>';
            modalHtml += '<button class="wsa-modal-close" type="button" aria-label="Close">';
            modalHtml += '<span class="dashicons dashicons-no-alt"></span>';
            modalHtml += '</button>';
            modalHtml += '</div>';
            modalHtml += '<div class="wsa-modal-body">' + content + '</div>';
            modalHtml += '<div class="wsa-modal-footer">';
            modalHtml += '<button type="button" class="button" onclick="$(\'.wsa-generic-modal\').remove(); $(\'body\').css(\'overflow\', \'\');">Close</button>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';
            
            $('body').append(modalHtml);
            $('.wsa-generic-modal').addClass('wsa-modal-open');
            $('body').css('overflow', 'hidden');
            
            // Handle close buttons
            $('.wsa-generic-modal .wsa-modal-close').on('click', function() {
                $('.wsa-generic-modal').remove();
                $('body').css('overflow', '');
            });
        },

        // Initialize dashboard specific functionality
        initializeDashboard: function() {
            // Initialize plugin filter state
            this.initializePluginFilter();
            
            // Auto-refresh data if it's older than 1 hour
            var lastScan = window.wsa_last_scan_timestamp;
            if (lastScan) {
                var hourAgo = Date.now() - (60 * 60 * 1000);
                var lastScanTime = new Date(lastScan).getTime();
                
                if (lastScanTime < hourAgo) {
                    this.showAutoScanNotice();
                }
            }
        },

        // Show notice for old scan data
        showAutoScanNotice: function() {
            var notice = $('<div class="notice notice-info is-dismissible">' +
                '<p><strong>WP SiteAdvisor:</strong> Your scan data is more than 1 hour old. ' +
                '<a href="#" id="wsa-auto-scan">Refresh now</a></p>' +
                '</div>');
            
            $('.wsa-dashboard').before(notice);
            
            // Handle auto-scan click
            notice.on('click', '#wsa-auto-scan', function(e) {
                e.preventDefault();
                WSAAdmin.scanSite();
                notice.fadeOut();
            });
        },

        // Scan site functionality
        scanSite: function(e) {
            e.preventDefault();
            
            var button = $('#wsa-scan-site');
            var loadingDiv = $('#wsa-loading');
            var loadingText = $('#wsa-loading-text');
            var resultsDiv = $('#wsa-results');
            
            // Update UI state
            button.prop('disabled', true);
            loadingDiv.show();
            loadingText.text(wsa_ajax.strings.scanning);
            
            // Make AJAX request
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_scan_site',
                    nonce: wsa_ajax.nonce
                },
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    if (response.success) {
                        WSAAdmin.handleScanSuccess(response.data);
                    } else {
                        WSAAdmin.showError('Scan failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    var message = 'Scan request failed';
                    
                    if (status === 'timeout') {
                        message = 'Scan timed out. The site may be slow to respond.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        message = xhr.responseJSON.data.message || message;
                    }
                    
                    WSAAdmin.showError(message);
                },
                complete: function() {
                    button.prop('disabled', false);
                    loadingDiv.hide();
                    $('#wsa-get-recommendations').prop('disabled', false);
                }
            });
        },

        // System scan functionality
        systemScan: function(e) {
            e.preventDefault();
            
            // Auto-switch to System Security tab
            WSAAdmin.switchToTab('system');
            
            var button = $('#wsa-system-scan');
            var loadingDiv = $('#wsa-loading');
            var loadingText = $('#wsa-loading-text');
            var resultsDiv = $('#wsa-results');
            
            // Update UI state
            button.prop('disabled', true);
            loadingDiv.show();
            loadingText.text('Starting system scan...');
            
            // Make AJAX request
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_scan_system',
                    nonce: wsa_ajax.nonce
                },
                timeout: 60000, // 60 second timeout (increased for progress updates)
                success: function(response) {
                    if (response.success && response.data.scan_id) {
                        // Start polling for progress updates
                        WSAAdmin.startProgressPolling(response.data.scan_id, function(finalData) {
                            WSAAdmin.handleSystemScanSuccess(finalData);
                        });
                    } else if (response.success) {
                        WSAAdmin.handleSystemScanSuccess(response.data);
                    } else {
                        WSAAdmin.showError('System scan failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    var message = 'System scan request failed';
                    
                    if (status === 'timeout') {
                        message = 'System scan timed out. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        message = xhr.responseJSON.data.message || message;
                    }
                    
                    WSAAdmin.showError(message);
                },
                complete: function() {
                    // Don't re-enable button immediately if we're polling for progress
                    if (!window.wsaProgressInterval) {
                        button.prop('disabled', false);
                        loadingDiv.hide();
                    }
                }
            });
        },

        // Handle successful system scan
        handleSystemScanSuccess: function(data) {
            // Show system scan results in modal
            WSAAdmin.showSystemScanModal(data);
            
            // Also show a brief success notification
            var securityScore = data.security_checks ? data.security_checks.security_score : 0;
            var totalIssues = (data.security_checks ? data.security_checks.issues_found : 0) +
                             (data.performance_checks ? data.performance_checks.issues_found : 0);
            
            var message = 'System scan completed! Security Score: ' + securityScore + '%';
            if (totalIssues > 0) {
                message += ', ' + totalIssues + ' issues found';
            }
            
            this.showSuccess(message);
            
            // No page reload needed - modal handles the results display
        },

        // Handle successful scan
        handleScanSuccess: function(data) {
            // Store scan timestamp globally
            window.wsa_last_scan_timestamp = data.timestamp;
            
            // Show site scan results in modal
            WSAAdmin.showSiteScanModal(data);
            
            // Show success message
            var pluginsCount = data.plugins ? data.plugins.length : 0;
            var themesCount = data.themes ? data.themes.length : 0;
            var integrationsCount = data.integrations ? data.integrations.length : 0;
            
            this.showSuccess('Site scan completed successfully! Found ' + 
                pluginsCount + ' plugins, ' + themesCount + ' themes, and ' + 
                integrationsCount + ' integrations.');
        },

        // Get AI recommendations
        getAIRecommendations: function(e) {
            e.preventDefault();
            
            var button = $('#wsa-get-recommendations');
            var loadingDiv = $('#wsa-loading');
            var loadingText = $('#wsa-loading-text');
            var recommendationsDiv = $('#wsa-recommendations');
            
            // Check if API key is configured
            if (!WSAAdmin.checkAPIKey()) {
                return;
            }
            
            // Update UI state
            button.prop('disabled', true);
            loadingDiv.show();
            loadingText.text(wsa_ajax.strings.getting_recommendations);
            
            // Make AJAX request
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_ai_recommendations',
                    nonce: wsa_ajax.nonce
                },
                timeout: 120000, // 2 minute timeout for AI requests
                success: function(response) {
                    if (response.success) {
                        WSAAdmin.handleRecommendationsSuccess(response.data);
                    } else {
                        WSAAdmin.showError('AI recommendations failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    var message = 'AI recommendations request failed';
                    
                    if (status === 'timeout') {
                        message = 'AI request timed out. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        message = xhr.responseJSON.data.message || message;
                    }
                    
                    WSAAdmin.showError(message);
                },
                complete: function() {
                    button.prop('disabled', false);
                    loadingDiv.hide();
                }
            });
        },

        // Handle successful AI recommendations
        handleRecommendationsSuccess: function(data) {
            var recommendationsDiv = $('#wsa-recommendations');
            var content = $('.wsa-recommendations-content');
            
            // Build recommendations HTML
            var html = this.buildRecommendationsHTML(data);
            content.html(html);
            recommendationsDiv.show();
            
            // Scroll to recommendations
            $('html, body').animate({
                scrollTop: recommendationsDiv.offset().top - 50
            }, 500);
            
            this.showSuccess('AI recommendations generated successfully!');
        },

        // Build recommendations HTML
        buildRecommendationsHTML: function(data) {
            var html = '';
            
            // Overall health and summary
            if (data.summary) {
                html += '<div class="wsa-recommendation wsa-summary">';
                html += '<h3>Overall Assessment</h3>';
                html += '<p><strong>Health Status:</strong> ' + this.capitalizeFirst(data.overall_health) + '</p>';
                html += '<p><strong>Priority Score:</strong> ' + data.priority_score + '/10</p>';
                html += '<p>' + data.summary + '</p>';
                html += '</div>';
            }
            
            // Quick wins
            if (data.quick_wins && data.quick_wins.length > 0) {
                html += '<div class="wsa-recommendation">';
                html += '<h4>Quick Wins</h4>';
                html += '<ul>';
                $.each(data.quick_wins, function(index, win) {
                    html += '<li>' + win + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            // Main recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                html += '<h3>Detailed Recommendations</h3>';
                
                $.each(data.recommendations, function(index, rec) {
                    html += '<div class="wsa-recommendation">';
                    html += '<span class="wsa-recommendation-priority wsa-priority-' + rec.priority + '">' + rec.priority + '</span>';
                    html += '<h4>' + rec.title + '</h4>';
                    html += '<p><strong>Category:</strong> ' + WSAAdmin.capitalizeFirst(rec.category) + '</p>';
                    
                    if (rec.description) {
                        html += '<p>' + rec.description + '</p>';
                    }
                    
                    if (rec.action_steps && rec.action_steps.length > 0) {
                        html += '<div class="wsa-recommendation-steps">';
                        html += '<strong>Action Steps:</strong>';
                        html += '<ol>';
                        $.each(rec.action_steps, function(stepIndex, step) {
                            html += '<li>' + step + '</li>';
                        });
                        html += '</ol>';
                        html += '</div>';
                    }
                    
                    if (rec.estimated_time || rec.difficulty) {
                        html += '<p><small>';
                        if (rec.estimated_time) {
                            html += '<strong>Time:</strong> ' + rec.estimated_time + ' ';
                        }
                        if (rec.difficulty) {
                            html += '<strong>Difficulty:</strong> ' + WSAAdmin.capitalizeFirst(rec.difficulty);
                        }
                        html += '</small></p>';
                    }
                    
                    html += '</div>';
                });
            }
            
            // Long term goals
            if (data.long_term_goals && data.long_term_goals.length > 0) {
                html += '<div class="wsa-recommendation">';
                html += '<h4>Long-term Strategic Goals</h4>';
                html += '<ul>';
                $.each(data.long_term_goals, function(index, goal) {
                    html += '<li>' + goal + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            return html;
        },

        // Test API connection
        testAPIConnection: function(e) {
            e.preventDefault();
            
            var button = $(this);
            var resultDiv = $('#wsa-api-test-result');
            var apiKeyField = $('#wsa_openai_api_key');
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.hide().removeClass('success error');
            
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_test_openai_connection',
                    nonce: wsa_ajax.nonce,
                    api_key: apiKeyField.val()
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.addClass('success').text(response.data.message).show();
                    } else {
                        resultDiv.addClass('error').text(response.data.message).show();
                    }
                },
                error: function() {
                    resultDiv.addClass('error').text('Connection test failed').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Test OpenAI Connection');
                }
            });
        },

        // Check if API key is configured
        checkAPIKey: function() {
            // This is a basic check - the actual validation happens on the server
            if ($('#wsa-get-recommendations').data('api-configured') === false) {
                this.showError('Please configure your OpenAI API key in the settings page first.');
                return false;
            }
            return true;
        },

        // Show success message
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        // Show error message
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        // Show notice
        showNotice: function(message, type) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible">' +
                '<p><strong>WP SiteAdvisor:</strong> ' + message + '</p>' +
                '</div>');
            
            // Remove existing notices
            $('.notice').not('.settings-error').fadeOut();
            
            // Add new notice
            $('.wsa-dashboard, .wrap').first().before(notice);
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut();
                }, 5000);
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: notice.offset().top - 50
            }, 300);
        },

        // Handle global AJAX errors
        handleAjaxError: function(event, xhr, settings, error) {
            // Only handle our AJAX requests
            if (settings.url === wsa_ajax.ajax_url && settings.data && 
                settings.data.indexOf('wsa_') !== -1) {
                
                console.error('WP SiteAdvisor AJAX Error:', error, xhr);
                
                // Show user-friendly error message
                if (xhr.status === 0) {
                    WSAAdmin.showError('Connection failed. Please check your internet connection.');
                } else if (xhr.status === 500) {
                    WSAAdmin.showError('Server error occurred. Please try again or contact support.');
                } else if (xhr.status === 403) {
                    WSAAdmin.showError('Permission denied. Please refresh the page and try again.');
                }
            }
        },

        // Utility function to capitalize first letter
        capitalizeFirst: function(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },

        // Send test report
        sendTestReport: function(e) {
            e.preventDefault();
            
            var button = $('#wsa-send-test-report');
            var emailInput = $('#wsa-test-email');
            var resultSpan = $('#wsa-test-report-result');
            var email = emailInput.val().trim();
            
            if (!email) {
                resultSpan.html('<span style="color: #dc3232;">Please enter an email address</span>');
                return;
            }
            
            // Basic email validation
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                resultSpan.html('<span style="color: #dc3232;">Please enter a valid email address</span>');
                return;
            }
            
            button.prop('disabled', true).text('Sending...');
            resultSpan.html('<span style="color: #666;">Sending test report...</span>');
            
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_send_test_report',
                    test_email: email,
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultSpan.html('<span style="color: #00a32a;">✓ Test report sent successfully!</span>');
                    } else {
                        resultSpan.html('<span style="color: #dc3232;">✗ ' + (response.data || 'Failed to send test report') + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    resultSpan.html('<span style="color: #dc3232;">✗ Error sending test report</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Send Test Report');
                }
            });
        },

        // Preview weekly report
        previewReport: function(e) {
            e.preventDefault();
            
            var button = $('#wsa-preview-report');
            button.prop('disabled', true).text('Generating Preview...');
            
            // Open preview in new window
            var previewUrl = wsa_ajax.ajax_url + '?action=wsa_preview_weekly_report&nonce=' + wsa_ajax.nonce;
            var previewWindow = window.open(previewUrl, 'wsa_preview', 'width=800,height=600,scrollbars=yes,resizable=yes');
            
            if (!previewWindow) {
            }
            
            button.prop('disabled', false).text('Preview Report');
        },

        // Initialize tab functionality
        initTabs: function() {
            $(document).on('click', '.wsa-tab-nav .nav-tab', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).data('tab');
                var $tabNav = $(this).closest('.wsa-tab-nav');
                var $tabContent = $tabNav.siblings('.wsa-tab-content');
                
                // Update tab navigation
                $tabNav.find('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update tab panels
                $tabContent.find('.wsa-tab-panel').removeClass('wsa-tab-panel-active');
                $tabContent.find('#wsa-' + targetTab).addClass('wsa-tab-panel-active');
                
                // Store active tab in localStorage for persistence
                localStorage.setItem('wsa_active_tab', targetTab);
                
                // Trigger custom event for Pro plugin integration
                $(document).trigger('wsa:tabChanged', [targetTab]);
            });
            
            // Restore last active tab from localStorage
            var savedTab = localStorage.getItem('wsa_active_tab');
            if (savedTab) {
                var $targetTab = $('.wsa-tab-nav .nav-tab[data-tab="' + savedTab + '"]');
                if ($targetTab.length) {
                    $targetTab.trigger('click');
                }
            }
        },

        // Handle keyboard navigation for tabs
        handleTabKeyboard: function(e) {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                var $currentTab = $('.wsa-tab-nav .nav-tab.nav-tab-active');
                var $allTabs = $('.wsa-tab-nav .nav-tab');
                var currentIndex = $allTabs.index($currentTab);
                var newIndex;
                
                if (e.key === 'ArrowRight') {
                    newIndex = (currentIndex + 1) % $allTabs.length;
                } else {
                    newIndex = (currentIndex - 1 + $allTabs.length) % $allTabs.length;
                }
                
                $allTabs.eq(newIndex).focus().trigger('click');
                e.preventDefault();
            }
        },

        // Utility function to format numbers
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        // Switch to specific tab
        switchToTab: function(tabName) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.wsa-tab-panel').removeClass('wsa-tab-panel-active');
            
            $('[data-tab="' + tabName + '"]').addClass('nav-tab-active');
            $('#wsa-' + tabName).addClass('wsa-tab-panel-active');
        },

        // Start polling for scan progress
        startProgressPolling: function(scanId, onComplete) {
            var pollCount = 0;
            var maxPolls = 60; // Maximum 60 polls (60 seconds)
            
            window.wsaProgressInterval = setInterval(function() {
                pollCount++;
                
                if (pollCount > maxPolls) {
                    clearInterval(window.wsaProgressInterval);
                    window.wsaProgressInterval = null;
                    $('#wsa-loading').hide();
                    $('#wsa-system-scan').prop('disabled', false);
                    WSAAdmin.showError('Scan progress timeout. Please check results manually.');
                    return;
                }
                
                $.ajax({
                    url: wsa_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wsa_get_scan_progress',
                        scan_id: scanId,
                        nonce: wsa_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var progress = response.data;
                            $('#wsa-loading-text').text(progress.status + ' (' + progress.progress + '%)');
                            
                            // If scan is complete, stop polling and show results
                            if (progress.progress >= 100) {
                                clearInterval(window.wsaProgressInterval);
                                window.wsaProgressInterval = null;
                                $('#wsa-loading').hide();
                                $('#wsa-system-scan').prop('disabled', false);
                                
                                // Get the final scan results and show modal
                                setTimeout(function() {
                                    var systemScanResults = WSAAdmin.getStoredSystemScanResults();
                                    if (systemScanResults) {
                                        WSAAdmin.showSystemScanModal(systemScanResults);
                                        if (onComplete) onComplete(systemScanResults);
                                    }
                                }, 500);
                            }
                        }
                    },
                    error: function() {
                        // Continue polling on individual request errors
                    }
                });
            }, 1000); // Poll every second
        },

        // Get stored system scan results
        getStoredSystemScanResults: function() {
            // Try to get from localStorage first
            var stored = localStorage.getItem('wsa_last_system_scan_results');
            if (stored) {
                return JSON.parse(stored);
            }
            
            // Return mock data for testing if no real data exists
            return {
                security_checks: {
                    security_score: 85,
                    issues_found: 2,
                    checks: {
                        ssl_enabled: {
                            status: 'good',
                            message: 'SSL/HTTPS is properly configured'
                        },
                        file_permissions: {
                            status: 'warning',
                            message: 'Some files have overly permissive permissions'
                        },
                        wp_core_updated: {
                            status: 'good',
                            message: 'WordPress core is up to date'
                        }
                    }
                },
                performance_checks: {
                    issues_found: 1,
                    checks: {
                        memory_limit: {
                            status: 'good',
                            message: 'PHP memory limit is adequate (512M)'
                        },
                        object_cache: {
                            status: 'warning',
                            message: 'Object caching is not enabled'
                        }
                    }
                }
            };
        },

        // Show system scan results in modal
        showSystemScanModal: function(data) {
            var modal = $('#wsa-system-scan-modal');
            var modalContent = $('#wsa-modal-system-scan-content');
            
            if (!modal.length) {
                // Fallback to old behavior
                this.handleSystemScanSuccess(data);
                return;
            }
            
            var html = this.buildSystemScanHTML(data);
            modalContent.html(html);
            
            // Show modal using the same method as performance modal
            modal.addClass('wsa-modal-open');
            $('body').css('overflow', 'hidden');
            
            // Store results for persistence
            localStorage.setItem('wsa_last_system_scan_results', JSON.stringify(data));
        },

        // Build system scan results HTML
        buildSystemScanHTML: function(data) {
            var html = '<div class="wsa-system-scan-results">';
            
            // Security Overview
            if (data.security_checks) {
                var security = data.security_checks;
                html += '<div class="wsa-scan-section">';
                html += '<h4><span class="dashicons dashicons-shield-alt"></span> Security Analysis</h4>';
                html += '<div class="wsa-security-summary">';
                html += '<div class="wsa-security-score wsa-score-' + (security.security_score >= 80 ? 'good' : security.security_score >= 60 ? 'medium' : 'poor') + '">';
                html += '<span class="score">' + security.security_score + '%</span>';
                html += '<span class="label">Security Score</span>';
                html += '</div>';
                html += '<div class="wsa-security-stats">';
                html += '<div class="stat"><strong>' + security.issues_found + '</strong><br>Issues Found</div>';
                html += '<div class="stat"><strong>' + Object.keys(security.checks).length + '</strong><br>Checks Performed</div>';
                html += '</div>';
                html += '</div>';
                
                // Security checks details
                if (security.checks) {
                    html += '<div class="wsa-security-checks">';
                    Object.keys(security.checks).forEach(function(checkName) {
                        var check = security.checks[checkName];
                        var statusClass = check.status === 'good' ? 'success' : 'warning';
                        html += '<div class="wsa-check-item wsa-' + statusClass + '">';
                        html += '<span class="dashicons dashicons-' + (check.status === 'good' ? 'yes-alt' : 'warning') + '"></span>';
                        html += '<div class="wsa-check-details">';
                        html += '<strong>' + WSAAdmin.formatCheckName(checkName) + '</strong>';
                        html += '<p>' + check.message + '</p>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
                html += '</div>';
            }
            
            // Performance Overview
            if (data.performance_checks) {
                var performance = data.performance_checks;
                html += '<div class="wsa-scan-section">';
                html += '<h4><span class="dashicons dashicons-performance"></span> Performance Analysis</h4>';
                html += '<div class="wsa-performance-summary">';
                html += '<div class="wsa-performance-stats">';
                html += '<div class="stat"><strong>' + performance.issues_found + '</strong><br>Issues Found</div>';
                html += '<div class="stat"><strong>' + Object.keys(performance.checks).length + '</strong><br>Checks Performed</div>';
                html += '</div>';
                html += '</div>';
                
                // Performance checks details
                if (performance.checks) {
                    html += '<div class="wsa-performance-checks">';
                    Object.keys(performance.checks).forEach(function(checkName) {
                        var check = performance.checks[checkName];
                        var statusClass = check.status === 'good' ? 'success' : check.status === 'warning' ? 'warning' : 'info';
                        html += '<div class="wsa-check-item wsa-' + statusClass + '">';
                        html += '<span class="dashicons dashicons-' + (check.status === 'good' ? 'yes-alt' : check.status === 'warning' ? 'warning' : 'info') + '"></span>';
                        html += '<div class="wsa-check-details">';
                        html += '<strong>' + WSAAdmin.formatCheckName(checkName) + '</strong>';
                        html += '<p>' + check.message + '</p>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
                html += '</div>';
            }
            
            html += '</div>';
            return html;
        },

        // Format check names for display
        formatCheckName: function(checkName) {
            return checkName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        // Close system scan modal
        closeSystemScanModal: function(e) {
            e.preventDefault();
            var modal = $('#wsa-system-scan-modal');
            modal.removeClass('wsa-modal-open');
            $('body').css('overflow', ''); // Restore scrolling
        },

        // Show site scan results in modal (or fallback to inline display)
        showSiteScanModal: function(data) {
            var modal = $('#wsa-site-scan-modal');
            var modalContent = $('#wsa-modal-site-scan-content');
            
            if (!modal.length) {
                // Pro plugin not active - show results inline with enhanced display
                this.showScanResultsInline(data);
                return;
            }
            
            var html = this.buildSiteScanHTML(data);
            modalContent.html(html);
            
            // Show modal
            modal.addClass('wsa-modal-open');
            $('body').css('overflow', 'hidden');
            
            // Store results for persistence
            localStorage.setItem('wsa_last_site_scan_results', JSON.stringify(data));
        },

        // Show scan results inline (fallback for free version)
        showScanResultsInline: function(data) {
            // Update the dashboard with new data and show a completion notice
            this.updateDashboardData(data);
            
            // Show a success notification
            this.showScanSuccessNotice();
            
            // Store results for persistence
            localStorage.setItem('wsa_last_site_scan_results', JSON.stringify(data));
        },

        // Update dashboard with fresh scan data
        updateDashboardData: function(data) {
            // Reload to pick up new results
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        },

        // Show scan success notice
        showScanSuccessNotice: function() {
            // Remove any existing notices
            $('.wsa-scan-success-notice').remove();
            
            var notice = $('<div class="notice notice-success wsa-scan-success-notice is-dismissible">' +
                '<p><strong>WP SiteAdvisor:</strong> Site scan completed successfully! ' +
                'Page will refresh to show updated results...</p>' +
                '</div>');
            
            $('.wsa-dashboard').before(notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                notice.fadeOut();
            }, 3000);
        },

        // Build site scan results HTML
        buildSiteScanHTML: function(data) {
            
            var html = '<div class="wsa-site-scan-results">';
            
            // Overview Cards - using correct data structure
            var pluginsCount = data.plugins ? data.plugins.length : 0;
            var themesCount = data.theme_analysis ? 1 : 0; // Theme analysis means we have a theme
            // Count detected integrations
            var integrationsCount = 0;
            if (data.google_integrations) {
                Object.keys(data.google_integrations).forEach(function(key) {
                    if (data.google_integrations[key].detected) {
                        integrationsCount++;
                    }
                });
            }
            var issuesCount = 0;
            
            // Count issues from plugins
            if (data.plugins) {
                data.plugins.forEach(function(plugin) {
                    if (plugin.needs_update || (plugin.vulnerabilities && plugin.vulnerabilities.length > 0)) {
                        issuesCount++;
                    }
                });
            }
            
            // Count issues from theme
            if (data.theme_analysis && data.theme_analysis.needs_update) {
                issuesCount++;
            }
            
            html += '<div class="wsa-scan-overview">';
            html += '<div class="wsa-scan-summary-card wsa-plugins-card">';
            html += '<h4><span class="dashicons dashicons-admin-plugins"></span> Plugins</h4>';
            html += '<div class="wsa-count">' + pluginsCount + '</div>';
            html += '<div class="wsa-label">Active Plugins</div>';
            html += '</div>';
            
            html += '<div class="wsa-scan-summary-card wsa-theme-card">';
            html += '<h4><span class="dashicons dashicons-admin-appearance"></span> Theme</h4>';
            html += '<div class="wsa-count">' + themesCount + '</div>';
            html += '<div class="wsa-label">Active Theme</div>';
            html += '</div>';
            
            html += '<div class="wsa-scan-summary-card wsa-integrations-card">';
            html += '<h4><span class="dashicons dashicons-admin-tools"></span> Integrations</h4>';
            html += '<div class="wsa-count">' + integrationsCount + '</div>';
            html += '<div class="wsa-label">Detected</div>';
            html += '</div>';
            
            html += '<div class="wsa-scan-summary-card wsa-issues-card">';
            html += '<h4><span class="dashicons dashicons-warning"></span> Issues</h4>';
            html += '<div class="wsa-count">' + issuesCount + '</div>';
            html += '<div class="wsa-label">Found</div>';
            html += '</div>';
            html += '</div>';
            
            // Detailed Results
            html += '<div class="wsa-scan-detailed-results">';
            
            // Plugins Section
            if (data.plugins && data.plugins.length > 0) {
                html += '<div class="wsa-result-section">';
                html += '<h5><span class="dashicons dashicons-admin-plugins"></span> Plugin Analysis</h5>';
                html += '<div class="wsa-plugins-list">';
                
                data.plugins.forEach(function(plugin) {
                    var statusClass = 'wsa-secure';
                    var iconClass = 'wsa-secure';
                    var iconName = 'yes-alt';
                    
                    if (plugin.vulnerabilities && plugin.vulnerabilities.length > 0) {
                        statusClass = 'wsa-has-issues';
                        iconClass = 'wsa-error';
                        iconName = 'dismiss';
                    } else if (plugin.needs_update) {
                        statusClass = 'wsa-needs-update';
                        iconClass = 'wsa-warning';
                        iconName = 'warning';
                    }
                    
                    html += '<div class="wsa-plugin-item ' + statusClass + '">';
                    html += '<span class="wsa-item-icon ' + iconClass + ' dashicons dashicons-' + iconName + '"></span>';
                    html += '<div class="wsa-item-details">';
                    html += '<strong>' + (plugin.name || plugin.plugin) + '</strong>';
                    html += '<div class="wsa-description">' + (plugin.description || 'WordPress Plugin') + '</div>';
                    html += '<div class="wsa-item-meta">';
                    html += '<span>Version: ' + (plugin.version || 'Unknown') + '</span>';
                    if (plugin.author) html += '<span>Author: ' + plugin.author + '</span>';
                    html += '</div>';
                    
                    if (plugin.needs_update) {
                        html += '<span class="wsa-badge wsa-update">Update Available</span>';
                    }
                    if (plugin.vulnerabilities && plugin.vulnerabilities.length > 0) {
                        html += '<span class="wsa-badge wsa-vulnerability">' + plugin.vulnerabilities.length + ' Vulnerabilities</span>';
                    }
                    if (!plugin.needs_update && (!plugin.vulnerabilities || plugin.vulnerabilities.length === 0)) {
                        html += '<span class="wsa-badge wsa-secure">Secure</span>';
                    }
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
                html += '</div>';
            }
            
            // Theme Section
            if (data.theme_analysis) {
                var theme = data.theme_analysis;
                html += '<div class="wsa-result-section">';
                html += '<h5><span class="dashicons dashicons-admin-appearance"></span> Theme Analysis</h5>';
                html += '<div class="wsa-theme-info">';
                
                var themeStatusClass = 'wsa-secure';
                var themeIconClass = 'wsa-secure';
                var themeIconName = 'yes-alt';
                
                if (theme.needs_update) {
                    themeStatusClass = 'wsa-needs-update';
                    themeIconClass = 'wsa-warning';
                    themeIconName = 'warning';
                }
                
                html += '<div class="wsa-plugin-item ' + themeStatusClass + '">';
                html += '<span class="wsa-item-icon ' + themeIconClass + ' dashicons dashicons-' + themeIconName + '"></span>';
                html += '<div class="wsa-item-details">';
                html += '<strong>' + (theme.name || 'Active Theme') + '</strong>';
                html += '<div class="wsa-description">' + (theme.description || 'Currently active WordPress theme') + '</div>';
                html += '<div class="wsa-item-meta">';
                html += '<span>Version: ' + (theme.version || 'Unknown') + '</span>';
                if (theme.author) html += '<span>Author: ' + theme.author + '</span>';
                if (theme.template) html += '<span>Template: ' + theme.template + '</span>';
                html += '</div>';
                
                if (theme.needs_update) {
                    html += '<span class="wsa-badge wsa-update">Update Available</span>';
                } else {
                    html += '<span class="wsa-badge wsa-secure">Up to Date</span>';
                }
                
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            // Google Integrations Section
            if (data.google_integrations && Object.keys(data.google_integrations).length > 0) {
                html += '<div class="wsa-result-section">';
                html += '<h5><span class="dashicons dashicons-admin-tools"></span> Google Integrations</h5>';
                html += '<div class="wsa-integrations-list">';
                
                // Define service names and descriptions
                var serviceDetails = {
                    'analytics': {name: 'Google Analytics', description: 'Web analytics and reporting'},
                    'tag_manager': {name: 'Google Tag Manager', description: 'Tag management system'},
                    'search_console': {name: 'Google Search Console', description: 'SEO and search performance'},
                    'adsense': {name: 'Google AdSense', description: 'Advertisement platform'},
                    'fonts': {name: 'Google Fonts', description: 'Web font service'},
                    'maps': {name: 'Google Maps', description: 'Mapping and location services'},
                    'recaptcha': {name: 'Google reCAPTCHA', description: 'Security and spam protection'},
                    'youtube': {name: 'YouTube', description: 'Video embedding and content'}
                };
                
                // Iterate through all possible services
                Object.keys(serviceDetails).forEach(function(serviceKey) {
                    var service = serviceDetails[serviceKey];
                    var isDetected = data.google_integrations[serviceKey] === true;
                    var serviceId = data.google_integrations[serviceKey + '_id'] || '';
                    var statusClass = isDetected ? 'wsa-detected' : 'wsa-missing';
                    var iconClass = isDetected ? 'wsa-secure' : 'wsa-info';
                    var iconName = isDetected ? 'yes-alt' : 'minus';
                    
                    html += '<div class="wsa-integration-item ' + statusClass + '">';
                    html += '<span class="wsa-item-icon ' + iconClass + ' dashicons dashicons-' + iconName + '"></span>';
                    html += '<div class="wsa-item-content">';
                    html += '<div class="wsa-item-details">';
                    html += '<strong>' + service.name + '</strong>';
                    html += '<div class="wsa-description">' + service.description + '</div>';
                    
                    if (isDetected) {
                        html += '<div class="wsa-item-meta">';
                        html += '<span class="wsa-status-detected">✓ Detected</span>';
                        if (serviceId) {
                            html += '<span>ID: ' + serviceId + '</span>';
                        }
                        // Add detection method if available
                        if (data.google_integrations.detection_methods) {
                            var method = data.google_integrations.detection_methods.find(function(m) {
                                return m.toLowerCase().includes(service.name.toLowerCase());
                            });
                            if (method) {
                                html += '<span>Method: ' + method.split(': ')[1] + '</span>';
                            }
                        }
                        html += '</div>';
                    } else {
                        html += '<div class="wsa-item-meta">';
                        html += '<span class="wsa-status-missing">Not Found</span>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    
                    // Add View Details button
                    html += '<div class="wsa-item-actions">';
                    if (isDetected) {
                        html += '<button type="button" class="button button-small wsa-view-details-btn" data-service="' + serviceKey + '" data-integration=\'' + JSON.stringify({
                            name: service.name,
                            detected: true,
                            id: serviceId,
                            description: service.description
                        }) + '\'>';
                        html += '<span class="dashicons dashicons-visibility"></span> View Details';
                        html += '</button>';
                    } else {
                        html += '<button type="button" class="button button-small wsa-setup-guide-btn" data-service="' + serviceKey + '" data-service-name="' + service.name + '">';
                        html += '<span class="dashicons dashicons-admin-tools"></span> Setup Guide';
                        html += '</button>';
                    }
                    html += '</div>';
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },

        // Close site scan modal
        closeSiteScanModal: function(e) {
            e.preventDefault();
            var modal = $('#wsa-site-scan-modal');
            modal.removeClass('wsa-modal-open');
            $('body').css('overflow', ''); // Restore scrolling
        },

        // Run new system scan from modal
        runNewSystemScan: function(e) {
            e.preventDefault();
            $('#wsa-system-scan-modal').removeClass('wsa-modal-open');
            $('body').css('overflow', '');
            WSAAdmin.systemScan(e);
        },

        // Run new site scan from modal
        runNewSiteScan: function(e) {
            e.preventDefault();
            $('#wsa-site-scan-modal').removeClass('wsa-modal-open');
            $('body').css('overflow', '');
            WSAAdmin.scanSite(e);
        },

        // Debug function to test modal (call from browser console: WSAAdmin.testModal())
        testModal: function() {
            var testData = this.getStoredSystemScanResults();
            this.showSystemScanModal(testData);
        },

        // ===============================
        // AI FEATURES METHODS
        // ===============================

        /**
         * Handle AI feature button actions
         */
        handleAIFeatureAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var feature = $button.data('feature');
            
            // Debug logging
            
            if (!feature) {
                return;
            }
            
            if (!window.wsa_ajax) {
                return;
            }
            
            // Store original button text
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Running...');
            
            // Make AJAX request
            
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_run_ai_analysis',
                    feature: feature,
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WSAAdmin.showAIFeatureSuccess($button, response.data);
                    } else {
                        WSAAdmin.showAIFeatureError($button, response.data || 'Unknown error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    WSAAdmin.showAIFeatureError($button, 'AJAX Error: ' + error);
                },
                complete: function() {
                    // Re-enable button after 3 seconds
                    setTimeout(function() {
                        WSAAdmin.resetAIFeatureButton($button);
                    }, 3000);
                }
            });
        },

        /**
         * Show AI feature success result
         */
        showAIFeatureSuccess: function($button, data) {
            $button.removeClass('button-primary').addClass('button-secondary')
                   .text('✓ Complete');
            
            // Special handling for test button
            if ($button.attr('id') === 'test-ajax') {
                $('#test-results').html('<div style="color: green; font-weight: bold;">✓ ' + JSON.stringify(data) + '</div>');
            } else {
                // Show success notification
                this.showAINotification('Analysis completed successfully!', 'success');
                
                // Display the results
                this.displayAIResults($button, data);
                
                // Show the View Results button for this feature
                var feature = $button.data('feature');
                $('.wsa-view-results-btn[data-feature="' + feature + '"]').show();
                
                // Refresh AI status indicators
                this.refreshAIStatuses();
            }
        },

        /**
         * Show AI feature error result
         */
        showAIFeatureError: function($button, message) {
            $button.removeClass('button-primary').addClass('button-secondary')
                   .text('✗ Error');
            
            // Special handling for test button
            if ($button.attr('id') === 'test-ajax') {
                $('#test-results').html('<div style="color: red; font-weight: bold;">✗ Error: ' + message + '</div>');
            } else {
                // Show error notification
                this.showAINotification(message, 'error');
            }
        },

        /**
         * Reset AI feature button to original state
         */
        resetAIFeatureButton: function($button) {
            var originalText = $button.data('original-text') || 'Run Analysis';
            
            $button.prop('disabled', false)
                   .removeClass('button-secondary')
                   .addClass('button-primary')
                   .text(originalText);
        },

        /**
         * Show AI notification message
         */
        showAINotification: function(message, type) {
            var $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Find the dashboard header
            var $header = $('.wsa-dashboard h1');
            if ($header.length) {
                $header.after($notification);
            } else {
                // Fallback to showing after wrap
                $('.wrap h1').after($notification);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Refresh AI status indicators
         */
        refreshAIStatuses: function() {
            // Update status indicators
            $.ajax({
                url: wsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_ai_status',
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        WSAAdmin.updateAIStatuses(response.data);
                    }
                },
                error: function() {
                }
            });
        },

        /**
         * Update AI status indicators with new data
         */
        updateAIStatuses: function(data) {
            // Update each status indicator
            Object.keys(data.statuses || {}).forEach(function(feature) {
                var $indicator = $('#' + feature + '-status');
                if ($indicator.length) {
                    $indicator.html(data.statuses[feature]);
                }
            });
            
            // Update quick stats
            Object.keys(data.stats || {}).forEach(function(stat) {
                var $stat = $('#' + stat);
                if ($stat.length) {
                    $stat.text(data.stats[stat]);
                }
            });
        },

        /**
         * Display AI analysis results in modal
         */
        displayAIResults: function($button, data) {
            var feature = $button.data('feature');
            var featureName = this.getFeatureName(feature);
            
            // Build the results HTML
            var resultsHTML = '<div class="wsa-results-header">';
            if (data.timestamp) {
                resultsHTML += '<span class="results-timestamp">Last updated: ' + data.timestamp + '</span>';
            }
            resultsHTML += '</div>';
            
            if (data.html) {
                resultsHTML += data.html;
            } else if (data.raw_data) {
                // Fallback: show raw data in a more readable format
                resultsHTML += '<div class="wsa-raw-results">';
                if (Array.isArray(data.raw_data)) {
                    data.raw_data.forEach(function(item, index) {
                        resultsHTML += '<div class="result-item">';
                        if (item.title) {
                            resultsHTML += '<h5>' + item.title + '</h5>';
                        }
                        if (item.description) {
                            resultsHTML += '<p>' + item.description + '</p>';
                        }
                        resultsHTML += '</div>';
                    });
                } else {
                    resultsHTML += '<pre>' + JSON.stringify(data.raw_data, null, 2) + '</pre>';
                }
                resultsHTML += '</div>';
            }
            
            // Update modal content and show
            this.showModal(featureName + ' Results', resultsHTML);
        },

        /**
         * Get friendly feature name
         */
        getFeatureName: function(feature) {
            var names = {
                'optimizer': 'Site Optimizer',
                'content': 'Content Analyzer', 
                'analytics': 'Predictive Analytics',
                'pagespeed': 'PageSpeed Analysis',
                'reports': 'White Label Reports'
            };
            return names[feature] || feature;
        },

        /**
         * Show modal with content
         */
        showModal: function(title, content) {
            $('#wsa-modal-title').text(title);
            $('#wsa-modal-results-container').html(content);
            $('#wsa-ai-results-modal').fadeIn(300);
            $('#wsa-modal-backdrop').fadeIn(300);
            $('body').addClass('wsa-modal-open');
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            $('#wsa-ai-results-modal').fadeOut(300);
            $('#wsa-modal-backdrop').fadeOut(300);
            $('body').removeClass('wsa-modal-open');
        },

        /**
         * View existing results for a feature
         */
        viewAIResults: function(feature) {
            var self = this;
            var featureName = this.getFeatureName(feature);
            
            // Show loading in modal
            this.showModal(featureName + ' Results', '<div class="wsa-loading">Loading results...</div>');
            
            // Get stored results via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_get_ai_results',
                    feature: feature,
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.displayAIResults($('[data-feature="' + feature + '"]'), response.data);
                    } else {
                        $('#wsa-modal-results-container').html('<div class="wsa-no-results">No results found for this feature.</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading results:', error);
                    $('#wsa-modal-results-container').html('<div class="wsa-error">Error loading results. Please try again.</div>');
                }
            });
        },

        /**
         * Handle view results button click
         */
        handleViewResults: function(e) {
            e.preventDefault();
            var feature = $(e.target).data('feature');
            this.viewAIResults(feature);
        },

        /**
         * Handle create test results button click
         */
        handleCreateTestResults: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            
            $button.prop('disabled', true).text('Creating Test Results...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_create_test_results',
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#test-results').html('<div style="color: green; font-weight: bold;">✓ ' + response.data + '</div>');
                        
                        // Show all View Results buttons
                        $('.wsa-view-results-btn').show();
                        
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#test-results').html('<div style="color: red; font-weight: bold;">✗ Error: ' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#test-results').html('<div style="color: red; font-weight: bold;">✗ AJAX Error: ' + error + '</div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Create Test Results');
                }
            });
        },

        /**
         * Initialize View Results buttons on page load
         */
        initViewResultsButtons: function() {
            
            var self = this;
            // Check each AI feature for existing results
            var features = ['optimizer', 'content', 'analytics', 'pagespeed', 'reports'];
            
            
            features.forEach(function(feature) {
                var $button = $('.wsa-view-results-btn[data-feature="' + feature + '"]');
                self.checkFeatureResults(feature);
            });
        },

        /**
         * Check if a feature has results and show button accordingly
         */
        checkFeatureResults: function(feature) {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_check_ai_results',
                    feature: feature,
                    nonce: wsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_results) {
                        $('.wsa-view-results-btn[data-feature="' + feature + '"]').show();
                    } else {
                        $('.wsa-view-results-btn[data-feature="' + feature + '"]').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking results for feature:', feature, error);
                }
            });
        }
    };

    // Make WSAAdmin available globally
    window.WSAAdmin = WSAAdmin;

})(jQuery);
