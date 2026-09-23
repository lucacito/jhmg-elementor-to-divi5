<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--blog--post--title" widget (Animation Addons for
 * Elementor, post-title.php) to divi/post-title.
 *
 * Same target as HfePageTitleConverter (HFE's 'page-title' widget), but kept
 * as its own class rather than adding 'header_size' as a fallback there:
 * tests/AddonSettingNamesSweepTest.php checks each addon-named converter only
 * reads setting names that addon actually defines, and 'header_size' is this
 * widget's own key, not HFE's. The widget has no static text of its own — it
 * always prints the current post's the_title(), same as divi/post-title.
 */
class WcfBlogPostTitleConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_post_title_' );
        $settings = $element['settings'] ?? [];

        $tag = is_string( $settings['header_size'] ?? '' ) ? ( $settings['header_size'] ?? 'h2' ) : 'h2';

        $this->engine->logConverted( 'post-title' );
        $this->logUnmappedSettings( $id, $settings, [
            'header_size', 'current_link', 'link',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/post-title',
            'settings' => [
                'title' => [
                    'decoration' => [
                        'font' => [
                            'font' => [
                                'desktop' => [ 'value' => [ 'headingLevel' => $tag ] ],
                            ],
                        ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
