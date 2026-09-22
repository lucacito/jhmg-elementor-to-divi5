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

        $raw_images = $settings['gallery'] ?? $settings['wp_gallery'] ?? null;
        if ( $raw_images === null && is_array( $settings['wcf_image_gallery'] ?? null ) ) {
            // Animation Addons' wcf--image-gallery (image-gallery.php) repeats an
            // {image: {id,url,...}, ...per-slide style fields} item, not a flat
            // attachment list — pull just the image out of each entry.
            $raw_images = array_map(
                static fn( $item ) => is_array( $item ) ? ( $item['image'] ?? [] ) : [],
                $settings['wcf_image_gallery']
            );
        }
        $raw_images = $raw_images ?? [];
        $ids        = AttachmentResolver::ids( is_array( $raw_images ) ? $raw_images : [] );

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'gallery', 'wp_gallery',
            'gallery_rand', 'gallery_columns', 'gallery_link', 'gallery_ids',
            'orderby', 'order',
            // wcf--image-gallery's own repeater key and lightbox/layout controls.
            'wcf_image_gallery', 'layout_style', 'enable_lightbox',
            'lightbox_animation', 'lightbox_close_icon', 'lightbox_counter',
            'lightbox_fullscreen_icon', 'lightbox_next_icon', 'lightbox_prev_icon',
            'img_b_radius', 'el_hover_effects', 'parallax',
        ] );

        $block_settings = [];

        if ( ! empty( $ids ) ) {
            $columns = (int) ( $settings['gallery_columns'] ?? 4 );
            $block_settings['image']['advanced']['galleryIds']['desktop']['value'] = $ids;
            // Divi paginates at postsNumber (default 4); list everything, as Elementor does.
            $block_settings['module']['advanced']['postsNumber']['desktop']['value'] = (string) count( $ids );
            if ( $columns > 0 ) {
                $block_settings = array_merge( $block_settings, $this->galleryGridSettings( (string) $columns ) );
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
