<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelInfoBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['eael_infobox_title'] ?? '' ) ? ( $settings['eael_infobox_title'] ?? '' ) : '';
        $sub_title   = is_string( $settings['eael_infobox_sub_title'] ?? '' ) ? ( $settings['eael_infobox_sub_title'] ?? '' ) : '';
        $description = $settings['eael_infobox_text'] ?? $settings['eael_infobox_content'] ?? '';
        $description = is_string( $description ) ? $description : '';

        // Combine title + optional subtitle as the blurb title.
        $full_title = $title;
        if ( $sub_title !== '' ) {
            $full_title = $title !== '' ? $title . ' — ' . $sub_title : $sub_title;
        }

        // Resolve image.
        $image_raw = $settings['eael_infobox_image'] ?? [];
        $image_url = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        }

        // divi/blurb (blurb/module.json, BlurbModule.php): title is a
        // headingLink whose value is {text}; the body is content.innerContent;
        // the picture is imageIcon.innerContent {useIcon, icon, src}. Writing
        // module.advanced.text and a FontAwesome class rendered nothing at all.
        $block_settings = [];
        if ( $full_title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = [ 'text' => $full_title ];
        }
        if ( $description !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $description;
        }

        // EAEL 6.6.7 Info_Box.php: eael_infobox_img_or_icon is icon (default), img or number.
        $mode     = $settings['eael_infobox_img_or_icon'] ?? 'icon';
        $icon_raw = $settings['eael_infobox_icon_new'] ?? null;
        if ( $mode === 'img' && $image_url !== '' ) {
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
        } elseif ( $mode === 'icon' && is_array( $icon_raw ) && ( $icon_raw['value'] ?? '' ) !== '' ) {
            $divi_icon = FontAwesomeIcons::fromControl( $icon_raw );
            if ( $divi_icon === null ) {
                $label = is_string( $icon_raw['value'] ) ? $icon_raw['value'] : 'svg';
                $this->engine->logWarning( "Info box {$id}: icon '{$label}' has no FontAwesome equivalent in Divi; a star was used." );
                $divi_icon = FontAwesomeIcons::STAR;
            }
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $divi_icon ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_infobox_title', 'eael_infobox_sub_title', 'eael_infobox_text', 'eael_infobox_content',
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
