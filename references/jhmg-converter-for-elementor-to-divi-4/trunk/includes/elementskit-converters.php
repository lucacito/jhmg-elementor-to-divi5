<?php
/**
 * Elementor to Divi Exporter - ElementsKit Lite Widget Converters
 *
 * This file contains conversion functions for ElementsKit Lite widgets.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process an ElementsKit widget and map to Divi module
 * 
 * @param array $widget Elementor widget data
 * @param string $builder_version Divi builder version
 * @return string Divi module shortcode or empty string if not an ElementsKit widget
 */
function jhmgced_process_elementskit_widget($widget, $builder_version, $style_attrs = '') {
    if (!isset($widget['widgetType'])) {
        return '';
    }
    
    $widget_type = $widget['widgetType'];
    
    // Only process ElementsKit widgets
    if (strpos($widget_type, 'elementskit-') !== 0) {
        return '';
    }
    
    $settings = isset($widget['settings']) ? $widget['settings'] : array();
    
    // Log widget types we're processing for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        jhmgced_log("Processing ElementsKit widget: {$widget_type}");
    }
    
    switch ($widget_type) {
        case 'elementskit-accordion':
            return jhmgced_create_elementskit_accordion_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-tab':
            return jhmgced_create_elementskit_tab_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-testimonial':
            return jhmgced_create_elementskit_testimonial_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-heading':
            return jhmgced_create_elementskit_heading_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-button':
            return jhmgced_create_elementskit_button_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-icon-box':
            return jhmgced_create_elementskit_icon_box_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-image':
            return jhmgced_create_elementskit_image_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-progress-bar':
            return jhmgced_create_elementskit_progress_bar_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-pricing-table':
            return jhmgced_create_elementskit_pricing_table_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-faq':
            return jhmgced_create_elementskit_faq_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-team':
            return jhmgced_create_elementskit_team_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-gallery':
            return jhmgced_create_elementskit_gallery_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-countdown-timer':
            return jhmgced_create_elementskit_countdown_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-google-map':
            return jhmgced_create_elementskit_google_map_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-dual-button':
            return jhmgced_create_elementskit_dual_button_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-social-media':
            return jhmgced_create_elementskit_social_media_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-video':
            return jhmgced_create_elementskit_video_module($settings, $builder_version, $style_attrs);
            
        case 'elementskit-audio-player':
            return jhmgced_create_elementskit_audio_player_module($settings, $builder_version, $style_attrs);
            
        default:
            // For any unhandled ElementsKit widgets, provide informative output
            return "[et_pb_text _builder_version=\"{$builder_version}\"{$style_attrs}]\n" . 
                   "<p>Imported ElementsKit widget: " . esc_html($widget_type) . "</p>\n" .
                   "<p><em>This ElementsKit widget type is not yet supported by the converter. The content may need manual adjustment.</em></p>\n" .
                   "[/et_pb_text]\n";
    }
}

/**
 * Create a Divi accordion module from ElementsKit accordion
 * 
 * @param array $settings ElementsKit accordion settings
 * @param string $builder_version Divi builder version
 * @return string Divi accordion module shortcode
 */
function jhmgced_create_elementskit_accordion_module($settings, $builder_version) {
    $accordion_shortcode = "[et_pb_accordion _builder_version=\"{$builder_version}\"]\n";
    
    // Process accordion items
    if (isset($settings['ekit_accordion_items']) && is_array($settings['ekit_accordion_items'])) {
        foreach ($settings['ekit_accordion_items'] as $item) {
            $title = isset($item['ekit_acc_title']) ? esc_html($item['ekit_acc_title']) : 'Accordion Title';
            $content = isset($item['ekit_acc_content']) ? wp_kses_post($item['ekit_acc_content']) : 'Accordion Content';
            
            // Check if this item should be open by default
            $is_active = isset($item['ekit_accordion_is_active']) && $item['ekit_accordion_is_active'] === 'yes';
            $open_attr = $is_active ? ' open="on"' : '';
            
            $accordion_shortcode .= "[et_pb_accordion_item title=\"{$title}\"{$open_attr} _builder_version=\"{$builder_version}\"]{$content}[/et_pb_accordion_item]\n";
        }
    } else {
        // Default accordion item if none provided
        $accordion_shortcode .= "[et_pb_accordion_item title=\"Accordion Title\" _builder_version=\"{$builder_version}\"]Accordion Content[/et_pb_accordion_item]\n";
    }
    
    $accordion_shortcode .= "[/et_pb_accordion]\n";
    return $accordion_shortcode;
}

/**
 * Create a Divi tabs module from ElementsKit tab
 * 
 * @param array $settings ElementsKit tab settings
 * @param string $builder_version Divi builder version
 * @return string Divi tabs module shortcode
 */
