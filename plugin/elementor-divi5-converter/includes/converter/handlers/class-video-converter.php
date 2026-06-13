<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VideoConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_video_' );
        $settings = $element['settings'] ?? [];

        $src = $this->extractVideoUrl( $settings );

        $video_value = [];
        if ( $src !== '' ) {
            $video_value['src'] = $src;
        }

        $style_result = ( new StyleMapper() )->map( 'video', $settings );
        $attrs        = array_merge(
            [
                'video' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $video_value ],
                    ],
                ],
            ],
            $style_result['divi_attrs']
        );

        $this->engine->logConverted( 'video' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'video_type', 'youtube_url', 'vimeo_url', 'hosted_url',
                'autoplay', 'mute', 'loop', 'controls', 'show_controls',
                'image_overlay', 'show_image_overlay', 'lightbox',
            ],
            $style_result['handled_keys']
        ) );

        if ( $src === '' ) {
            $this->engine->logWarning( "Video missing source URL: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/video',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function extractVideoUrl( array $settings ): string {
        $type = is_string( $settings['video_type'] ?? '' ) ? ( $settings['video_type'] ?? '' ) : '';

        if ( $type === 'vimeo' ) {
            $url = $settings['vimeo_url'] ?? '';
            return is_string( $url ) ? $url : '';
        }

        if ( $type === 'hosted' ) {
            $hosted = $settings['hosted_url'] ?? '';
            if ( is_array( $hosted ) ) {
                return is_string( $hosted['url'] ?? '' ) ? ( $hosted['url'] ?? '' ) : '';
            }
            return is_string( $hosted ) ? $hosted : '';
        }

        // Default: YouTube (also the fallback for any unrecognized type).
        $url = $settings['youtube_url'] ?? '';
        return is_string( $url ) ? $url : '';
    }
}
