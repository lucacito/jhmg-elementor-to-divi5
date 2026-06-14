<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelInfoBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['eael_infobox_title'] ?? '' ) ? ( $settings['eael_infobox_title'] ?? '' ) : '';
        $sub_title   = is_string( $settings['eael_infobox_sub_title'] ?? '' ) ? ( $settings['eael_infobox_sub_title'] ?? '' ) : '';
        $description = is_string( $settings['eael_infobox_content'] ?? '' ) ? ( $settings['eael_infobox_content'] ?? '' ) : '';

        // Combine title + optional subtitle as the blurb title.
        $full_title = $title;
        if ( $sub_title !== '' ) {
            $full_title = $title !== '' ? $title . ' — ' . $sub_title : $sub_title;
        }

        // Resolve icon.
        $icon_raw = $settings['eael_infobox_icon_new'] ?? null;
        $icon     = '';
        if ( is_array( $icon_raw ) ) {
            $icon = is_string( $icon_raw['value'] ?? '' ) ? ( $icon_raw['value'] ?? '' ) : '';
        }

        // Resolve image.
        $image_raw = $settings['eael_infobox_image'] ?? [];
        $image_url = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        }

        $block_settings = [];

        if ( $full_title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $full_title ] ] ];
        }

        if ( $icon !== '' ) {
            $block_settings['imageIcon'] = [
                'innerContent' => [ 'desktop' => [ 'value' => [ 'icon' => $icon ] ] ],
            ];
        } elseif ( $image_url !== '' ) {
            $block_settings['imageIcon'] = [
                'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => $image_url ] ] ],
            ];
        }

        if ( $description !== '' ) {
            $block_settings['module'] = [
                'advanced' => [ 'text' => [ 'desktop' => [ 'value' => $description ] ] ],
            ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_infobox_title', 'eael_infobox_sub_title', 'eael_infobox_content',
            'eael_infobox_icon_new', 'eael_infobox_image', 'eael_infobox_img_type',
            'eael_infobox_title_tag', 'eael_infobox_sub_title_tag',
            'eael_show_infobox_content', 'eael_show_infobox_clickable',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
