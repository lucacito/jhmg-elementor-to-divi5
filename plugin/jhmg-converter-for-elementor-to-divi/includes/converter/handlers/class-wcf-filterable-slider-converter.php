<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--filterable-slider" widget (Animation Addons for
 * Elementor, filterable-slider.php) to divi/group-carousel + one divi/group
 * per project — the same shape WcfImageBoxSliderConverter already builds
 * per-slide (image/title/subtitle/description as child blocks).
 *
 * The widget's JS category filtering (each item's own
 * 'project_item_filter_name' plus a separate 'filter_items' repeater of
 * filter-button labels) has no Divi carousel equivalent and is logged as not
 * carried over — the items themselves still convert. Each item's own 'link'
 * wraps the whole slide in an <a>; divi/group has no module-level link
 * attribute (the same gap wcf--image-box's 'details_link' / 'link_type' =>
 * 'wrapper' already has), so it's left unmapped rather than fabricating a
 * button the real widget never had.
 */
class WcfFilterableSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $title_tag  = is_string( $settings['title_tag'] ?? null ) ? ( $settings['title_tag'] ?? 'h3' ) : 'h3';
        $raw_items  = is_array( $settings['project_items'] ?? null ) ? $settings['project_items'] : [];
        $children   = [];
        $has_filter = false;

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title    = is_string( $item['title'] ?? null ) ? ( $item['title'] ?? '' ) : '';
            $subtitle = is_string( $item['subtitle'] ?? null ) ? ( $item['subtitle'] ?? '' ) : '';
            $desc     = is_string( $item['description'] ?? null ) ? ( $item['description'] ?? '' ) : '';

            $image_raw = $item['project_image'] ?? null;
            $img_url   = '';
            $img_alt   = '';
            if ( is_array( $image_raw ) ) {
                $img_url = is_string( $image_raw['url'] ?? null ) ? ( $image_raw['url'] ?? '' ) : '';
                $img_alt = is_string( $image_raw['alt'] ?? null ) ? ( $image_raw['alt'] ?? '' ) : '';
            }

            $group_children = [];
            $group_id       = $id . '-item-' . ( $idx + 1 );

            if ( $img_url !== '' ) {
                $image_value = [ 'src' => $img_url ];
                if ( $img_alt !== '' ) {
                    $image_value['alt'] = $img_alt;
                }
                $group_children[] = [
                    'id'       => $group_id . '-image',
                    'name'     => 'divi/image',
                    'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                    'elements' => [],
                ];
                if ( $img_alt === '' ) {
                    $this->engine->logWarning( "Image missing alt text: {$group_id}-image" );
                }
            }

            if ( $title !== '' ) {
                $group_children[] = [
                    'id'       => $group_id . '-title',
                    'name'     => 'divi/heading',
                    'settings' => [
                        'title' => [
                            'innerContent' => [ 'desktop' => [ 'value' => $title ] ],
                            'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => $title_tag ] ] ] ] ],
                        ],
                    ],
                    'elements' => [],
                ];
            }

            $content_parts = array_filter( [ $subtitle, $desc ] );
            if ( $content_parts !== [] ) {
                $group_children[] = [
                    'id'       => $group_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                    'elements' => [],
                ];
            }

            if ( ! empty( $item['project_item_filter_name'] ?? '' ) ) {
                $has_filter = true;
            }

            $children[] = [
                'id'       => $group_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $group_children,
            ];
        }

        if ( $has_filter || is_array( $settings['filter_items'] ?? null ) ) {
            $this->engine->logNotCarriedOver( 'carousel_filter', (string) $id, "the widget's JS category-filter buttons have no Divi group-carousel equivalent; items were converted without filtering" );
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'project_items', 'title_tag', 'filter_items', 'show_filter', 'filter_all_text',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'space_between', 'navigation', 'pagination', 'autoplay',
            'project_item_filter_name', 'link',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
