<?php
/**
 * Elementor to Divi Exporter - Style Handler
 *
 * This file contains functions to convert Elementor styles to Divi attributes.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main function to process Elementor styling and convert to Divi attributes
 * 
 * @param array $settings Element settings
 * @param bool $exclude_backgrounds Whether to exclude background styles
 * @return string Style attributes for Divi shortcode
 */
function jhmgced_process_element_styles($settings, $exclude_backgrounds = false) {
    // Add debug log to confirm this function is being called
    if (defined('WP_DEBUG') && WP_DEBUG) {
        jhmgced_log("Style handler invoked with settings: " . json_encode(array_keys($settings)), null, 'info');
    }
    
    $style_attrs = '';
    
    // Process positioning
    $position_attrs = jhmgced_process_element_positioning($settings);
    $style_attrs .= $position_attrs;
    
    // Process background colors with explicit debug output
    if (!$exclude_backgrounds) {
        $background_attrs = jhmgced_process_background_colors($settings);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            jhmgced_log("Background attributes generated: {$background_attrs}");
        }
        $style_attrs .= $background_attrs;
    }
    
    // Log the final style attributes
    if (defined('WP_DEBUG') && WP_DEBUG) {
        jhmgced_log("Final style attributes: {$style_attrs}");
    }
    
    return $style_attrs;
}

/**
 * Process Elementor positioning settings to Divi attributes
 * 
 * @param array $settings Elementor widget settings
 * @return string Positioning attributes for Divi shortcode
 */
function jhmgced_process_element_positioning($settings) {
    $position_attrs = '';
    
    // Check if position is set
    if (!isset($settings['_position'])) {
        return '';
    }
    
    // Get Elementor position value
    $position = $settings['_position'];
    
    // Map Elementor position to Divi
    switch ($position) {
        case 'absolute':
            $position_attrs .= ' positioning="absolute"';
            break;
        case 'fixed':
            $position_attrs .= ' positioning="fixed"';
            break;
        case 'relative':
        default:
            // Relative positioning is the default in Divi, but we can explicitly set it
            $position_attrs .= ' positioning="relative"';
    }
    
    // Process position origin (anchor point)
    $position_origin = jhmgced_determine_position_origin($settings);
    if (!empty($position_origin)) {
        $position_attrs .= " position_origin_a=\"{$position_origin}\"";
    }
    
    // Process offsets
    $offset_attrs = jhmgced_process_position_offsets($settings);
    $position_attrs .= $offset_attrs;
    
    return $position_attrs;
}

/**
 * Determine position origin (anchor point) based on Elementor settings
 * 
 * @param array $settings Elementor widget settings
 * @return string Position origin for Divi
 */
function jhmgced_determine_position_origin($settings) {
    // Default position origin
    $position_origin = '';
    
    // Initialize horizontal and vertical values
    $horizontal = '';
    $vertical = '';
    
    // Check for horizontal positioning
    if (isset($settings['_offset_x']) || isset($settings['_offset_x_end'])) {
        // If _offset_x is set, it's likely left-aligned
        if (isset($settings['_offset_x']) && !empty($settings['_offset_x']['size'])) {
            $horizontal = 'left';
        }
        // If _offset_x_end is set, it's likely right-aligned
        elseif (isset($settings['_offset_x_end']) && !empty($settings['_offset_x_end']['size'])) {
            $horizontal = 'right';
        }
    }
    
    // Check for vertical positioning
    if (isset($settings['_offset_y']) || isset($settings['_offset_y_end'])) {
        // If _offset_y is set, it's likely top-aligned
        if (isset($settings['_offset_y']) && !empty($settings['_offset_y']['size'])) {
            $vertical = 'top';
        }
        // If _offset_y_end is set, it's likely bottom-aligned
        elseif (isset($settings['_offset_y_end']) && !empty($settings['_offset_y_end']['size'])) {
            $vertical = 'bottom';
        }
    }
    
    // Alternative: check for explicit alignment settings
    if (isset($settings['_element_vertical_align'])) {
        $vertical = strtolower($settings['_element_vertical_align']);
    }
    
    if (isset($settings['_element_horizontal_align'])) {
        $horizontal = strtolower($settings['_element_horizontal_align']);
    }
    
    // Map to Divi position origin
    if (!empty($vertical) && !empty($horizontal)) {
        $position_origin = "{$vertical}_{$horizontal}";
    }
    // Default to top_left if we can't determine
    elseif (empty($position_origin) && $settings['_position'] === 'absolute') {
        $position_origin = 'top_left';
    }
    
    return $position_origin;
}

