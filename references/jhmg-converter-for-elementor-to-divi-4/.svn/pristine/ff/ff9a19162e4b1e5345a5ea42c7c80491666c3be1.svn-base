/**
 * JHMG Converter For Elementor to Divi - Admin Scripts
 */

(function($) {
    'use strict';

    var JHMGCED_Admin = {
        /**
         * Initialize the admin scripts
         */
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Basic form validation
            $('#jhmgced-export-form').on('submit', this.validateExport);
            
            // Filter pages dropdown
            $('.jhmgced-page-filter').on('change', this.filterPageList);
            
            // Dismissible notices
            $('.jhmgced-notice-dismiss').on('click', this.dismissNotice);
            
            // Add this new event listener for when the export form is submitted
            // This will automatically hide the loading indicator after a timeout
            $('#jhmgced-export-form').on('submit', function() {
                // Hide the loading indicator after a reasonable timeout (10 seconds)
                setTimeout(function() {
                    JHMGCED_Admin.hideLoading();
                }, 10000);
            });
        },

        /**
         * Simple validation before exporting
         * @param {Event} e 
         */
        validateExport: function(e) {
            var selectedPage = $('#jhmgced_page_id').val();
            
            if (!selectedPage) {
                e.preventDefault();
                alert(jhmgced_ajax.i18n.select_page);
                return false;
            }
            
            // Show loading animation
            JHMGCED_Admin.showLoading(jhmgced_ajax.i18n.exporting);
            return true;
        },

        /**
         * Show a loading message
         * @param {string} message 
         */
        showLoading: function(message) {
            // Remove any existing loading elements first to prevent duplicates
            this.hideLoading();
            
            var $loading = $('<div class="jhmgced-loading"><span class="spinner is-active"></span>' + message + '</div>');
            $('body').append($loading);
        },

        /**
         * Hide the loading message
         */
        hideLoading: function() {
            $('.jhmgced-loading').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Filter the pages dropdown
         */
        filterPageList: function() {
            var filter = $(this).val();
            var $select = $('#jhmgced_page_id');
            
            if (filter === 'all') {
                $select.find('option').show();
            } else {
                $select.find('option').hide();
                $select.find('option[data-status="' + filter + '"]').show();
            }
            
            // Reset selection
            $select.val('');
        },

        /**
         * Dismiss a notice
         * @param {Event} e 
         */
        dismissNotice: function(e) {
            e.preventDefault();
            var $notice = $(this).closest('.jhmgced-notice');
            
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
            
            // Also save the dismissed state if a notice ID is available
            var noticeId = $(this).data('notice-id');
            if (noticeId) {
                $.post(jhmgced_ajax.ajax_url, {
                    action: 'jhmgced_dismiss_notice',
                    notice_id: noticeId,
                    nonce: jhmgced_ajax.nonce
                });
            }
        },

        /**
         * Initialize tooltips
         */
        initializeTooltips: function() {
            $('.jhmgced-tooltip').each(function() {
                if ($(this).find('.jhmgced-tooltip-text').length === 0) {
                    $(this).append('<span class="jhmgced-tooltip-text">' + $(this).data('tooltip') + '</span>');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        JHMGCED_Admin.init();
    });

})(jQuery);