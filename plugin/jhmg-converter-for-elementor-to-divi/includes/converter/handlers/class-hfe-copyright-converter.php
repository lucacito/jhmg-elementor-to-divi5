<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeCopyrightConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];

        $text = is_string( $settings['copyright_text'] ?? '' ) ? ( $settings['copyright_text'] ?? '' ) : '';

        // Resolve the [hfe_current_year] shortcode when running inside WordPress.
        if ( function_exists( 'do_shortcode' ) ) {
            $text = do_shortcode( $text );
        }

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'copyright_text', 'link', 'alignment', 'text_color', 'caption_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $text ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