/**
 * Process position offsets from Elementor to Divi
 * 
 * @param array $settings Elementor widget settings
 * @return string Offset attributes for Divi
 */
function jhmgced_process_position_offsets($settings) {
    $offset_attrs = '';
    
    // Process X offset (left or right)
    if (isset($settings['_offset_x']) && isset($settings['_offset_x']['size']) && isset($settings['_offset_x']['unit'])) {
        $x_size = $settings['_offset_x']['size'];
        $x_unit = $settings['_offset_x']['unit'];
        
        if (!empty($x_size)) {
            $offset_attrs .= " left=\"{$x_size}{$x_unit}\"";
        }
    }
    // Process X end offset (right)
    elseif (isset($settings['_offset_x_end']) && isset($settings['_offset_x_end']['size']) && isset($settings['_offset_x_end']['unit'])) {
        $x_end_size = $settings['_offset_x_end']['size'];
        $x_end_unit = $settings['_offset_x_end']['unit'];
        
        if (!empty($x_end_size)) {
            $offset_attrs .= " right=\"{$x_end_size}{$x_end_unit}\"";
        }
    }
    
    // Process Y offset (top or bottom)
    if (isset($settings['_offset_y']) && isset($settings['_offset_y']['size']) && isset($settings['_offset_y']['unit'])) {
        $y_size = $settings['_offset_y']['size'];
        $y_unit = $settings['_offset_y']['unit'];
        
        if (!empty($y_size)) {
            $offset_attrs .= " top=\"{$y_size}{$y_unit}\"";
        }
    }
    // Process Y end offset (bottom)
    elseif (isset($settings['_offset_y_end']) && isset($settings['_offset_y_end']['size']) && isset($settings['_offset_y_end']['unit'])) {
        $y_end_size = $settings['_offset_y_end']['size'];
        $y_end_unit = $settings['_offset_y_end']['unit'];
        
        if (!empty($y_end_size)) {
            $offset_attrs .= " bottom=\"{$y_end_size}{$y_end_unit}\"";
        }
    }
    
    return $offset_attrs;
}

/**
 * Process Elementor z-index to Divi
 * 
 * @param array $settings Elementor widget settings
 * @return string Z-index attribute for Divi
 */
function jhmgced_process_z_index($settings) {
    if (isset($settings['_z_index']) && !empty($settings['_z_index'])) {
        $z_index = intval($settings['_z_index']);
        return " z_index=\"{$z_index}\"";
    }
    return '';
}

/**
 * Detect if Elementor element has custom positioning
 * 
 * @param array $settings Elementor widget settings
 * @return bool True if custom positioning is detected
 */
function jhmgced_has_custom_positioning($settings) {
    return isset($settings['_position']) && in_array($settings['_position'], ['absolute', 'fixed', 'relative']);
}

/**
 * Process Elementor gradient background settings to Divi attributes
 * 
 * @param array $settings Elementor widget settings
 * @return string Gradient background attributes for Divi shortcode
 */
