<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TestimonialConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_testimonial_' );
        $settings = $element['settings'] ?? [];

        $content = is_string( $settings['testimonial_content'] ?? '' ) ? ( $settings['testimonial_content'] ?? '' ) : '';
        $name    = is_string( $settings['testimonial_name'] ?? '' ) ? ( $settings['testimonial_name'] ?? '' ) : '';
        $job     = is_string( $settings['testimonial_job'] ?? '' ) ? ( $settings['testimonial_job'] ?? '' ) : '';

        $image_raw = $settings['testimonial_image'] ?? [];
        $image_url = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        }

        // divi/testimonial (testimonial/module.json, TestimonialModule.php): content,
        // author and jobTitle are each an innerContent; the photo is
        // portrait.innerContent {src} (line 98). The old company.innerContent
        // {author, company} and module.advanced.portrait were never read, so only
        // the quote showed (found on the Painting Company kit by the schema test).
        $block_settings = [];
        if ( $content !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $content;
        }
        if ( $name !== '' ) {
            $block_settings['author']['innerContent']['desktop']['value'] = $name;
        }
        if ( $job !== '' ) {
            $block_settings['jobTitle']['innerContent']['desktop']['value'] = $job;
        }
        if ( $image_url !== '' ) {
            $block_settings['portrait']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
        }

        $this->engine->logConverted( 'testimonial' );
        $this->logUnmappedSettings( $id, $settings, [
            'testimonial_content', 'testimonial_name', 'testimonial_job', 'testimonial_image',
            'testimonial_alignment', 'testimonial_cite_separator',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/testimonial',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
