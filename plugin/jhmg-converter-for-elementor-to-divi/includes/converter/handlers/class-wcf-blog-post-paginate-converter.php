<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--blog--post--paginate" widget (Animation Addons for
 * Elementor, post-paginate.php) to divi/post-nav.
 *
 * 'enable_prev'/'prev_title' and 'enable_next'/'next_title' map to
 * divi/post-nav's links.advanced.showPrev/prevText and showNext/nextText
 * (PostNavModule.php, fixtures/divi-schema/modules.json) — both dynamically
 * link to the adjacent post at render time, only the visible label differs.
 */
class WcfBlogPostPaginateConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_post_nav_' );
        $settings = $element['settings'] ?? [];

        $show_prev = ( $settings['enable_prev'] ?? 'yes' ) === 'yes';
        $show_next = ( $settings['enable_next'] ?? 'yes' ) === 'yes';
        $prev_text = is_string( $settings['prev_title'] ?? null ) ? ( $settings['prev_title'] ?? '' ) : '';
        $next_text = is_string( $settings['next_title'] ?? null ) ? ( $settings['next_title'] ?? '' ) : '';

        $advanced = [
            'showPrev' => [ 'desktop' => [ 'value' => $show_prev ? 'on' : 'off' ] ],
            'showNext' => [ 'desktop' => [ 'value' => $show_next ? 'on' : 'off' ] ],
        ];
        if ( $prev_text !== '' ) {
            $advanced['prevText'] = [ 'desktop' => [ 'value' => $prev_text ] ];
        }
        if ( $next_text !== '' ) {
            $advanced['nextText'] = [ 'desktop' => [ 'value' => $next_text ] ];
        }

        $this->engine->logConverted( 'post-nav' );
        $this->logUnmappedSettings( $id, $settings, [
            'enable_prev', 'prev_title', 'prev_icon', 'prev_heading',
            'enable_next', 'next_title', 'next_icon', 'next_heading',
            'preset_style', 'show_title',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/post-nav',
            'settings' => [ 'links' => [ 'advanced' => $advanced ] ],
            'elements' => [],
        ];
    }
}
