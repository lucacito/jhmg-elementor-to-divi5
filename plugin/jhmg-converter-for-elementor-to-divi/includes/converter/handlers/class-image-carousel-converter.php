<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Image Carousel widget into a divi/text block containing
 * a flex row of inline images.
 *
 * A live carousel cannot be reproduced as static Divi 5 block attributes, so this
 * converter emits all carousel images side-by-side in a flex container. The result
 * is editable in the Divi builder and preserves every image URL from the source.
 */
class ImageCarouselConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        $slides   = $settings['carousel'] ?? [];

        $html = $this->buildCarouselHtml( $slides );

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'carousel',
            'image_size', 'image_fit', 'thumbnail_size', 'thumbnail_custom_dimension',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'slides_to_scroll', 'autoplay', 'autoplay_speed', 'infinite', 'pause_on_hover',
            'effect', 'speed', 'navigation', 'pagination',
            'image_spacing', 'image_spacing_tablet', 'image_spacing_mobile',
            'arrows_position', 'arrows_size', 'gallery_vertical_align', 'carousel_name',
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

    private function buildCarouselHtml( array $slides ): string {
        $imgs = [];
        foreach ( $slides as $slide ) {
            $url = '';
            $alt = '';
            if ( is_array( $slide ) ) {
                $url = is_string( $slide['url'] ?? '' ) ? ( $slide['url'] ?? '' ) : '';
                $alt = is_string( $slide['alt'] ?? '' ) ? ( $slide['alt'] ?? '' ) : '';
            } elseif ( is_string( $slide ) ) {
                $url = $slide;
            }

            if ( $url === '' ) {
                continue;
            }

            $imgs[] = '<img src="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '" alt="' . htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ) . '" style="max-height:60px;width:auto;object-fit:contain;" />';
        }

        if ( empty( $imgs ) ) {
            return '';
        }

        return '<div style="display:flex;flex-wrap:wrap;gap:30px;align-items:center;justify-content:center;">'
            . implode( '', $imgs )
            . '</div>';
    }
}
