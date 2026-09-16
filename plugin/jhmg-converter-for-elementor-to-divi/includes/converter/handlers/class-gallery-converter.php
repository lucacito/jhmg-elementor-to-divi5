<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\AttachmentResolver;

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
        $ids        = AttachmentResolver::ids( is_array( $raw_images ) ? $raw_images : [] );

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'gallery', 'wp_gallery',
            'gallery_rand', 'gallery_columns', 'gallery_link', 'gallery_ids',
            'orderby', 'order',
        ] );

        $block_settings = [];

        if ( ! empty( $ids ) ) {
            $columns = (int) ( $settings['gallery_columns'] ?? 4 );
            $block_settings['image']['advanced']['galleryIds']['desktop']['value'] = $ids;
            // Divi paginates at postsNumber (default 4); list everything, as Elementor does.
            $block_settings['module']['advanced']['postsNumber']['desktop']['value'] = (string) count( $ids );
            if ( $columns > 0 ) {
                $block_settings['galleryGrid']['decoration']['layout']['desktop']['value'] = [ 'display' => 'grid', 'gridColumnCount' => (string) $columns ];
            }
        }

        return [
            'id'       => $id,
            'name'     => 'divi/gallery',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
