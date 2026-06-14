<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelCodeSnippetConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $code = is_string( $settings['eael_code_snippet_code'] ?? '' ) ? ( $settings['eael_code_snippet_code'] ?? '' ) : '';
        $type = is_string( $settings['eael_code_snippet_type'] ?? '' ) ? ( $settings['eael_code_snippet_type'] ?? '' ) : '';

        // Wrap in <pre><code> when the type is a code language (not HTML output).
        if ( $code !== '' && $type !== '' && $type !== 'html' ) {
            $code = '<pre><code class="language-' . esc_attr( $type ) . '">' . htmlspecialchars( $code ) . '</code></pre>';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_code_snippet_code', 'eael_code_snippet_type',
            'eael_code_snippet_theme', 'eael_code_snippet_line_numbers',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $code ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
