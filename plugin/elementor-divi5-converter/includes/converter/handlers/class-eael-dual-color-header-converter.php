<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Dual Color Header → divi/heading.
 *
 * The dual-color effect (two differently-coloured segments in one heading) is not
 * reproducible as a static Divi 5 block attribute. Both parts are concatenated
 * into a single heading text; the colour split is lost.
 */
class EaelDualColorHeaderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $first = is_string( $settings['eael_dch_first_title'] ?? '' ) ? ( $settings['eael_dch_first_title'] ?? '' ) : '';
        $last  = is_string( $settings['eael_dch_last_title'] ?? '' ) ? ( $settings['eael_dch_last_title'] ?? '' ) : '';
        $full  = is_string( $settings['eael_dch_title'] ?? '' ) ? ( $settings['eael_dch_title'] ?? '' ) : '';
        $tag   = is_string( $settings['title_tag'] ?? '' ) ? ( $settings['title_tag'] ?? 'h2' ) : 'h2';

        // Prefer combined single-title; fall back to first + last.
        $text = $full !== '' ? $full : trim( $first . ' ' . $last );

        $title_attrs = [
            'innerContent' => [ 'desktop' => [ 'value' => $text ] ],
        ];
        if ( $tag !== '' ) {
            $title_attrs['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_dch_first_title', 'eael_dch_last_title', 'eael_dch_title',
            'title_tag', 'eael_dch_type', 'eael_show_dch_separator',
            'eael_show_dch_icon_content', 'eael_dch_enable_multiple_titles',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => [ 'title' => $title_attrs ],
            'elements' => [],
        ];
    }
}
