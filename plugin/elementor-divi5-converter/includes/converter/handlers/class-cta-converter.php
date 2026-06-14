<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Call to Action widget to divi/cta.
 *
 * Elementor CTA has a title, description, and an optional button with text and URL.
 * All three map directly to Divi's CTA innerContent attrs.
 */
class CtaConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_cta_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? '' ) : '';
        $description = is_string( $settings['description'] ?? '' ) ? ( $settings['description'] ?? '' ) : '';
        $btn_text    = is_string( $settings['button'] ?? $settings['button_text'] ?? '' )
            ? ( $settings['button'] ?? $settings['button_text'] ?? '' )
            : '';

        $link_raw = $settings['link'] ?? $settings['button_url'] ?? [];
        $url      = '';
        if ( is_array( $link_raw ) ) {
            $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
        } elseif ( is_string( $link_raw ) ) {
            $url = $link_raw;
        }

        $block_settings = [];

        if ( $title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
        }

        if ( $description !== '' ) {
            $block_settings['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $description ] ] ];
        }

        if ( $btn_text !== '' || $url !== '' ) {
            $btn_value = array_filter( [ 'text' => $btn_text, 'linkUrl' => $url ] );
            $block_settings['button'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $btn_value ] ] ];
        }

        $this->engine->logConverted( 'cta' );
        $this->logUnmappedSettings( $id, $settings, [
            'title', 'description',
            'button', 'button_text', 'link', 'button_url',
            'layout', 'content_align', 'ribbon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/cta',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
