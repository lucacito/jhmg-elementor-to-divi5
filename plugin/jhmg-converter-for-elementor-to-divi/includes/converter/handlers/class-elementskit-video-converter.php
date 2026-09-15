<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts ElementsKit Video → divi/video.
 *
 * ElementsKit stores its source under its own names, which the core
 * VideoConverter never read, so the video converted with no URL. This
 * translates them into Elementor's core video settings and delegates:
 *
 *   ekit_video_popup_video_type  youtube (default) | vimeo | self
 *   ekit_video_popup_url         YouTube or Vimeo URL
 *   ekit_video_self_url          switch, default 'yes': use the external URL below
 *   ekit_video_self_external_url self-hosted URL typed in
 *   ekit_video_player_self_hosted self-hosted media-library file
 */
class ElementskitVideoConverter extends BaseElementorConverter {

    private const EKIT_SOURCE_KEYS = [
        'ekit_video_popup_video_type', 'ekit_video_popup_url', 'ekit_video_self_url',
        'ekit_video_self_external_url', 'ekit_video_player_self_hosted',
    ];

    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $type     = $settings['ekit_video_popup_video_type'] ?? 'youtube';

        $core = array_diff_key( $settings, array_flip( self::EKIT_SOURCE_KEYS ) );

        if ( $type === 'self' ) {
            $url = '';
            if ( ( $settings['ekit_video_self_url'] ?? 'yes' ) === 'yes' ) {
                $url = $settings['ekit_video_self_external_url'] ?? '';
            }
            if ( ! is_string( $url ) || $url === '' ) {
                $media = $settings['ekit_video_player_self_hosted'] ?? [];
                $url   = is_array( $media ) ? ( $media['url'] ?? '' ) : '';
            }
            $core['video_type'] = 'hosted';
            $core['hosted_url'] = [ 'url' => is_string( $url ) ? $url : '' ];
        } elseif ( $type === 'vimeo' ) {
            $core['video_type'] = 'vimeo';
            $core['vimeo_url']  = $settings['ekit_video_popup_url'] ?? '';
        } else {
            $core['video_type']  = 'youtube';
            $core['youtube_url'] = $settings['ekit_video_popup_url'] ?? '';
        }

        $element['settings'] = $core;

        return ( new VideoConverter( $this->engine ) )->convert( $element );
    }
}
