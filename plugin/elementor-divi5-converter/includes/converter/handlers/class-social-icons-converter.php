<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Social Icons widget to divi/social-media-follow.
 *
 * Each social icon item becomes a divi/social-media-follow-network child block.
 * The Elementor FA class name (e.g. `fa-facebook`) is mapped to Divi's network
 * title string (e.g. `facebook`).
 */
class SocialIconsConverter extends BaseElementorConverter {
    /**
     * Maps FontAwesome icon slug fragments to the Divi network title values
     * accepted by divi/social-media-follow-network.
     */
    private const NETWORK_MAP = [
        'facebook'   => 'facebook',
        'twitter'    => 'twitter',
        'x-twitter'  => 'twitter',
        'instagram'  => 'instagram',
        'linkedin'   => 'linkedin',
        'youtube'    => 'youtube',
        'pinterest'  => 'pinterest',
        'tumblr'     => 'tumblr',
        'snapchat'   => 'snapchat',
        'whatsapp'   => 'whatsapp',
        'vimeo'      => 'vimeo',
        'reddit'     => 'reddit',
        'tiktok'     => 'tiktok',
        'discord'    => 'discord',
        'github'     => 'github',
        'dribbble'   => 'dribbble',
        'behance'    => 'behance',
        'rss'        => 'rss',
        'soundcloud' => 'soundcloud',
        'skype'      => 'skype',
        'slack'      => 'slack',
        'twitch'     => 'twitch',
        'medium'     => 'medium',
        'telegram'   => 'telegram',
        'spotify'    => 'spotify',
        'google'     => 'google',
        'flickr'     => 'flickr',
        'mixcloud'   => 'mixcloud',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_social_' );
        $settings = $element['settings'] ?? [];

        $raw_items = $settings['social_icon_list'] ?? [];
        $children  = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $network = $this->resolveNetwork( $item );

            $link_raw = $item['link'] ?? [];
            $url      = '';
            if ( is_array( $link_raw ) ) {
                $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $link_raw ) ) {
                $url = $link_raw;
            }

            $label = ucfirst( $network );

            $children[] = [
                'id'       => $id . '-network-' . ( $idx + 1 ),
                'name'     => 'divi/social-media-follow-network',
                'settings' => [
                    'socialNetwork' => [
                        'innerContent' => [
                            'desktop' => [
                                'value' => [
                                    'title' => $network,
                                    'link'  => $url,
                                    'label' => $label,
                                ],
                            ],
                        ],
                    ],
                ],
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'social-media-follow' );
        $this->logUnmappedSettings( $id, $settings, [
            'social_icon_list',
            'view', 'shape', 'columns', 'icon_size', 'icon_padding', 'icon_spacing',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/social-media-follow',
            'settings' => [],
            'elements' => $children,
        ];
    }

    private function resolveNetwork( array $item ): string {
        // Elementor stores the icon as either a `social_icon` string (FA class) or
        // a `selected_icon` composite `{library: 'fa', value: 'fab fa-facebook'}`.
        $icon_raw = $item['social_icon'] ?? '';
        if ( ! is_string( $icon_raw ) ) {
            $selected = $item['selected_icon'] ?? [];
            if ( is_array( $selected ) ) {
                $icon_raw = is_string( $selected['value'] ?? '' ) ? ( $selected['value'] ?? '' ) : '';
            }
        }

        // Normalize: strip 'fab ', 'fas ', 'fa ' prefixes and extract the slug.
        $icon_raw = preg_replace( '/^(fab?|fas?)\s+/i', '', (string) $icon_raw );
        $icon_raw = ltrim( $icon_raw, 'f' ); // strip leading 'f' from 'fa-facebook' → 'a-facebook'
        // Actually just extract after 'fa-' pattern.
        if ( preg_match( '/fa-([a-z0-9_-]+)/i', (string) $icon_raw, $matches ) ) {
            $slug = strtolower( $matches[1] );
            return self::NETWORK_MAP[ $slug ] ?? $slug;
        }

        // Try direct lookup on the raw value.
        $slug = strtolower( trim( $icon_raw, " \t\n-_" ) );
        return self::NETWORK_MAP[ $slug ] ?? 'facebook';
    }
}
