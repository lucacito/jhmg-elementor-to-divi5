<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor HTML widget into a divi/code block.
 *
 * The widget's raw `html` setting is passed through verbatim — inline styles,
 * scripts, and arbitrary markup are preserved so that any custom functionality
 * authored in the original HTML widget survives the conversion.
 */
class HtmlConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];
        $html     = is_string( $settings['html'] ?? '' ) ? ( $settings['html'] ?? '' ) : '';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ 'html' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $html ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
