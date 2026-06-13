<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $id       = $element['id'] ?? uniqid( 'divi_image_' );
        $image    = $this->getSettingValue( $settings, 'image', '' );
        $url      = $this->extractImageSource( $image );
        $alt      = $this->extractImageAlt( $image );
        $link     = $this->preserveResponsiveValue( $settings['link'] ?? [] );

        $this->engine->logConverted( 'image' );

        if ( $alt === '' ) {
            $this->engine->logWarning( "Image missing alt text: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/image',
            'settings' => [
                'src'    => $url,
                'alt'    => $alt,
                'link'   => $link,
                'module' => $this->normalizeSettings( $settings ),
            ],
            'elements' => [],
        ];
    }

    private function extractImageSource( mixed $image ): string {
        if ( is_array( $image ) && isset( $image['src'] ) ) {
            return $image['src'];
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
