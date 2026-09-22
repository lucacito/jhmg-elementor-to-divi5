<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--content-slider" widget (Animation Addons for Elementor,
 * content-slider.php) to divi/group-carousel + one divi/group per slide.
 *
 * Each item is either free WYSIWYG HTML (content_type 'content', the common
 * case) or a reference to one of the site's own saved Elementor templates
 * (content_type 'template', elementor_templates holds its post ID) — that
 * template's content lives in a separate Elementor document this converter
 * never sees, so a 'template' item is reported rather than left empty.
 */
class WcfContentSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $raw_items = is_array( $settings['content_slider'] ?? null ) ? $settings['content_slider'] : [];
        $children  = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $type    = is_string( $item['content_type'] ?? null ) ? ( $item['content_type'] ?? 'content' ) : 'content';
            $group_id = $id . '-slide-' . ( $idx + 1 );

            if ( $type === 'template' ) {
                $template_id = $item['elementor_templates'] ?? '';
                $this->engine->logNotCarriedOver(
                    'saved_template',
                    $group_id,
                    'slide used a saved Elementor template (id ' . ( is_scalar( $template_id ) ? (string) $template_id : '?' ) . '); its content was not converted'
                );
                $children[] = [ 'id' => $group_id, 'name' => 'divi/group', 'settings' => [], 'elements' => [] ];
                continue;
            }

            $html = is_string( $item['slide_content'] ?? null ) ? ( $item['slide_content'] ?? '' ) : '';

            $children[] = [
                'id'       => $group_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $html !== '' ? [ [
                    'id'       => $group_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $html ] ] ] ],
                    'elements' => [],
                ] ] : [],
            ];
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'content_slider',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'autoplay', 'autoplay_delay', 'autoplay_interaction', 'allow_touch_move',
            'loop', 'mousewheel', 'speed', 'space_between', 'enable_grid', 'grid_rows',
            'navigation', 'navigation_previous_icon', 'navigation_next_icon',
            'pagination', 'pagination_type', 'direction',
            'slide_align', 'center_slide', 'effect', 'slide_popover_toggle',
            'slide_scale_x', 'slide_scale_y', 'slider_pagination_position', 'slider-max-width',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
