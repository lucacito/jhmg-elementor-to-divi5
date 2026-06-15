<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SearchConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_search_' );
        $settings = $element['settings'] ?? [];

        $placeholder = is_string( $settings['placeholder'] ?? '' ) ? ( $settings['placeholder'] ?? '' ) : '';
        $btn_text    = is_string( $settings['button_text'] ?? '' ) ? ( $settings['button_text'] ?? '' ) : '';

        $block_settings = [];
        if ( $placeholder !== '' || $btn_text !== '' ) {
            $value = array_filter( [ 'placeholder' => $placeholder, 'buttonText' => $btn_text ] );
            $block_settings['search'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $value ] ],
            ];
        }

        $this->engine->logConverted( 'search' );
        $this->logUnmappedSettings( $id, $settings, [
            'placeholder', 'button_text', 'skin', 'size',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/search',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