function jhmgced_create_elementskit_tab_module($settings, $builder_version) {
    $tabs_shortcode = "[et_pb_tabs _builder_version=\"{$builder_version}\"]\n";
    
    // Process tab items
    if (isset($settings['ekit_tab_items']) && is_array($settings['ekit_tab_items'])) {
        foreach ($settings['ekit_tab_items'] as $item) {
            $title = isset($item['ekit_tab_title']) ? esc_html($item['ekit_tab_title']) : 'Tab Title';
            $content = isset($item['ekit_tab_content']) ? wp_kses_post($item['ekit_tab_content']) : 'Tab Content';
            
            $tabs_shortcode .= "[et_pb_tab title=\"{$title}\" _builder_version=\"{$builder_version}\"]{$content}[/et_pb_tab]\n";
        }
    } else {
        // Default tab if none provided
        $tabs_shortcode .= "[et_pb_tab title=\"Tab 1\" _builder_version=\"{$builder_version}\"]Tab 1 Content[/et_pb_tab]\n";
    }
    
    $tabs_shortcode .= "[/et_pb_tabs]\n";
    return $tabs_shortcode;
}

/**
 * Create a Divi testimonial module from ElementsKit testimonial
 * 
 * @param array $settings ElementsKit testimonial settings
 * @param string $builder_version Divi builder version
 * @return string Divi testimonial module shortcode
 */
function jhmgced_create_elementskit_testimonial_module($settings, $builder_version) {
    // Handle multiple testimonials slider case
    if (isset($settings['ekit_testimonial_items']) && is_array($settings['ekit_testimonial_items']) && count($settings['ekit_testimonial_items']) > 1) {
        // If multiple testimonials, create a slider
        $slider_shortcode = "[et_pb_slider _builder_version=\"{$builder_version}\" show_arrows=\"on\" show_pagination=\"on\"]\n";
        
        foreach ($settings['ekit_testimonial_items'] as $item) {
            $name = isset($item['client_name']) ? esc_html($item['client_name']) : '';
            $designation = isset($item['designation']) ? esc_html($item['designation']) : '';
            $content = isset($item['content']) ? wp_kses_post($item['content']) : '';
            
            $title = $name;
            if (!empty($designation)) {
                $title .= ' - ' . $designation;
            }
            
            $slider_shortcode .= "[et_pb_slide heading=\"{$title}\" _builder_version=\"{$builder_version}\"]{$content}[/et_pb_slide]\n";
        }
        
        $slider_shortcode .= "[/et_pb_slider]\n";
        return $slider_shortcode;
    }
    
    // Handle single testimonial case
    $content = '';
    $author = '';
    $job = '';
    
    // Get testimonial content
    if (isset($settings['ekit_testimonial_items']) && is_array($settings['ekit_testimonial_items']) && !empty($settings['ekit_testimonial_items'])) {
        $first_item = $settings['ekit_testimonial_items'][0];
        $content = isset($first_item['content']) ? wp_kses_post($first_item['content']) : '';
        $author = isset($first_item['client_name']) ? esc_html($first_item['client_name']) : '';
        $job = isset($first_item['designation']) ? esc_html($first_item['designation']) : '';
    }
    
    // If no content found in items, try alternative fields
    if (empty($content) && isset($settings['ekit_testimonial_content'])) {
        $content = wp_kses_post($settings['ekit_testimonial_content']);
    }
    
    if (empty($author) && isset($settings['ekit_testimonial_name'])) {
        $author = esc_html($settings['ekit_testimonial_name']);
    }
    
    if (empty($job) && isset($settings['ekit_testimonial_designation'])) {
        $job = esc_html($settings['ekit_testimonial_designation']);
    }
    
    // Default values if still empty
    if (empty($content)) $content = 'Testimonial content';
    if (empty($author)) $author = 'Client Name';
    
    // Create the testimonial
    $attrs = " author=\"{$author}\"";
    if (!empty($job)) {
        $attrs .= " job_title=\"{$job}\"";
    }
    
    // Check for portrait/image
    if (isset($settings['ekit_testimonial_client_image']) && isset($settings['ekit_testimonial_client_image']['url'])) {
        $image_url = esc_url($settings['ekit_testimonial_client_image']['url']);
        $attrs .= " portrait_url=\"{$image_url}\"";
    }
    
    return "[et_pb_testimonial _builder_version=\"{$builder_version}\"{$attrs}]{$content}[/et_pb_testimonial]\n";
}

/**
 * Create a Divi heading module from ElementsKit heading
 * 
 * @param array $settings ElementsKit heading settings
 * @param string $builder_version Divi builder version
 * @return string Divi text module shortcode
 */
