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

        $number = (string) ( $settings['ending_number'] ?? $settings['number'] ?? '0' );
        $title  = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? '' ) : '';
        $prefix = is_string( $settings['prefix'] ?? '' ) ? ( $settings['prefix'] ?? '' ) : '';
        $suffix = is_string( $settings['suffix'] ?? '' ) ? ( $settings['suffix'] ?? '' ) : '';

        $display_number = $prefix . $number . $suffix;

        $style        = ( new StyleMapper() )->map( 'counter', $settings );
        $block_settings = $style['divi_attrs'];

        $block_settings['number']['innerContent']['desktop']['value'] = $display_number;

        if ( $title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = $title;
        }

        $this->engine->logConverted( 'number-counter' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [
            'starting_number', 'ending_number', 'number',
            'prefix', 'suffix', 'title',
            'duration', 'separator', 'separator_char',
        ], $style['handled_keys'] ) );

        return [
            'id'       => $id,
            'name'     => 'divi/number-counter',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
