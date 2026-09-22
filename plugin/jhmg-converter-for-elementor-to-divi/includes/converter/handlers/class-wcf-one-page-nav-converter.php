<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--one-page-nav" widget (Animation Addons for Elementor,
 * one-page-nav.php) to divi/icon-list.
 *
 * The widget is a fixed, scroll-spy side navigation (each item scrolls to
 * and highlights a page section as the user scrolls); that fixed positioning
 * and scroll-spy behaviour has no Divi equivalent and is logged as not
 * carried over. Its items themselves (icon + text + a 'section_id' anchor)
 * carry over cleanly to divi/icon-list-item — the same shape PriceListConverter
 * already builds (text in content.innerContent, link in module.advanced.link)
 * — using '#' + section_id as the link instead of a URL control, since this
 * widget links to in-page anchors, not external URLs.
 */
class WcfOnePageNavConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        $items    = is_array( $settings['wcf_one_page_nav'] ?? null ) ? $settings['wcf_one_page_nav'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $text       = is_string( $item['nav_text'] ?? null ) ? ( $item['nav_text'] ?? '' ) : '';
            $section_id = is_string( $item['section_id'] ?? null ) ? ( $item['section_id'] ?? '' ) : '';

            $child_attrs = [];
            if ( $text !== '' ) {
                $child_attrs['content']['innerContent']['desktop']['value'] = $text;
            }
            if ( $section_id !== '' ) {
                $child_attrs['module']['advanced']['link']['desktop']['value'] = [ 'url' => '#' . $section_id ];
            }

            $divi_icon = FontAwesomeIcons::fromControl( $item['selected_icon'] ?? null );
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

        $this->engine->logNotCarriedOver( 'fixed_scroll_spy_nav', (string) $id, "the widget's fixed position and scroll-spy active-section highlighting have no Divi equivalent; its items were converted as a plain icon list" );

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [
            'wcf_one_page_nav', 'aae_hide_title',
            // wcf--one-page-nav's own fixed-positioning and colour controls
            // (one-page-nav.php) — no Divi equivalent, covered by the
            // not_carried_over log above.
            'nav_position', 'nav_gap', 'nav_bg_color', 'nav_active_color',
            'nav_icon_color', 'nav_text_color', 'nav_typography_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
