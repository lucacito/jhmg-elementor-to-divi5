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

        $url = $settings['eael_video_url'] ?? $settings['url'] ?? '';
        $url = is_string( $url ) ? $url : '';

        if ( $url === '' ) {
            // EAEL 6.x: `eael_video_source` is youtube (the default, so often
            // absent), vimeo or self_hosted, each with its own link setting.
            $source = $settings['eael_video_source'] ?? 'youtube';

            if ( $source === 'vimeo' ) {
                $url = $settings['eaelsv_link_vimeo'] ?? '';
            } elseif ( $source === 'self_hosted' ) {
                if ( ( $settings['eaelsv_link_external'] ?? '' ) === 'yes' ) {
                    $url = $settings['eaelsv_external_url'] ?? '';
                } else {
                    $hosted = $settings['eaelsv_hosted_url'] ?? [];
                    $url    = is_array( $hosted ) ? ( $hosted['url'] ?? '' ) : '';
                }
            } else {
                $url = $settings['eaelsv_link_youtube'] ?? '';
            }

            $url = is_string( $url ) ? $url : '';
        }

        $block_settings = [];
        if ( $url !== '' ) {
            $block_settings['module'] = [
                'advanced' => [ 'videoUrl' => [ 'desktop' => [ 'value' => $url ] ] ],
            ];
        }

        $this->engine->logConverted( 'video' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_video_source', 'eaelsv_link_youtube', 'eaelsv_link_vimeo',
            'eaelsv_link_external', 'eaelsv_external_url', 'eaelsv_hosted_url',
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
