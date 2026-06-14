<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles both `site-logo` and `retina` HFE widgets → divi/image.
 *
 * When the user has set a custom image it takes priority; otherwise we fall back
 * to the WordPress site logo URL (if available at conversion time).
 */
class HfeSiteLogoConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_' );
        $settings = $element['settings'] ?? [];

        $src = $this->resolveImageSrc( $settings );

        $image_value = [];
        if ( $src !== '' ) {
            $image_value['src'] = $src;
        }

        $this->engine->logConverted( 'image' );
        $this->logUnmappedSettings( $id, $settings, [
            'custom_image', 'custom_image_url',
            'site_logo_size', 'alignment', 'caption', 'custom_caption',
            'link_select', 'custom_link', 'open_lightbox',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/image',
            'settings' => [
                'image' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $image_value ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }

    private function resolveImageSrc( array $settings ): string {
        // Explicit custom image field (HFE site-logo).
        $custom = $settings['custom_image'] ?? null;
        if ( is_array( $custom ) ) {
            $url = $custom['url'] ?? '';
            if ( is_string( $url ) && $url !== '' ) {
                return $url;
            }
        }

        // Retina widget uses a plain 'logo' media field.
        $logo = $settings['logo'] ?? null;
        if ( is_array( $logo ) ) {
            $url = $logo['url'] ?? '';
            if ( is_string( $url ) && $url !== '' ) {
                return $url;
            }
        }

        // Fall back to the WordPress site logo at conversion time.
        $logo_id = get_option( 'site_logo' );
        if ( $logo_id ) {
            $src = wp_get_attachment_image_url( (int) $logo_id, 'full' );
            if ( $src ) {
                return $src;
            }
        }

        return '';
    }
}
