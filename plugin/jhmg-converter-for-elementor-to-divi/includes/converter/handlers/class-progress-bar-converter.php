<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts progress-bar widgets to divi/counters + divi/counter children.
 *
 * Divi's bar counter parent is `divi/counters`; each bar is a `divi/counter` child with
 * `title.innerContent` for the label and `barProgress.innerContent` for the percent value.
 *
 * Two input shapes reach this converter:
 *
 * - a `bars` repeater array, each entry carrying a `label` and `percent`
 * - Elementor's own `progress` widget (verified against 3.28.2
 *   includes/widgets/progress.php), which is a SINGLE bar with flat `title` and
 *   `percent` keys and no repeater at all
 *
 * The second shape is handled explicitly because reading only `bars` against a
 * native progress widget yields a counters module with no children — the content
 * disappears while the report still counts the widget as converted.
 */
class ProgressBarConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_bars_' );
        $settings = $element['settings'] ?? [];

        $bars     = $this->extractBars( $settings );
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
            // Native `progress` widget keys consumed by extractBars().
            'title', 'percent',
            // Native `progress` controls with no divi/counter equivalent: the
            // inline percentage toggle, the free-text suffix shown inside the
            // bar, and the preset colour variant.
            'display_percentage', 'inner_text', 'progress_type', 'title_html_tag',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/counters',
            'settings' => [],
            'elements' => $children,
        ];
    }

    /**
     * Normalises both accepted shapes to a list of `{label, percent}` entries.
     *
     * A `bars` repeater wins when present. Otherwise a native Elementor
     * `progress` widget is recognised by carrying a `percent` or `title` key of
     * its own, and becomes a single bar.
     *
     * @return array<int,array{label:string,percent:mixed}>
     */
    private function extractBars( array $settings ): array {
        $bars = $settings['bars'] ?? [];
        if ( is_array( $bars ) && ! empty( $bars ) ) {
            return $bars;
        }

        $title   = $this->getSettingValue( $settings, 'title', '' );
        $percent = $settings['percent'] ?? null;

        if ( $percent === null && ( ! is_string( $title ) || $title === '' ) ) {
            return [];
        }

        return [ [
            'label'   => is_string( $title ) ? $title : '',
            'percent' => $percent,
        ] ];
    }
}
