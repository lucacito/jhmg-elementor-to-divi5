<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelPostGridConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blog_' );
        $settings = $element['settings'] ?? [];

        $per_page  = (int) ( $settings['posts_per_page'] ?? 6 );
        $post_type = is_string( $settings['post_type'] ?? '' ) ? ( $settings['post_type'] ?? 'post' ) : 'post';

        $block_settings = [
            'post' => [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'perPage' => $per_page ] ],
                ],
            ],
        ];

        $this->engine->logConverted( 'blog' );
        $this->logUnmappedSettings( $id, $settings, [
            'posts_per_page', 'post_type', 'layout_mode', 'eael_post_grid_preset_style',
            'eael_show_title', 'title_tag', 'eael_title_length', 'show_load_more',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blog',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
