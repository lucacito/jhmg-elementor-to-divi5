<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Fancy Text → divi/heading.
 *
 * The cycling animated strings are flattened to static text. The first
 * rotating string is used as the "fancy" part so at least one word is always
 * represented in the output.
 */
class EaelFancyTextConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $prefix = is_string( $settings['eael_fancy_text_prefix'] ?? '' ) ? ( $settings['eael_fancy_text_prefix'] ?? '' ) : '';
        $suffix = is_string( $settings['eael_fancy_text_suffix'] ?? '' ) ? ( $settings['eael_fancy_text_suffix'] ?? '' ) : '';

        $fancy_items = $settings['eael_fancy_strings'] ?? [];
        $first_word  = '';
        if ( is_array( $fancy_items ) && ! empty( $fancy_items ) ) {
            $first_item = reset( $fancy_items );
            if ( is_array( $first_item ) ) {
                $first_word = is_string( $first_item['eael_fancy_string_text'] ?? '' ) ? ( $first_item['eael_fancy_string_text'] ?? '' ) : '';
            }
        }

        $parts = array_filter( [ $prefix, $first_word, $suffix ] );
        $title = implode( ' ', $parts );

        $block_settings = [];
        if ( $title !== '' ) {
            $block_settings['title'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $title ] ],
            ];
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_fancy_text_prefix', 'eael_fancy_text_suffix', 'eael_fancy_strings',
            'eael_fancy_text_type', 'eael_fancy_text_speed', 'eael_fancy_text_loop',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
