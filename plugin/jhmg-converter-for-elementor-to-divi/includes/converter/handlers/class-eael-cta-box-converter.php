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
        // EAEL 6.6.7 Cta_Box.php renders eael_cta_sub_title and eael_cta_content;
        // the body used to be dropped.
        $body_raw = $settings['eael_cta_content'] ?? '';
        $body     = is_string( $body_raw ) && trim( wp_strip_all_tags( $body_raw ) ) !== '' ? trim( $body_raw ) : '';
        $parts    = [];
        if ( $subtitle !== '' ) {
            $parts[] = str_starts_with( trim( $subtitle ), '<' ) ? $subtitle : '<p>' . $subtitle . '</p>';
        }
        if ( $body !== '' ) {
            $parts[] = $body;
        }
        if ( $parts !== [] ) {
            $block_settings['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", $parts ) ] ] ];
        }
        if ( $btn_text !== '' || $btn_url !== '' ) {
            $block_settings['button'] = [
                'innerContent' => [ 'desktop' => [ 'value' => array_filter( [ 'text' => $btn_text, 'linkUrl' => $btn_url ] ) ] ],
            ];
        }

        $this->engine->logConverted( 'cta' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_cta_title', 'eael_cta_sub_title', 'eael_cta_content', 'eael_cta_btn_text', 'eael_cta_btn_link',
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
