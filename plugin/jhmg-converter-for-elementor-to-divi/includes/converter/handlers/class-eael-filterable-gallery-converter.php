<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Filterable Gallery → divi/gallery.
 *
 * The filterable category UI is not supported in divi/gallery; images are
 * converted to a flat gallery. Filter labels are silently dropped.
 */
class EaelFilterableGalleryConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];

        $items  = $settings['eael_fg_gallery_items'] ?? [];
        $images = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $img = $item['eael_fg_gallery_img'] ?? $item['fg_gallery_img'] ?? [];
            if ( ! is_array( $img ) ) {
                continue;
            }
            $url = is_string( $img['url'] ?? '' ) ? ( $img['url'] ?? '' ) : '';
            if ( $url !== '' ) {
                $images[] = [ 'src' => $url ];
            }
        }

        $block_settings = [];
        if ( ! empty( $images ) ) {
            $block_settings['galleryGrid'] = [
                'innerContent' => [
                    'desktop' => [ 'value' => [ 'images' => $images ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_fg_gallery_items', 'eael_fg_show_popup',
            'eael_section_fg_full_image_action', 'load_more_icon_new',
            'fg_all_label_icon', 'show_load_more',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/gallery',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
