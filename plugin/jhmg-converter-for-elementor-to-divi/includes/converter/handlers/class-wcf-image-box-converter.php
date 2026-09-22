<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--image-box" widget (Animation Addons for Elementor,
 * image-box.php) to a divi/group with its image/icon/title/subtitle/
 * description/button built as ordinary child blocks.
 *
 * Unlike wcf--image-box-slider (a repeater of several boxes shown in a
 * carousel, WcfImageBoxSliderConverter), this widget is a single standalone
 * box — but it has the same shape (image, title, subtitle, description) plus
 * an optional icon and an optional button (Aaeaddon_Button_Trait's shared
 * 'btn_text'/'btn_link', the same controls the standalone wcf--button widget
 * uses via ButtonConverter's fallback). No single Divi module covers all of
 * that, so it's built the same free-form-container way as the slider's boxes.
 */
class WcfImageBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_box_' );
        $settings = $element['settings'] ?? [];

        $title    = is_string( $settings['title'] ?? null ) ? ( $settings['title'] ?? '' ) : '';
        $subtitle = is_string( $settings['subtitle'] ?? null ) ? ( $settings['subtitle'] ?? '' ) : '';
        $desc     = is_string( $settings['description'] ?? null ) ? ( $settings['description'] ?? '' ) : '';
        $title_tag = is_string( $settings['title_tag'] ?? null ) ? ( $settings['title_tag'] ?? 'h4' ) : 'h4';

        $image_raw = $settings['image'] ?? null;
        $img_url   = '';
        $img_alt   = '';
        if ( is_array( $image_raw ) ) {
            $img_url = is_string( $image_raw['url'] ?? null ) ? ( $image_raw['url'] ?? '' ) : ( is_string( $image_raw['src'] ?? null ) ? ( $image_raw['src'] ?? '' ) : '' );
            $img_alt = is_string( $image_raw['alt'] ?? null ) ? ( $image_raw['alt'] ?? '' ) : '';
        } elseif ( is_string( $image_raw ) ) {
            $img_url = $image_raw;
        }

        $children = [];

        $icon_control = $settings['image_box_icon'] ?? null;
        $divi_icon    = FontAwesomeIcons::fromControl( $icon_control );
        if ( $divi_icon !== null ) {
            $children[] = [
                'id'       => $id . '-icon',
                'name'     => 'divi/icon',
                'settings' => [ 'icon' => [ 'innerContent' => [ 'desktop' => [ 'value' => $divi_icon ] ] ] ],
                'elements' => [],
            ];
        }

        if ( $img_url !== '' ) {
            $image_value = [ 'src' => $img_url ];
            if ( $img_alt !== '' ) {
                $image_value['alt'] = $img_alt;
            }
            $children[] = [
                'id'       => $id . '-image',
                'name'     => 'divi/image',
                'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                'elements' => [],
            ];
            if ( $img_alt === '' ) {
                $this->engine->logWarning( "Image missing alt text: {$id}" );
            }
        }

        if ( $title !== '' ) {
            $children[] = [
                'id'       => $id . '-title',
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

        // divi/group has no dedicated subtitle field; fold it into the text
        // module ahead of the description, the same way
        // WcfImageBoxSliderConverter combines its per-slide subtitle+description.
        $content_parts = array_filter( [ $subtitle, $desc ] );
        if ( $content_parts !== [] ) {
            $children[] = [
                'id'       => $id . '-text',
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                'elements' => [],
            ];
        }

        $link_type = is_string( $settings['link_type'] ?? null ) ? ( $settings['link_type'] ?? 'button' ) : 'button';
        if ( $link_type === 'button' ) {
            $btn_text = is_string( $settings['btn_text'] ?? null ) ? ( $settings['btn_text'] ?? '' ) : '';
            $btn_link = is_array( $settings['btn_link'] ?? null ) ? $settings['btn_link'] : [];
            $btn_url  = is_string( $btn_link['url'] ?? '' ) ? ( $btn_link['url'] ?? '' ) : '';

            if ( $btn_text !== '' || $btn_url !== '' ) {
                $button_value = [];
                if ( $btn_text !== '' ) {
                    $button_value['text'] = $btn_text;
                }
                if ( $btn_url !== '' ) {
                    $button_value['linkUrl'] = $btn_url;
                }
                $children[] = [
                    'id'       => $id . '-button',
                    'name'     => 'divi/button',
                    'settings' => [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => $button_value ] ] ] ],
                    'elements' => [],
                ];
            }
        }

        $this->engine->logConverted( 'group' );

        // 'link_type' => 'wrapper' with 'details_link' wraps the whole box in an
        // <a>; divi/group has no module-level link attribute (fixtures/divi-schema/
        // modules.json), so that variant has no equivalent — it's still logged as
        // converted (the box's content itself carries over) rather than a failure.
        $this->logUnmappedSettings( $id, $settings, [
            'image', 'title', 'title_tag', 'subtitle', 'subtitle_position', 'description',
            'image_box_icon', 'icon', 'link_type', 'details_link', 'btn_text', 'btn_link',
            'image_box_style', 'img_content_direction', 'content_align', 'img_content_gap',
            'image_box_align', 'content_position', 'image_size', 'image_size_size',
            'img_width', 'img_height', 'object_fit', 'object_position', 'img_b_radius',
            'box_padding', 'box_border_radius', 'box_border_border', 'el_hover_effects',
            'title_space', 'title_hover_space', 'title_color', 'title_typography_typography',
            'subtitle_space', 'subtitle_color', 'subtitle_typography_typography',
            'desc_space', 'desc_color', 'desc_typography_typography',
            'icon_space', 'icon_color', 'icon_size', 'icon_rotate',
            'btn_element_list', 'btn_hover_list',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
