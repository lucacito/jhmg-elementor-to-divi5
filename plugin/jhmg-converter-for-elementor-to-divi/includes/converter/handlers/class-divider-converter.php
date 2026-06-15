<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DividerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_divider_' );
        $settings = $element['settings'] ?? [];

        $color  = is_string( $settings['color'] ?? '' ) ? ( $settings['color'] ?? '' ) : '';
        $style  = is_string( $settings['style'] ?? '' ) ? ( $settings['style'] ?? 'solid' ) : 'solid';
        $weight = $this->extractSize( $settings['weight'] ?? null, '1px' );

        $line_value = [ 'show' => true ];
        if ( $color !== '' ) {
            $line_value['color'] = $color;
        }
        $line_value['style']  = $style !== '' ? $style : 'solid';
        $line_value['weight'] = $weight;

        $style_result = ( new StyleMapper() )->map( 'divider', $settings );
        $attrs        = array_merge(
            [
                'divider' => [
                    'advanced' => [
                        'line' => [
                            'desktop' => [ 'value' => $line_value ],
                        ],
                    ],
                ],
            ],
            $style_result['divi_attrs']
        );

        $this->engine->logConverted( 'divider' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'color', 'style', 'weight', 'width', 'gap', 'look', 'align', 'icon', 'icon_type', 'view', 'text', 'html_tag' ],
            $style_result['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/divider',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function extractSize( mixed $raw, string $default ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        if ( is_string( $raw ) && $raw !== '' ) {
            return $raw;
        }
        return $default;
    }
}
