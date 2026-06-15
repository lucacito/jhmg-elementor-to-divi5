<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Text Path → divi/text.
 *
 * Divi 5 has no text-on-a-path module. The text content is preserved as a
 * plain text block; the path shape is discarded.
 */
class TextPathConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];

        $text = is_string( $settings['text'] ?? '' ) ? ( $settings['text'] ?? '' ) : '';

        $block_settings = [];
        if ( $text !== '' ) {
            $block_settings['content'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $text ] ],
            ];
        }

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'text', 'path_type', 'startpoint', 'direction',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
