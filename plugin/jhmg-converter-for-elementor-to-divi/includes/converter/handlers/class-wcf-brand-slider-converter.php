<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--brand-slider" widget (Animation Addons for Elementor,
 * brand-slider.php) to divi/group-carousel + one divi/group per item.
 *
 * `slide_content` picks between two entirely different shapes: 'image' reads a
 * GALLERY control (wcf_brand_carousel, a flat array of attachment objects,
 * same shape as Elementor's own Gallery widget); 'text' reads a REPEATER
 * (repeat_list_text) of plain strings. Both share divi/group-carousel's
 * settings via Aaeaddon_Slider_Trait.
 */
class WcfBrandSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];
        $mode     = is_string( $settings['slide_content'] ?? null ) ? ( $settings['slide_content'] ?? '' ) : 'text';

        $children = $mode === 'image'
            ? $this->imageChildren( $id, $settings['wcf_brand_carousel'] ?? [] )
            : $this->textChildren( $id, $settings['repeat_list_text'] ?? [] );

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'slide_content', 'wcf_brand_carousel', 'repeat_list_text', 'separator_icon',
            'thumbnail_size', 'thumbnail_custom_dimension',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile', 'auto_slide_width',
            'autoplay', 'autoplay_delay', 'autoplay_interaction', 'allow_touch_move',
            'loop', 'mousewheel', 'speed', 'space_between', 'enable_grid', 'grid_rows',
            'navigation', 'navigation_previous_icon', 'navigation_next_icon',
            'pagination', 'pagination_type', 'direction',
            'item_bg_color', 'title_color', 'separator_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }

    private function imageChildren( string $id, mixed $raw_images ): array {
        if ( ! is_array( $raw_images ) ) {
            return [];
        }

        $children = [];
        foreach ( $raw_images as $idx => $attachment ) {
            if ( ! is_array( $attachment ) ) {
                continue;
            }
            $url = is_string( $attachment['url'] ?? null ) ? $attachment['url'] : '';
            if ( $url === '' ) {
                continue;
            }
            $image_value = [ 'src' => $url ];
            $alt         = is_string( $attachment['alt'] ?? null ) ? $attachment['alt'] : '';
            if ( $alt !== '' ) {
                $image_value['alt'] = $alt;
            }

            $children[] = [
                'id'       => $id . '-brand-' . ( $idx + 1 ),
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => [ [
                    'id'       => $id . '-brand-' . ( $idx + 1 ) . '-image',
                    'name'     => 'divi/image',
                    'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                    'elements' => [],
                ] ],
            ];
        }
        return $children;
    }

    private function textChildren( string $id, mixed $raw_items ): array {
        if ( ! is_array( $raw_items ) ) {
            return [];
        }

        $children = [];
        foreach ( $raw_items as $idx => $item ) {
            $text = is_array( $item ) && is_string( $item['list_text'] ?? null ) ? $item['list_text'] : '';
            if ( $text === '' ) {
                continue;
            }

            $children[] = [
                'id'       => $id . '-brand-' . ( $idx + 1 ),
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => [ [
                    'id'       => $id . '-brand-' . ( $idx + 1 ) . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => "<p>{$text}</p>" ] ] ] ],
                    'elements' => [],
                ] ],
            ];
        }
        return $children;
    }
}
