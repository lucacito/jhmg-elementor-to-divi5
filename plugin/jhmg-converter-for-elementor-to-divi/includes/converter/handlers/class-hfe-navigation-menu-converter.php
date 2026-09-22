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
            // menu/conversion-outline.json: menu_id → menu.advanced.menuId; the
            // renderer reads that path (MenuModule.php:904). innerContent was never read.
            $block_settings['menu'] = [
                'advanced' => [
                    'menuId' => [ 'desktop' => [ 'value' => (string) $menu_id ] ],
                ],
            ];
        }

        // Divi paints #ffffff behind the menu by default
        // (menu/module-default-render-attributes.json), a white bar over the
        // header's background. HFE's menu has no bar background of its own; its
        // per-item bg_color_menu_item (navigation-menu.php) is the nearest thing.
        $item_bg = $settings['bg_color_menu_item'] ?? '';
        $block_settings['module']['decoration']['background']['desktop']['value']['color'] =
            is_string( $item_bg ) && $item_bg !== '' ? $item_bg : 'rgba(255,255,255,0)';

        $this->engine->logConverted( 'menu' );
        $this->logUnmappedSettings( $id, $settings, [
            'menu', 'nav_menu', 'bg_color_menu_item', 'layout', 'submenu_icon', 'submenu_animation',
            'dropdown_animation', 'flyout_orientation', 'appear_effect',
            'menu_last_item', 'schema_support', 'hide_plus_minus',
            'menu_items_align', 'hamburger_align',
            // wcf--nav-menu's own layout/colour controls (nav-menu/nav-menu.php) —
            // desktop/mobile submenu and hamburger styling, no divi/menu equivalent.
            'submenu_indicator', 'innersubmenu_indicator_icon', 'menu_layout', 'menu_hover_pointer',
            'hamburger_icon', 'mobile_close', 'mobile_menu_breakpoint',
            'desktop_menu_item_text_color', 'desktop_menu_item_hover_color',
            'desktop_menu_item_hover_border_color', 'desktop_menu_item_active_color',
            'desktop_menu_item_active_border_color', 'desktop_submenu_width',
            'desktop_submenu_item_text_color', 'desktop_submenu_item_hover_color',
            'desktop_submenu_item_hover_border_color', 'desktop_submenu_item_active_color',
            'desktop_submenu_item_active_border_color', 'mobile_menu_position',
            'mobile_menu_item_text_color', 'mobile_menu_item_hover_color',
            'mobile_menu_item_hover_border_color', 'hamburger_color', 'hamburger_hover_color',
            'hamburger_hover_border_color', 'mobile_menu_close_color', 'mobile_menu_close_hover_color',
            'mobile_menu_close_hover_border_color', 'back_icon', 'mobile_menu_back_color',
            'mobile_menu_back_hover_color', 'hover_pointer_width', 'hover_pointer_height',
            'hover_pointer_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/menu',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
