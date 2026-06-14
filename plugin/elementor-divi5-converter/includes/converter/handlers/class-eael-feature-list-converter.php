<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelFeatureListConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['eael_feature_list'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title = is_string( $item['eael_feature_list_title'] ?? '' ) ? ( $item['eael_feature_list_title'] ?? '' ) : '';

            $icon_raw = $item['eael_feature_list_icon_new'] ?? null;
            $icon     = '';
            if ( is_array( $icon_raw ) ) {
                $icon = is_string( $icon_raw['value'] ?? '' ) ? ( $icon_raw['value'] ?? '' ) : '';
            }

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['module'] = [
                    'advanced' => [ 'text' => [ 'desktop' => [ 'value' => $title ] ] ],
                ];
            }
            if ( $icon !== '' ) {
                $child_attrs['icon'] = [
                    'innerContent' => [ 'desktop' => [ 'value' => $icon ] ],
                ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/icon-list-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_feature_list',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
