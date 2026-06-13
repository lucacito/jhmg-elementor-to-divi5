<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the ElementsKit `elementskit-testimonial` widget to one
 * `divi/testimonial` block per testimonial item.
 *
 * Returns an array of blocks (multi-block return) which ConverterEngine
 * spreads as siblings inside the parent column.
 */
class ElementskitTestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_testimonial_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['ekit_testimonial_data'] ?? [];

        $this->engine->logConverted( 'elementskit-testimonial' );
        $this->logUnmappedSettings( $id, $settings, [
            'ekit_testimonial_data',
            'ekit_testimonial_style',
            'ekit_testimonial_wartermark_position',
            'ekit_testimonial_slidetoshow',
            'ekit_testimonial_slidetoshow_tablet',
            'ekit_testimonial_slidetoshow_mobile',
            'ekit_testimonial_autoplay',
            'ekit_testimonial_pause_on_hover',
            'ekit_testimonial_wartermark_enable',
            'ekit_testimonial_layout_padding',
            'ekit_testimonial_layout_border_radius',
            'ekit_testimonial_layout_background_background',
            'ekit_testimonial_layout_shadow_box_shadow_type',
            'ekit_testimonial_layout_shadow_box_shadow',
            'ekit_testimonial_layout_active_background_background',
            'ekit_testimonial_section_wraper_padding',
            'ekit_testimonial_section_wraper_padding_mobile',
            'ekit_testimonial_description_margin',
            'ekit_testimonial_description_margin_tablet',
            'ekit_testimonial_section_wathermark_typography',
            'ekit_testimonial_section_wathermark_margin_bottom',
            'ekit_testimonial_client_name_spacing_bottom',
            'ekit_testimonial_designation_typography_typography',
            'ekit_testimonial_designation_typography_line_height',
            'ekit_testimonial_designation_typography_line_height_tablet',
            'ekit_testimonial_designation_typography_line_height_mobile',
            'ekit_testimonial_designation_typography_font_size',
            'ekit_testimonial_designation_typography_letter_spacing',
            'ekit_testimonial_client_img_pos',
            'ekit_testimonial_client_img_pos_tablet',
            'ekit_testimonial_client_area_alignment',
            'ekit_testimonial_client_image_size',
            'ekit_testimonial_client_image_margin_',
            'ekit_testimonial_client_image_margin__tablet',
            'ekit_testimonial_left_right_spacing',
            'ekit_testimonial_left_right_spacing_tablet',
            'ekit_testimonial_left_right_spacing_mobile',
            'ekit_testimonial_layout_margin',
            'ekit_testimonial_rating_enable',
            '_padding',
            '_padding_tablet',
            '_element_custom_width_mobile',
            '_css_classes',
        ] );

        if ( empty( $items ) ) {
            $this->engine->logWarning( "Testimonial widget {$id} has no items." );
            return [];
        }

        $blocks = [];
        foreach ( $items as $index => $item ) {
            $blocks[] = $this->buildTestimonialBlock( $id . '-' . $index, $item );
        }

        return $blocks;
    }

    private function buildTestimonialBlock( string $block_id, array $item ): array {
        $name        = is_string( $item['client_name'] ?? '' ) ? $item['client_name'] : '';
        $designation = is_string( $item['designation'] ?? '' ) ? $item['designation'] : '';
        $review      = is_string( $item['review'] ?? '' ) ? $item['review'] : '';
        $photo_url   = is_string( ( $item['client_photo'] ?? [] )['url'] ?? '' )
            ? ( $item['client_photo']['url'] ?? '' )
            : '';

        $attrs = [];

        if ( $review !== '' ) {
            $attrs['content']['innerContent']['desktop']['value'] = $review;
        }

        if ( $name !== '' ) {
            $attrs['author']['innerContent']['desktop']['value'] = $name;
        }

        if ( $designation !== '' ) {
            $attrs['jobTitle']['innerContent']['desktop']['value'] = $designation;
        }

        if ( $photo_url !== '' ) {
            $attrs['portrait']['innerContent']['desktop']['value']['src'] = $photo_url;
        }

        return [
            'id'       => $block_id,
            'name'     => 'divi/testimonial',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
