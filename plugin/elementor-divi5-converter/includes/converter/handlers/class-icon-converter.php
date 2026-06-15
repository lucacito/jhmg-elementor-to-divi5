<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IconConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_' );
        $settings = $element['settings'] ?? [];

        $icon_value = $this->extractIconValue( $settings );
        $color      = $this->firstString( $settings, [ 'primary_color', 'icon_color' ] );
        $size       = $this->sizeString( $settings['size'] ?? null );

        if ( $icon_value === '' ) {
            $icon_value = 'fa-star';
        }
        if ( $size === '' ) {
            $size = '30px';
        }

        $icon_attrs = [
            'innerContent' => [ 'desktop' => [ 'value' => $icon_value ] ],
        ];

        $advanced = [
            'size' => [ 'desktop' => [ 'value' => $size ] ],
        ];
        if ( $color !== '' ) {
            $advanced['color'] = [ 'desktop' => [ 'value' => $color ] ];
        }
        $icon_attrs['advanced'] = $advanced;

        $style_result = ( new StyleMapper() )->map( 'icon', $settings );
        $attrs        = array_merge(
            empty( $icon_attrs ) ? [] : [ 'icon' => $icon_attrs ],
            $style_result['divi_attrs']
        );

        $this->engine->logConverted( 'icon' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'selected_icon', 'icon', 'primary_color', 'icon_color', 'size',
                'view', 'shape', 'link', 'border_width', 'border_radius',
                'hover_primary_color', 'hover_secondary_color', 'icon_padding',
                'rotate', 'font_awesome_5_icon',
            ],
            $style_result['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/icon',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function extractIconValue( array $settings ): string {
        $selected = $settings['selected_icon'] ?? null;
        if ( is_array( $selected ) ) {
            return is_string( $selected['value'] ?? '' ) ? ( $selected['value'] ?? '' ) : '';
        }
        if ( is_string( $selected ) && $selected !== '' ) {
            return $selected;
        }
        $legacy = $settings['icon'] ?? '';
        return is_string( $legacy ) ? $legacy : '';
    }

    private function firstString( array $settings, array $keys ): string {
        foreach ( $keys as $key ) {
            $val = $settings[ $key ] ?? '';
            if ( is_string( $val ) && $val !== '' ) {
                return $val;
            }
        }
        return '';
    }

    private function sizeString( mixed $raw ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        return '';
    }
}
