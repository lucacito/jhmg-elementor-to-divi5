<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Animation Addons' testimonial carousel widgets — wcf--testimonial,
 * wcf--testimonial2, wcf--testimonial3 (testimonial.php, testimonial2.php,
 * testimonial3.php) — to divi/group-carousel + one divi/group per testimonial,
 * each holding a divi/testimonial child.
 *
 * All three share the same `testimonials` repeater and the same item field
 * names (testimonial_content/testimonial_name/testimonial_job/
 * testimonial_image) as TestimonialConverter already reads for Elementor's own
 * single-item Testimonial widget — testimonial3 simply omits the image field,
 * handled the same way TestimonialConverter already handles a missing image.
 */
class WcfTestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $raw_items = is_array( $settings['testimonials'] ?? null ) ? $settings['testimonials'] : [];
        $children  = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $content = is_string( $item['testimonial_content'] ?? null ) ? $item['testimonial_content'] : '';
            $name    = is_string( $item['testimonial_name'] ?? null ) ? $item['testimonial_name'] : '';
            $job     = is_string( $item['testimonial_job'] ?? null ) ? $item['testimonial_job'] : '';

            $image_raw = $item['testimonial_image'] ?? null;
            $image_url = is_array( $image_raw ) && is_string( $image_raw['url'] ?? null ) ? $image_raw['url'] : '';

            $testimonial_attrs = [];
            if ( $content !== '' ) {
                $testimonial_attrs['content']['innerContent']['desktop']['value'] = $content;
            }
            if ( $name !== '' ) {
                $testimonial_attrs['author']['innerContent']['desktop']['value'] = $name;
            }
            if ( $job !== '' ) {
                $testimonial_attrs['jobTitle']['innerContent']['desktop']['value'] = $job;
            }
            if ( $image_url !== '' ) {
                $testimonial_attrs['portrait']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
            }

            $group_id = $id . '-item-' . ( $idx + 1 );
            $children[] = [
                'id'       => $group_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => [ [
                    'id'       => $group_id . '-testimonial',
                    'name'     => 'divi/testimonial',
                    'settings' => $testimonial_attrs,
                    'elements' => [],
                ] ],
            ];
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'testimonials', 'element_list', 'link', 'quote_icon',
            'testimonial_sect_title', 'header_size',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'autoplay', 'autoplay_delay', 'autoplay_interaction', 'allow_touch_move',
            'loop', 'mousewheel', 'speed', 'space_between', 'direction',
            'navigation', 'navigation_previous_icon', 'navigation_next_icon',
            'pagination', 'pagination_type',
            'section_border_color', 'content_content_color', 'name_text_color', 'job_text_color',
            'sec_title_color', 'quote_color', 'image_width',
            'heading_style_arrows', 'arrows_b_radius', 'arrows_size', 'arrows_color',
            'arrows_h_color', 'arrows_hb_color', 'arrows_hover_color',
            'heading_style_dots', 'dots_inactive_color', 'dots_color', 'dots_size',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
