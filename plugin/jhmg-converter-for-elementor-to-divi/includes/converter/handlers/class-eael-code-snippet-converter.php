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

        $code = $settings['code_content'] ?? $settings['eael_code_snippet_code'] ?? '';
        $code = is_string( $code ) ? $code : '';
        // EAEL 6.x's `language` select defaults to 'html', which is left unwrapped below.
        $type = $settings['language'] ?? $settings['eael_code_snippet_type'] ?? '';
        $type = is_string( $type ) ? $type : '';

        // Wrap in <pre><code> when the type is a code language (not HTML output).
        if ( $code !== '' && $type !== '' && $type !== 'html' ) {
            $code = '<pre><code class="language-' . esc_attr( $type ) . '">' . htmlspecialchars( $code ) . '</code></pre>';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'code_content', 'language', 'file_name', 'theme', 'show_line_numbers', 'show_copy_button',
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