function jhmgced_process_gradient_background($settings) {
    $gradient_attrs = '';
    
    // Check if this is a gradient background
    if (isset($settings['background_background']) && $settings['background_background'] === 'gradient') {
        // Get gradient type (linear or radial)
        $gradient_type = isset($settings['background_gradient_type']) ? $settings['background_gradient_type'] : 'linear';
        
        // Get gradient colors
        $color_a = isset($settings['background_gradient_color']) ? esc_attr($settings['background_gradient_color']) : '';
        $color_b = isset($settings['background_gradient_color_b']) ? esc_attr($settings['background_gradient_color_b']) : '';
        
        if (!empty($color_a) && !empty($color_b)) {
            if ($gradient_type === 'linear') {
                // Linear gradient
                $gradient_attrs .= " background_enable_gradient=\"on\"";
                $gradient_attrs .= " background_gradient_start=\"{$color_a}\"";
                $gradient_attrs .= " background_gradient_end=\"{$color_b}\"";
                
                // Get gradient direction
                if (isset($settings['background_gradient_angle']) && !empty($settings['background_gradient_angle']['size'])) {
                    $angle = intval($settings['background_gradient_angle']['size']);
                    
                    // Map angle to Divi's direction system
                    $direction = 'to bottom'; // Default
                    
                    if ($angle >= 0 && $angle < 45) {
                        $direction = 'to right';
                    } elseif ($angle >= 45 && $angle < 90) {
                        $direction = 'to bottom right';
                    } elseif ($angle >= 90 && $angle < 135) {
                        $direction = 'to bottom';
                    } elseif ($angle >= 135 && $angle < 180) {
                        $direction = 'to bottom left';
                    } elseif ($angle >= 180 && $angle < 225) {
                        $direction = 'to left';
                    } elseif ($angle >= 225 && $angle < 270) {
                        $direction = 'to top left';
                    } elseif ($angle >= 270 && $angle < 315) {
                        $direction = 'to top';
                    } else {
                        $direction = 'to top right';
                    }
                    
                    $gradient_attrs .= " background_gradient_direction=\"{$direction}\"";
                }
            } 
            elseif ($gradient_type === 'radial') {
                // Radial gradient - Divi can handle this too
                $gradient_attrs .= " background_enable_gradient=\"on\"";
                $gradient_attrs .= " background_gradient_start=\"{$color_a}\"";
                $gradient_attrs .= " background_gradient_end=\"{$color_b}\"";
                $gradient_attrs .= " background_gradient_type=\"radial\"";
                
                // Get radial position if available
                if (isset($settings['background_gradient_position'])) {
                    $position = esc_attr($settings['background_gradient_position']);
                    $gradient_attrs .= " background_gradient_position=\"{$position}\"";
                }
            }
        }
    }
    
    return $gradient_attrs;
}
/**
 * Specialized function to handle Elementor container backgrounds
 * 
 * @param array $settings Container settings
 * @return string Background attributes for Divi
 */
function jhmgced_process_container_background($settings) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        jhmgced_log("Processing container background with keys: " . json_encode(array_keys($settings)));
    }
    
    $bg_attrs = '';
    
    // New Elementor container formats
    if (isset($settings['background_color'])) {
        $bg_color = esc_attr($settings['background_color']);
        $bg_attrs .= " background_color=\"{$bg_color}\"";
        jhmgced_log("Container direct background color found: {$bg_color}");
    }
    
    // Container might use a different structure with a background object
    if (isset($settings['background'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            jhmgced_log("Container has background object with keys: " . json_encode(array_keys($settings['background'])));
        }
        
        if (isset($settings['background']['color'])) {
            $bg_color = esc_attr($settings['background']['color']);
            $bg_attrs .= " background_color=\"{$bg_color}\"";
            jhmgced_log("Container background.color found: {$bg_color}");
        }
    }
    
    return $bg_attrs;
}

/**
 * Process Elementor background settings to Divi attributes (both colors and images)
 * Updated to handle global colors
 * 
 * @param array $settings Elementor widget settings
 * @return string Background attributes for Divi shortcode
 */
