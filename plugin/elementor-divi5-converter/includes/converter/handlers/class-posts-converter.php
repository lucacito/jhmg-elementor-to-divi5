<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostsConverter extends BaseElementorConverter {
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
            'posts_per_page', 'post_type', 'skin', 'columns_mobile',
            'show_title', 'show_excerpt', 'show_read_more', 'show_thumbnail',
            'show_author', 'show_date', 'show_categories', 'show_comments',
            'pagination_type', 'excerpt_length',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blog',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
