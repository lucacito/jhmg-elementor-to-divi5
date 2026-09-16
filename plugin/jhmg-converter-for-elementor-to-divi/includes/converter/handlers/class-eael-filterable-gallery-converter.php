<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\AttachmentResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Filterable Gallery → divi/gallery.
 *
 * Divi's gallery lists attachments by ID (image.advanced.galleryIds) and has no
 * filter bar; the filter buttons, item names and captions are reported as not
 * carried over rather than dropped in silence.
 */
class EaelFilterableGalleryConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];

        $items  = is_array( $settings['eael_fg_gallery_items'] ?? null ) ? $settings['eael_fg_gallery_items'] : [];
        $images = [];
        $names  = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $img = $item['eael_fg_gallery_img'] ?? $item['fg_gallery_img'] ?? [];
            if ( is_array( $img ) ) {
                $images[] = $img;
            }
            foreach ( [ 'eael_fg_gallery_item_name', 'eael_fg_gallery_item_content' ] as $key ) {
                $text = $item[ $key ] ?? '';
                if ( is_string( $text ) && trim( wp_strip_all_tags( $text ) ) !== '' ) {
                    $names[] = trim( wp_strip_all_tags( $text ) );
                }
            }
        }
        $ids = AttachmentResolver::ids( $images );

        // EAEL 6.6.7 Filterable_Gallery.php: `columns` (1-6, default 3), the
        // `eael_fg_controls` repeater of filter labels, and per-item name,
        // content and category. Divi's gallery has no filter bar and takes its
        // captions from the media library, so those are reported, not dropped.
        $columns = (string) ( (int) ( $settings['columns'] ?? 3 ) ?: 3 );
        $filters = [];
        foreach ( is_array( $settings['eael_fg_controls'] ?? null ) ? $settings['eael_fg_controls'] : [] as $control ) {
            $label = is_array( $control ) ? ( $control['eael_fg_control'] ?? '' ) : '';
            if ( is_string( $label ) && $label !== '' ) {
                $filters[] = $label;
            }
        }
        if ( $filters !== [] ) {
            $this->engine->logNotCarriedOver( 'gallery_extras', (string) $id, 'filter buttons: ' . implode( ', ', $filters ) );
        }
        if ( $names !== [] ) {
            $this->engine->logNotCarriedOver( 'gallery_extras', (string) $id, count( $names ) . ' item names and captions (Divi shows the media library title and caption)' );
        }

        $block_settings = [];
        if ( $ids !== [] ) {
            // divi/gallery (gallery/module.json): attachments by ID, every image on
            // one page (Divi paginates at postsNumber, default 4), EAEL's column count.
            $block_settings = [
                'image'       => [ 'advanced' => [ 'galleryIds' => [ 'desktop' => [ 'value' => $ids ] ] ] ],
                'module'      => [ 'advanced' => [
                    'postsNumber'         => [ 'desktop' => [ 'value' => (string) count( $ids ) ] ],
                    'showTitleAndCaption' => [ 'desktop' => [ 'value' => 'off' ] ],
                ] ],
                'galleryGrid' => [ 'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'grid', 'gridColumnCount' => $columns ] ] ] ] ],
            ];
        } else {
            $this->engine->logWarning( "Filterable gallery {$id}: none of its images are in this site's media library; the Divi gallery would list every attachment, so it was left empty." );
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_fg_gallery_items', 'eael_fg_controls', 'columns', 'columns_tablet', 'columns_mobile', 'eael_fg_show_popup',
            'eael_section_fg_full_image_action', 'load_more_icon_new',
            'fg_all_label_icon', 'show_load_more',
        ] );

        return [ 'id' => $id, 'name' => 'divi/gallery', 'settings' => $block_settings, 'elements' => [] ];
    }
}
