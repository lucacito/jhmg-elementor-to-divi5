<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Slides widget to divi/slider + divi/slide children.
 *
 * Each Elementor slide item maps to a divi/slide block with title, body copy,
 * optional button, and optional background image.
 */
class SliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_slider_' );
        $settings = $element['settings'] ?? [];

        $raw_slides = $settings['slides'] ?? [];
        $children   = [];

        foreach ( $raw_slides as $idx => $slide ) {
            if ( ! is_array( $slide ) ) {
                continue;
            }

            $title   = is_string( $slide['heading'] ?? '' ) ? ( $slide['heading'] ?? '' ) : '';
            $content = is_string( $slide['description'] ?? '' ) ? ( $slide['description'] ?? '' ) : '';
            $btn     = is_string( $slide['button_text'] ?? '' ) ? ( $slide['button_text'] ?? '' ) : '';

            $link_raw = $slide['link'] ?? [];
            $url      = '';
            if ( is_array( $link_raw ) ) {
                $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $link_raw ) ) {
                $url = $link_raw;
            }

            $bg_image_raw = $slide['background_image'] ?? $slide['image'] ?? null;
            $bg_url       = '';
            if ( is_array( $bg_image_raw ) ) {
                $bg_url = is_string( $bg_image_raw['url'] ?? '' ) ? ( $bg_image_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $bg_image_raw ) ) {
                $bg_url = $bg_image_raw;
            }

            $slide_attrs = [];

            if ( $title !== '' ) {
                $slide_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }

            if ( $content !== '' ) {
                $slide_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            }

            if ( $btn !== '' || $url !== '' ) {
                $btn_value = array_filter( [ 'text' => $btn, 'linkUrl' => $url ] );
                $slide_attrs['button'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $btn_value ] ] ];
            }

            if ( $bg_url !== '' ) {
                $slide_attrs['image'] = [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => $bg_url ] ] ] ];
            }

            $bg_color = is_string( $slide['background_color'] ?? '' ) ? ( $slide['background_color'] ?? '' ) : '';
            if ( $bg_color !== '' ) {
                $slide_attrs['module']['decoration']['background']['desktop']['value']['color'] = $bg_color;
            }

            $children[] = [
                'id'       => $id . '-slide-' . ( $idx + 1 ),
                'name'     => 'divi/slide',
                'settings' => $slide_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'slider' );
        $this->logUnmappedSettings( $id, $settings, [
            'slides',
            'autoplay', 'autoplay_speed', 'pause_on_hover',
            'infinite', 'effect', 'direction',
            'navigation',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/slider',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
