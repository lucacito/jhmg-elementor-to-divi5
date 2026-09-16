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

        // EAEL 6.6.7 Traits/Controls.php: post_type, posts_per_page and one
        // "<taxonomy>_ids" control per taxonomy of that post type. Divi's blog
        // module lists posts only and filters by category
        // (blog/conversion-outline.json: post.advanced.number, .type,
        // .categories; BlogModule.php:769). post.innerContent.perPage was never read.
        $block_settings = [ 'post' => [ 'advanced' => [
            'number' => [ 'desktop' => [ 'value' => (string) $per_page ] ],
            'type'   => [ 'desktop' => [ 'value' => 'post' ] ],
        ] ] ];

        $categories = $settings['category_ids'] ?? [];
        if ( is_array( $categories ) && $categories !== [] ) {
            $block_settings['post']['advanced']['categories']['desktop']['value'] = array_values( array_map( 'strval', $categories ) );
        }

        $handled = [ 'posts_per_page', 'post_type', 'category_ids', 'layout_mode', 'eael_post_grid_preset_style', 'eael_show_title', 'title_tag', 'eael_title_length', 'show_load_more' ];
        if ( $post_type !== 'post' ) {
            $this->engine->logNotCarriedOver( 'query_filter', (string) $id, "post type '{$post_type}' (Divi's blog lists posts)" );
        }
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && str_ends_with( $key, '_ids' ) && $key !== 'category_ids' && $key !== 'posts_ids' && is_array( $value ) && $value !== [] ) {
                $handled[] = $key;
                $this->engine->logNotCarriedOver( 'query_filter', (string) $id, "{$key} (" . count( $value ) . ' terms)' );
            }
        }

        $this->engine->logConverted( 'blog' );
        $this->logUnmappedSettings( $id, $settings, $handled );

        return [
            'id'       => $id,
            'name'     => 'divi/blog',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