function jhmgced_create_elementskit_heading_module($settings, $builder_version) {
    $title = isset($settings['ekit_heading_title']) ? esc_html($settings['ekit_heading_title']) : '';
    $subtitle = isset($settings['ekit_heading_sub_title']) ? esc_html($settings['ekit_heading_sub_title']) : '';
    $desc = isset($settings['ekit_heading_description']) ? wp_kses_post($settings['ekit_heading_description']) : '';
    
    // Get heading tag
    $title_tag = isset($settings['ekit_heading_title_tag']) ? $settings['ekit_heading_title_tag'] : 'h2';
    $subtitle_tag = isset($settings['ekit_heading_sub_title_tag']) ? $settings['ekit_heading_sub_title_tag'] : 'h3';
    
    // Build the content with proper heading tags
    $content = '';
    
    if (!empty($title)) {
        $content .= "<{$title_tag}>{$title}</{$title_tag}>\n";
    }
    
    if (!empty($subtitle)) {
        $content .= "<{$subtitle_tag}>{$subtitle}</{$subtitle_tag}>\n";
    }
    
    if (!empty($desc)) {
        $content .= "<p>{$desc}</p>\n";
    }
    
    // Custom text style
    $text_attrs = '';
    
    // Check for text alignment
    if (isset($settings['ekit_heading_title_align'])) {
        $text_align = esc_attr($settings['ekit_heading_title_align']);
        $text_attrs .= " text_orientation=\"{$text_align}\"";
    }
    
    // Check for text color
    if (isset($settings['ekit_heading_title_color'])) {
        $text_color = esc_attr($settings['ekit_heading_title_color']);
        $text_attrs .= " text_color=\"{$text_color}\"";
    }
    
    return "[et_pb_text _builder_version=\"{$builder_version}\"{$text_attrs}]\n{$content}[/et_pb_text]\n";
}

/**
 * Create a Divi button module from ElementsKit button
 * 
 * @param array $settings ElementsKit button settings
 * @param string $builder_version Divi builder version
 * @return string Divi button module shortcode
 */
function jhmgced_create_elementskit_button_module($settings, $builder_version) {
    $button_text = isset($settings['ekit_btn_text']) ? esc_html($settings['ekit_btn_text']) : 'Click Here';
    $button_url = '#';
    
    // Get button URL
    if (isset($settings['ekit_btn_url']) && isset($settings['ekit_btn_url']['url'])) {
        $button_url = esc_url($settings['ekit_btn_url']['url']);
    }
    
    // Handle button attributes
    $button_attrs = '';
    
    // Button color
    if (isset($settings['ekit_btn_bg_color'])) {
        $bg_color = esc_attr($settings['ekit_btn_bg_color']);
        $button_attrs .= " button_bg_color=\"{$bg_color}\"";
    }
    
    // Text color
    if (isset($settings['ekit_btn_text_color'])) {
        $text_color = esc_attr($settings['ekit_btn_text_color']);
        $button_attrs .= " button_text_color=\"{$text_color}\"";
    }
    
    // Button alignment
    if (isset($settings['ekit_btn_align'])) {
        $alignment = esc_attr($settings['ekit_btn_align']);
        $button_attrs .= " button_alignment=\"{$alignment}\"";
    }
    
    // New tab target
    if (isset($settings['ekit_btn_url']['is_external']) && $settings['ekit_btn_url']['is_external']) {
        $button_attrs .= " url_new_window=\"on\"";
    }
    
    return "[et_pb_button button_text=\"{$button_text}\" button_url=\"{$button_url}\" _builder_version=\"{$builder_version}\"{$button_attrs}][/et_pb_button]\n";
}

/**
 * Create a Divi icon box (blurb) module from ElementsKit icon box
 * 
 * @param array $settings ElementsKit icon box settings
 * @param string $builder_version Divi builder version
 * @return string Divi blurb module shortcode
 */
function jhmgced_create_elementskit_icon_box_module($settings, $builder_version) {
    $title = isset($settings['ekit_iconbox_title_text']) ? esc_html($settings['ekit_iconbox_title_text']) : '';
    $description = isset($settings['ekit_iconbox_description_text']) ? wp_kses_post($settings['ekit_iconbox_description_text']) : '';
    
    $blurb_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Set title attribute
    if (!empty($title)) {
        $blurb_attrs .= " title=\"{$title}\"";
    }
    
    // Handle icon placement
    $icon_position = 'top';
    if (isset($settings['ekit_iconbox_icon_position'])) {
        switch ($settings['ekit_iconbox_icon_position']) {
            case 'left':
                $icon_position = 'left';
                break;
            case 'right':
                $icon_position = 'right';
                break;
            default:
                $icon_position = 'top';
        }
    }
    $blurb_attrs .= " icon_placement=\"{$icon_position}\"";
    
    // Handle icon or image
    $use_icon = isset($settings['ekit_iconbox_enable_icon']) && $settings['ekit_iconbox_enable_icon'] === 'icon';
    $use_image = isset($settings['ekit_iconbox_enable_icon']) && $settings['ekit_iconbox_enable_icon'] === 'image';
    
    if ($use_icon) {
        // Map ElementsKit icon to Divi icon
        $blurb_attrs .= " use_icon=\"on\"";
        
        // Default icon
        $icon = '%%1%%';
        
        // Try to get icon class
        if (isset($settings['ekit_iconbox_icon']) && isset($settings['ekit_iconbox_icon']['value'])) {
            $icon_class = $settings['ekit_iconbox_icon']['value'];
            
            // Map common icons to Divi's built-in icons
            if (strpos($icon_class, 'fa-facebook') !== false) {
                $icon = '%%3%%'; // Facebook in Divi
            } elseif (strpos($icon_class, 'fa-twitter') !== false) {
                $icon = '%%4%%'; // Twitter in Divi
            } elseif (strpos($icon_class, 'fa-envelope') !== false) {
                $icon = '%%5%%'; // Email in Divi
            } elseif (strpos($icon_class, 'fa-rss') !== false) {
                $icon = '%%6%%'; // RSS in Divi
            } else {
                // For other icons, we'll use a generic Divi icon
                $icon = '%%1%%';
            }
        }
        
        $blurb_attrs .= " font_icon=\"{$icon}\"";
        
        // Handle icon color
        if (isset($settings['ekit_iconbox_icon_color'])) {
            $icon_color = esc_attr($settings['ekit_iconbox_icon_color']);
            $blurb_attrs .= " icon_color=\"{$icon_color}\"";
        }
    } 
    elseif ($use_image && isset($settings['ekit_iconbox_image']) && isset($settings['ekit_iconbox_image']['url'])) {
        // Use image instead of icon
        $image_url = esc_url($settings['ekit_iconbox_image']['url']);
        $blurb_attrs .= " image=\"{$image_url}\" use_icon=\"off\"";
    }
    
    // Handle URL if present
    if (isset($settings['ekit_iconbox_link']) && isset($settings['ekit_iconbox_link']['url'])) {
        $url = esc_url($settings['ekit_iconbox_link']['url']);
        $blurb_attrs .= " url=\"{$url}\"";
        
        // Check for new window
        if (isset($settings['ekit_iconbox_link']['is_external']) && $settings['ekit_iconbox_link']['is_external']) {
            $blurb_attrs .= " url_new_window=\"on\"";
        }
    }
    
    return "[et_pb_blurb{$blurb_attrs}]{$description}[/et_pb_blurb]\n";
}

