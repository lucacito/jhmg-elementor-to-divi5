<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeSearchConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_search_' );
        $settings = $element['settings'] ?? [];

        $placeholder = is_string( $settings['placeholder'] ?? '' ) ? ( $settings['placeholder'] ?? '' ) : '';

        $block_settings = [];
        if ( $placeholder !== '' ) {
            $block_settings['search'] = [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'placeholder' => $placeholder ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'search' );
        $this->logUnmappedSettings( $id, $settings, [
            'placeholder', 'layout', 'size', 'input_typography',
            'input_width', 'text_color', 'placeholder_color', 'background_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/search',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
