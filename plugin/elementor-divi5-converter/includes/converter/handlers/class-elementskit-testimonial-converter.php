<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the ElementsKit `elementskit-testimonial` widget to a
 * `divi/group-carousel` wrapping one `divi/testimonial` block per item.
 *
 * Carousel settings (slidesToShow, auto, pauseOnHover) are mapped from
 * the EKIT slider controls where available; defaults mirror the EKIT defaults.
 */
class ElementskitTestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
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
            'ekit_testimonial_loop',
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
            'ekit_testimonial_client_image_size_tablet',
            'ekit_testimonial_client_image_size_mobile',
            'ekit_testimonial_client_image_margin_',
            'ekit_testimonial_client_image_margin__tablet',
            'ekit_testimonial_left_right_spacing',
            'ekit_testimonial_left_right_spacing_tablet',
            'ekit_testimonial_left_right_spacing_mobile',
            'ekit_testimonial_layout_margin',
            'ekit_testimonial_layout_margin_tablet',
            'ekit_testimonial_layout_margin_mobile',
            'ekit_testimonial_top_bottom_spacing',
            'ekit_testimonial_top_bottom_spacing_mobile',
            'ekit_testimonial_description_margin_mobile',
            'ekit_testimonial_layout_padding_tablet',
            'ekit_testimonial_rating_enable',
            'ekit_testimonial_review_ratting_right_spacing',
            'ekit_testimonial_review_ratting_color',
            'ekit_testimonial_section_wathermark_typography_tablet',
            'ekit_testimonial_section_wathermark_typography_mobile',
            'ekit_testimonial_client_image_background_image',
            'ekit_testimonial_client_image_background_video_fallback',
            'ekit_testimonial_client_image_background_slideshow_gallery',
            '_padding',
            '_padding_tablet',
            '_margin',
            '_margin_tablet',
            '_margin_mobile',
            '_element_custom_width_mobile',
            '_css_classes',
        ] );

        if ( empty( $items ) ) {
            $this->engine->logWarning( "Testimonial widget {$id} has no items." );
            return [];
        }

        $carousel_settings = $this->buildCarouselSettings( $settings );

        $slide_groups = [];
        foreach ( $items as $index => $item ) {
            $testimonial = $this->buildTestimonialBlock( $id . '-' . $index, $item );
            // group-carousel requires direct children to be divi/group blocks.
            // GroupModule detects the carousel parent and wraps itself in
            // <div class="et_pb_group_carousel_slide">, which the carousel JS
            // needs to identify individual slides.
            $slide_groups[] = [
                'id'       => $id . '-group-' . $index,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => [ $testimonial ],
            ];
        }

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $carousel_settings,
            'elements' => $slide_groups,
        ];
    }

    /**
     * Build the divi/group-carousel settings from EKIT slider controls.
     */
    private function buildCarouselSettings( array $settings ): array {
        $slides_desktop = (string) ( $settings['ekit_testimonial_slidetoshow'] ?? '1' );
        $slides_tablet  = (string) ( $settings['ekit_testimonial_slidetoshow_tablet'] ?? '' );
        $slides_phone   = (string) ( $settings['ekit_testimonial_slidetoshow_mobile'] ?? '1' );

        $autoplay     = ( $settings['ekit_testimonial_autoplay'] ?? '' ) === 'yes' ? 'on' : 'off';
        $pause_hover  = ( $settings['ekit_testimonial_pause_on_hover'] ?? 'yes' ) !== 'no' ? 'on' : 'off';

        $slides_to_show = [ 'desktop' => [ 'value' => $slides_desktop ] ];
        if ( $slides_tablet !== '' ) {
            $slides_to_show['tablet'] = [ 'value' => $slides_tablet ];
        }
        if ( $slides_phone !== '' ) {
            $slides_to_show['phone'] = [ 'value' => $slides_phone ];
        }

        return [
            'module' => [
                'advanced' => [
                    'slidesToShow'   => $slides_to_show,
                    'slidesToScroll' => [ 'desktop' => [ 'value' => '1' ] ],
                    'auto'           => [ 'desktop' => [ 'value' => $autoplay ] ],
                    'pauseOnHover'   => [ 'desktop' => [ 'value' => $pause_hover ] ],
                ],
            ],
            'arrows' => [
                'advanced' => [
                    'showArrows' => [ 'desktop' => [ 'value' => 'on' ] ],
                ],
            ],
            'dotNav' => [
                'advanced' => [
                    'showDots' => [ 'desktop' => [ 'value' => 'on' ] ],
                ],
            ],
        ];
    }

    private function buildTestimonialBlock( string $block_id, array $item ): array {
        $name        = is_string( $item['client_name'] ?? '' ) ? trim( (string) $item['client_name'] ) : '';
        $designation = is_string( $item['designation'] ?? '' ) ? trim( (string) $item['designation'] ) : '';
        $review      = is_string( $item['review'] ?? '' ) ? trim( (string) $item['review'] ) : '';
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