/**
 * Create a Divi toggle module from ElementsKit FAQ
 * 
 * @param array $settings ElementsKit FAQ settings
 * @param string $builder_version Divi builder version
 * @return string Divi toggle module shortcode
 */
function jhmgced_create_elementskit_faq_module($settings, $builder_version) {
    // Create toggle shortcodes for each FAQ item
    $output = "";
    
    if (isset($settings['ekit_faq_items']) && is_array($settings['ekit_faq_items'])) {
        foreach ($settings['ekit_faq_items'] as $item) {
            $title = isset($item['ekit_faq_title']) ? esc_html($item['ekit_faq_title']) : 'FAQ Question';
            $content = isset($item['ekit_faq_content']) ? wp_kses_post($item['ekit_faq_content']) : 'FAQ Answer';
            
            // Check if this item should be open by default
            $is_active = isset($item['ekit_faq_active']) && $item['ekit_faq_active'] === 'yes';
            $open_attr = $is_active ? ' open="on"' : '';
            
            $output .= "[et_pb_toggle title=\"{$title}\"{$open_attr} _builder_version=\"{$builder_version}\"]\n{$content}\n[/et_pb_toggle]\n";
        }
    } else {
        // Default FAQ item
        $output .= "[et_pb_toggle title=\"FAQ Question\" _builder_version=\"{$builder_version}\"]FAQ Answer[/et_pb_toggle]\n";
    }
    
    return $output;
}

/**
 * Create a Divi progress bar module from ElementsKit progress bar
 * 
 * @param array $settings ElementsKit progress bar settings
 * @param string $builder_version Divi builder version
 * @return string Divi bar counters module shortcode
 */
function jhmgced_create_elementskit_progress_bar_module($settings, $builder_version) {
    $bar_counters = "[et_pb_bar_counters _builder_version=\"{$builder_version}\"]\n";
    
    if (isset($settings['ekit_pbar_items']) && is_array($settings['ekit_pbar_items'])) {
        foreach ($settings['ekit_pbar_items'] as $item) {
            $title = isset($item['ekit_pbar_title']) ? esc_html($item['ekit_pbar_title']) : 'Skill';
            $percent = isset($item['ekit_pbar_percentage']['size']) ? intval($item['ekit_pbar_percentage']['size']) : 50;
            
            // Bar color
            $bar_attr = '';
            if (isset($item['ekit_pbar_bg_color'])) {
                $bar_color = esc_attr($item['ekit_pbar_bg_color']);
                $bar_attr .= " bar_background_color=\"{$bar_color}\"";
            }
            
            $bar_counters .= "[et_pb_counter percent=\"{$percent}\" _builder_version=\"{$builder_version}\"{$bar_attr}]{$title}[/et_pb_counter]\n";
        }
    } else {
        // Default bar counter
        $bar_counters .= "[et_pb_counter percent=\"50\" _builder_version=\"{$builder_version}\"]Skill[/et_pb_counter]\n";
    }
    
    $bar_counters .= "[/et_pb_bar_counters]\n";
    return $bar_counters;
}

/**
 * Create a Divi gallery module from ElementsKit gallery
 * 
 * @param array $settings ElementsKit gallery settings
 * @param string $builder_version Divi builder version
 * @return string Divi gallery module shortcode
 */
