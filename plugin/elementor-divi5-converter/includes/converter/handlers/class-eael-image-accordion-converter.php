<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Image Accordion → divi/accordion.
 *
 * Each accordion panel is built from the item's title, content, and image
 * (image URL prepended to the body as an <img> tag).
 */
class EaelImageAccordionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_accordion_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['eael_img_accordion_items'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title   = is_string( $item['eael_img_accordion_title'] ?? '' ) ? ( $item['eael_img_accordion_title'] ?? '' ) : '';
            $content = is_string( $item['eael_img_accordion_content'] ?? '' ) ? ( $item['eael_img_accordion_content'] ?? '' ) : '';

            $image_raw = $item['eael_img_accordion_image'] ?? [];
            $image_url = is_array( $image_raw ) ? ( is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '' ) : '';

            $body_parts = array_filter( [
                $image_url !== '' ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $title ) . '" style="max-width:100%;">' : '',
                $content,
            ] );
            $body = implode( "\n", $body_parts );

            $child_settings = [];
            if ( $title !== '' ) {
                $child_settings['title'] = [
                    'innerContent' => [ 'desktop' => [ 'value' => $title ] ],
                ];
            }
            if ( $body !== '' ) {
                $child_settings['content'] = [
                    'innerContent' => [ 'desktop' => [ 'value' => $body ] ],
                ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/accordion-item',
                'settings' => $child_settings,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'accordion' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_img_accordion_items', 'eael_img_accordion_event',
            'eael_img_accordion_active_item',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/accordion',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
