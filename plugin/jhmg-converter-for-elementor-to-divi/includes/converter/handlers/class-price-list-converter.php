<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PriceListConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['price_list'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title = is_string( $item['title'] ?? '' ) ? ( $item['title'] ?? '' ) : '';
            $price = is_string( $item['price'] ?? '' ) ? ( $item['price'] ?? '' ) : '';
            $label = $price !== '' ? $title . ' — ' . $price : $title;

            $child_attrs = [];
            if ( $label !== '' ) {
                $child_attrs['module'] = [
                    'advanced' => [ 'text' => [ 'desktop' => [ 'value' => $label ] ] ],
                ];
            }

            $link_raw = $item['link'] ?? [];
            if ( is_array( $link_raw ) ) {
                $link_url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
                if ( $link_url !== '' ) {
                    $child_attrs['link'] = [
                        'innerContent' => [ 'desktop' => [ 'value' => [ 'url' => $link_url ] ] ],
                    ];
                }
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/icon-list-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [ 'price_list' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
