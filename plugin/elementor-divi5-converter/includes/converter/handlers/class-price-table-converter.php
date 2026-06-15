<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PriceTableConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_pricing_' );
        $settings = $element['settings'] ?? [];

        $title    = is_string( $settings['title'] ?? '' ) ? ( $settings['title'] ?? '' ) : '';
        $price    = is_string( $settings['price'] ?? '' ) ? ( $settings['price'] ?? '' ) : '';
        $currency = is_string( $settings['currency_symbol'] ?? '' ) ? ( $settings['currency_symbol'] ?? '' ) : '';
        $period   = is_string( $settings['period'] ?? '' ) ? ( $settings['period'] ?? '' ) : '';
        $btn_text = is_string( $settings['button_text'] ?? '' ) ? ( $settings['button_text'] ?? '' ) : '';

        $btn_url_raw = $settings['button_url'] ?? [];
        $btn_url     = is_array( $btn_url_raw ) ? ( is_string( $btn_url_raw['url'] ?? '' ) ? ( $btn_url_raw['url'] ?? '' ) : '' ) : '';

        $feature_items = $settings['features_list'] ?? [];
        $features      = [];
        foreach ( $feature_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = is_string( $item['item_text'] ?? '' ) ? ( $item['item_text'] ?? '' ) : '';
            if ( $text !== '' ) {
                $features[] = $text;
            }
        }

        $child_settings = [];

        if ( $title !== '' ) {
            $child_settings['module']['advanced']['title'] = [ 'desktop' => [ 'value' => $title ] ];
        }

        if ( $price !== '' || $currency !== '' ) {
            $child_settings['module']['advanced']['priceText'] = [ 'desktop' => [ 'value' => $currency . $price ] ];
            $child_settings['module']['advanced']['perText']   = [ 'desktop' => [ 'value' => $period ] ];
        }

        if ( ! empty( $features ) ) {
            $child_settings['module']['advanced']['bulletItems'] = [
                'desktop' => [ 'value' => implode( "\n", $features ) ],
            ];
        }

        if ( $btn_text !== '' || $btn_url !== '' ) {
            $child_settings['module']['advanced']['buttonText'] = [ 'desktop' => [ 'value' => $btn_text ] ];
            $child_settings['module']['advanced']['buttonUrl']  = [ 'desktop' => [ 'value' => $btn_url ] ];
        }

        $child = [
            'id'       => $id . '-table',
            'name'     => 'divi/pricing-table',
            'settings' => $child_settings,
            'elements' => [],
        ];

        $this->engine->logConverted( 'pricing-tables' );
        $this->logUnmappedSettings( $id, $settings, [
            'title', 'price', 'currency_symbol', 'period',
            'features_list', 'button_text', 'button_url',
            'header_type', 'sub_heading', 'ribbon_title', 'best_value',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/pricing-tables',
            'settings' => [],
            'elements' => [ $child ],
        ];
    }
}
