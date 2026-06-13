<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpacerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_spacer_' );
        $settings = $element['settings'] ?? [];

        $height = $this->sizeString( $settings['space'] ?? null, '50px' );

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ 'space' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => "<div style=\"height:{$height}\"></div>" ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }

    private function sizeString( mixed $raw, string $default ): string {
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
