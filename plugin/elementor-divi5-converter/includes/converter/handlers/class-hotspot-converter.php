<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Hotspot → divi/image.
 *
 * Divi 5 has no interactive hotspot module. The background image is preserved
 * as a plain image block; hotspot pins are discarded.
 */
class HotspotConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_' );
        $settings = $element['settings'] ?? [];

        $image_raw = $settings['image'] ?? [];
        $image_url = '';
        $image_alt = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
            $image_alt = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';
        }

        $block_settings = [];
        if ( $image_url !== '' ) {
            $block_settings['image'] = [
                'innerContent' => [
                    'desktop' => [ 'value' => array_filter( [ 'src' => $image_url, 'alt' => $image_alt ] ) ],
                ],
            ];
        }

        $this->engine->logConverted( 'image' );
        $this->logUnmappedSettings( $id, $settings, [
            'image', 'hotspot_items', 'trigger', 'tooltip_trigger',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/image',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
