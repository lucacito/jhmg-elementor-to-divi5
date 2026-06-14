<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Progress Bar widget to divi/counters + divi/counter children.
 *
 * Elementor stores bars as a `bars` repeater array, each with a `label` and `percent`.
 * Divi's bar counter parent is `divi/counters`; each bar is a `divi/counter` child with
 * `title.innerContent` for the label and `barProgress.innerContent` for the percent value.
 */
class ProgressBarConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_bars_' );
        $settings = $element['settings'] ?? [];

        $bars     = $settings['bars'] ?? [];
        $children = [];

        foreach ( $bars as $idx => $bar ) {
            if ( ! is_array( $bar ) ) {
                continue;
            }

            $label   = is_string( $bar['label'] ?? '' ) ? ( $bar['label'] ?? '' ) : '';
            $percent = (string) ( $bar['percent']['size'] ?? $bar['percent'] ?? '0' );

            $bar_attrs = [];

            if ( $label !== '' ) {
                $bar_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $label ] ] ];
            }

            $bar_attrs['barProgress'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $percent ] ] ];

            $children[] = [
                'id'       => $id . '-bar-' . ( $idx + 1 ),
                'name'     => 'divi/counter',
                'settings' => $bar_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'counters' );
        $this->logUnmappedSettings( $id, $settings, [
            'bars',
            'bar_color', 'bar_inline_color',
            'bar_height',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/counters',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
