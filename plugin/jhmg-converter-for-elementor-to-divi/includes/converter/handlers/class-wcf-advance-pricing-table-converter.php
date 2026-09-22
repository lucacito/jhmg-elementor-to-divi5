<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--a-pricing-table" widget (Animation Addons for
 * Elementor, widgets/advance-pricing-table/advance-pricing-table.php — its
 * content controls are shared across all its skins; skin-pricing-table-*.php
 * only vary the style controls and markup) to divi/pricing-tables +
 * divi/pricing-table.
 *
 * divi/pricing-table (PricingTableModule.php, fixtures/divi-schema/modules.json)
 * has its own plain title/subtitle/price attributes plus a structured
 * currencyFrequency.innerContent.value.{currency,per} and a free-form 'content'
 * body for the feature list — not the combined 'priceText'/'perText'/
 * 'bulletItems'/'buttonText'/'buttonUrl' keys PriceTableConverter (Elementor's
 * native Price Table widget) mistakenly writes; those aren't declared
 * attributes at all and DiviModuleSchema::assertBlocksValid() catches it here.
 *
 * Its own 'currency_symbol' is an enum ('dollar', 'euro', …), not a literal
 * symbol string — mapped here to the same HTML-entity table
 * skin-pricing-table-base.php's get_currency_symbol() uses. Its button uses
 * Aaeaddon_Button_Trait's shared 'btn_text'/'btn_link'. 'title_tag', the
 * sale/original-price toggle and the ribbon have no divi/pricing-table
 * equivalent.
 */
class WcfAdvancePricingTableConverter extends BaseElementorConverter {
    private const CURRENCY_SYMBOLS = [
        'dollar'       => '&#36;',
        'euro'         => '&#128;',
        'franc'        => '&#8355;',
        'pound'        => '&#163;',
        'ruble'        => '&#8381;',
        'shekel'       => '&#8362;',
        'baht'         => '&#3647;',
        'yen'          => '&#165;',
        'won'          => '&#8361;',
        'guilder'      => '&fnof;',
        'peso'         => '&#8369;',
        'peseta'       => '&#8359;',
        'lira'         => '&#8356;',
        'rupee'        => '&#8360;',
        'indian_rupee' => '&#8377;',
        'krona'        => 'kr',
        'real'         => 'R$',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_pricing_' );
        $settings = $element['settings'] ?? [];

        $title    = is_string( $settings['title'] ?? null ) ? ( $settings['title'] ?? '' ) : '';
        $subtitle = is_string( $settings['sub_title'] ?? null ) ? ( $settings['sub_title'] ?? '' ) : '';
        $price    = is_string( $settings['price'] ?? null ) ? ( $settings['price'] ?? '' ) : '';
        $period   = is_string( $settings['period'] ?? null ) ? ( $settings['period'] ?? '' ) : '';

        $currency_key = is_string( $settings['currency_symbol'] ?? null ) ? ( $settings['currency_symbol'] ?? '' ) : '';
        if ( $currency_key === 'custom' ) {
            $currency = is_string( $settings['currency_symbol_custom'] ?? null ) ? ( $settings['currency_symbol_custom'] ?? '' ) : '';
        } elseif ( $currency_key !== '' && isset( self::CURRENCY_SYMBOLS[ $currency_key ] ) ) {
            $currency = html_entity_decode( self::CURRENCY_SYMBOLS[ $currency_key ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . ' ';
        } else {
            $currency = '';
        }

        $btn_text = is_string( $settings['btn_text'] ?? null ) ? ( $settings['btn_text'] ?? '' ) : '';
        $btn_link = is_array( $settings['btn_link'] ?? null ) ? $settings['btn_link'] : [];
        $btn_url  = is_string( $btn_link['url'] ?? '' ) ? ( $btn_link['url'] ?? '' ) : '';

        $feature_items = is_array( $settings['features_list'] ?? null ) ? $settings['features_list'] : [];
        $features      = [];
        foreach ( $feature_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = is_string( $item['item_text'] ?? null ) ? ( $item['item_text'] ?? '' ) : '';
            if ( $text !== '' ) {
                $features[] = $text;
            }
        }

        // divi/pricing-table (PricingTableModule.php, fixtures/divi-schema/modules.json):
        // title/subtitle/price are each their own plain-string attribute — there
        // is no single combined 'priceText'/'perText'/'bulletItems'/'buttonText'/
        // 'buttonUrl' set of keys (those don't exist in the declared schema).
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
            $child_settings['content']['innerContent']['desktop']['value'] =
                '<ul>' . implode( '', array_map( static fn( $f ) => '<li>' . esc_html( $f ) . '</li>', $features ) ) . '</ul>';
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
            'title', 'title_tag', 'sub_title', 'price', 'currency_symbol', 'currency_symbol_custom', 'period',
            'features_list', 'btn_text', 'btn_link', 'btn_element_list',
            // wcf--a-pricing-table's own sale/ribbon controls and style
            // sections (advance-pricing-table.php) — no divi/pricing-table
            // equivalent, same limitation PriceTableConverter already has.
            'sale', 'original_price', 'ribbon_title', 'show_ribbon',
            'heading_color', 'heading_typography_typography', 'subtitle_color', 'subtitle_typography_typography',
            'price_color', 'price_typography_typography', 'currency_typography_typography',
            'original_price_color', 'original_price_typography_typography',
            'period_color', 'period_typography_typography', 'price_bg_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/pricing-tables',
            'settings' => [],
            'elements' => [ $child ],
        ];
    }
}
