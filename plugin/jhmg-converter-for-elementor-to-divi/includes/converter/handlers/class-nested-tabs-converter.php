<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor's `nested-tabs` widget to divi/tabs + divi/tab.
 *
 * Shape read from Elementor 3.28.2
 * modules/nested-tabs/widgets/nested-tabs.php:
 *
 * - `settings.tabs` is a repeater carrying ONLY the titles: each entry has
 *   `tab_title`, plus `element_css_id` and `_id`. render_tab_titles_html()
 *   reads `$item_settings['item']['tab_title']`.
 * - each tab's body is a separate `container` in the widget's own `elements`
 *   array, index-aligned with that repeater, printed by
 *   `$this->print_child( $item_settings['index'], $item_settings )`.
 *
 * Elementor's default tabs widget since 3.15; unregistered, every current
 * export lost its tabs entirely.
 */
class NestedTabsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_tabs_' );
        $settings = $element['settings'] ?? [];
        $tabs     = $settings['tabs'] ?? [];
        $bodies   = array_values( array_filter( $element['elements'] ?? [], 'is_array' ) );

        $style = ( new StyleMapper() )->map( 'tabs', $settings );

        $children = [];

        foreach ( $tabs as $idx => $tab ) {
            if ( ! is_array( $tab ) ) {
                continue;
            }

            $title = is_string( $tab['tab_title'] ?? '' ) ? ( $tab['tab_title'] ?? '' ) : '';

            $content = isset( $bodies[ $idx ] )
                ? $this->inlineHtmlFromNestedChild( $bodies[ $idx ] )
                : '';

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }
            if ( $content !== '' ) {
                $child_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            }

            $children[] = [
                'id'       => $id . '-tab-' . ( $idx + 1 ),
                'name'     => 'divi/tab',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        if ( count( $bodies ) > count( $children ) ) {
            $this->engine->logWarning( sprintf(
                'Nested tabs %s has %d content panels but %d titles; the surplus panels were not converted.',
                $id,
                count( $bodies ),
                count( $children )
            ) );
        }

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'tabs', 'tabs_direction', 'tabs_justify_horizontal', 'tabs_title_space_between',
                'tabs_title_distance_from_content', 'tab_icon', 'tab_icon_active',
                'tab_icon_position', 'horizontal_scroll',
            ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/tabs',
            'settings' => $style['divi_attrs'],
            'elements' => $children,
        ];
    }
}
