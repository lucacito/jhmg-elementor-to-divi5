<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelPricingTableConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_pricing_' );
        $settings = $element['settings'] ?? [];

        $title    = is_string( $settings['eael_pricing_table_title'] ?? '' ) ? ( $settings['eael_pricing_table_title'] ?? '' ) : '';
        $price    = is_string( $settings['eael_pricing_table_price'] ?? '' ) ? ( $settings['eael_pricing_table_price'] ?? '' ) : '';
        $currency = is_string( $settings['eael_pricing_table_price_cur'] ?? '' ) ? ( $settings['eael_pricing_table_price_cur'] ?? '' ) : '';
        $per      = is_string( $settings['eael_pricing_table_price_per'] ?? '' ) ? ( $settings['eael_pricing_table_price_per'] ?? '' ) : '';
        $btn_text = is_string( $settings['eael_pricing_table_btn'] ?? '' ) ? ( $settings['eael_pricing_table_btn'] ?? '' ) : '';
        $btn_url  = '';
        $btn_raw  = $settings['eael_pricing_table_btn_url'] ?? [];
        if ( is_array( $btn_raw ) ) {
            $btn_url = is_string( $btn_raw['url'] ?? '' ) ? ( $btn_raw['url'] ?? '' ) : '';
        }

        // Build features list from repeater.
        $feature_items = $settings['eael_pricing_table_items'] ?? [];
        $features      = [];
        foreach ( $feature_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = is_string( $item['eael_pricing_table_item'] ?? '' ) ? ( $item['eael_pricing_table_item'] ?? '' ) : '';
            if ( $text !== '' ) {
                $features[] = $text;
            }
        }

        $child_settings = [];

        if ( $title !== '' ) {
            $child_settings['module'] = [
                'advanced' => [
                    'title' => [ 'desktop' => [ 'value' => $title ] ],
                ],
            ];
        }

        if ( $price !== '' || $currency !== '' ) {
            $child_settings['module']['advanced']['priceText']    = [ 'desktop' => [ 'value' => $currency . $price ] ];
            $child_settings['module']['advanced']['perText']      = [ 'desktop' => [ 'value' => $per ] ];
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
            'eael_pricing_table_title', 'eael_pricing_table_price',
            'eael_pricing_table_price_cur', 'eael_pricing_table_price_per',
            'eael_pricing_table_items', 'eael_pricing_table_btn',
            'eael_pricing_table_btn_url', 'eael_pricing_table_onsale',
            'eael_pricing_table_featured', 'eael_pricing_table_style',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/pricing-tables',
            'settings' => [],
            'elements' => [ $child ],
        ];
    }
}