function jhmgced_create_elementskit_gallery_module($settings, $builder_version) {
    $gallery_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Set gallery layout based on ElementsKit style
    $gallery_layout = 'grid';
    if (isset($settings['ekit_gallery_style']) && $settings['ekit_gallery_style'] === 'masonry') {
        $gallery_layout = 'masonry';
    } elseif (isset($settings['ekit_gallery_style']) && $settings['ekit_gallery_style'] === 'carousel') {
        $gallery_layout = 'slider';
    }
    
    $gallery_attrs .= " gallery_layout=\"{$gallery_layout}\"";
    
    // Set columns
    if (isset($settings['ekit_gallery_columns'])) {
        $columns = intval($settings['ekit_gallery_columns']);
        if ($columns < 1 || $columns > 6) {
            $columns = 3; // Limit to valid Divi values
        }
        $gallery_attrs .= " gallery_columns=\"{$columns}\"";
    }
    
    // Process gallery images
    $image_ids = array();
    if (isset($settings['ekit_gallery_items']) && is_array($settings['ekit_gallery_items'])) {
        foreach ($settings['ekit_gallery_items'] as $item) {
            if (isset($item['ekit_gallery_img']) && isset($item['ekit_gallery_img']['id'])) {
                $image_ids[] = $item['ekit_gallery_img']['id'];
            }
        }
    }
    
    // If we found image IDs, add them to gallery
    if (!empty($image_ids)) {
        $gallery_attrs .= " gallery_ids=\"" . implode(',', $image_ids) . "\"";
        return "[et_pb_gallery{$gallery_attrs}][/et_pb_gallery]\n";
    } 
    // If no images IDs but we have URLs
    elseif (isset($settings['ekit_gallery_items']) && is_array($settings['ekit_gallery_items'])) {
        // Create a text module with images
        $content = "<div class=\"imported-gallery-grid\">\n";
        
        foreach ($settings['ekit_gallery_items'] as $item) {
            if (isset($item['ekit_gallery_img']) && isset($item['ekit_gallery_img']['url'])) {
                $url = esc_url($item['ekit_gallery_img']['url']);
                $alt = isset($item['ekit_gallery_img']['alt']) ? esc_attr($item['ekit_gallery_img']['alt']) : '';
                // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                $content .= "  <div class=\"gallery-item\"><img src=\"" . esc_url($url) . "\" alt=\"" . esc_attr($alt) . "\" /></div>\n";
            }
        }
        
        $content .= "</div>\n";
        $content .= "<p><em>This gallery was imported from ElementsKit. You may need to adjust the gallery settings in Divi.</em></p>\n";
        
        return "[et_pb_text _builder_version=\"{$builder_version}\"]\n{$content}[/et_pb_text]\n";
    } 
    // Default empty gallery
    else {
        return "[et_pb_gallery{$gallery_attrs}][/et_pb_gallery]\n";
    }
}

/**
 * Create a Divi pricing table module from ElementsKit pricing table
 * 
 * @param array $settings ElementsKit pricing table settings
 * @param string $builder_version Divi builder version
 * @return string Divi pricing table module shortcode
 */
function jhmgced_create_elementskit_pricing_table_module($settings, $builder_version) {
    $pricing_table = "[et_pb_pricing_tables _builder_version=\"{$builder_version}\"]\n";
    
    // Get pricing table details
    $title = isset($settings['ekit_pricing_title']) ? esc_html($settings['ekit_pricing_title']) : 'Basic Plan';
    $subtitle = isset($settings['ekit_pricing_subtitle']) ? esc_html($settings['ekit_pricing_subtitle']) : '';
    
    // Get price
    $currency = isset($settings['ekit_pricing_currency']) ? esc_html($settings['ekit_pricing_currency']) : '$';
    $price = isset($settings['ekit_pricing_price']) ? esc_html($settings['ekit_pricing_price']) : '0';
    $period = isset($settings['ekit_pricing_period']) ? esc_html($settings['ekit_pricing_period']) : '/mo';
    
    // Check for featured table
    $featured = isset($settings['ekit_pricing_featured']) && $settings['ekit_pricing_featured'] === 'yes' ? ' featured="on"' : '';
    
    // Get button details
    $button_text = isset($settings['ekit_pricing_btn_text']) ? esc_html($settings['ekit_pricing_btn_text']) : 'Buy Now';
    $button_url = '#';
    
    if (isset($settings['ekit_pricing_btn_link']) && isset($settings['ekit_pricing_btn_link']['url'])) {
        $button_url = esc_url($settings['ekit_pricing_btn_link']['url']);
    }
    
    // Create pricing table item
    $pricing_table .= "[et_pb_pricing_table title=\"{$title}\" subtitle=\"{$subtitle}\" currency=\"{$currency}\" sum=\"{$price}\" per=\"{$period}\" button_url=\"{$button_url}\" button_text=\"{$button_text}\" _builder_version=\"{$builder_version}\"{$featured}]\n";
    
    // Add features list
    if (isset($settings['ekit_pricing_features']) && is_array($settings['ekit_pricing_features'])) {
        foreach ($settings['ekit_pricing_features'] as $feature) {
            if (isset($feature['ekit_pricing_feature']) && !empty($feature['ekit_pricing_feature'])) {
                $pricing_table .= esc_html($feature['ekit_pricing_feature']) . "\n";
            }
        }
    } else {
        // Add default features
        $pricing_table .= "Feature 1\nFeature 2\nFeature 3\n";
    }
    
    $pricing_table .= "[/et_pb_pricing_table]\n";
    $pricing_table .= "[/et_pb_pricing_tables]\n";
    return $pricing_table;
}

