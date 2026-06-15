<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AudioConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $source = is_string( $settings['audio_source'] ?? '' ) ? ( $settings['audio_source'] ?? 'hosted' ) : 'hosted';
        $url    = '';

        if ( $source === 'external' ) {
            $url = is_string( $settings['audio_external_url'] ?? '' ) ? ( $settings['audio_external_url'] ?? '' ) : '';
        } else {
            $hosted = $settings['audio_hosted'] ?? [];
            if ( is_array( $hosted ) ) {
                $url = is_string( $hosted['url'] ?? '' ) ? ( $hosted['url'] ?? '' ) : '';
            }
        }

        $html = $url !== ''
            ? '[audio src="' . esc_url( $url ) . '"]'
            : '<!-- Elementor Audio widget: no audio URL found -->';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'audio_source', 'audio_hosted', 'audio_external_url',
            'loop', 'autoplay', 'options',
        ] );

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
