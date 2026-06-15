<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Flip Box → divi/blurb.
 *
 * Divi 5 has no flip-box module. The front face is mapped to the blurb's
 * title and icon; the back face content is appended to the body so no
 * information is lost.
 */
class FlipBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $front_title = is_string( $settings['title_text_a'] ?? '' ) ? ( $settings['title_text_a'] ?? '' ) : '';
        $front_desc  = is_string( $settings['description_text_a'] ?? '' ) ? ( $settings['description_text_a'] ?? '' ) : '';
        $back_title  = is_string( $settings['title_text_b'] ?? '' ) ? ( $settings['title_text_b'] ?? '' ) : '';
        $back_desc   = is_string( $settings['description_text_b'] ?? '' ) ? ( $settings['description_text_b'] ?? '' ) : '';
        $btn_text    = is_string( $settings['button_text'] ?? '' ) ? ( $settings['button_text'] ?? '' ) : '';

        $btn_url_raw = $settings['button_url'] ?? [];
        $btn_url     = is_array( $btn_url_raw ) ? ( is_string( $btn_url_raw['url'] ?? '' ) ? ( $btn_url_raw['url'] ?? '' ) : '' ) : '';

        $body_parts = array_filter( [
            $front_desc,
            $back_title !== '' ? '<strong>' . $back_title . '</strong>' : '',
            $back_desc,
            ( $btn_text !== '' && $btn_url !== '' ) ? '<a href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_text ) . '</a>' : '',
        ] );
        $body = implode( "\n", $body_parts );

        $icon_raw = $settings['selected_icon_a'] ?? $settings['icon_a'] ?? null;
        $icon     = '';
        if ( is_array( $icon_raw ) ) {
            $icon = is_string( $icon_raw['value'] ?? '' ) ? ( $icon_raw['value'] ?? '' ) : '';
        } elseif ( is_string( $icon_raw ) ) {
            $icon = $icon_raw;
        }

        $block_settings = [];
        if ( $front_title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $front_title ] ] ];
        }
        if ( $icon !== '' ) {
            $block_settings['imageIcon'] = [
                'innerContent' => [ 'desktop' => [ 'value' => [ 'icon' => $icon ] ] ],
            ];
        }
        if ( $body !== '' ) {
            $block_settings['module'] = [
                'advanced' => [ 'text' => [ 'desktop' => [ 'value' => $body ] ] ],
            ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, [
            'title_text_a', 'description_text_a', 'title_text_b', 'description_text_b',
            'button_text', 'button_url', 'selected_icon_a', 'icon_a',
            'flip_effect', 'flip_direction', 'height',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
