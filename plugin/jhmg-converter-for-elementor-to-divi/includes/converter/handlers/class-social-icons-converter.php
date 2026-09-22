<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

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
        'flickr'     => 'flikr',   // Divi's spelling
        'mixcloud'   => 'mixcloud',
        // FontAwesome 5/6 brand variants Elementor offers for the same networks.
        'facebook-f'       => 'facebook',
        'square-facebook'  => 'facebook',
        'facebook-square'  => 'facebook',
        'linkedin-in'      => 'linkedin',
        'square-instagram' => 'instagram',
        'instagram-square' => 'instagram',
        'square-x-twitter' => 'twitter',
        'twitter-square'   => 'twitter',
        'square-youtube'   => 'youtube',
        'youtube-square'   => 'youtube',
        'pinterest-p'      => 'pinterest',
        'square-pinterest' => 'pinterest',
        'pinterest-square' => 'pinterest',
        'google-plus'      => 'google',
        'google-plus-g'    => 'google',
        'vk'               => 'vk',
        'xing'             => 'xing',
        'yelp'             => 'yelp',
        'meetup'           => 'meetup',
        'quora'            => 'quora',
        'amazon'           => 'amazon',
        'flipboard'        => 'flipboard',
    ];

    /** Every network divi/social-media-follow-network renders (SocialMediaFollowItemModule::get_social_networks()). */
    private const DIVI_NETWORKS = [
        'amazon', 'bandcamp', 'behance', 'bitbucket', 'buffer', 'codepen', 'deviantart', 'dribbble', 'facebook',
        'flikr', 'flipboard', 'foursquare', 'github', 'goodreads', 'google', 'houzz', 'instagram', 'itunes',
        'last_fm', 'line', 'linkedin', 'medium', 'meetup', 'myspace', 'odnoklassniki', 'patreon', 'periscope',
        'pinterest', 'quora', 'reddit', 'researchgate', 'rss', 'skype', 'snapchat', 'soundcloud', 'spotify',
        'steam', 'telegram', 'tiktok', 'tripadvisor', 'tumblr', 'twitch', 'twitter', 'vimeo', 'vk', 'weibo',
        'whatsapp', 'xing', 'yelp', 'youtube',
    ];

    /**
     * Each network's colour, from SocialMediaFollowItemModule::get_social_networks().
     * divi/social-media-follow-network renders no background unless the block sets
     * one (social-media-follow-item/module-default-render-attributes.json has none;
     * the Visual Builder writes this colour when an item is added), and the parent
     * module's icons default to light (text.color 'light'): a converted list with no
     * colours is white icons on nothing, invisible on a light section.
     */
    private const NETWORK_BACKGROUND = [
        'amazon'     => '#ff9900',
        'behance'    => '#0057ff',
        'discord'    => '#5865f2',
        'dribbble'   => '#ea4c8d',
        'facebook'   => '#3b5998',
        'flikr'      => '#ff0084',
        'flipboard'  => '#e12828',
        'github'     => '#333333',
        'google'     => '#4285f4',
        'instagram'  => '#ea2c59',
        'linkedin'   => '#007bb6',
        'medium'     => '#00ab6c',
        'meetup'     => '#e0393e',
        'mixcloud'   => '#314359',
        'pinterest'  => '#cb2027',
        'quora'      => '#a82400',
        'reddit'     => '#ff4500',
        'rss'        => '#ff8a3c',
        'skype'      => '#12A5F4',
        'slack'      => '#4a154b',
        'snapchat'   => '#fffc00',
        'soundcloud' => '#ff8800',
        'spotify'    => '#1db954',
        'telegram'   => '#179cde',
        'tiktok'     => '#fe2c55',
        'tumblr'     => '#32506d',
        'twitch'     => '#6441a5',
        'twitter'    => '#000000',
        'vimeo'      => '#45bbff',
        'vk'         => '#45668e',
        'whatsapp'   => '#25D366',
        'xing'       => '#026466',
        'yelp'       => '#af0606',
        'youtube'    => '#a82400',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_social_' );
        $settings = $element['settings'] ?? [];

        // Elementor's Social Icons default to each network's official colour behind a
        // white glyph (social-icons.php:356-395: icon_color 'default'); 'custom' uses the
        // widget's primary colour behind the icon and secondary colour for the glyph.
        $globals      = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : [];
        $custom       = ( $settings['icon_color'] ?? 'default' ) === 'custom';
        $custom_bg    = $custom ? $this->resolveColor( $settings, $globals, 'icon_primary_color' ) : '';
        $custom_glyph = $custom ? $this->resolveColor( $settings, $globals, 'icon_secondary_color' ) : '';
        // Divi paints the glyph in the section's link colour unless the item says otherwise.
        $glyph        = $custom_glyph !== '' ? $custom_glyph : '#ffffff';

        $raw_items = $settings['social_icon_list'] ?? [];
        $children  = [];

        foreach ( $raw_items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $network = $this->resolveNetwork( $item );
            if ( $network === null ) {
                $raw = $item['social_icon']['value'] ?? $item['social'] ?? '';
                $this->engine->logNotCarriedOver( 'social_network', (string) $id, 'no Divi network for ' . ( is_string( $raw ) && $raw !== '' ? $raw : 'this icon' ) );
                continue;
            }

            $link_raw = $item['link'] ?? [];
            $url      = '';
            if ( is_array( $link_raw ) ) {
                $url = is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '';
            } elseif ( is_string( $link_raw ) ) {
                $url = $link_raw;
            }

            $label = ucfirst( $network );

            $child_settings = [
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
            ];
            $background = $custom_bg !== '' ? $custom_bg : ( self::NETWORK_BACKGROUND[ $network ] ?? '' );
            if ( $background !== '' ) {
                $child_settings['module']['decoration']['background']['desktop']['value']['color'] = $background;
            }
            $child_settings['icon']['advanced']['color']['desktop']['value'] = $glyph;

            $children[] = [
                'id'       => $id . '-network-' . ( $idx + 1 ),
                'name'     => 'divi/social-media-follow-network',
                'settings' => $child_settings,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'social-media-follow' );
        $this->logUnmappedSettings( $id, $settings, [
            'social_icon_list', 'icon_color', 'icon_primary_color', 'icon_secondary_color',
            'view', 'shape', 'columns', 'icon_size', 'icon_padding', 'icon_spacing',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/social-media-follow',
            'settings' => [],
            'elements' => $children,
        ];
    }

    /**
     * Elementor 4.1.3 social-icons.php: `social_icon` is an ICONS value
     * {value: "fab fa-instagram", library}; `social` is the pre-FA5 string
     * ("fa fa-facebook"). Returns the Divi network slug, or null when Divi has
     * no such network. Reading `social_icon` as a string found nothing and made
     * every icon Facebook.
     */
    private function resolveNetwork( array $item ): ?string {
        $raw  = '';
        $icon = $item['social_icon'] ?? null;
        if ( is_array( $icon ) && is_string( $icon['value'] ?? null ) ) {
            $raw = $icon['value'];
        } elseif ( is_string( $icon ) ) {
            $raw = $icon;
        }
        if ( $raw === '' && is_string( $item['social'] ?? null ) ) {
            $raw = $item['social'];
        }

        if ( preg_match( '/fa-([a-z0-9_-]+)/i', $raw, $m ) ) {
            $slug = strtolower( $m[1] );
        } else {
            $slug = strtolower( trim( (string) preg_replace( '/^(fab?|fas?|far)\s+/i', '', $raw ), " \t\n-_" ) );
        }
        if ( $slug === '' ) {
            return null;
        }
        $network = self::NETWORK_MAP[ $slug ] ?? $slug;

        return in_array( $network, self::DIVI_NETWORKS, true ) ? $network : null;
    }

    /** A colour control's value: the literal, or the kit colour its `__globals__` entry names. */
    private function resolveColor( array $settings, array $globals, string $key ): string {
        $direct = $settings[ $key ] ?? '';
        if ( is_string( $direct ) && $direct !== '' ) {
            return $direct;
        }
        $ref = $globals[ $key ] ?? '';
        $id  = is_string( $ref ) && $ref !== '' ? GlobalsResolver::colorIdFromRef( $ref ) : null;

        return $id === null ? '' : ( GlobalsResolver::resolveColor( $id ) ?? '' );
    }
}
