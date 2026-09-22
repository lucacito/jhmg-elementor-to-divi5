<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Counter widget to divi/number-counter.
 *
 * Elementor's counter has a starting and ending number, prefix/suffix, and a title.
 * Divi shows only the final number value plus an optional title.
 */
class CounterConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_counter_' );
        $settings = $element['settings'] ?? [];

        // Elementor's counter: ending_number. HFE's counter: end_number.
        $number = (string) ( $settings['ending_number'] ?? $settings['end_number'] ?? $settings['number'] ?? '0' );
        $title  = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? '' ) : '';
        $prefix = is_string( $settings['prefix'] ?? '' ) ? ( $settings['prefix'] ?? '' ) : '';
        $suffix = is_string( $settings['suffix'] ?? '' ) ? ( $settings['suffix'] ?? '' ) : '';

        // number.innerContent must be the bare number: Divi animates it and adds
        // its own sign while number.advanced.enablePercentSign is on, the
        // default (number-counter/module.json). Only a '%' suffix maps; any other
        // prefix or suffix has no home in the module and is reported.
        $numeric = preg_replace( '/[^0-9.\-]/', '', $number );
        $numeric = $numeric === '' || $numeric === '-' ? '0' : $numeric;

        // Elementor's counter falls back to the kit's Primary typography for the number
        // and Secondary for the title (counter.php).
        $style          = ( new StyleMapper() )->map( 'counter', $settings, [ 'elementor_defaults' => true ] );
        $block_settings = $style['divi_attrs'];

        $block_settings['number']['innerContent']['desktop']['value']                 = $numeric;
        $block_settings['number']['advanced']['enablePercentSign']['desktop']['value'] = trim( $suffix ) === '%' ? 'on' : 'off';

        $dropped = [];
        if ( trim( $prefix ) !== '' ) {
            $dropped[] = "prefix '" . trim( $prefix ) . "'";
        }
        if ( trim( $suffix ) !== '' && trim( $suffix ) !== '%' ) {
            $dropped[] = "suffix '" . trim( $suffix ) . "'";
        }
        if ( $dropped !== [] ) {
            $this->engine->logNotCarriedOver( 'counter_affix', (string) $id, implode( ', ', $dropped ) );
        }

        if ( $title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = $title;
        }

        $this->engine->logConverted( 'number-counter' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [
            'starting_number', 'ending_number', 'start_number', 'end_number', 'number',
            'prefix', 'suffix', 'title',
            'duration', 'separator', 'separator_char',
            // wcf--counter's own thousand-separator and colour controls (counter.php).
            'thousand_separator', 'thousand_separator_char',
            'number_color', 'suffix_prefix_color', 'separator_color', 'title_color',
        ], $style['handled_keys'] ) );

        return [
            'id'       => $id,
            'name'     => 'divi/number-counter',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
