<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor's `nested-accordion` widget to divi/accordion +
 * divi/accordion-item.
 *
 * Shape read from Elementor 3.28.2
 * modules/nested-accordion/widgets/nested-accordion.php:
 *
 * - `settings.items` is a repeater carrying ONLY the titles: each entry has
 *   `item_title`, plus `element_css_id` and `_id`.
 * - each item's body is a separate `container` in the widget's own `elements`
 *   array, index-aligned with that repeater. render() pairs them with
 *   `$this->print_child( $index )`, and Widget_Nested_Base::get_raw_data()
 *   serialises the children into `elements`.
 * - `settings.title_tag` sets the heading tag; `settings.default_state`
 *   ('expanded'/'closed') controls whether the first item starts open.
 *
 * This is the widget Elementor has shipped as its default accordion since 3.15,
 * so an unregistered slug here meant most current exports lost their accordions
 * entirely.
 */
class NestedAccordionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_accordion_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['items'] ?? [];
        $bodies   = array_values( array_filter( $element['elements'] ?? [], 'is_array' ) );

        $style = ( new StyleMapper() )->map( 'accordion', $settings );

        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title = is_string( $item['item_title'] ?? '' ) ? ( $item['item_title'] ?? '' ) : '';

            // Index alignment is the widget's own contract between `items` and
            // its children; a missing body is an item with no content, not a
            // reason to drop the item.
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
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/accordion-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        // A body with no matching repeater entry would otherwise be dropped in
        // silence, which is the failure this converter exists to remove.
        if ( count( $bodies ) > count( $children ) ) {
            $this->engine->logWarning( sprintf(
                'Nested accordion %s has %d content panels but %d titles; the surplus panels were not converted.',
                $id,
                count( $bodies ),
                count( $children )
            ) );
        }

        $this->engine->logConverted( 'accordion' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'items', 'title_tag', 'default_state', 'max_items_expended', 'faq_schema',
                'accordion_item_title_icon', 'accordion_item_title_icon_active',
                'accordion_item_title_position_horizontal', 'accordion_item_title_icon_position',
                'accordion_item_title_space_between', 'accordion_item_title_distance_from_content',
            ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/accordion',
            'settings' => $style['divi_attrs'],
            'elements' => $children,
        ];
    }
}
