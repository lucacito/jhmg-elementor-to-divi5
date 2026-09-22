<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--image-box-slider" widget (an animation/box-slider addon
 * widget bundled with themes such as Brandberry) to divi/slider + divi/slide
 * children — the same target SliderConverter uses for Elementor's own Slides
 * widget, since each item here carries the same title/description/image shape
 * as a full slide rather than a plain gallery photo.
 *
 * The source widget can show several boxes side by side (slides_to_show > 1);
 * divi/slider always shows one slide at a time, so that case is reported as
 * an approximation rather than silently collapsed.
 */
class WcfImageBoxSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_slider_' );
        $settings = $element['settings'] ?? [];

        $raw_slides = is_array( $settings['image_box_slider'] ?? null ) ? $settings['image_box_slider'] : [];
        $children   = [];

        foreach ( $raw_slides as $idx => $slide ) {
            if ( ! is_array( $slide ) ) {
                continue;
            }

            $title    = is_string( $slide['title'] ?? '' ) ? ( $slide['title'] ?? '' ) : '';
            $subtitle = is_string( $slide['subtitle'] ?? '' ) ? ( $slide['subtitle'] ?? '' ) : '';
            $desc     = is_string( $slide['description'] ?? '' ) ? ( $slide['description'] ?? '' ) : '';

            $image_raw = $slide['image'] ?? null;
            $img_url   = '';
            $img_alt   = '';
            if ( is_array( $image_raw ) ) {
                $img_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
                $img_alt = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';
            } elseif ( is_string( $image_raw ) ) {
                $img_url = $image_raw;
            }

            $slide_attrs = [];

            if ( $title !== '' ) {
                $slide_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }

            // divi/slide has no dedicated subtitle field (module.json: content is the
            // only body element); fold it into content ahead of the description, the
            // same way EAEL's CTA box subtitle is combined with its body text.
            $content_parts = array_filter( [ $subtitle, $desc ] );
            if ( $content_parts !== [] ) {
                $slide_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ];
            }

            if ( $img_url !== '' ) {
                $image_value = [ 'src' => $img_url ];
                if ( $img_alt !== '' ) {
                    $image_value['alt'] = $img_alt;
                }
                $slide_attrs['image'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ];
            }

            $children[] = [
                'id'       => $id . '-slide-' . ( $idx + 1 ),
                'name'     => 'divi/slide',
                'settings' => $slide_attrs,
                'elements' => [],
            ];
        }

        $block_settings = [];

        if ( ( $settings['autoplay'] ?? '' ) === 'yes' ) {
            $block_settings['module']['advanced']['auto'] = [ 'desktop' => [ 'value' => 'on' ] ];
        }

        if ( ( $settings['navigation'] ?? '' ) !== '' ) {
            $block_settings['arrows']['advanced']['show'] = [ 'desktop' => [ 'value' => 'on' ] ];
        }

        if ( ( $settings['pagination'] ?? '' ) !== '' ) {
            $block_settings['pagination']['advanced']['show'] = [ 'desktop' => [ 'value' => 'on' ] ];
        }

        $slides_to_show = (int) ( $settings['slides_to_show'] ?? 1 );
        if ( $slides_to_show > 1 ) {
            $this->engine->logNotCarriedOver(
                'slider_layout',
                $id,
                "showed {$slides_to_show} slides side by side; Divi's slider always shows one slide at a time"
            );
        }

        $this->engine->logConverted( 'slider' );
        $this->logUnmappedSettings( $id, $settings, [
            'image_box_slider',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile', 'slides_to_show_mobile_extra',
            'space_between', 'space_between_tablet', 'space_between_mobile', 'space_between_mobile_extra',
            'navigation', 'pagination', 'autoplay', 'center_slide', 'allow_touch_move',
            'image_box_style', 'slide_popover_toggle', 'btn_text',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/slider',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
