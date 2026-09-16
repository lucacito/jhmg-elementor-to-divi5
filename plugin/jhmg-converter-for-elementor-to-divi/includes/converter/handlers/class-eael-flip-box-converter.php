<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Flip Box → divi/blurb using the front-face content.
 *
 * Divi 5 has no native flip-box module. The front title and text are mapped to
 * the blurb's title and body; the back-face content is appended as a secondary
 * paragraph so no information is silently lost.
 */
class EaelFlipBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $front_title = is_string( $settings['eael_flipbox_front_title'] ?? '' ) ? ( $settings['eael_flipbox_front_title'] ?? '' ) : '';
        $front_text  = is_string( $settings['eael_flipbox_front_text'] ?? '' ) ? ( $settings['eael_flipbox_front_text'] ?? '' ) : '';
        $back_title  = is_string( $settings['eael_flipbox_back_title'] ?? '' ) ? ( $settings['eael_flipbox_back_title'] ?? '' ) : '';
        $back_text   = is_string( $settings['eael_flipbox_back_text'] ?? '' ) ? ( $settings['eael_flipbox_back_text'] ?? '' ) : '';

        $body_parts = array_filter( [ $front_text, $back_title !== '' ? '<strong>' . $back_title . '</strong>' : '', $back_text ] );
        $body       = implode( "\n", $body_parts );

        // divi/blurb (blurb/module.json, BlurbModule.php): title {text}, body in
        // content.innerContent, picture in imageIcon.innerContent {useIcon, icon, src}.
        $block_settings = [];
        if ( $front_title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = [ 'text' => $front_title ];
        }
        if ( $body !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $body;
        }

        // EAEL 6.6.7 Flip_Box.php: eael_flipbox_img_or_icon is icon (default) or img.
        $mode      = $settings['eael_flipbox_img_or_icon'] ?? 'icon';
        $icon_raw  = $settings['eael_flipbox_icon_new'] ?? null;
        $image_raw = $settings['eael_flipbox_image'] ?? [];
        $image_url = is_array( $image_raw ) && is_string( $image_raw['url'] ?? null ) ? $image_raw['url'] : '';
        if ( $mode === 'img' && $image_url !== '' ) {
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
        } elseif ( $mode === 'icon' && is_array( $icon_raw ) && ( $icon_raw['value'] ?? '' ) !== '' ) {
            $divi_icon = FontAwesomeIcons::fromControl( $icon_raw );
            if ( $divi_icon === null ) {
                $label = is_string( $icon_raw['value'] ) ? $icon_raw['value'] : 'svg';
                $this->engine->logWarning( "Flip box {$id}: icon '{$label}' has no FontAwesome equivalent in Divi; a star was used." );
                $divi_icon = FontAwesomeIcons::STAR;
            }
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $divi_icon ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_flipbox_front_title', 'eael_flipbox_front_text',
            'eael_flipbox_back_title', 'eael_flipbox_back_text',
            'eael_flipbox_icon_new', 'eael_flipbox_image', 'eael_flipbox_img_or_icon', 'eael_flipbox_front_content_type',
            'eael_flipbox_back_content_type', 'eael_flipbox_type',
            'eael_flipbox_event_type', 'eael_flipbox_3d',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
