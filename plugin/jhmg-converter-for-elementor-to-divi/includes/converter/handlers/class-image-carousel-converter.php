<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\AttachmentResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Image Carousel widget into a divi/gallery: a grid of
 * slides_to_show columns, or Divi's slider layout when one slide shows at a time.
 * Images that are not attachments on this site fall back to a row of inline
 * images in a text module, with a warning.
 */
class ImageCarouselConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];
        $slides   = is_array( $settings['carousel'] ?? null ) ? $settings['carousel'] : [];
        $ids      = AttachmentResolver::ids( $slides );

        $handled = [
            'carousel', 'image_size', 'image_fit', 'thumbnail_size', 'thumbnail_custom_dimension',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'slides_to_scroll', 'autoplay', 'autoplay_speed', 'infinite', 'pause_on_hover',
            'effect', 'speed', 'navigation', 'pagination',
            'image_spacing', 'image_spacing_tablet', 'image_spacing_mobile',
            'arrows_position', 'arrows_size', 'gallery_vertical_align', 'carousel_name',
        ];

        if ( $ids === [] ) {
            // Nothing in this media library: keep the images inline so they are
            // at least visible and editable, and say why they are not a gallery.
            $this->engine->logWarning( "Image carousel {$id}: none of its images are in this site's media library, so it was placed as a row of inline images instead of a Divi gallery." );
            $this->engine->logConverted( 'text' );
            $this->logUnmappedSettings( $id, $settings, $handled );
            return [
                'id'       => $id,
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $this->buildInlineHtml( $slides ) ] ] ] ],
                'elements' => [],
            ];
        }

        // divi/gallery (gallery/module.json): image.advanced.galleryIds lists the
        // attachments; module.advanced.postsNumber is the page size (default 4);
        // module.advanced.fullwidth = on is Divi's one-image-at-a-time slider.
        // Elementor shows slides_to_show images side by side (default 3), which
        // a grid of that many columns matches better than a slider.
        $slides_to_show = (int) ( $settings['slides_to_show'] ?? 3 );
        $slides_to_show = $slides_to_show > 0 ? $slides_to_show : 3;

        $block_settings = [
            'image'  => [ 'advanced' => [ 'galleryIds' => [ 'desktop' => [ 'value' => $ids ] ] ] ],
            'module' => [ 'advanced' => [
                'postsNumber'         => [ 'desktop' => [ 'value' => (string) count( $ids ) ] ],
                'showTitleAndCaption' => [ 'desktop' => [ 'value' => 'off' ] ],
            ] ],
        ];

        if ( $slides_to_show === 1 ) {
            $block_settings['module']['advanced']['fullwidth'] = [ 'desktop' => [ 'value' => 'on' ] ];
            if ( ( $settings['autoplay'] ?? '' ) === 'yes' ) {
                $block_settings['module']['advanced']['auto'] = [ 'desktop' => [ 'value' => 'on' ] ];
                $speed = (int) ( $settings['autoplay_speed'] ?? 0 );
                if ( $speed > 0 ) {
                    $block_settings['module']['advanced']['autoSpeed'] = [ 'desktop' => [ 'value' => (string) $speed ] ];
                }
            }
        } else {
            $block_settings = array_merge( $block_settings, $this->galleryGridSettings( (string) $slides_to_show ) );
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, $handled );

        return [ 'id' => $id, 'name' => 'divi/gallery', 'settings' => $block_settings, 'elements' => [] ];
    }

    private function buildInlineHtml( array $slides ): string {
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

            $imgs[] = '<img src="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '" alt="' . htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ) . '" style="max-width:100%;height:auto;" />';
        }

        if ( empty( $imgs ) ) {
            return '';
        }

        return '<div style="display:flex;flex-wrap:wrap;gap:30px;align-items:center;justify-content:center;">'
            . implode( '', $imgs )
            . '</div>';
    }
}
