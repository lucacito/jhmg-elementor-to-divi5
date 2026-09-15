<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeNavigationMenuConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_menu_' );
        $settings = $element['settings'] ?? [];

        // HFE 2.x stores the menu's slug in `menu`; Divi's menu module needs the
        // term ID, so resolve it when WordPress can. `nav_menu` (an ID) is kept
        // for older exports.
        $menu_id = $settings['menu'] ?? $settings['nav_menu'] ?? '';
        if ( is_string( $menu_id ) && $menu_id !== '' && ! ctype_digit( $menu_id ) && function_exists( 'wp_get_nav_menu_object' ) ) {
            $menu_object = wp_get_nav_menu_object( $menu_id );
            if ( is_object( $menu_object ) && isset( $menu_object->term_id ) ) {
                $menu_id = (string) $menu_object->term_id;
            }
        }

        $block_settings = [];
        if ( $menu_id !== '' && $menu_id !== 0 ) {
            $block_settings['menu'] = [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'menuId' => (string) $menu_id ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'menu' );
        $this->logUnmappedSettings( $id, $settings, [
            'menu', 'nav_menu', 'layout', 'submenu_icon', 'submenu_animation',
            'dropdown_animation', 'flyout_orientation', 'appear_effect',
            'menu_last_item', 'schema_support', 'hide_plus_minus',
            'menu_items_align', 'hamburger_align',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/menu',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
