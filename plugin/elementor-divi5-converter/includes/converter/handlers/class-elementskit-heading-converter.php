<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the ElementsKit Heading (elementskit-heading) widget to Divi 5 blocks.
 *
 * Produces a divi/heading for the main title and, when present, a divi/text
 * block for the extra description content.
 */
class ElementskitHeadingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $title     = (string) ( $settings['ekit_heading_title'] ?? '' );
        $tag       = (string) ( $settings['ekit_heading_title_tag'] ?? 'h4' );
        $sub_title = (string) ( $settings['ekit_heading_sub_title'] ?? '' );
        $extra     = (string) ( $settings['ekit_heading_extra_title'] ?? '' );
        $align_raw = (string) ( $settings['ekit_heading_title_align'] ?? '' );

        // Elementor ekit uses 'text_center' format; strip the 'text_' prefix for CSS.
        $align = str_replace( 'text_', '', $align_raw );

        $style = ( new StyleMapper() )->map( 'heading', $settings );
        $attrs = $style['divi_attrs'];

        // Strip ElementsKit focused-word delimiters: "{{word}}" → "word".
        $title_text = html_entity_decode( preg_replace( '/\{\{(.*?)\}\}/s', '$1', $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        $title_attrs = [
            'innerContent' => [
                'desktop' => [ 'value' => $title_text ],
            ],
        ];

        if ( isset( $attrs['title']['decoration'] ) ) {
            $title_attrs['decoration'] = $attrs['title']['decoration'];
        }

        if ( $tag !== '' ) {
            $title_attrs['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        if ( $align !== '' ) {
            $title_attrs['decoration']['font']['font']['desktop']['value']['textAlign'] = $align;
        }

        $attrs['title'] = $title_attrs;

        $handled = array_merge(
            [
                'ekit_heading_title', 'ekit_heading_title_tag', 'ekit_heading_sub_title',
                'ekit_heading_extra_title', 'ekit_heading_title_align',
                'ekit_heading_title_align_mobile', 'ekit_heading_title_align_tablet',
                'ekit_heading_section_extra_title_show', 'shadow_text_content',
                'ekit_heading_title_margin', 'ekit_heading_extra_title_margin',
                'ekit_heading_seperator_image',
                // Color / hover color for title variants — not yet mapped.
                'ekit_heading_title_color', 'ekit_heading_title_color_hover',
                'ekit_heading_focused_title_color', 'ekit_heading_focused_title_color_hover',
                'ekit_heading_extra_title_color',
                // Focused-title typography — not yet mapped.
                'ekit_heading_focused_title_typography_typography',
                'ekit_heading_focused_title_typography_font_family',
                'ekit_heading_focused_title_typography_font_size',
                'ekit_heading_focused_title_typography_font_weight',
                'ekit_heading_focused_title_typography_line_height',
                'ekit_heading_focused_title_typography_letter_spacing',
                // Extra-title typography — not yet mapped.
                'ekit_heading_extra_title_typography_typography',
                'ekit_heading_extra_title_typography_font_family',
                'ekit_heading_extra_title_typography_font_size',
                'ekit_heading_extra_title_typography_font_weight',
                'ekit_heading_extra_title_typography_line_height',
                'ekit_heading_extra_title_typography_letter_spacing',
                // Image/video fallbacks for heading decorators — not mappable.
                'title_left_border_color_image', 'title_left_border_color_video_fallback',
                'ekit_heading_focused_title_secondary_bg_image', 'ekit_heading_focused_title_secondary_bg_video_fallback',
                'ekit_heading_title_secondary_bg_image', 'ekit_heading_title_secondary_bg_video_fallback',
                'ekit_heading_sub_title_secondary_bg_image', 'ekit_heading_sub_title_secondary_bg_video_fallback',
                'ekit_heading_sub_title_border_color_left_image', 'ekit_heading_sub_title_border_color_left_video_fallback',
                'ekit_heading_sub_title_border_color_right_image', 'ekit_heading_sub_title_border_color_right_video_fallback',
            ],
            $style['handled_keys']
        );

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, $handled );

        $heading_block = [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $attrs,
            'elements' => [],
        ];

        // When there is extra description content, emit it as a sibling text block.
        $extra_stripped = trim( function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $extra ) : strip_tags( $extra ) );
        if ( $extra_stripped !== '' ) {
            $this->engine->logConverted( 'text' );
            return [
                $heading_block,
                [
                    'id'       => $id . '-desc',
                    'name'     => 'divi/text',
                    'settings' => [
                        'content' => [
                            'innerContent' => [
                                'desktop' => [ 'value' => $extra ],
                            ],
                        ],
                    ],
                    'elements' => [],
                ],
            ];
        }

        return $heading_block;
    }
}
