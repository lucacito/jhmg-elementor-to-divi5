<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GoogleMapsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $address = is_string( $settings['address'] ?? '' ) ? ( $settings['address'] ?? '' ) : '';
        $zoom    = (int) ( $settings['zoom']['size'] ?? $settings['zoom'] ?? 10 );

        if ( $address !== '' ) {
            $encoded  = rawurlencode( $address );
            $html     = '<iframe src="https://maps.google.com/maps?q=' . $encoded . '&amp;z=' . $zoom . '&amp;output=embed"'
                . ' width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy"></iframe>';
        } else {
            $html = '<!-- Elementor Google Maps widget: no address set -->';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ 'address', 'zoom', 'height' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $html ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
