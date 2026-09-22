<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts "wcf--a-testimonial" (Animation Addons for Elementor,
 * advanced-testimonial.php) to divi/group-carousel + one divi/group per
 * testimonial, each holding a divi/testimonial child.
 *
 * Its own `testimonials` repeater uses tsm_* field names, not
 * TestimonialConverter's testimonial_* ones: tsm_reason (a short lead-in,
 * rendered before the main text) folds into content ahead of tsm_content;
 * tsm_name/tsm_role/tsm_image map to author/jobTitle/portrait as usual.
 * tsm_quote (a decorative quote-mark icon) and tsm_rating (a star rating) have
 * no divi/testimonial field and are reported rather than dropped silently.
 */
class WcfAdvancedTestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $raw_items = is_array( $settings['testimonials'] ?? null ) ? $settings['testimonials'] : [];
        $children  = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            // reason_show/client_img_show gate whether tsm_reason/tsm_image are
            // shown at all (advanced-testimonial.php:1118,1128) — a widget-level
            // toggle, not per-item, but it decides what the page actually showed.
            $reason  = ( $settings['reason_show'] ?? '' ) === 'yes' && is_string( $item['tsm_reason'] ?? null ) ? $item['tsm_reason'] : '';
            $content = is_string( $item['tsm_content'] ?? null ) ? $item['tsm_content'] : '';
            $name    = is_string( $item['tsm_name'] ?? null ) ? $item['tsm_name'] : '';
            $role    = is_string( $item['tsm_role'] ?? null ) ? $item['tsm_role'] : '';

            $image_url = '';
            if ( ( $settings['client_img_show'] ?? '' ) === 'yes' ) {
                $image_raw = $item['tsm_image'] ?? null;
                $image_url = is_array( $image_raw ) && is_string( $image_raw['url'] ?? null ) ? $image_raw['url'] : '';
            }

            $rating = ( $settings['rating_show'] ?? '' ) === 'yes' ? ( $item['tsm_rating'] ?? null ) : null;
            if ( $rating !== null && $rating !== '' ) {
                $this->engine->logNotCarriedOver( 'testimonial_rating', $id . '-item-' . ( $idx + 1 ), 'star rating ' . ( is_scalar( $rating ) ? (string) $rating : '' ) . ' has no divi/testimonial field' );
            }

            $testimonial_attrs = [];
            $combined_content  = trim( implode( ' ', array_filter( [ $reason, $content ] ) ) );
            if ( $combined_content !== '' ) {
                $testimonial_attrs['content']['innerContent']['desktop']['value'] = $combined_content;
            }
            if ( $name !== '' ) {
                $testimonial_attrs['author']['innerContent']['desktop']['value'] = $name;
            }
            if ( $role !== '' ) {
                $testimonial_attrs['jobTitle']['innerContent']['desktop']['value'] = $role;
            }
            if ( $image_url !== '' ) {
                $testimonial_attrs['portrait']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
            }

            $group_id   = $id . '-item-' . ( $idx + 1 );
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
            'testimonials', 'element_list', 'quote_show', 'client_img_show', 'rating_show', 'reason_show',
            'slide_sep_color', 'feedback_color', 'rating_color', 'reason_color', 'separator_color',
            'name_heading', 'name_color', 'role_heading', 'role_color', 'client_img_heading',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'autoplay', 'autoplay_delay', 'autoplay_interaction', 'allow_touch_move',
            'loop', 'mousewheel', 'speed', 'space_between', 'direction',
            'navigation', 'navigation_previous_icon', 'navigation_next_icon',
            'pagination', 'pagination_type',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
