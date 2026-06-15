<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Star Rating widget to divi/text with Unicode star characters.
 *
 * Divi 5 has no native star-rating module, so the rating is rendered as a simple
 * text block containing filled (★) and empty (☆) Unicode stars.
 */
class StarRatingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_stars_' );
        $settings = $element['settings'] ?? [];

        $raw_rating = $settings['rating'] ?? $settings['rating_scale'] ?? 5;
        $out_of     = (int) ( $settings['rating_scale'] ?? 5 );
        $rating     = (float) ( is_array( $raw_rating ) ? ( $raw_rating['size'] ?? 5 ) : $raw_rating );

        $filled  = (int) round( $rating );
        $filled  = max( 0, min( $out_of, $filled ) );
        $empty   = max( 0, $out_of - $filled );

        $stars = str_repeat( '★', $filled ) . str_repeat( '☆', $empty );

        $title = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? '' ) : '';
        $html  = $title !== '' ? '<p>' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '</p>' : '';
        $html .= '<p>' . $stars . '</p>';

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'rating', 'rating_scale', 'title',
            'icon', 'star_style', 'unmarked_star_style',
            'icon_size', 'icon_color', 'icon_unmarked_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $html ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
