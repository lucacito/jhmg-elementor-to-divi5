<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'jhmgced_add_admin_menu');

/**
 * Add the plugin menu to WordPress admin
 */
function jhmgced_add_admin_menu() {
    add_menu_page(
        esc_html__('JHMG Converter For Elementor to Divi', 'jhmg-converter-for-elementor-to-divi'), 
        esc_html__('ED Exporter', 'jhmg-converter-for-elementor-to-divi'), 
        'manage_options', 
        'jhmgced-exporter', 
        'jhmgced_admin_page', 
        'dashicons-migrate'
    );
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'jhmgced_enqueue_admin_scripts');

/**
 * Enqueue admin scripts and styles
 * 
 * @param string $hook Current admin page
 */
function jhmgced_enqueue_admin_scripts($hook) {
    if ('toplevel_page_jhmgced-exporter' != $hook) {
        return;
    }
    
    // Enqueue the already registered style and script
    wp_enqueue_style('jhmgced-admin-style');
    wp_enqueue_script('jhmgced-admin-script');
}

// Handle notice dismissals
add_action('wp_ajax_jhmgced_dismiss_notice', 'jhmgced_ajax_dismiss_notice');

/**
 * AJAX handler for dismissing admin notices
 */
function jhmgced_ajax_dismiss_notice() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'jhmgced_ajax_nonce')) {
        wp_send_json_error('Invalid security token');
    }
    
    // Get the notice ID
    $notice_id = isset($_POST['notice_id']) ? sanitize_text_field(wp_unslash($_POST['notice_id'])) : '';
    if (empty($notice_id)) {
        wp_send_json_error('No notice ID provided');
    }
    
    // Save as user meta
    update_user_meta(get_current_user_id(), 'jhmgced_dismissed_' . $notice_id, time());
    wp_send_json_success();
}

/**
 * Admin page content
 */
