<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeSiteTitleConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $before = $settings['before'] ?? $settings['before_title_text'] ?? '';
        $before = is_string( $before ) ? $before : '';
        $after  = $settings['after'] ?? $settings['after_title_text'] ?? '';
        $after  = is_string( $after ) ? $after : '';
        $tag    = $settings['heading_tag'] ?? $settings['title_html_tag'] ?? 'h2';
        $tag    = is_string( $tag ) ? $tag : 'h2';

        $text   = trim( $before . ' ' . get_bloginfo( 'name' ) . ' ' . $after );

        $title_attrs = [
            'innerContent' => [
                'desktop' => [ 'value' => $text !== '' ? $text : get_bloginfo( 'name' ) ],
            ],
        ];
        if ( $tag !== '' ) {
            $title_attrs['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'before', 'after', 'heading_tag', 'heading_link', 'icon', 'before_title_text', 'after_title_text', 'title_html_tag',
            'new_icon', 'icon_spacing', 'link_select', 'custom_link', 'size', 'heading_alignment',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => [ 'title' => $title_attrs ],
            'elements' => [],
        ];
    }
}
