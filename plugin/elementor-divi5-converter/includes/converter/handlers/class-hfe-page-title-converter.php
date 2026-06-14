<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfePageTitleConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_post_title_' );
        $settings = $element['settings'] ?? [];

        $tag = is_string( $settings['heading_tag'] ?? '' ) ? ( $settings['heading_tag'] ?? 'h2' ) : 'h2';

        $block_settings = [];
        if ( $tag !== '' ) {
            $block_settings['title'] = [
                'decoration' => [
                    'font' => [
                        'font' => [
                            'desktop' => [ 'value' => [ 'headingLevel' => $tag ] ],
                        ],
                    ],
                ],
            ];
        }

        $this->engine->logConverted( 'post-title' );
        $this->logUnmappedSettings( $id, $settings, [
            'heading_tag', 'before_title_text', 'after_title_text',
            'new_icon', 'icon_spacing', 'link_select', 'custom_link', 'size', 'alignment',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/post-title',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
