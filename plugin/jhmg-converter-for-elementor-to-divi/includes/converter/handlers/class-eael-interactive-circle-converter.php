<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Interactive Circle → divi/tabs.
 *
 * Each item in `eael_interactive_circle_item` is a button title
 * (`eael_interactive_circle_btn_title`) that reveals rich content
 * (`eael_interactive_circle_item_content`) — tabs arranged around a circle.
 * Divi has no circular layout, so the items become ordinary tabs. The circular
 * arrangement, icons and autoplay are not carried over.
 */
class EaelInteractiveCircleConverter extends BaseElementorConverter {

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_tabs_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['eael_interactive_circle_item'] ?? [];
        $children = [];

        foreach ( is_array( $items ) ? $items : [] as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title   = $item['eael_interactive_circle_btn_title'] ?? $item['eael_ic_title'] ?? '';
            $title   = is_string( $title ) ? $title : '';
            $content = $item['eael_interactive_circle_item_content'] ?? '';
            $content = is_string( $content ) ? $content : '';

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }
            if ( $content !== '' ) {
                $child_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            }

            $children[] = [
                'id'       => $id . '-tab-' . ( $idx + 1 ),
                'name'     => 'divi/tab',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_interactive_circle_item', 'eael_interactive_circle_preset',
            'eael_interactive_circle_event', 'eael_interactive_circle_autoplay',
            'eael_interactive_circle_autoplay_interval', 'eael_interactive_circle_rotation',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/tabs',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
