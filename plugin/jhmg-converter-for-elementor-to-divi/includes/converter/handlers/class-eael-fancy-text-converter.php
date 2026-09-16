<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Fancy Text → divi/heading.
 *
 * The cycling animated strings are flattened to static text. The first
 * rotating string is used as the "fancy" part so at least one word is always
 * represented in the output.
 *
 * EAEL renders the widget as spans with no typography of their own: its
 * typography controls default to Elementor's Primary global font at 22px, weight
 * 600 (Fancy_Text.php:348-357, 456-465, 592-600), and Elementor stores no control
 * defaults. Divi's heading module inherits nothing of the kind, so the converter
 * writes what EAEL would have rendered wherever the widget left the typography alone.
 */
class EaelFancyTextConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $prefix = is_string( $settings['eael_fancy_text_prefix'] ?? '' ) ? ( $settings['eael_fancy_text_prefix'] ?? '' ) : '';
        $suffix = is_string( $settings['eael_fancy_text_suffix'] ?? '' ) ? ( $settings['eael_fancy_text_suffix'] ?? '' ) : '';

        $fancy_items = $settings['eael_fancy_text_strings'] ?? $settings['eael_fancy_strings'] ?? [];
        $first_word  = '';
        if ( is_array( $fancy_items ) && ! empty( $fancy_items ) ) {
            $item = reset( $fancy_items );
            if ( is_array( $item ) ) {
                $text       = $item['eael_fancy_text_strings_text_field'] ?? $item['eael_fancy_string_text'] ?? '';
                $first_word = is_string( $text ) ? $text : '';
            }
        }

        $parts = array_filter( [ $prefix, $first_word, $suffix ] );
        $title = implode( ' ', $parts );

        // `elementor_defaults` points an untouched typography control at the kit's
        // Primary preset, the same fallback EAEL declares for this widget.
        $style          = ( new StyleMapper() )->map( 'heading', $settings, [ 'elementor_defaults' => true ] );
        $block_settings = $style['divi_attrs'];

        $font  = $block_settings['title']['decoration']['font']['font']['desktop']['value'] ?? [];
        $font += [ 'size' => '22px', 'weight' => '600' ];
        $block_settings['title']['decoration']['font']['font']['desktop']['value'] = $font;

        if ( $title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ] + $block_settings['title'];
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [
            'eael_fancy_text_prefix', 'eael_fancy_text_suffix', 'eael_fancy_text_strings', 'eael_fancy_strings',
            'eael_fancy_text_type', 'eael_fancy_text_speed', 'eael_fancy_text_loop',
        ], $style['handled_keys'] ) );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
