<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Progress Bar → divi/counters + divi/counter children.
 *
 * EAEL uses a single-bar model (one `progress_bar_title` / `progress_bar_value`),
 * while Divi expects a parent `divi/counters` with individual `divi/counter` children.
 */
class EaelProgressBarConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_bars_' );
        $settings = $element['settings'] ?? [];

        $title   = is_string( $settings['progress_bar_title'] ?? '' ) ? ( $settings['progress_bar_title'] ?? '' ) : '';
        $percent = (string) ( $settings['progress_bar_value'] ?? $settings['progress_bar_value_dynamic'] ?? '0' );

        $bar_attrs = [];
        if ( $title !== '' ) {
            $bar_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
        }
        $bar_attrs['barProgress'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $percent ] ] ];

        $this->engine->logConverted( 'counters' );
        $this->logUnmappedSettings( $id, $settings, [
            'progress_bar_title', 'progress_bar_value', 'progress_bar_value_dynamic',
            'progress_bar_layout', 'progress_bar_show_count',
            'progress_bar_title_html_tag', 'progress_bar_value_type',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/counters',
            'settings' => [],
            'elements' => [
                [
                    'id'       => $id . '-bar-1',
                    'name'     => 'divi/counter',
                    'settings' => $bar_attrs,
                    'elements' => [],
                ],
            ],
        ];
    }
}
