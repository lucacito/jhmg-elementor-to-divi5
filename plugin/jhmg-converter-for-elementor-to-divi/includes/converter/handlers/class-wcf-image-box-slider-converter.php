<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--image-box-slider" widget (Animation Addons for Elementor,
 * image-box-slider.php) to divi/group-carousel + one divi/group per box.
 *
 * The widget can show several boxes side by side (slides_to_show), unlike
 * Elementor's own Slides widget (one full slide at a time), so it targets
 * divi/group-carousel rather than divi/slider — group-carousel's
 * module.advanced.slidesToShow/slidesToScroll/centerMode natively support that,
 * with no approximation needed. Each divi/group is a free-form container
 * (module.json: childrenName []), so its title/subtitle/description/image are
 * built as ordinary child blocks (divi/image, divi/heading, divi/text) rather
 * than through fixed slide fields the way divi/slide works.
 */
class WcfImageBoxSliderConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $raw_slides = is_array( $settings['image_box_slider'] ?? null ) ? $settings['image_box_slider'] : [];
        $children   = [];

        foreach ( $raw_slides as $idx => $slide ) {
            if ( ! is_array( $slide ) ) {
                continue;
            }

            $title    = is_string( $slide['title'] ?? null ) ? ( $slide['title'] ?? '' ) : '';
            $subtitle = is_string( $slide['subtitle'] ?? null ) ? ( $slide['subtitle'] ?? '' ) : '';
            $desc     = is_string( $slide['description'] ?? null ) ? ( $slide['description'] ?? '' ) : '';

            $image_raw = $slide['image'] ?? null;
            $img_url   = '';
            $img_alt   = '';
            if ( is_array( $image_raw ) ) {
                $img_url = is_string( $image_raw['url'] ?? null ) ? ( $image_raw['url'] ?? '' ) : '';
                $img_alt = is_string( $image_raw['alt'] ?? null ) ? ( $image_raw['alt'] ?? '' ) : '';
            } elseif ( is_string( $image_raw ) ) {
                $img_url = $image_raw;
            }

            $group_children = [];
            $group_id       = $id . '-box-' . ( $idx + 1 );

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
            }

            if ( $title !== '' ) {
                $group_children[] = [
                    'id'       => $group_id . '-title',
                    'name'     => 'divi/heading',
                    'settings' => [
                        'title' => [
                            'innerContent' => [ 'desktop' => [ 'value' => $title ] ],
                            'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => 'h3' ] ] ] ] ],
                        ],
                    ],
                    'elements' => [],
                ];
            }

            // divi/group has no dedicated subtitle field; fold it into the text
            // module ahead of the description, the same way EAEL's CTA box
            // subtitle is combined with its body text.
            $content_parts = array_filter( [ $subtitle, $desc ] );
            if ( $content_parts !== [] ) {
                $group_children[] = [
                    'id'       => $group_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                    'elements' => [],
                ];
            }

            $children[] = [
                'id'       => $group_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $group_children,
            ];
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        if ( ( $settings['center_slide'] ?? '' ) === 'yes' ) {
            $block_settings['module']['advanced']['centerMode']['desktop']['value'] = 'on';
        }

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'image_box_slider',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile', 'slides_to_show_mobile_extra',
            'space_between', 'space_between_tablet', 'space_between_mobile', 'space_between_mobile_extra',
            'navigation', 'pagination', 'autoplay', 'center_slide', 'allow_touch_move',
            'image_box_style', 'slide_popover_toggle', 'btn_text', 'wcf-animation',
            // Style-only controls found on a real client export: box padding/radius
            // and title/subtitle colour+typography for the widget's own box styles
            // (image-box-slider.php) — no divi/group-carousel or divi/heading/text
            // equivalent beyond what the child blocks already carry.
            'box_padding', 'box_padding_mobile', 'box_padding_tablet',
            'content_padding_mobile', 'content_padding_tablet',
            'box_border_radius',
            'title_color', 'title_typography_typography',
            'title_typography_font_size', 'title_typography_font_size_mobile', 'title_typography_font_size_tablet',
            'title_typography_font_weight', 'title_typography_line_height',
            'subtitle_color', 'subtitle_typography_typography',
            'subtitle_typography_font_size', 'subtitle_typography_font_weight', 'subtitle_typography_line_height',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
