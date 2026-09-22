<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--event-slider" widget (Animation Addons for Elementor,
 * event-slider.php) to divi/group-carousel + one divi/group per event, the
 * same target as wcf--image-box-slider: several events can show side by side
 * (slides_to_show, from the shared Aaeaddon_Slider_Trait), which divi/slider's
 * one-full-slide-at-a-time model does not support.
 */
class WcfEventSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $raw_events = is_array( $settings['events'] ?? null ) ? $settings['events'] : [];
        $children   = [];

        foreach ( $raw_events as $idx => $event ) {
            if ( ! is_array( $event ) ) {
                continue;
            }

            $name = is_string( $event['event_name'] ?? '' ) ? ( $event['event_name'] ?? '' ) : '';
            $date = is_string( $event['event_date'] ?? '' ) ? ( $event['event_date'] ?? '' ) : '';
            $desc = is_string( $event['event_desc'] ?? '' ) ? ( $event['event_desc'] ?? '' ) : '';

            $image_raw = $event['event_image'] ?? null;
            $img_url   = is_array( $image_raw ) && is_string( $image_raw['url'] ?? '' ) ? $image_raw['url'] : '';
            $img_alt   = is_array( $image_raw ) && is_string( $image_raw['alt'] ?? '' ) ? $image_raw['alt'] : '';

            $link_raw = $event['event_link'] ?? [];
            $link_url = is_array( $link_raw ) && is_string( $link_raw['url'] ?? '' ) ? $link_raw['url'] : '';

            $group_children = [];
            $group_id       = $id . '-event-' . ( $idx + 1 );

            if ( $img_url !== '' ) {
                $image_value = [ 'src' => $img_url ];
                if ( $img_alt !== '' ) {
                    $image_value['alt'] = $img_alt;
                }
                $group_children[] = [
                    'id'       => $group_id . '-image',
                    'name'     => 'divi/image',
                    'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                    'elements' => [],
                ];
            }

            if ( $name !== '' ) {
                $heading_attrs = [
                    'title' => [
                        'innerContent' => [ 'desktop' => [ 'value' => $name ] ],
                        'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => 'h3' ] ] ] ] ],
                    ],
                ];
                if ( $link_url !== '' ) {
                    $heading_attrs['module']['advanced']['link']['desktop']['value'] = [ 'url' => $link_url ];
                }
                $group_children[] = [
                    'id'       => $group_id . '-name',
                    'name'     => 'divi/heading',
                    'settings' => $heading_attrs,
                    'elements' => [],
                ];
            }

            // divi/group has no dedicated date field; fold it into the text
            // module ahead of the description, the same way EAEL's CTA box
            // subtitle is combined with its body text.
            $content_parts = array_filter( [ $date, $desc ] );
            if ( $content_parts !== [] ) {
                $group_children[] = [
                    'id'       => $group_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                    'elements' => [],
                ];
            }

            $children[] = [
                'id'       => $group_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $group_children,
            ];
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'events',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'autoplay', 'autoplay_delay', 'autoplay_interaction', 'allow_touch_move',
            'loop', 'mousewheel', 'speed', 'space_between', 'enable_grid', 'grid_rows',
            'navigation', 'navigation_previous_icon', 'navigation_next_icon',
            'pagination', 'pagination_type', 'direction',
            'element_list', 'title_tag', 'title_heading_style', 'date_heading_style', 'desc_heading_style',
            'image', 'image_size', 'image_custom_dimension',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
