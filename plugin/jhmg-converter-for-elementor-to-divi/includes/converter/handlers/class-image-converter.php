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

        // 'link' (Elementor's own image widget, and Animation Addons' wcf--image,
        // image-box.php's details_link) is a plain URL control. divi/image reads
        // its link from image.innerContent.linkUrl/linkTarget (module.json:
        // elementType "imageLink"), not module.advanced.link.
        $link     = is_array( $settings['link'] ?? null ) ? $settings['link'] : [];
        $link_url = is_string( $link['url'] ?? '' ) ? trim( (string) ( $link['url'] ?? '' ) ) : '';
        if ( $link_url !== '' ) {
            $attrs['image']['innerContent']['desktop']['value']['linkUrl'] = $link_url;
            $is_external = is_string( $link['is_external'] ?? '' ) ? ( $link['is_external'] ?? '' ) : '';
            if ( $is_external === 'on' || $is_external === 'true' || $is_external === '1' || ! empty( $link['isExternal'] ) ) {
                $attrs['image']['innerContent']['desktop']['value']['linkTarget'] = '_blank';
            }
        }

        $this->engine->logConverted( 'image' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'image', 'link', 'link_to', 'open_lightbox' ],
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
