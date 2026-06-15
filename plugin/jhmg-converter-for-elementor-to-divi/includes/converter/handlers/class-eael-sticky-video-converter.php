<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelStickyVideoConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_video_' );
        $settings = $element['settings'] ?? [];

        $url = is_string( $settings['eael_video_url'] ?? '' ) ? ( $settings['eael_video_url'] ?? '' ) : '';
        if ( $url === '' ) {
            $url = is_string( $settings['url'] ?? '' ) ? ( $settings['url'] ?? '' ) : '';
        }

        $block_settings = [];
        if ( $url !== '' ) {
            $block_settings['module'] = [
                'advanced' => [ 'videoUrl' => [ 'desktop' => [ 'value' => $url ] ] ],
            ];
        }

        $this->engine->logConverted( 'video' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_video_url', 'url', 'eael_sticky_video_type',
            'eael_sticky_video_sticky_position', 'eael_video_autoplay',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/video',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
