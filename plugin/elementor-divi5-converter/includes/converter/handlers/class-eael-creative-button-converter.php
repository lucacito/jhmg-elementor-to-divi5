<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelCreativeButtonConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_button_' );
        $settings = $element['settings'] ?? [];

        $text    = is_string( $settings['creative_button_text'] ?? '' ) ? ( $settings['creative_button_text'] ?? '' ) : '';
        $url_raw = $settings['creative_button_link_url'] ?? [];
        $url     = '';
        if ( is_array( $url_raw ) ) {
            $url = is_string( $url_raw['url'] ?? '' ) ? ( $url_raw['url'] ?? '' ) : '';
        }

        $block_settings = [];
        if ( $text !== '' ) {
            $block_settings['module'] = [
                'advanced' => [ 'link' => [ 'desktop' => [ 'value' => [ 'text' => $text, 'url' => $url ] ] ] ],
            ];
        }

        $this->engine->logConverted( 'button' );
        $this->logUnmappedSettings( $id, $settings, [
            'creative_button_text', 'creative_button_link_url',
            'creative_button_effect', 'eael_creative_button_icon_new',
            'eael_creative_button_icon_alignment', 'creative_button_secondary_text',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/button',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
