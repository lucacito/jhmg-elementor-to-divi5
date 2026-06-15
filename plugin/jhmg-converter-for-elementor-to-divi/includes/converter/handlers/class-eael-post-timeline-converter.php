<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Post Timeline → divi/blog.
 *
 * The timeline layout is not representable in Divi 5. Posts are surfaced as a
 * standard blog listing which preserves the content.
 */
class EaelPostTimelineConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blog_' );
        $settings = $element['settings'] ?? [];

        $per_page = (int) ( $settings['posts_per_page'] ?? $settings['eael_post_timeline_per_page'] ?? 6 );

        $block_settings = [
            'post' => [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'perPage' => $per_page ] ],
                ],
            ],
        ];

        $this->engine->logConverted( 'blog' );
        $this->logUnmappedSettings( $id, $settings, [
            'posts_per_page', 'eael_post_timeline_per_page',
            'eael_post_timeline_post_type', 'eael_post_timeline_preset_style',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blog',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
