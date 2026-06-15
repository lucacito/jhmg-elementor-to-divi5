<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Table of Contents → divi/code.
 *
 * Divi 5 has no table-of-contents module. A placeholder code block is
 * emitted so editors can manually add a TOC plugin shortcode if needed.
 */
class TableOfContentsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $title = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? 'Table of Contents' ) : 'Table of Contents';

        $html = '<!-- Table of Contents: "' . esc_attr( $title ) . '" – install a TOC plugin and replace this block -->';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'title', 'html_tag', 'minimize_box', 'hierarchical_view',
            'word_wrap', 'include_headings', 'exclude_headings_by_selector',
            'marker_view', 'icon', 'list_type',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $html ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