function jhmgced_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get pages with Elementor data - with caching
    $pages = get_transient('jhmgced_elementor_pages');
    if (false === $pages) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // First get all page IDs
        $page_ids = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        // Then filter them by checking for meta
        $elementor_pages = array();
        foreach ($page_ids as $page_id) {
            if (get_post_meta($page_id, '_elementor_data', true)) {
                $elementor_pages[] = get_post($page_id);
            }
        }
        $pages = $elementor_pages;
        set_transient('jhmgced_elementor_pages', $pages, HOUR_IN_SECONDS);
    }
    
    // Get some statistics
    $total_pages = count($pages);
    $exported_pages = get_option('jhmgced_exported_pages', array());
    $total_exported = count($exported_pages);
    
    // Display welcome message the first time
    $first_time = !get_user_meta(get_current_user_id(), 'jhmgced_dismissed_welcome', true);
    
    // Get latest export result
    $export_result = get_transient('jhmgced_last_export_result');
    delete_transient('jhmgced_last_export_result'); // Clear after retrieving
    
    ?>
    <div class="wrap">
        <div class="jhmgced-container">
            <div class="jhmgced-header">
                <div class="jhmgced-logo">
                    <span class="dashicons dashicons-migrate" style="font-size: 36px; color: #2ea3f2;"></span>
                </div>
                <div class="jhmgced-title">
                    <h1><?php esc_html_e('JHMG Converter For Elementor to Divi', 'jhmg-converter-for-elementor-to-divi'); ?></h1>
                    <p><?php esc_html_e('Export your Elementor pages to Divi-compatible JSON format.', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                </div>
            </div>
            
            <?php if ($first_time): ?>
            <div class="jhmgced-notice jhmgced-notice-info">
                <h4><?php esc_html_e('Welcome to JHMG Converter For Elementor to Divi!', 'jhmg-converter-for-elementor-to-divi'); ?></h4>
                <p><?php esc_html_e('This plugin helps you migrate your content from Elementor to Divi. Select a page below to get started.', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                <p>
                    <a href="#" class="jhmgced-notice-dismiss" data-notice-id="welcome"><?php esc_html_e('Dismiss', 'jhmg-converter-for-elementor-to-divi'); ?></a>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if ($export_result): ?>
            <div class="jhmgced-notice jhmgced-notice-<?php echo esc_attr($export_result['status']); ?>">
                <h4><?php echo esc_html($export_result['title']); ?></h4>
                <p><?php echo esc_html($export_result['message']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="jhmgced-dashboard">
                <div class="jhmgced-dashboard-card">
                    <h3><?php esc_html_e('Elementor Pages', 'jhmg-converter-for-elementor-to-divi'); ?></h3>
                    <div class="jhmgced-dashboard-count"><?php echo esc_html($total_pages); ?></div>
                    <p><?php esc_html_e('Total pages built with Elementor', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                </div>
                
                <div class="jhmgced-dashboard-card">
                    <h3><?php esc_html_e('Exported Pages', 'jhmg-converter-for-elementor-to-divi'); ?></h3>
                    <div class="jhmgced-dashboard-count"><?php echo esc_html($total_exported); ?></div>
                    <p><?php esc_html_e('Pages exported to Divi format', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                </div>
                
                <div class="jhmgced-dashboard-card">
                    <h3><?php esc_html_e('Quick Links', 'jhmg-converter-for-elementor-to-divi'); ?></h3>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=et_divi_options')); ?>"><?php esc_html_e('Divi Theme Options', 'jhmg-converter-for-elementor-to-divi'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>"><?php esc_html_e('All Pages', 'jhmg-converter-for-elementor-to-divi'); ?></a></li>
                        <li><a href="https://www.elegantthemes.com/documentation/divi/" target="_blank"><?php esc_html_e('Divi Documentation', 'jhmg-converter-for-elementor-to-divi'); ?></a></li>
                    </ul>
                </div>
            </div>
            
            <?php if (empty($pages)) : ?>
                <div class="jhmgced-notice jhmgced-notice-warning">
                    <p><?php esc_html_e('No Elementor pages were found on this site. You need to have at least one page built with Elementor to use this plugin.', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                </div>
            <?php else : ?>
                <div class="jhmgced-form">
                    <h2><?php esc_html_e('Export an Elementor Page', 'jhmg-converter-for-elementor-to-divi'); ?></h2>
                    <p><?php esc_html_e('Select a page below to export it to Divi format. The exported file can be imported in the Divi Builder.', 'jhmg-converter-for-elementor-to-divi'); ?></p>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="jhmgced-export-form">
                        <input type="hidden" name="action" value="jhmgced_export_page">
                        <?php wp_nonce_field('jhmgced_export_action', 'jhmgced_export_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="jhmgced_page_id"><?php esc_html_e('Select a page:', 'jhmg-converter-for-elementor-to-divi'); ?></label></th>
                                <td>
                                    <select name="jhmgced_page_id" id="jhmgced_page_id" required>
                                        <option value=""><?php esc_html_e('-- Select a page --', 'jhmg-converter-for-elementor-to-divi'); ?></option>
                                        <?php foreach ($pages as $page) : 
                                            $status = in_array($page->ID, $exported_pages) ? 'exported' : 'not-exported';
                                        ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>" data-status="<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                                <?php if ($status === 'exported'): ?>
                                                    (<?php esc_html_e('Previously Exported', 'jhmg-converter-for-elementor-to-divi'); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php 
                                        printf(
                                            // translators: %d is the number of pages built with Elementor.
                                            esc_html__('Showing %d pages built with Elementor.', 'jhmg-converter-for-elementor-to-divi'),
                                            count($pages)
                                        ); 
                                    ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="jhmgced_export_options"><?php esc_html_e('Export Options:', 'jhmg-converter-for-elementor-to-divi'); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="jhmgced_include_images" id="jhmgced_include_images" value="1" checked>
                                            <?php esc_html_e('Include image URLs in export', 'jhmg-converter-for-elementor-to-divi'); ?>
                                            <span class="jhmgced-tooltip" data-tooltip="<?php esc_attr_e('Images will be referenced by URL. You\'ll need to ensure the images exist on the target site.', 'jhmg-converter-for-elementor-to-divi'); ?>">?</span>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="jhmgced_detailed_log" id="jhmgced_detailed_log" value="1">
                                            <?php esc_html_e('Create detailed conversion log', 'jhmg-converter-for-elementor-to-divi'); ?>
                                            <span class="jhmgced-tooltip" data-tooltip="<?php esc_attr_e('Creates a detailed log file of the conversion process. Useful for troubleshooting.', 'jhmg-converter-for-elementor-to-divi'); ?>">?</span>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Export to Divi JSON', 'jhmg-converter-for-elementor-to-divi'); ?>">
                        </p>
                    </form>
                </div>
                
                <div class="jhmgced-help-section">
                    <h2><?php esc_html_e('How to Import into Divi', 'jhmg-converter-for-elementor-to-divi'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('After exporting, download and save the JSON file to your computer', 'jhmg-converter-for-elementor-to-divi'); ?></li>
                        <li><?php esc_html_e('Open or create a page in WordPress that uses the Divi Builder', 'jhmg-converter-for-elementor-to-divi'); ?></li>
                        <li><?php esc_html_e('Click the "Import & Export" button (↔) in the Divi Builder interface', 'jhmg-converter-for-elementor-to-divi'); ?></li>
                        <li><?php esc_html_e('Choose "Import" and upload your JSON file', 'jhmg-converter-for-elementor-to-divi'); ?></li>
                        <li><?php esc_html_e('Adjust any elements that may need fine-tuning after import', 'jhmg-converter-for-elementor-to-divi'); ?></li>
                    </ol>
                    
                    <h3><?php esc_html_e('Frequently Asked Questions', 'jhmg-converter-for-elementor-to-divi'); ?></h3>
                    <div class="jhmgced-faq-item">
                        <div class="jhmgced-faq-question"><?php esc_html_e('Will this plugin convert everything perfectly?', 'jhmg-converter-for-elementor-to-divi'); ?></div>
                        <div class="jhmgced-faq-answer">
                            <?php esc_html_e('While the plugin aims to convert as much as possible, there may be some elements that don\'t convert perfectly due to differences between Elementor and Divi. Complex layouts, custom CSS, and unique Elementor features might require some manual adjustments.', 'jhmg-converter-for-elementor-to-divi'); ?>
                        </div>
                    </div>
                    
                    <div class="jhmgced-faq-item">
                        <div class="jhmgced-faq-question"><?php esc_html_e('Does the plugin delete or modify my Elementor pages?', 'jhmg-converter-for-elementor-to-divi'); ?></div>
                        <div class="jhmgced-faq-answer">
                            <?php esc_html_e('No, the plugin only reads your Elementor data and creates a new export file. It doesn\'t modify your existing pages in any way.', 'jhmg-converter-for-elementor-to-divi'); ?>
                        </div>
                    </div>
                    
                    <div class="jhmgced-faq-item">
                        <div class="jhmgced-faq-question"><?php esc_html_e('What happens to custom CSS from Elementor?', 'jhmg-converter-for-elementor-to-divi'); ?></div>
                        <div class="jhmgced-faq-answer">
                            <?php esc_html_e('Basic inline styles are converted where possible, but custom CSS classes and complex styling may need to be manually recreated in Divi.', 'jhmg-converter-for-elementor-to-divi'); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Track exported pages
 * 
 * @param int $page_id The ID of the exported page
 */
function jhmgced_track_export($page_id) {
    $exported_pages = get_option('jhmgced_exported_pages', array());
    
    if (!in_array($page_id, $exported_pages)) {
        $exported_pages[] = $page_id;
        update_option('jhmgced_exported_pages', $exported_pages);
    }
    
    // Save export timestamp
    update_post_meta($page_id, '_jhmgced_exported', time());
}

/**
 * Set export result for admin notices
 * 
 * @param string $status Status of the export (success/error)
 * @param string $title Title of the notice
 * @param string $message Message to display
 */
function jhmgced_set_export_result($status, $title, $message) {
    set_transient('jhmgced_last_export_result', array(
        'status' => $status,
        'title' => $title,
        'message' => $message
    ), 60); // Keep for 1 minute
}

// Hook into the export process to track and set result
add_action('jhmgced_after_export', 'jhmgced_handle_after_export', 10, 2);

/**
 * Handle events after export
 * 
 * @param int $page_id The ID of the exported page
 * @param bool $success Whether the export was successful
 */
function jhmgced_handle_after_export($page_id, $success) {
    if ($success) {
        jhmgced_track_export($page_id);
        jhmgced_set_export_result('success', esc_html__('Export Successful', 'jhmg-converter-for-elementor-to-divi'), esc_html__('The page was successfully exported to Divi format.', 'jhmg-converter-for-elementor-to-divi'));
    } else {
        jhmgced_set_export_result('error', esc_html__('Export Failed', 'jhmg-converter-for-elementor-to-divi'), esc_html__('There was an error exporting the page. Please check the error log for details.', 'jhmg-converter-for-elementor-to-divi'));
    }
}