function jhmgced_process_background_colors($settings) {
    // Add debug output to examine the settings
    if (defined('WP_DEBUG') && WP_DEBUG) {
        jhmgced_log("Processing background colors with settings: " . json_encode($settings));
        
        // Special debug for globals
        if (isset($settings['__globals__'])) {
            jhmgced_log("Found __globals__ property: " . json_encode($settings['__globals__']), null, 'info');
        }
    }
    
    $bg_attrs = '';
    
    // Check for global color settings first - these are newer Elementor format
    if (isset($settings['__globals__'])) {
        if (isset($settings['__globals__']['background_color'])) {
            // Add detailed logging for global color extraction
            jhmgced_log("Processing global background color: " . $settings['__globals__']['background_color'], null, 'info');
            
            // Global color reference - we need to extract the actual color value
            $global_color = jhmgced_extract_global_color($settings['__globals__']['background_color']);
            
            if (!empty($global_color)) {
                // Store the original color before conversion for debugging
                $original_color = $global_color;
                
                $bg_attrs .= " background_color=\"{$global_color}\"";
                jhmgced_log("Added global background_color attribute: {$global_color} (original: {$original_color})", null, 'info');
            }
        }
        
        // Check for _background_color in globals (used for some widgets)
        if (isset($settings['__globals__']['_background_color'])) {
            jhmgced_log("Processing global _background_color: " . $settings['__globals__']['_background_color'], null, 'info');
            
            $global_color = jhmgced_extract_global_color($settings['__globals__']['_background_color']);
            
            if (!empty($global_color)) {
                $original_color = $global_color;
                
                $bg_attrs .= " background_color=\"{$global_color}\"";
                jhmgced_log("Added global _background_color attribute: {$global_color} (original: {$original_color})", null, 'info');
            }
        }
    }
    
    // If no global color found, check for standard color settings
    if (empty($bg_attrs)) {
        // Check for background color in various Elementor format locations
        if (isset($settings['background_color']) && !empty($settings['background_color'])) {
            // Regular background color setting
            $bg_color = esc_attr($settings['background_color']);
            $bg_attrs .= " background_color=\"{$bg_color}\"";
            jhmgced_log("Extracted direct background_color: {$bg_color}", null, 'info');
        } 
        elseif (isset($settings['_background_color']) && !empty($settings['_background_color'])) {
            // Alternate background color setting in Elementor
            $bg_color = esc_attr($settings['_background_color']);
            $bg_attrs .= " background_color=\"{$bg_color}\"";
            jhmgced_log("Extracted direct _background_color: {$bg_color}", null, 'info');
        }
        elseif (isset($settings['background_background']) && $settings['background_background'] === 'classic' && 
                isset($settings['background_color']) && !empty($settings['background_color'])) {
            // Classic background setting
            $bg_color = esc_attr($settings['background_color']);
            $bg_attrs .= " background_color=\"{$bg_color}\"";
            jhmgced_log("Extracted classic background_color: {$bg_color}", null, 'info');
        }
        
        // Check for section background
        if (isset($settings['section_background'])) {
            if (isset($settings['section_background']['color']) && !empty($settings['section_background']['color'])) {
                $bg_color = esc_attr($settings['section_background']['color']);
                $bg_attrs .= " background_color=\"{$bg_color}\"";
                jhmgced_log("Extracted section background_color: {$bg_color}", null, 'info');
            }
        }
        
        // Look for background in background_settings group
        if (isset($settings['background_settings'])) {
            if (isset($settings['background_settings']['background_color']) && !empty($settings['background_settings']['background_color'])) {
                $bg_color = esc_attr($settings['background_settings']['background_color']);
                $bg_attrs .= " background_color=\"{$bg_color}\"";
                jhmgced_log("Extracted background_settings color: {$bg_color}", null, 'info');
            }
        }
    }
    
    // Process background image if available
    $bg_image_attrs = jhmgced_process_background_image($settings);
    $bg_attrs .= $bg_image_attrs;
    
    return $bg_attrs;
}

/**
 * Extract actual color value from Elementor global color reference
 * 
 * @param string $global_color_ref The global color reference string
 * @return string The actual color value or transparent
 */
function jhmgced_extract_global_color($global_color_ref) {
    // Log the global color reference for debugging
    jhmgced_log("Extracting global color from: {$global_color_ref}", null, 'info');
    
    // Pattern examples: "globals/colors?id=primary", "globals/colors?id=astglobalcolor0"
    
    // Method 1: Try to get global color from Elementor directly if it's available
    if (function_exists('elementor_get_global_color_by_id')) {
        // Extract ID from the reference
        $matches = [];
        if (preg_match('/id=([^&]+)/', $global_color_ref, $matches)) {
            $color_id = $matches[1];
            $color = elementor_get_global_color_by_id($color_id);
            
            if (!empty($color)) {
                jhmgced_log("Found global color via Elementor function: {$color}", null, 'info');
                return $color;
            }
        }
    }
    
    // Method 2: Extract the color ID/name from the reference
    $color_name = '';
    $matches = [];
    if (preg_match('/id=([^&]+)/', $global_color_ref, $matches)) {
        $color_name = $matches[1];
    }
    
    // Method 3: Check in the database for Elementor global colors
    $elementor_globals = get_option('elementor_globals');
    if (!empty($elementor_globals) && isset($elementor_globals['colors'])) {
        $colors = $elementor_globals['colors'];
        
        // Check if our ID exists in the global colors
        if (!empty($color_name) && isset($colors[$color_name])) {
            $color = $colors[$color_name];
            jhmgced_log("Found global color in database: {$color}", null, 'info');
            return $color;
        }
    }
    
    // Method 4: Check in kit settings (Elementor 3.0+)
    $elementor_kit = get_option('elementor_active_kit');
    if (!empty($elementor_kit)) {
        $kit_settings = get_post_meta($elementor_kit, '_elementor_page_settings', true);
        if (!empty($kit_settings) && isset($kit_settings['system_colors'])) {
            foreach ($kit_settings['system_colors'] as $system_color) {
                if (isset($system_color['_id']) && $system_color['_id'] === $color_name && isset($system_color['color'])) {
                    $color = $system_color['color'];
                    jhmgced_log("Found color in Elementor kit settings: {$color}", null, 'info');
                    return $color;
                }
            }
        }
        
        // Also check custom colors
        if (!empty($kit_settings) && isset($kit_settings['custom_colors'])) {
            foreach ($kit_settings['custom_colors'] as $custom_color) {
                if (isset($custom_color['_id']) && $custom_color['_id'] === $color_name && isset($custom_color['color'])) {
                    $color = $custom_color['color'];
                    jhmgced_log("Found color in Elementor kit custom colors: {$color}", null, 'info');
                    return $color;
                }
            }
        }
    }
    
    // If all else fails, return transparent as default
    jhmgced_log("Could not determine global color '{$color_name}', using transparent", null, 'warning');
    return 'rgba(0,0,0,0)'; // Transparent default
}

