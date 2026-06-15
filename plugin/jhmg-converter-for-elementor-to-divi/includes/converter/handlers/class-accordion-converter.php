<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles both Elementor `accordion` and `toggle` widget types.
 *
 * Both produce a `divi/accordion` wrapper with one `divi/accordion-item` per
 * panel. Divi does not have a distinct multi-panel toggle module, so the mapping
 * is accordion for both.
 */
class AccordionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_accordion_' );
        $settings = $element['settings'] ?? [];

        // Native Elementor uses 'tabs'; some versions use 'items'.
        // ElementsKit accordion uses 'ekit_accordion_items' with 'acc_title'/'acc_content' keys.
        $raw_items  = $settings['ekit_accordion_items'] ?? $settings['tabs'] ?? $settings['items'] ?? [];
        $is_ekit    = isset( $settings['ekit_accordion_items'] );
        $children   = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            if ( $is_ekit ) {
                $title   = is_string( $item['acc_title'] ?? '' ) ? ( $item['acc_title'] ?? '' ) : '';
                $content = is_string( $item['acc_content'] ?? '' ) ? ( $item['acc_content'] ?? '' ) : '';
            } else {
                $title   = is_string( $item['tab_title'] ?? '' ) ? ( $item['tab_title'] ?? '' ) : '';
                $content = is_string( $item['tab_content'] ?? '' ) ? ( $item['tab_content'] ?? '' ) : '';
            }

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }
            if ( $content !== '' ) {
                $child_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/accordion-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'accordion' );
        $this->logUnmappedSettings( $id, $settings, [
            'tabs', 'items',
            'ekit_accordion_items', 'ekit_accordion_open_first_slide',
            'ekit_accordion_right_icon_actives',
            'ekit_accordion_background_background', 'ekit_accordion_background_close_background',
            'ekit_accordion_title_padding',
            'selected_icon', 'selected_active_icon',
            'title_html_tag', 'faq_schema',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/accordion',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
