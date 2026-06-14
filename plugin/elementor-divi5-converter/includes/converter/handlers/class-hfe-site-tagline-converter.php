<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeSiteTaglineConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];

        $before = is_string( $settings['before_title_text'] ?? '' ) ? ( $settings['before_title_text'] ?? '' ) : '';
        $after  = is_string( $settings['after_title_text'] ?? '' ) ? ( $settings['after_title_text'] ?? '' ) : '';
        $text   = trim( $before . ' ' . get_bloginfo( 'description' ) . ' ' . $after );

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'before_title_text', 'after_title_text', 'heading_alignment',
            'new_icon', 'icon_spacing', 'link_select', 'custom_link',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $text !== '' ? $text : get_bloginfo( 'description' ) ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