/**
 * Create a Divi social media module from ElementsKit social media
 * 
 * @param array $settings ElementsKit social media settings
 * @param string $builder_version Divi builder version
 * @return string Divi social media follow module shortcode
 */
function jhmgced_create_elementskit_social_media_module($settings, $builder_version) {
    $social_follow = "[et_pb_social_media_follow _builder_version=\"{$builder_version}\"]\n";
    
    if (isset($settings['ekit_social_icons']) && is_array($settings['ekit_social_icons'])) {
        foreach ($settings['ekit_social_icons'] as $social) {
            $network = 'facebook';
            $url = '#';
            
            // Get the social network
            if (isset($social['ekit_social_icon']) && isset($social['ekit_social_icon']['value'])) {
                $icon_value = $social['ekit_social_icon']['value'];
                
                // Map social network based on icon
                if (strpos($icon_value, 'facebook') !== false) {
                    $network = 'facebook';
                } elseif (strpos($icon_value, 'twitter') !== false) {
                    $network = 'twitter';
                } elseif (strpos($icon_value, 'instagram') !== false) {
                    $network = 'instagram';
                } elseif (strpos($icon_value, 'linkedin') !== false) {
                    $network = 'linkedin';
                } elseif (strpos($icon_value, 'youtube') !== false) {
                    $network = 'youtube';
                } elseif (strpos($icon_value, 'pinterest') !== false) {
                    $network = 'pinterest';
                }
            }
            
            // Get social URL
            if (isset($social['ekit_social_link']) && isset($social['ekit_social_link']['url'])) {
                $url = esc_url($social['ekit_social_link']['url']);
            }
            
            // Network specific color
            $network_attr = '';
            if (isset($social['ekit_social_icon_bg_color'])) {
                $bg_color = esc_attr($social['ekit_social_icon_bg_color']);
                $network_attr .= " custom_bg=\"{$bg_color}\"";
            }
            
            $social_follow .= "[et_pb_social_media_follow_network social_network=\"{$network}\" url=\"{$url}\" _builder_version=\"{$builder_version}\"{$network_attr}] {$network} [/et_pb_social_media_follow_network]\n";
        }
    } else {
        // Default social icon
        $social_follow .= "[et_pb_social_media_follow_network social_network=\"facebook\" url=\"#\" _builder_version=\"{$builder_version}\"] facebook [/et_pb_social_media_follow_network]\n";
    }
    
    $social_follow .= "[/et_pb_social_media_follow]\n";
    return $social_follow;
}

/**
 * Create a Divi countdown module from ElementsKit countdown timer
 * 
 * @param array $settings ElementsKit countdown settings
 * @param string $builder_version Divi builder version
 * @return string Divi countdown module shortcode
 */
function jhmgced_create_elementskit_countdown_module($settings, $builder_version) {
    $countdown_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Get date in format Divi expects (YYYY-MM-DD HH:MM:SS)
    $date = '';
    
    if (isset($settings['ekit_countdown_due_date'])) {
        $date = esc_attr($settings['ekit_countdown_due_date']);
    }
    
    // If no date provided, set a default date 7 days from now
    if (empty($date)) {
        $date = gmdate('Y-m-d H:i', strtotime('+7 days'));
    }
    
    $countdown_attrs .= " date=\"{$date}\"";
    
    // Title
    if (isset($settings['ekit_countdown_title'])) {
        $title = esc_html($settings['ekit_countdown_title']);
        $countdown_attrs .= " title=\"{$title}\"";
    }
    
    // Background color
    if (isset($settings['ekit_countdown_bg_color'])) {
        $bg_color = esc_attr($settings['ekit_countdown_bg_color']);
        $countdown_attrs .= " background_color=\"{$bg_color}\"";
    }
    
    return "[et_pb_countdown_timer{$countdown_attrs}][/et_pb_countdown_timer]\n";
}

/**
 * Create a Divi team member module from ElementsKit team
 * 
 * @param array $settings ElementsKit team settings
 * @param string $builder_version Divi builder version
 * @return string Divi team member module shortcode
 */
function jhmgced_create_elementskit_team_module($settings, $builder_version) {
    // Get team member details
    $name = isset($settings['ekit_team_name']) ? esc_html($settings['ekit_team_name']) : 'Team Member';
    $position = isset($settings['ekit_team_position']) ? esc_html($settings['ekit_team_position']) : '';
    $content = isset($settings['ekit_team_description']) ? wp_kses_post($settings['ekit_team_description']) : '';
    
    // Team member attributes
    $team_attrs = " _builder_version=\"{$builder_version}\" name=\"{$name}\"";
    
    if (!empty($position)) {
        $team_attrs .= " position=\"{$position}\"";
    }
    
    // Get image if available
    if (isset($settings['ekit_team_image']) && isset($settings['ekit_team_image']['url'])) {
        $image_url = esc_url($settings['ekit_team_image']['url']);
        $team_attrs .= " image_url=\"{$image_url}\"";
    }
    
    // Process social networks if available
    $team_social = '';
    if (isset($settings['ekit_team_social_icons']) && is_array($settings['ekit_team_social_icons'])) {
        foreach ($settings['ekit_team_social_icons'] as $social) {
            if (isset($social['ekit_team_social_link']) && isset($social['ekit_team_social_link']['url'])) {
                $url = esc_url($social['ekit_team_social_link']['url']);
                $network = 'facebook'; // Default
                
                // Try to determine network from icon
                if (isset($social['ekit_team_social_icon']) && isset($social['ekit_team_social_icon']['value'])) {
                    $icon = $social['ekit_team_social_icon']['value'];
                    
                    if (strpos($icon, 'facebook') !== false) {
                        $network = 'facebook';
                    } elseif (strpos($icon, 'twitter') !== false) {
                        $network = 'twitter';
                    } elseif (strpos($icon, 'google') !== false) {
                        $network = 'google-plus';
                    } elseif (strpos($icon, 'linkedin') !== false) {
                        $network = 'linkedin';
                    }
                }
                
                $team_social .= " {$network}_url=\"{$url}\"";
            }
        }
    }
    
    return "[et_pb_team_member{$team_attrs}{$team_social}]\n{$content}\n[/et_pb_team_member]\n";
}

