<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--nested-slider" widget (Animation Addons for Elementor,
 * nested-slider.php) to divi/group-carousel + one divi/group per slide.
 *
 * Unlike every other carousel widget in this suite, 'carousel_items' is a
 * Control_Nested_Repeater (frontend_available): each slide's body is a full
 * container of real Elementor elements in the widget's own top-level
 * 'elements' array, index-aligned with the repeater — the same shape
 * Elementor's own nested-tabs/nested-accordion use (NestedTabsConverter).
 * Those bodies are recursively converted with convertChildren() and nested
 * as real child blocks inside each divi/group, since — unlike divi/tab or
 * divi/accordion-item — divi/group actually supports child modules
 * (childrenName: [] means free-form, not "no children").
 *
 * Each item's own 'slide_title' is confirmed from source
 * (nested-slider.php's render()/data-binding attributes) to be purely an
 * editor-panel label, never printed on the frontend, so it's dropped rather
 * than fabricated as visible content.
 */
class WcfNestedSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $items  = is_array( $settings['carousel_items'] ?? null ) ? $settings['carousel_items'] : [];
        $bodies = array_values( array_filter( $element['elements'] ?? [], 'is_array' ) );

        $children = [];
        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $body_children = isset( $bodies[ $idx ] ) ? $this->convertChildren( $bodies[ $idx ] ) : [];

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $body_children,
            ];
        }

        if ( count( $bodies ) > count( $children ) ) {
            $this->engine->logWarning( sprintf(
                'Nested slider %s has %d slide bodies but %d slide entries; the surplus bodies were not converted.',
                $id,
                count( $bodies ),
                count( $children )
            ) );
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'carousel_items', 'carousel_name', 'slide_title',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'space_between', 'navigation', 'pagination', 'autoplay',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
