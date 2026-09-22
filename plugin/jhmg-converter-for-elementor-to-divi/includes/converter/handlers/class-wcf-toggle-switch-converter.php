<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--toggle-switch" widget (Animation Addons for Elementor,
 * toggle-switcher.php — get_name() returns 'wcf--toggle-switch', not
 * 'wcf--toggle-switcher') to divi/tabs.
 *
 * It's a fixed two-item repeater (a billing-period switch) whose 'switch_title'/
 * 'switch_content' fields are the same title/content shape TabsConverter
 * already builds from 'tab_title'/'tab_content', so it's a dedicated converter
 * only because the outer/inner key names differ, not because the shape does.
 * An item using the "Saved Templates" content_type has no static content to
 * carry over (it renders another Elementor template at runtime), so it becomes
 * a title-only tab with a warning instead of a fabricated content value.
 */
class WcfToggleSwitchConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_tabs_' );
        $settings = $element['settings'] ?? [];
        $items    = is_array( $settings['toggle_switcher'] ?? null ) ? $settings['toggle_switcher'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $title        = is_string( $item['switch_title'] ?? null ) ? ( $item['switch_title'] ?? '' ) : '';
            $content_type = is_string( $item['content_type'] ?? null ) ? ( $item['content_type'] ?? 'content' ) : 'content';
            $content      = is_string( $item['switch_content'] ?? null ) ? ( $item['switch_content'] ?? '' ) : '';

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }
            if ( $content_type === 'content' && $content !== '' ) {
                $child_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            } elseif ( $content_type === 'template' ) {
                $this->engine->logWarning( "Toggle switch {$id}, item " . ( $idx + 1 ) . ": renders a saved Elementor template at runtime, which has no static Divi equivalent; only its title was carried over." );
            }

            $children[] = [
                'id'       => $id . '-tab-' . ( $idx + 1 ),
                'name'     => 'divi/tab',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, [
            'toggle_switcher',
            // wcf--toggle-switch's own layout and colour controls (toggle-switcher.php).
            'element_list', 'toggle_gap', 'before_label_color', 'after_label_color',
            'active_label_color', 'switcher_bg_color', 'switcher_active_bg_color',
            'switcher_knob_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/tabs',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
