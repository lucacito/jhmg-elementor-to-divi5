<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Interactive Circle → divi/number-counter.
 *
 * The circular progress display maps naturally to a counter module. The
 * animation and circular layout are lost but the numerical content is kept.
 */
class EaelInteractiveCircleConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_counter_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['eael_interactive_circle_item'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title  = is_string( $item['eael_ic_title'] ?? '' ) ? ( $item['eael_ic_title'] ?? '' ) : '';
            $number = (string) ( $item['eael_ic_progress'] ?? $item['eael_ic_count'] ?? '0' );

            $child_settings = [
                'number' => [ 'innerContent' => [ 'desktop' => [ 'value' => $number ] ] ],
            ];
            if ( $title !== '' ) {
                $child_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/number-counter',
                'settings' => $child_settings,
                'elements' => [],
            ];
        }

        // If there are multiple counter items, wrap them in a group.
        if ( count( $children ) === 1 ) {
            $this->engine->logConverted( 'number-counter' );
            $this->logUnmappedSettings( $id, $settings, [ 'eael_interactive_circle_item' ] );
            return array_merge( [ 'id' => $id ], $children[0] );
        }

        $this->engine->logConverted( 'number-counter' );
        $this->logUnmappedSettings( $id, $settings, [ 'eael_interactive_circle_item' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