/**
 * Create a Divi image module from ElementsKit image
 * 
 * @param array $settings ElementsKit image settings
 * @param string $builder_version Divi builder version
 * @return string Divi image module shortcode
 */
function jhmgced_create_elementskit_image_module($settings, $builder_version) {
    $image_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Get image URL
    $image_url = '';
    if (isset($settings['ekit_img_image']) && isset($settings['ekit_img_image']['url'])) {
        $image_url = esc_url($settings['ekit_img_image']['url']);
    }
    
    // If no image, use Divi placeholder
    if (empty($image_url)) {
        $image_url = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTA4MCIgaGVpZ2h0PSI1NDAiIHZpZXdCb3g9IjAgMCAxMDgwIDU0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxnIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPHBhdGggZmlsbD0iI0VCRUJFQiIgZD0iTTAgMGgxMDgwdjU0MEgweiIvPgogICAgICAgIDxwYXRoIGQ9Ik00NDUuNjQ5IDU0MGgtOTguOTk1TDE0NC42NDkgMzM3Ljk5NSAwIDQ4Mi42NDR2LTk4Ljk5NWwxMTYuMzY1LTExNi4zNjVjMTUuNjItMTUuNjIgNDAuOTQ3LTE1LjYyIDU2LjU2OCAwTDQ0NS42NSA1NDB6IiBmaWxsLW9wYWNpdHk9Ii4xIiBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9Im5vbnplcm8iLz4KICAgICAgICA8Y2lyY2xlIGZpbGwtb3BhY2l0eT0iLjA1IiBmaWxsPSIjMDAwIiBjeD0iMzMxIiBjeT0iMTQ4IiByPSI3MCIvPgogICAgICAgIDxwYXRoIGQ9Ik0xMDgwIDM3OXYxMTMuMTM3TDcyOC4xNjIgMTQwLjMgMzI4LjQ2MiA1NDBIMjE1LjMyNEw2OTkuODc4IDU1LjQ0NmMxNS42Mi0xNS42MiA0MC45NDgtMTUuNjIgNTYuNTY4IDBMMTA4MCAzNzl6IiBmaWxsLW9wYWNpdHk9Ii4yIiBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9Im5vbnplcm8iLz4KICAgIDwvZz4KPC9zdmc+Cg==';
    }
    
    $image_attrs .= " src=\"{$image_url}\"";
    
    // Alt text
    if (isset($settings['ekit_img_image']['alt'])) {
        $alt_text = esc_attr($settings['ekit_img_image']['alt']);
        $image_attrs .= " alt=\"{$alt_text}\"";
    }
    
    // Get link if available
    if (isset($settings['ekit_img_link']) && isset($settings['ekit_img_link']['url'])) {
        $url = esc_url($settings['ekit_img_link']['url']);
        $image_attrs .= " url=\"{$url}\"";
        
        // Check if link opens in new window
        if (isset($settings['ekit_img_link']['is_external']) && $settings['ekit_img_link']['is_external']) {
            $image_attrs .= " url_new_window=\"on\"";
        }
    }
    
    // Handle alignment
    if (isset($settings['ekit_img_alignment'])) {
        $alignment = esc_attr($settings['ekit_img_alignment']);
        $image_attrs .= " align=\"{$alignment}\"";
    }
    
    return "[et_pb_image{$image_attrs}][/et_pb_image]\n";
}

/**
 * Create a Divi map module from ElementsKit Google Map
 * 
 * @param array $settings ElementsKit Google Map settings
 * @param string $builder_version Divi builder version
 * @return string Divi map module shortcode
 */
