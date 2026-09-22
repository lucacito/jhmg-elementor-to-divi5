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
        $subtitle = is_string( $settings['sub_heading'] ?? '' ) ? ( $settings['sub_heading'] ?? '' ) : '';
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

        // divi/pricing-table (PricingTableModule.php, fixtures/divi-schema/
        // modules.json) has its own plain title/subtitle/price attributes, a
        // structured currencyFrequency.innerContent.value.{currency,per}, a
        // free-form 'content' body for the feature list, and a 'button' field —
        // not a combined 'priceText'/'perText'/'bulletItems'/'buttonText'/
        // 'buttonUrl' set (those keys don't exist in the declared schema and
        // rendered nothing).
        $child_settings = [];

        if ( $title !== '' ) {
            $child_settings['title']['innerContent']['desktop']['value'] = $title;
        }

        if ( $subtitle !== '' ) {
            $child_settings['subtitle']['innerContent']['desktop']['value'] = $subtitle;
        }

        if ( $price !== '' ) {
            $child_settings['price']['innerContent']['desktop']['value'] = $price;
        }

        if ( $currency !== '' || $period !== '' ) {
            $child_settings['currencyFrequency']['innerContent']['desktop']['value'] = [
                'currency' => $currency,
                'per'      => $period,
            ];
        }

        if ( $features !== [] ) {
            // divi/pricing-table's own render_pricing_list() splits 'content' on
            // newlines into <li> items itself (EaelPricingTableConverter's
            // already-verified pattern) — it is not raw HTML.
            $child_settings['content']['innerContent']['desktop']['value'] = implode( "\n", $features );
        }

        if ( $btn_text !== '' || $btn_url !== '' ) {
            $button_value = [];
            if ( $btn_text !== '' ) {
                $button_value['text'] = $btn_text;
            }
            if ( $btn_url !== '' ) {
                $button_value['linkUrl'] = $btn_url;
            }
            $child_settings['button']['innerContent']['desktop']['value'] = $button_value;
        }

        $child = [
            'id'       => $id . '-table',
            'name'     => 'divi/pricing-table',
            'settings' => $child_settings,
            'elements' => [],
        ];

        $this->engine->logConverted( 'pricing-tables' );
        $this->logUnmappedSettings( $id, $settings, [
            'title', 'sub_heading', 'price', 'currency_symbol', 'period',
            'features_list', 'button_text', 'button_url',
            'header_type', 'ribbon_title', 'best_value',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/pricing-tables',
            'settings' => [],
            'elements' => [ $child ],
        ];
    }
}
