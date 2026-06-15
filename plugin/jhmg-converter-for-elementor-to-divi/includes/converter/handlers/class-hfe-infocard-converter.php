<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeInfocardConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['infocard_title'] ?? '' ) ? ( $settings['infocard_title'] ?? '' ) : '';
        $description = is_string( $settings['infocard_description'] ?? '' ) ? ( $settings['infocard_description'] ?? '' ) : '';

        $block_settings = [];
        if ( $title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
        }
        if ( $description !== '' ) {
            $block_settings['module'] = [
                'advanced' => [
                    'text' => [
                        'desktop' => [ 'value' => $description ],
                    ],
                ],
            ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, [
            'infocard_title', 'infocard_description', 'infocard_title_tag',
            'alignment', 'link', 'icon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
