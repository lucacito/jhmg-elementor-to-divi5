<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_' );
        $settings = $element['settings'] ?? [];
        $image    = $this->getSettingValue( $settings, 'image', '' );
        $src      = $this->extractImageSource( $image );
        $alt      = $this->extractImageAlt( $image );

        $image_value = [];
        if ( $src !== '' ) {
            $image_value['src'] = $src;
        }
        if ( $alt !== '' ) {
            $image_value['alt'] = $alt;
        }

        $style = ( new StyleMapper() )->map( 'image', $settings );
        $attrs = array_merge(
            [
                'image' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $image_value ],
                    ],
                ],
            ],
            $style['divi_attrs']
        );

        $this->engine->logConverted( 'image' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'image' ],
            $style['handled_keys']
        ) );

        if ( $alt === '' ) {
            $this->engine->logWarning( "Image missing alt text: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/image',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function extractImageSource( mixed $image ): string {
        if ( is_array( $image ) ) {
            // Our fixtures use 'src'; real Elementor exports use 'url'.
            return $image['src'] ?? $image['url'] ?? '';
        }

        if ( is_string( $image ) ) {
            return $image;
        }

        return '';
    }

    private function extractImageAlt( mixed $image ): string {
        if ( is_array( $image ) && isset( $image['alt'] ) && is_string( $image['alt'] ) ) {
            return $image['alt'];
        }

        return '';
    }
}
