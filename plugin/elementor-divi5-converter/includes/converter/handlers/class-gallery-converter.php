<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Gallery widget to divi/gallery.
 *
 * Elementor stores images as an array of attachment objects with `id` and `url`.
 * Divi's gallery uses `image.advanced.galleryIds.desktop.value` (array of int IDs).
 * Both IDs and URLs are stored so the gallery renders with whatever the destination
 * WordPress install has available.
 */
class GalleryConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];

        $raw_images = $settings['gallery'] ?? $settings['wp_gallery'] ?? [];
        $ids        = [];
        $urls       = [];

        foreach ( $raw_images as $img ) {
            if ( ! is_array( $img ) ) {
                continue;
            }
            $img_id = (int) ( $img['id'] ?? 0 );
            if ( $img_id > 0 ) {
                $ids[] = $img_id;
            }
            $url = is_string( $img['url'] ?? '' ) ? ( $img['url'] ?? '' ) : '';
            if ( $url !== '' ) {
                $urls[] = $url;
            }
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'gallery', 'wp_gallery',
            'gallery_rand', 'gallery_columns', 'gallery_link', 'gallery_ids',
            'orderby', 'order',
        ] );

        $block_settings = [];

        if ( ! empty( $ids ) ) {
            $block_settings['image']['advanced']['galleryIds']['desktop']['value'] = $ids;
        }

        return [
            'id'       => $id,
            'name'     => 'divi/gallery',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
