<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelTestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_testimonial_' );
        $settings = $element['settings'] ?? [];

        $name    = is_string( $settings['eael_testimonial_name'] ?? '' ) ? ( $settings['eael_testimonial_name'] ?? '' ) : '';
        $company = is_string( $settings['eael_testimonial_company_title'] ?? '' ) ? ( $settings['eael_testimonial_company_title'] ?? '' ) : '';
        $content = is_string( $settings['eael_testimonial_description'] ?? '' ) ? ( $settings['eael_testimonial_description'] ?? '' ) : '';

        $image_raw = $settings['image'] ?? [];
        $image_url = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        }

        $block_settings = [];

        if ( $content !== '' ) {
            $block_settings['content'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $content ] ],
            ];
        }

        if ( $name !== '' || $company !== '' ) {
            $block_settings['company'] = [
                'innerContent' => [ 'desktop' => [ 'value' => array_filter( [
                    'author'  => $name,
                    'company' => $company,
                ] ) ] ],
            ];
        }

        if ( $image_url !== '' ) {
            $block_settings['module'] = [
                'advanced' => [
                    'portrait' => [ 'desktop' => [ 'value' => [ 'src' => $image_url ] ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'testimonial' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_testimonial_name', 'eael_testimonial_company_title',
            'eael_testimonial_description', 'image',
            'eael_testimonial_style', 'eael_testimonial_enable_avatar',
            'eael_testimonial_enable_rating', 'eael_testimonial_rating_number',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/testimonial',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
