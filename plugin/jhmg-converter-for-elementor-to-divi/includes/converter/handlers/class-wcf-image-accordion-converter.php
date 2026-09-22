<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--image-accordion" widget (Animation Addons for Elementor,
 * image-accordion.php) to a divi/group of divi/group children.
 *
 * Unlike the group-carousel cluster (wcf--image-box-slider and friends), this
 * widget has no Aaeaddon_Slider_Trait — every item is shown side by side at
 * once, with no slidesToShow/navigation, so a plain divi/group wrapper is
 * enough; each item becomes its own free-form divi/group child (image, title,
 * subtitle+description, button), the same shape WcfImageBoxSliderConverter
 * builds per-slide. 'title_tag' and 'btn_text' are widget-wide (one heading
 * level and one button label for every item); each item's own link comes from
 * its own 'details_link' repeater field, not a shared URL control
 * (register_button_content_controls() is called with 'btn_link' => false).
 */
class WcfImageAccordionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_accordion_' );
        $settings = $element['settings'] ?? [];

        $title_tag = is_string( $settings['title_tag'] ?? null ) ? ( $settings['title_tag'] ?? 'h4' ) : 'h4';
        $link_type = is_string( $settings['link_type'] ?? null ) ? ( $settings['link_type'] ?? 'button' ) : 'button';
        $btn_text  = is_string( $settings['btn_text'] ?? null ) ? ( $settings['btn_text'] ?? '' ) : '';

        $items    = is_array( $settings['accordions'] ?? null ) ? $settings['accordions'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title    = is_string( $item['title'] ?? null ) ? ( $item['title'] ?? '' ) : '';
            $subtitle = is_string( $item['subtitle'] ?? null ) ? ( $item['subtitle'] ?? '' ) : '';
            $desc     = is_string( $item['description'] ?? null ) ? ( $item['description'] ?? '' ) : '';

            $image_raw = $item['image'] ?? null;
            $img_url   = '';
            $img_alt   = '';
            if ( is_array( $image_raw ) ) {
                $img_url = is_string( $image_raw['url'] ?? null ) ? ( $image_raw['url'] ?? '' ) : '';
                $img_alt = is_string( $image_raw['alt'] ?? null ) ? ( $image_raw['alt'] ?? '' ) : '';
            } elseif ( is_string( $image_raw ) ) {
                $img_url = $image_raw;
            }

            $item_children = [];
            $item_id       = $id . '-item-' . ( $idx + 1 );

            if ( $img_url !== '' ) {
                $image_value = [ 'src' => $img_url ];
                if ( $img_alt !== '' ) {
                    $image_value['alt'] = $img_alt;
                }
                $item_children[] = [
                    'id'       => $item_id . '-image',
                    'name'     => 'divi/image',
                    'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                    'elements' => [],
                ];
                if ( $img_alt === '' ) {
                    $this->engine->logWarning( "Image missing alt text: {$item_id}" );
                }
            }

            if ( $title !== '' ) {
                $item_children[] = [
                    'id'       => $item_id . '-title',
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
                $item_children[] = [
                    'id'       => $item_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                    'elements' => [],
                ];
            }

            if ( $link_type === 'button' ) {
                $details_link = is_array( $item['details_link'] ?? null ) ? $item['details_link'] : [];
                $btn_url      = is_string( $details_link['url'] ?? '' ) ? ( $details_link['url'] ?? '' ) : '';

                if ( $btn_text !== '' || $btn_url !== '' ) {
                    $button_value = [];
                    if ( $btn_text !== '' ) {
                        $button_value['text'] = $btn_text;
                    }
                    if ( $btn_url !== '' ) {
                        $button_value['linkUrl'] = $btn_url;
                    }
                    $item_children[] = [
                        'id'       => $item_id . '-button',
                        'name'     => 'divi/button',
                        'settings' => [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => $button_value ] ] ] ],
                        'elements' => [],
                    ];
                }
            }

            $children[] = [
                'id'       => $item_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $item_children,
            ];
        }

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'accordions', 'title_tag', 'link_type', 'btn_text', 'btn_link', 'btn_element_list',
            // wcf--image-accordion's own layout and style controls (image-accordion.php).
            'accordion_layout', 'mobile_breakpoint', 'image_size', 'image_size_size',
            'overlay_color', 'content_padding', 'title_color', 'title_typography_typography',
            'subtitle_color', 'subtitle_typography_typography',
            'desc_color', 'description_typography_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
