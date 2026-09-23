<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "aae--clickdrop" widget (Animation Addons for Elementor,
 * clickdrop.php) to divi/icon-list.
 *
 * The widget shows a login link when logged out and an account dropdown menu
 * when logged in (is_user_logged_in() at render time) — that state switch has
 * no static equivalent and is logged as not carried over. The dropdown's own
 * menu items ('menus_url' repeater of menu_title/menu_link/menu_icon) still
 * carry over cleanly to divi/icon-list-item — the same shape
 * WcfOnePageNavConverter already builds (text in content.innerContent, link
 * in module.advanced.link, icon in icon.innerContent).
 */
class AaeClickdropConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        $items    = is_array( $settings['menus_url'] ?? null ) ? $settings['menus_url'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $text = is_string( $item['menu_title'] ?? null ) ? ( $item['menu_title'] ?? '' ) : '';
            $link = is_array( $item['menu_link'] ?? null ) ? $item['menu_link'] : [];
            $url  = is_string( $link['url'] ?? '' ) ? ( $link['url'] ?? '' ) : '';

            $child_attrs = [];
            if ( $text !== '' ) {
                $child_attrs['content']['innerContent']['desktop']['value'] = $text;
            }
            if ( $url !== '' ) {
                $child_attrs['module']['advanced']['link']['desktop']['value'] = [ 'url' => $url ];
            }
            $divi_icon = FontAwesomeIcons::fromControl( $item['menu_icon'] ?? null );
            if ( $divi_icon !== null ) {
                $child_attrs['icon']['innerContent']['desktop']['value'] = $divi_icon;
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/icon-list-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logNotCarriedOver( 'login_state_switch', (string) $id, "the widget's logged-in/logged-out account state switch has no Divi equivalent; the menu items were converted as a plain icon list" );

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [
            'menus_url', 'login_label', 'login_url', 'logged_label',
            'menu_title', 'menu_link', 'menu_icon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
