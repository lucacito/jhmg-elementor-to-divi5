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
        $per      = $settings['eael_pricing_table_price_period'] ?? $settings['eael_pricing_table_price_per'] ?? '';
        $per      = is_string( $per ) ? $per : '';
        $btn_text = is_string( $settings['eael_pricing_table_btn'] ?? '' ) ? ( $settings['eael_pricing_table_btn'] ?? '' ) : '';
        $btn_url  = '';
        $btn_raw  = $settings['eael_pricing_table_btn_link'] ?? $settings['eael_pricing_table_btn_url'] ?? [];
        if ( is_array( $btn_raw ) ) {
            $btn_url = is_string( $btn_raw['url'] ?? '' ) ? ( $btn_raw['url'] ?? '' ) : '';
        }

        $sub_title = is_string( $settings['eael_pricing_table_sub_title'] ?? '' ) ? ( $settings['eael_pricing_table_sub_title'] ?? '' ) : '';

        // divi/pricing-table (pricing-table/module.json, PricingTablesItemModule.php):
        // every text is its own attribute's innerContent; the feature list is one
        // line per item, a leading "-" marking an excluded item
        // (render_pricing_list); featured is module.advanced.featured. The old
        // module.advanced.title/priceText/… paths were never read: empty boxes.
        $features = [];
        foreach ( is_array( $settings['eael_pricing_table_items'] ?? null ) ? $settings['eael_pricing_table_items'] : [] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = is_string( $item['eael_pricing_table_item'] ?? '' ) ? trim( $item['eael_pricing_table_item'] ?? '' ) : '';
            if ( $text === '' ) {
                continue;
            }
            // EAEL 6.6.7 Pricing_Table.php: eael_pricing_table_icon_mood "Item Active?", default yes.
            $active     = ( $item['eael_pricing_table_icon_mood'] ?? 'yes' ) === 'yes';
            $features[] = ( $active ? '' : '-' ) . $text;
        }

        $child_settings = [];
        if ( $title !== '' ) {
            $child_settings['title']['innerContent']['desktop']['value'] = $title;
        }
        if ( $sub_title !== '' ) {
            $child_settings['subtitle']['innerContent']['desktop']['value'] = $sub_title;
        }
        if ( $currency !== '' || $per !== '' ) {
            $child_settings['currencyFrequency']['innerContent']['desktop']['value'] = array_filter( [ 'currency' => $currency, 'per' => $per ], static fn( string $v ): bool => $v !== '' );
        }
        if ( $price !== '' ) {
            $child_settings['price']['innerContent']['desktop']['value'] = $price;
        }
        if ( $features !== [] ) {
            $child_settings['content']['innerContent']['desktop']['value'] = implode( "\n", $features );
        }
        if ( $btn_text !== '' || $btn_url !== '' ) {
            $child_settings['button']['innerContent']['desktop']['value'] = array_filter( [ 'text' => $btn_text, 'linkUrl' => $btn_url ], static fn( string $v ): bool => $v !== '' );
        }
        if ( ( $settings['eael_pricing_table_featured'] ?? '' ) === 'yes' ) {
            $child_settings['module']['advanced']['featured']['desktop']['value'] = 'on';
        }

        $child = [
            'id'       => $id . '-table',
            'name'     => 'divi/pricing-table',
            'settings' => $child_settings,
            'elements' => [],
        ];

        $this->engine->logConverted( 'pricing-tables' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_pricing_table_title', 'eael_pricing_table_sub_title', 'eael_pricing_table_price',
            'eael_pricing_table_price_cur', 'eael_pricing_table_price_period', 'eael_pricing_table_price_per',
            'eael_pricing_table_items', 'eael_pricing_table_btn',
            'eael_pricing_table_btn_link', 'eael_pricing_table_btn_url', 'eael_pricing_table_onsale',
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
