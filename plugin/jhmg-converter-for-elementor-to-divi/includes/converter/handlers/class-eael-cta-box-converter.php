<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelCtaBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_cta_' );
        $settings = $element['settings'] ?? [];

        $title    = is_string( $settings['eael_cta_title'] ?? '' ) ? ( $settings['eael_cta_title'] ?? '' ) : '';
        $subtitle = is_string( $settings['eael_cta_sub_title'] ?? '' ) ? ( $settings['eael_cta_sub_title'] ?? '' ) : '';
        $btn_text = is_string( $settings['eael_cta_btn_text'] ?? '' ) ? ( $settings['eael_cta_btn_text'] ?? '' ) : '';

        $btn_url_raw = $settings['eael_cta_btn_link'] ?? [];
        $btn_url     = '';
        if ( is_array( $btn_url_raw ) ) {
            $btn_url = is_string( $btn_url_raw['url'] ?? '' ) ? ( $btn_url_raw['url'] ?? '' ) : '';
        }

        $block_settings = [];

        if ( $title !== '' ) {
            $block_settings['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
        }
        if ( $subtitle !== '' ) {
            $block_settings['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $subtitle ] ] ];
        }
        if ( $btn_text !== '' || $btn_url !== '' ) {
            $block_settings['button'] = [
                'innerContent' => [ 'desktop' => [ 'value' => array_filter( [ 'text' => $btn_text, 'linkUrl' => $btn_url ] ) ] ],
            ];
        }

        $this->engine->logConverted( 'cta' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_cta_title', 'eael_cta_sub_title', 'eael_cta_btn_text', 'eael_cta_btn_link',
            'eael_cta_color_type', 'eael_cta_btn_effect_type', 'eael_cta_content_type',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/cta',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
