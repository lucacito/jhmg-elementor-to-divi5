<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the ElementsKit Dual Button widget into two divi/button blocks side by side
 * in a flex-row divi/group.
 *
 * The widget holds two independent button definitions under `ekit_button_one_*` and
 * `ekit_button_two_*` keys. Width and hover styles are dropped since Divi 5 does not
 * support those as static block attrs.
 *
 * ElementsKit styles the buttons itself, not through Elementor's kit: white 14px bold
 * text on #2575fc for the first and rgb(23%,23%,23%) for the second, 5px apart
 * (elementskit-lite widgets/init/assets/css/widget-styles.css `.ekit-double-btn`,
 * dual-button.php `ekit_dual_button_gap` default 5). Elementor stores no control
 * defaults, so the converter writes that look wherever the widget left a colour unset;
 * Divi's own default would be an outline button in the body link colour.
 */
class ElementsKitDualButtonConverter extends BaseElementorConverter {
    private const DEFAULT_BACKGROUND = [ 'one' => '#2575fc', 'two' => '#3b3b3b' ];
    private const DEFAULT_TEXT_COLOR = '#ffffff';
    private const DEFAULT_GAP        = '5px';

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_btn_' );
        $settings = $element['settings'] ?? [];

        $blocks = [];

        foreach ( [ 'one' => $id . '-btn1', 'two' => $id . '-btn2' ] as $key => $block_id ) {
            $ekit_key = $key === 'one' ? 'one' : 'two';
            $text     = $settings[ "ekit_button_{$ekit_key}_text" ] ?? '';
            if ( ! is_string( $text ) || $text === '' ) {
                continue;
            }

            $link_raw = $settings[ "ekit_button_{$ekit_key}_link" ] ?? [];
            $url      = '';
            if ( is_array( $link_raw ) ) {
                $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $link_raw ) ) {
                $url = $link_raw;
            }

            $bg_key   = "ekit_double_button_{$ekit_key}_background_color";
            $text_key = "ekit_double_button_{$ekit_key}_color";
            $globals  = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : [];

            $bg_color   = $this->resolveColor( $settings, $globals, $bg_key );
            $text_color = $this->resolveColor( $settings, $globals, $text_key );
            if ( $bg_color === '' ) {
                $bg_color = self::DEFAULT_BACKGROUND[ $ekit_key ];
            }
            if ( $text_color === '' ) {
                $text_color = self::DEFAULT_TEXT_COLOR;
            }

            $button_decoration = [];
            if ( $bg_color !== '' ) {
                $button_decoration['background'] = [
                    'desktop' => [ 'value' => [ 'color' => $bg_color ] ],
                    'tablet'  => [ 'value' => [ 'color' => $bg_color ] ],
                    'phone'   => [ 'value' => [ 'color' => $bg_color ] ],
                ];
            }
            if ( $text_color !== '' ) {
                $button_decoration['font']['font'] = [
                    'desktop' => [ 'value' => [ 'color' => $text_color, 'size' => '14px', 'weight' => '700' ] ],
                ];
            }

            $button_settings = [
                'innerContent' => [
                    'desktop' => [
                        'value' => array_filter( [
                            'text'    => $text,
                            'linkUrl' => $url,
                        ] ),
                    ],
                ],
            ];

            if ( ! empty( $button_decoration ) ) {
                $button_settings['decoration'] = $button_decoration;
            }

            $blocks[] = [
                'id'       => $block_id,
                'name'     => 'divi/button',
                'settings' => [
                    'button' => $button_settings,
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
            'ekit_double_button_one_background_color', 'ekit_double_button_one_color',
            'ekit_double_button_two_background_color', 'ekit_double_button_two_color',
            'ekit_double_button_one_background_background', 'ekit_double_button_one_hover_background_background',
            'ekit_double_button_two_background_background', 'ekit_double_button_two_hover_background_background',
        ] );

        if ( count( $blocks ) < 2 ) {
            return $blocks;
        }

        // Two modules in a Divi column stack; a flex-row group keeps them on one line,
        // as ElementsKit does, with its gap and alignment.
        $gap     = $settings['ekit_dual_button_gap'] ?? null;
        $gap     = is_array( $gap ) && isset( $gap['size'] ) && $gap['size'] !== '' ? $gap['size'] . ( $gap['unit'] ?? 'px' ) : self::DEFAULT_GAP;
        $layout  = [ 'display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'columnGap' => $gap, 'rowGap' => $gap ];
        $align   = $settings['ekit_double_button_align'] ?? '';
        $justify = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ is_string( $align ) ? $align : '' ] ?? null;
        if ( $justify !== null ) {
            $layout['justifyContent'] = $justify;
        }

        return [ [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [ 'module' => [ 'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => $layout ] ] ] ] ],
            'elements' => $blocks,
        ] ];
    }

    private function resolveColor( array $settings, array $globals, string $key ): string {
        $direct = $settings[ $key ] ?? '';
        if ( is_string( $direct ) && $direct !== '' ) {
            return $direct;
        }

        $ref = $globals[ $key ] ?? '';
        if ( ! is_string( $ref ) || $ref === '' ) {
            return '';
        }

        $id = GlobalsResolver::colorIdFromRef( $ref );
        if ( $id === null ) {
            return '';
        }

        return GlobalsResolver::resolveColor( $id ) ?? '';
    }
}
