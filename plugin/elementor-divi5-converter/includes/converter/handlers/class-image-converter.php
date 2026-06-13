<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $image = $this->getSettingValue( $settings, 'image', '' );
        $url = $this->extractImageSource( $image );
        $link = $this->preserveResponsiveValue( $settings['link'] ?? [] );

        return [
            'id' => $element['id'] ?? uniqid( 'divi_image_' ),
            'name' => 'divi/image',
            'settings' => [
                'src' => $url,
                'link' => $link,
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
}
