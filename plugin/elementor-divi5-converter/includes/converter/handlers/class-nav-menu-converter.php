<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the native Elementor "Nav Menu" widget (widgetType = 'nav-menu')
 * and maps it to divi/menu.
 *
 * Unlike the HFE navigation-menu widget (which stores the menu ID in 'nav_menu'),
 * the native Elementor widget stores it in 'menu' as either an integer term ID
 * or a string slug.
 */
class NavMenuConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_menu_' );
        $settings = $element['settings'] ?? [];

        $menu_id = $settings['menu'] ?? '';

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
            'menu', 'layout', 'align_items', 'pointer', 'animation_duration',
            'indicator', 'submenu_icon', 'heading_mobile_dropdown', 'breakpoint',
            'full_width', 'text_align', 'toggle_button_align', 'toggle_align',
            'hamburger_align',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/menu',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
