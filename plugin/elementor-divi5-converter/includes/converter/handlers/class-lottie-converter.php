<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LottieConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $source = is_string( $settings['source'] ?? '' ) ? ( $settings['source'] ?? 'hosted' ) : 'hosted';
        $url    = '';

        if ( $source === 'external_url' ) {
            $url = is_string( $settings['source_external_url'] ?? '' ) ? ( $settings['source_external_url'] ?? '' ) : '';
        } else {
            $json_raw = $settings['source_json'] ?? [];
            if ( is_array( $json_raw ) ) {
                $url = is_string( $json_raw['url'] ?? '' ) ? ( $json_raw['url'] ?? '' ) : '';
            }
        }

        $loop     = ( ( $settings['loop'] ?? 'yes' ) === 'yes' ) ? 'true' : 'false';
        $autoplay = ( ( $settings['autoplay'] ?? 'yes' ) === 'yes' ) ? 'true' : 'false';

        if ( $url !== '' ) {
            $html = '<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>'
                . '<lottie-player src="' . esc_url( $url ) . '" background="transparent" speed="1"'
                . ( $loop === 'true' ? ' loop' : '' )
                . ( $autoplay === 'true' ? ' autoplay' : '' )
                . '></lottie-player>';
        } else {
            $html = '<!-- Elementor Lottie widget: no animation URL found -->';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'source', 'source_json', 'source_external_url',
            'loop', 'autoplay', 'trigger', 'speed',
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
