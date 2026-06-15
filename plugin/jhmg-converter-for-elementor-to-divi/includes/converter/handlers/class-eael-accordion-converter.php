<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelAccordionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_accordion_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['eael_adv_accordion_tab'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title   = is_string( $item['eael_adv_accordion_tab_title'] ?? '' ) ? ( $item['eael_adv_accordion_tab_title'] ?? '' ) : '';
            $content = is_string( $item['eael_adv_accordion_tab_content'] ?? '' ) ? ( $item['eael_adv_accordion_tab_content'] ?? '' ) : '';

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
            'eael_adv_accordion_tab', 'eael_adv_accordion_type', 'eael_adv_accordion_title_tag',
            'eael_adv_accordion_icon_show', 'eael_adv_accordion_toggle_speed',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/accordion',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
