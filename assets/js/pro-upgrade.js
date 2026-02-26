/**
 * WP Site Advisory Pro Upgrade JavaScript
 * 
 * Handles Pro upgrade interactions,                             <h3>🤖 Activate AI Automation</h3>
                            <p>Unlock cutting-edge AI features that automatically optimize your WordPress site with zero manual work.</p>
                            <div class="wsa-feature-benefit">
                                <span class="dashicons dashicons-yes-alt"></span>
                                AI Optimizer, Content Analyzer, Site Detective, and more
                            </div>
                        </div>
                        <div class="wsa-modal-footer">
                            <a href="${this.getUpgradeUrl(feature)}" class="button button-primary button-hero" target="_blank">
                                Activate AI Features Now
                            </a> feature tracking
 */

(function($) {
    'use strict';

    /**
     * Pro Upgrade Handler
     */
    window.WSAProUpgrade = {
        
        // Initialize upgrade functionality
        init: function() {
            this.bindEvents();
            this.trackFeatureAttempts();
            this.handleProNotices();
        },

        // Bind event handlers
        bindEvents: function() {
            // Modal functionality
            $(document).on('click', '.wsa-pro-feature-trigger', this.showUpgradeModal);
            $(document).on('click', '.wsa-modal-close', this.hideUpgradeModal);
            $(document).on('click', '.wsa-upgrade-modal', function(e) {
                if (e.target === this) {
                    WSAProUpgrade.hideUpgradeModal();
                }
            });

            // Upgrade button clicks
            $(document).on('click', '.wsa-upgrade-btn', this.trackUpgradeClick);
            $(document).on('click', '.wsa-upgrade-link', this.trackUpgradeClick);

            // Pro feature attempt tracking
            $(document).on('click', '.wsa-feature-disabled', this.handleDisabledFeatureClick);
            
            // Dismiss Pro notices
            $(document).on('click', '.wsa-pro-notice .notice-dismiss', this.dismissProNotice);

            // ESC key to close modal
            $(document).keyup(function(e) {
                if (e.keyCode === 27) { // ESC key
                    WSAProUpgrade.hideUpgradeModal();
                }
            });
        },

        // Show upgrade modal
        showUpgradeModal: function(e) {
            e.preventDefault();
            
            var feature = $(this).data('feature') || 'general';
            var modalId = '#wsa-upgrade-modal-' + feature;
            
            // If specific modal doesn't exist, create a generic one
            if ($(modalId).length === 0) {
                WSAProUpgrade.createGenericModal(feature);
                modalId = '#wsa-upgrade-modal-generic';
            }
            
            $(modalId).fadeIn(300);
            $('body').addClass('wsa-modal-open');
            
            // Track modal view
            WSAProUpgrade.trackEvent('modal_view', feature);
        },

        // Hide upgrade modal
        hideUpgradeModal: function() {
            $('.wsa-upgrade-modal').fadeOut(300);
            $('body').removeClass('wsa-modal-open');
        },

        // Create generic modal for features without specific modals
        createGenericModal: function(feature) {
            var featureName = feature.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            var modalHtml = `
                <div class="wsa-upgrade-modal" id="wsa-upgrade-modal-generic">
                    <div class="wsa-modal-content">
                        <div class="wsa-modal-header">
                            <span class="wsa-pro-badge-large">🔒 PRO FEATURE</span>
                            <button class="wsa-modal-close">&times;</button>
                        </div>
                        <div class="wsa-modal-body">
                            <h3>${featureName} is a Pro Feature</h3>
                            <p>Unlock advanced capabilities and take your site analysis to the next level with WP SiteAdvisor Pro.</p>
                            <div class="wsa-feature-benefit">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Get instant access to all Pro features with no setup required
                            </div>
                        </div>
                        <div class="wsa-modal-footer">
                            <a href="${this.getUpgradeUrl(feature)}" class="button button-primary button-hero" target="_blank">
                                Upgrade to Pro Now
                            </a>
                            <button class="button wsa-modal-close">Maybe Later</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing generic modal
            $('#wsa-upgrade-modal-generic').remove();
            
            // Append new modal
            $('body').append(modalHtml);
        },

        // Handle clicks on disabled features
        handleDisabledFeatureClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var feature = $(this).data('feature') || $(this).closest('[data-feature]').data('feature') || 'general';
            
            // Show upgrade modal or create one
            WSAProUpgrade.showUpgradeModal.call($('<div>').data('feature', feature)[0]);
            
            // Track the attempt
            WSAProUpgrade.trackFeatureAttempt(feature);
        },

        // Track feature attempt via AJAX
        trackFeatureAttempt: function(feature) {
            $.post(ajaxurl, {
                action: 'wsa_track_pro_attempt',
                feature: feature,
                nonce: wsa_admin_ajax.nonce
            });
            
            // Also track locally
            this.trackEvent('feature_attempt', feature);
        },

        // Track upgrade button clicks
        trackUpgradeClick: function(e) {
            var feature = $(this).data('feature') || 
                         $(this).closest('[data-feature]').data('feature') || 
                         WSAProUpgrade.getFeatureFromUrl(this.href) || 
                         'general';
            
            WSAProUpgrade.trackEvent('upgrade_click', feature);
            
            // Let the link proceed normally
            return true;
        },

        // Extract feature from URL
        getFeatureFromUrl: function(url) {
            var match = url.match(/[?&]feature=([^&]+)/);
            return match ? match[1] : null;
        },

        // Get upgrade URL for feature
        getUpgradeUrl: function(feature) {
            var baseUrl = 'https://wpsiteadvisor.com/upgrade';
            var params = new URLSearchParams({
                feature: feature || 'general',
                utm_source: 'plugin',
                utm_medium: 'upgrade_modal',
                utm_campaign: 'wp_siteadvisor_free'
            });
            
            return baseUrl + '?' + params.toString();
        },

        // Track events (could be extended to send to analytics)
        trackEvent: function(action, feature) {
            // Store locally for debugging
            var events = JSON.parse(localStorage.getItem('wsa_pro_events') || '[]');
            events.push({
                action: action,
                feature: feature,
                timestamp: Date.now(),
                page: window.location.pathname
            });
            
            // Keep only last 50 events
            if (events.length > 50) {
                events = events.slice(-50);
            }
            
            localStorage.setItem('wsa_pro_events', JSON.stringify(events));
            
            // Could send to analytics service here
        },

        // Handle feature attempt tracking from server
        trackFeatureAttempts: function() {
            // Look for attempted features in URL or data attributes
            var urlParams = new URLSearchParams(window.location.search);
            var attemptedFeature = urlParams.get('attempted_feature');
            
            if (attemptedFeature) {
                setTimeout(function() {
                    WSAProUpgrade.showUpgradeModal.call($('<div>').data('feature', attemptedFeature)[0]);
                }, 1000);
            }
        },

        // Handle Pro notice dismissal
        dismissProNotice: function() {
            var feature = $(this).closest('.wsa-pro-notice').data('feature');
            
            if (feature) {
                // Track dismissal
                WSAProUpgrade.trackEvent('notice_dismiss', feature);
                
                // Send to server to prevent showing again soon
                $.post(ajaxurl, {
                    action: 'wsa_dismiss_pro_notice',
                    feature: feature,
                    nonce: wsa_admin_ajax.nonce
                });
            }
        },

        // Handle Pro notice display
        handleProNotices: function() {
            // Auto-dismiss notices after 30 seconds
            setTimeout(function() {
                $('.wsa-pro-notice').fadeOut();
            }, 30000);
        },

        // Show Pro features on dashboard
        showProFeatures: function() {
            $('.wsa-pro-showcase').slideDown();
        },

        // Hide Pro features
        hideProFeatures: function() {
            $('.wsa-pro-showcase').slideUp();
        },

        // Add Pro overlay to feature
        addProOverlay: function(selector, feature) {
            $(selector).addClass('wsa-feature-disabled').attr('data-feature', feature);
        },

        // Remove Pro overlay from feature
        removeProOverlay: function(selector) {
            $(selector).removeClass('wsa-feature-disabled').removeAttr('data-feature');
        },

        // Check if Pro is active (could be set by server)
        isProActive: function() {
            return window.wsa_pro_active === true;
        },

        // Display Pro required message
        showProRequiredMessage: function(feature, container) {
            var message = `
                <div class="wsa-pro-required-message">
                    <div class="wsa-pro-icon">🤖</div>
                    <div class="wsa-pro-text">
                        <h4>AI Feature Locked</h4>
                        <p>Unlock this AI-powered feature and transform your WordPress management with intelligent automation.</p>
                        <a href="${this.getUpgradeUrl(feature)}" class="button button-primary" target="_blank">
                            Activate AI Features
                        </a>
                    </div>
                </div>
            `;
            
            $(container).html(message);
        },

        // Animate upgrade buttons
        animateUpgradeButtons: function() {
            $('.wsa-upgrade-btn').each(function(index) {
                $(this).delay(index * 100).queue(function() {
                    $(this).addClass('wsa-animate-in').dequeue();
                });
            });
        }
    };

    /**
     * Pro Feature Integration Helper
     */
    window.WSAProFeatures = {
        
        // Initialize Pro feature checks
        init: function() {
            this.checkAIAnalysis();
            this.checkVulnerabilityScanning();
            this.checkAdvancedReporting();
            this.checkWhiteLabel();
        },

        // Check AI Analysis feature
        checkAIAnalysis: function() {
            if (!WSAProUpgrade.isProActive()) {
                // Disable AI analysis buttons
                $('.wsa-ai-analysis-btn').each(function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true)
                        .text('🔒 AI Analysis (Pro)')
                        .addClass('wsa-pro-feature-trigger')
                        .attr('data-feature', 'ai_analysis');
                });

                // Add overlay to AI analysis sections
                WSAProUpgrade.addProOverlay('.wsa-ai-analysis-section', 'ai_analysis');
            }
        },

        // Check Vulnerability Scanning feature
        checkVulnerabilityScanning: function() {
            if (!WSAProUpgrade.isProActive()) {
                // Disable advanced vulnerability scans
                $('.wsa-vulnerability-scan-btn').each(function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true)
                        .text('🔒 Advanced Scan (Pro)')
                        .addClass('wsa-pro-feature-trigger')
                        .attr('data-feature', 'vulnerability_scan');
                });

                // Show Pro required for advanced scans
                $('.wsa-advanced-vulnerability-section').each(function() {
                    WSAProUpgrade.showProRequiredMessage('vulnerability_scan', this);
                });
            }
        },

        // Check Advanced Reporting feature
        checkAdvancedReporting: function() {
            if (!WSAProUpgrade.isProActive()) {
                // Disable advanced reporting options
                $('.wsa-advanced-report-btn').prop('disabled', true)
                    .text('🔒 Advanced Reports (Pro)')
                    .addClass('wsa-pro-feature-trigger')
                    .attr('data-feature', 'advanced_reporting');

                // Add overlay to advanced report sections
                WSAProUpgrade.addProOverlay('.wsa-advanced-reports-section', 'advanced_reporting');
            }
        },

        // Check White Label feature
        checkWhiteLabel: function() {
            if (!WSAProUpgrade.isProActive()) {
                // Disable white label options
                $('.wsa-white-label-section').each(function() {
                    WSAProUpgrade.showProRequiredMessage('white_label', this);
                });
            }
        }
    };

    /**
     * Document Ready
     */
    $(document).ready(function() {
        // Initialize Pro upgrade functionality
        WSAProUpgrade.init();
        WSAProFeatures.init();

        // Animate upgrade elements when they come into view
        if (typeof IntersectionObserver !== 'undefined') {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('wsa-animate-in');
                    }
                });
            });

            $('.wsa-pro-showcase, .wsa-upgrade-cta').each(function() {
                observer.observe(this);
            });
        }

        // Add CSS for animations
        if (!$('#wsa-pro-animations').length) {
            $('<style id="wsa-pro-animations">')
                .text(`
                    .wsa-animate-in {
                        animation: wsaFadeInUp 0.6s ease-out;
                    }
                    
                    @keyframes wsaFadeInUp {
                        from {
                            opacity: 0;
                            transform: translateY(30px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    
                    .wsa-modal-open {
                        overflow: hidden;
                    }
                    
                    .wsa-pro-required-message {
                        display: flex;
                        align-items: center;
                        padding: 20px;
                        background: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    
                    .wsa-pro-icon {
                        font-size: 48px;
                        margin-right: 20px;
                        opacity: 0.7;
                    }
                    
                    .wsa-pro-text h4 {
                        margin: 0 0 8px 0;
                        color: #333;
                    }
                    
                    .wsa-pro-text p {
                        margin: 0 0 15px 0;
                        color: #666;
                    }
                `)
                .appendTo('head');
        }
    });

})(jQuery);
