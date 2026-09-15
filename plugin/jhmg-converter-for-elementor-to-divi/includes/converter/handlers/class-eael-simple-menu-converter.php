<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelSimpleMenuConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_menu_' );
        $settings = $element['settings'] ?? [];

        // EAEL 6.x stores the menu's term ID in `eael_simple_menu_menu`.
        $menu_id = $settings['eael_simple_menu_menu'] ?? $settings['eael_simple_menu_slug'] ?? $settings['menu_slug'] ?? $settings['menu'] ?? '';
        if ( ! is_string( $menu_id ) ) {
            $menu_id = (string) $menu_id;
        }

        $block_settings = [];
        if ( $menu_id !== '' ) {
            $block_settings['menu'] = [
                // menu/conversion-outline.json: menu_id → menu.advanced.menuId, the
                // path MenuModule.php:904 reads. innerContent was never read.
                'advanced' => [
                    'menuId' => [ 'desktop' => [ 'value' => $menu_id ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'menu' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_simple_menu_menu', 'eael_simple_menu_slug', 'menu_slug', 'menu',
            'layout', 'align', 'submenu_icon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/menu',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
