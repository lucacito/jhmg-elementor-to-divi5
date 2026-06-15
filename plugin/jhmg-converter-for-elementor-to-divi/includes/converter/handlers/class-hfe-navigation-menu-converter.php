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

        // `nav_menu` holds the WordPress menu ID (integer stored as string/int).
        $menu_id = $settings['nav_menu'] ?? '';

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
            'nav_menu', 'layout', 'submenu_icon', 'submenu_animation',
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
