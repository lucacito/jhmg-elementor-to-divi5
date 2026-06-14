<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the ElementsKit Dual Button widget into two sequential divi/button blocks.
 *
 * The widget holds two independent button definitions under `ekit_button_one_*` and
 * `ekit_button_two_*` keys. Layout controls (gap, width) and hover styles are dropped
 * since Divi 5 does not support those as static block attrs.
 */
class ElementsKitDualButtonConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_btn_' );
        $settings = $element['settings'] ?? [];

        $blocks = [];

        foreach ( [ 'one' => $id . '-btn1', 'two' => $id . '-btn2' ] as $key => $block_id ) {
            $text = $settings[ "ekit_button_{$key}_text" ] ?? '';
            if ( ! is_string( $text ) || $text === '' ) {
                continue;
            }

            $link_raw = $settings[ "ekit_button_{$key}_link" ] ?? [];
            $url      = '';
            if ( is_array( $link_raw ) ) {
                $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $link_raw ) ) {
                $url = $link_raw;
            }

            $blocks[] = [
                'id'       => $block_id,
                'name'     => 'divi/button',
                'settings' => [
                    'button' => [
                        'innerContent' => [
                            'desktop' => [
                                'value' => array_filter( [
                                    'text'    => $text,
                                    'linkUrl' => $url,
                                ] ),
                            ],
                        ],
                    ],
                ],
                'elements' => [],
            ];

            $this->engine->logConverted( 'button' );
        }

        $this->logUnmappedSettings( $id, $settings, [
            'ekit_button_one_text', 'ekit_button_one_link', 'ekit_button_one_icons__switch',
            'ekit_button_two_text', 'ekit_button_two_link', 'ekit_button_two_icons__switch',
            'ekit_button_middle_text',
            'ekit_dual_button_width', 'ekit_dual_button_width_tablet', 'ekit_dual_button_width_mobile',
            'ekit_dual_button_gap', 'ekit_double_button_align',
            'ekit_double_button_one_background_background', 'ekit_double_button_one_hover_background_background',
            'ekit_double_button_two_background_background', 'ekit_double_button_two_hover_background_background',
        ] );

        return $blocks;
    }
}
