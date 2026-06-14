<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Flip Box → divi/blurb using the front-face content.
 *
 * Divi 5 has no native flip-box module. The front title and text are mapped to
 * the blurb's title and body; the back-face content is appended as a secondary
 * paragraph so no information is silently lost.
 */
class EaelFlipBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $front_title = is_string( $settings['eael_flipbox_front_title'] ?? '' ) ? ( $settings['eael_flipbox_front_title'] ?? '' ) : '';
        $front_text  = is_string( $settings['eael_flipbox_front_text'] ?? '' ) ? ( $settings['eael_flipbox_front_text'] ?? '' ) : '';
        $back_title  = is_string( $settings['eael_flipbox_back_title'] ?? '' ) ? ( $settings['eael_flipbox_back_title'] ?? '' ) : '';
        $back_text   = is_string( $settings['eael_flipbox_back_text'] ?? '' ) ? ( $settings['eael_flipbox_back_text'] ?? '' ) : '';

        $body_parts = array_filter( [ $front_text, $back_title !== '' ? '<strong>' . $back_title . '</strong>' : '', $back_text ] );
        $body       = implode( "\n", $body_parts );

        // Resolve front icon.
        $icon_raw = $settings['eael_flipbox_icon_new'] ?? null;
        $icon     = '';
        if ( is_array( $icon_raw ) ) {
            $icon = is_string( $icon_raw['value'] ?? '' ) ? ( $icon_raw['value'] ?? '' ) : '';
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
            'eael_flipbox_front_title', 'eael_flipbox_front_text',
            'eael_flipbox_back_title', 'eael_flipbox_back_text',
            'eael_flipbox_icon_new', 'eael_flipbox_front_content_type',
            'eael_flipbox_back_content_type', 'eael_flipbox_type',
            'eael_flipbox_event_type', 'eael_flipbox_3d',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
