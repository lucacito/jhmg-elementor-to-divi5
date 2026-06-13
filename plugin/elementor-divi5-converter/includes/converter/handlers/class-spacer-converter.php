<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpacerConverter extends BaseElementorConverter {

    private const BREAKPOINTS = [
        'space'        => 'desktop',
        'space_tablet' => 'tablet',
        'space_mobile' => 'phone',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_divider_' );
        $settings = $element['settings'] ?? [];

        $sizing = [];
        foreach ( self::BREAKPOINTS as $key => $breakpoint ) {
            $height = $this->sizeString( $settings[ $key ] ?? null );
            if ( $height !== '' ) {
                $sizing[ $breakpoint ] = [ 'value' => [ 'minHeight' => $height ] ];
            }
        }

        $attrs = [
            'divider' => [
                'advanced' => [
                    'line' => [
                        'desktop' => [ 'value' => [ 'show' => 'off' ] ],
                    ],
                ],
            ],
        ];

        if ( ! empty( $sizing ) ) {
            $attrs['module'] = [ 'decoration' => [ 'sizing' => $sizing ] ];
        }

        $this->engine->logConverted( 'divider' );
        $this->logUnmappedSettings( $id, $settings, array_keys( self::BREAKPOINTS ) );

        return [
            'id'       => $id,
            'name'     => 'divi/divider',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function sizeString( mixed $raw ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) && $raw['size'] > 0 ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        if ( is_string( $raw ) && $raw !== '' ) {
            return $raw;
        }
        return '';
    }
}