/**
 * Process Elementor background image settings to Divi attributes
 * 
 * @param array $settings Elementor widget settings
 * @return string Background image attributes for Divi shortcode
 */
function jhmgced_process_background_image($settings) {
    $bg_image_attrs = '';
    
    // Check for background image in various Elementor formats
    if (isset($settings['background_image']) && isset($settings['background_image']['url']) && !empty($settings['background_image']['url'])) {
        $bg_image_url = esc_url($settings['background_image']['url']);
        $bg_image_attrs .= " background_image=\"{$bg_image_url}\"";
        
        // Process background size
        if (isset($settings['background_size']) && !empty($settings['background_size'])) {
            $bg_size = esc_attr($settings['background_size']);
            $bg_image_attrs .= " background_size=\"{$bg_size}\"";
        }
        
        // Process background position
        if (isset($settings['background_position']) && !empty($settings['background_position'])) {
            $bg_position = esc_attr($settings['background_position']);
            $bg_image_attrs .= " background_position=\"{$bg_position}\"";
        }
        
        // Process background repeat
        if (isset($settings['background_repeat']) && !empty($settings['background_repeat'])) {
            $bg_repeat = esc_attr($settings['background_repeat']);
            $bg_image_attrs .= " background_repeat=\"{$bg_repeat}\"";
        }
        
        // Process background blend mode
        if (isset($settings['background_blend_mode']) && !empty($settings['background_blend_mode'])) {
            $bg_blend = esc_attr($settings['background_blend_mode']);
            $bg_image_attrs .= " background_blend=\"{$bg_blend}\"";
        }
    }
    
    // Check for background image in section_background 
    if (isset($settings['section_background'])) {
        if (isset($settings['section_background']['image']) && 
            isset($settings['section_background']['image']['url']) && 
            !empty($settings['section_background']['image']['url'])) {
            
            $bg_image_url = esc_url($settings['section_background']['image']['url']);
            $bg_image_attrs .= " background_image=\"{$bg_image_url}\"";
            
            // Add section background properties if available
            if (isset($settings['section_background']['size'])) {
                $bg_size = esc_attr($settings['section_background']['size']);
                $bg_image_attrs .= " background_size=\"{$bg_size}\"";
            }
        }
    }
    
    // Check for background in the classic background settings format
    if (isset($settings['background_background']) && $settings['background_background'] === 'classic') {
        if (isset($settings['background_image']) && 
            isset($settings['background_image']['url']) && 
            !empty($settings['background_image']['url'])) {
            
            $bg_image_url = esc_url($settings['background_image']['url']);
            $bg_image_attrs .= " background_image=\"{$bg_image_url}\"";
            
            // Process additional background properties
            if (isset($settings['background_size'])) {
                $bg_size = esc_attr($settings['background_size']);
                $bg_image_attrs .= " background_size=\"{$bg_size}\"";
            }
            
            if (isset($settings['background_position'])) {
                $bg_position = esc_attr($settings['background_position']);
                $bg_image_attrs .= " background_position=\"{$bg_position}\"";
            }
            
            if (isset($settings['background_repeat'])) {
                $bg_repeat = esc_attr($settings['background_repeat']);
                $bg_image_attrs .= " background_repeat=\"{$bg_repeat}\"";
            }
        }
    }
    
    return $bg_image_attrs;
}