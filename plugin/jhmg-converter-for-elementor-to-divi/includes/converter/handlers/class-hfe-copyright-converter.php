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

        $text = $settings['shortcode'] ?? $settings['copyright_text'] ?? '';
        $text = is_string( $text ) ? $text : '';

        // HFE's own shortcodes are filled in here: a migrated site may no longer
        // run HFE, and they would otherwise print as literal text.
        $site_title = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
        $text       = str_replace( [ '[hfe_current_year]', '[hfe_site_title]' ], [ gmdate( 'Y' ), $site_title ], $text );

        if ( function_exists( 'do_shortcode' ) ) {
            $text = do_shortcode( $text );
        }

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'shortcode', 'copyright_text', 'link', 'alignment', 'text_color', 'caption_typography',
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
