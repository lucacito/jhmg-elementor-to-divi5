<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TabsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_tabs_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['tabs'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $title   = is_string( $item['tab_title'] ?? '' ) ? ( $item['tab_title'] ?? '' ) : '';
            $content = is_string( $item['tab_content'] ?? '' ) ? ( $item['tab_content'] ?? '' ) : '';

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

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, [
            'tabs', 'type', 'tab_width',
            // wcf--tabs' own layout and colour controls (tabs.php).
            'tabs_direction', 'tabs_align', 'tabs_content_type', 'navigation_width',
            'breakpoint_selector', 'title_text_color', 'title_text_hover_color',
            'title_hover_border_color', 'view',
            // wcf--services-tab's own extra widget-level controls (services-tab.php):
            // each item's own 'tab_number'/'tab_image'/'link' aren't top-level
            // settings keys, so they need no entry here; 'element_list' is the
            // style-variant switch, 'btn_text'/'image_size'/'image_size_size'
            // are its Button/Image sections (register_button_content_controls()
            // called with 'btn_link' => false — items link via their own 'link').
            'element_list', 'btn_text', 'image_size', 'image_size_size',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/tabs',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
