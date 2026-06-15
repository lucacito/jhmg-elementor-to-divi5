<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeBasicPostsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blog_' );
        $settings = $element['settings'] ?? [];

        $per_page = (int) ( $settings['posts_per_page'] ?? 6 );

        $block_settings = [
            'post' => [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'perPage' => $per_page ] ],
                ],
            ],
        ];

        $this->engine->logConverted( 'blog' );
        $this->logUnmappedSettings( $id, $settings, [
            'posts_per_page', 'orderby', 'order', 'exclude_current',
            'post_category', 'show_excerpt', 'excerpt_length', 'show_thumbnail',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blog',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
