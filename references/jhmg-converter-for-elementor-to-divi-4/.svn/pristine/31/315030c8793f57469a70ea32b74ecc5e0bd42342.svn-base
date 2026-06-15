<?php
/**
 * Helper functions for JHMG Converter For Elementor to Divi
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the plugin's uploads directory
 *
 * @return string Path to the plugin's uploads directory
 */
function jhmgced_get_uploads_dir() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/jhmgced-files';
    
    if (!file_exists($plugin_upload_dir)) {
        wp_mkdir_p($plugin_upload_dir);
        
        // Create an index.php file to prevent directory listing
        $index_file = $plugin_upload_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    return $plugin_upload_dir;
}

/**
 * Get a setting from the plugin options
 *
 * @param string $key Setting key to retrieve
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed Setting value or default
 */
function jhmgced_get_setting($key, $default = '') {
    $options = get_option('jhmgced_settings', array());
    
    if (isset($options[$key])) {
        return $options[$key];
    }
    
    return $default;
}

/**
 * Update a plugin setting
 *
 * @param string $key Setting key to update
 * @param mixed $value New value
 * @return bool Whether the setting was updated
 */
function jhmgced_update_setting($key, $value) {
    $options = get_option('jhmgced_settings', array());
    $options[$key] = $value;
    return update_option('jhmgced_settings', $options);
}

/**
 * Check if Elementor is active
 * 
 * @return bool Whether Elementor is installed and active
 */
function jhmgced_is_elementor_active() {
    return class_exists('\Elementor\Plugin');
}

/**
 * Check if Divi is active
 * 
 * @return bool Whether Divi is installed and active
 */
function jhmgced_is_divi_active() {
    return function_exists('et_setup_theme');
}

/**
 * Safely get nested values from an array
 * 
 * @param array $array The array to extract from
 * @param array $keys Array of keys to traverse
 * @param mixed $default Default value if path doesn't exist
 * @return mixed The found value or default
 */
function jhmgced_get_nested_value($array, $keys, $default = '') {
    $current = $array;
    
    foreach ($keys as $key) {
        if (!is_array($current) || !isset($current[$key])) {
            return $default;
        }
        $current = $current[$key];
    }
    
    return $current;
}

/**
 * Sanitize and escape values for use in Divi attributes
 * 
 * @param string $value The value to sanitize
 * @param string $type The type of sanitization: 'text', 'attr', 'url', 'html'
 * @return string The sanitized value
 */
function jhmgced_sanitize_value($value, $type = 'attr') {
    if (empty($value)) {
        return '';
    }
    
    switch ($type) {
        case 'text':
            return esc_html($value);
        case 'attr':
            return esc_attr($value);
        case 'url':
            return esc_url($value);
        case 'html':
            return wp_kses_post($value);
        default:
            return esc_attr($value);
    }
}