function jhmgced_create_elementskit_google_map_module($settings, $builder_version) {
    $map_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Get address
    $address = '';
    if (isset($settings['ekit_google_map_address'])) {
        $address = esc_attr($settings['ekit_google_map_address']);
    }
    
    $map_attrs .= " address=\"{$address}\"";
    
    // Get zoom level
    if (isset($settings['ekit_google_map_zoom']['size'])) {
        $zoom = intval($settings['ekit_google_map_zoom']['size']);
        $map_attrs .= " zoom_level=\"{$zoom}\"";
    }
    
    // Map type
    if (isset($settings['ekit_google_map_type'])) {
        $map_type = esc_attr($settings['ekit_google_map_type']);
        $map_attrs .= " map_type=\"{$map_type}\"";
    }
    
    // Mouse wheel
    if (isset($settings['ekit_google_map_scroll_zoom']) && $settings['ekit_google_map_scroll_zoom'] === 'yes') {
        $map_attrs .= " mouse_wheel=\"on\"";
    }
    
    return "[et_pb_map{$map_attrs}][/et_pb_map]\n";
}

/**
 * Create a single Divi button from ElementsKit dual button
 * 
 * @param array $settings ElementsKit dual button settings
 * @param string $builder_version Divi builder version
 * @return string Single Divi button module
 */
function jhmgced_create_elementskit_dual_button_module($settings, $builder_version) {
    // We'll only use the primary button from the dual button widget
    if (isset($settings['ekit_dual_button_primary_text'])) {
        $button_text = esc_html($settings['ekit_dual_button_primary_text']);
        $button_url = '#';
        
        if (isset($settings['ekit_dual_button_primary_link']) && isset($settings['ekit_dual_button_primary_link']['url'])) {
            $button_url = esc_url($settings['ekit_dual_button_primary_link']['url']);
        }
        
        // Button styling
        $button_attrs = '';
        
        if (isset($settings['ekit_dual_button_primary_bg_color'])) {
            $bg_color = esc_attr($settings['ekit_dual_button_primary_bg_color']);
            $button_attrs .= " button_bg_color=\"{$bg_color}\"";
        }
        
        if (isset($settings['ekit_dual_button_primary_text_color'])) {
            $text_color = esc_attr($settings['ekit_dual_button_primary_text_color']);
            $button_attrs .= " button_text_color=\"{$text_color}\"";
        }
        
        // Add a note in the button text that this was a dual button in Elementor
        $button_text_with_note = $button_text;
        
        return "[et_pb_button button_text=\"{$button_text_with_note}\" button_url=\"{$button_url}\" _builder_version=\"{$builder_version}\"{$button_attrs}][/et_pb_button]\n";
    }
    
    // Fallback if primary button info isn't available
    return "[et_pb_button button_text=\"Button\" button_url=\"#\" _builder_version=\"{$builder_version}\"][/et_pb_button]\n";
}

/**
 * Create a Divi video module from ElementsKit video
 * 
 * @param array $settings ElementsKit video settings
 * @param string $builder_version Divi builder version
 * @return string Divi video module shortcode
 */
function jhmgced_create_elementskit_video_module($settings, $builder_version) {
    $video_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Get video URL
    $video_url = '';
    
    if (isset($settings['ekit_video_url'])) {
        $video_url = esc_url($settings['ekit_video_url']);
    }
    
    if (empty($video_url)) {
        return "[et_pb_text _builder_version=\"{$builder_version}\"]\n<p>No video URL found in ElementsKit widget</p>\n[/et_pb_text]\n";
    }
    
    $video_attrs .= " src=\"{$video_url}\"";
    
    // Image overlay
    if (isset($settings['ekit_video_image_overlay']) && isset($settings['ekit_video_image_overlay']['url'])) {
        $image_url = esc_url($settings['ekit_video_image_overlay']['url']);
        $video_attrs .= " image_src=\"{$image_url}\"";
    }
    
    return "[et_pb_video{$video_attrs}][/et_pb_video]\n";
}

/**
 * Create a Divi audio module from ElementsKit audio player
 * 
 * @param array $settings ElementsKit audio player settings
 * @param string $builder_version Divi builder version
 * @return string Divi audio module shortcode
 */
function jhmgced_create_elementskit_audio_player_module($settings, $builder_version) {
    $audio_attrs = " _builder_version=\"{$builder_version}\"";
    
    // Get audio URL
    $audio_url = '';
    if (isset($settings['ekit_audio_file']) && isset($settings['ekit_audio_file']['url'])) {
        $audio_url = esc_url($settings['ekit_audio_file']['url']);
    }
    
    if (empty($audio_url)) {
        return "[et_pb_text _builder_version=\"{$builder_version}\"]\n<p>No audio file found in ElementsKit widget</p>\n[/et_pb_text]\n";
    }
    
    $audio_attrs .= " audio=\"{$audio_url}\"";
    
    // Get title
    if (isset($settings['ekit_audio_title'])) {
        $title = esc_html($settings['ekit_audio_title']);
        $audio_attrs .= " title=\"{$title}\"";
    }
    
    // Get artist
    if (isset($settings['ekit_audio_artist'])) {
        $artist = esc_html($settings['ekit_audio_artist']);
        $audio_attrs .= " artist_name=\"{$artist}\"";
    }
    
    // Cover image
    if (isset($settings['ekit_audio_cover_image']) && isset($settings['ekit_audio_cover_image']['url'])) {
        $image_url = esc_url($settings['ekit_audio_cover_image']['url']);
        $audio_attrs .= " image_url=\"{$image_url}\"";
    }
    
    return "[et_pb_audio{$audio_attrs}][/et_pb_audio]\n";
}