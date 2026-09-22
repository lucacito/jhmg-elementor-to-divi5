<?php

namespace ElementorDivi5Converter\Converter\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ElementorDivi5Converter\Converter\BaseElementorConverter;

/**
 * Converts the "wfc--team-slider" widget (Animation Addons for Elementor,
 * team-slider.php — get_name() returns 'wfc--team-slider', a typo'd prefix,
 * not 'wcf--team-slider'; confirmed from source rather than guessed) to
 * divi/group-carousel + one divi/group per member, each holding a
 * divi/team-member — the same "typed module wrapped in a group" shape
 * WcfTestimonialConverter already uses for its carousel.
 *
 * Its own slider controls (slides_to_show/autoplay/navigation/space_between)
 * happen to already match groupCarouselModuleSettings()'s expected keys.
 * Its four social-icon slots (social_icon_01..04 + link_one..four) use the
 * same network-detection as WcfTeamConverter, restricted to divi/team-member's
 * four networks — but the real widget's own render_team_slider_one() has a
 * copy-paste bug: every social <a href> reads $item['link_one']['url']
 * regardless of which icon it's for, so social_icon_02/03/04 all point at
 * link_one's URL on the live site. Reproduced here (not "fixed") for visual
 * parity with what the real plugin (v4.2.2) actually renders.
 */
class WfcTeamSliderConverter extends BaseElementorConverter {
    private const NETWORK_MAP = [ 'facebook', 'twitter', 'x-twitter', 'linkedin', 'linkedin-in', 'google', 'google-plus' ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_carousel_' );
        $settings = $element['settings'] ?? [];

        $title_tag = is_string( $settings['title_tag'] ?? null ) ? ( $settings['title_tag'] ?? 'h2' ) : 'h2';
        $items     = is_array( $settings['team_slides'] ?? null ) ? $settings['team_slides'] : [];
        $children  = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $name = is_string( $item['title'] ?? null ) ? ( $item['title'] ?? '' ) : '';
            $desc = is_string( $item['desc'] ?? null ) ? ( $item['desc'] ?? '' ) : '';

            $image_raw = is_array( $item['image'] ?? null ) ? $item['image'] : [];
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';

            $member_attrs = [];
            if ( $name !== '' ) {
                $member_attrs['name']['innerContent']['desktop']['value'] = $name;
            }
            if ( $desc !== '' ) {
                $member_attrs['position']['innerContent']['desktop']['value'] = $desc;
            }
            if ( $image_url !== '' ) {
                // divi/team-member's image field uses 'url', not divi/image's 'src' (TeamMemberModule.php).
                $member_attrs['image']['innerContent']['desktop']['value'] = [ 'url' => $image_url ];
            }

            if ( ( $item['helo_show_social'] ?? '' ) === 'yes' ) {
                $link_one_url = is_string( ( $item['link_one'] ?? [] )['url'] ?? '' ) ? ( $item['link_one']['url'] ?? '' ) : '';

                $social = [];
                foreach ( [ '01' => 'link_one', '02' => 'link_two', '03' => 'link_three', '04' => 'link_four' ] as $slot => $link_key ) {
                    $icon_control = $item[ "social_icon_{$slot}" ] ?? null;
                    $own_link     = is_array( $item[ $link_key ] ?? null ) ? $item[ $link_key ] : [];
                    $own_url      = is_string( $own_link['url'] ?? '' ) ? ( $own_link['url'] ?? '' ) : '';
                    if ( $own_url === '' ) {
                        continue;
                    }

                    $slug    = is_array( $icon_control ) && is_string( $icon_control['value'] ?? null ) ? $icon_control['value'] : '';
                    $network = null;
                    foreach ( self::NETWORK_MAP as $candidate ) {
                        if ( str_contains( $slug, 'fa-' . $candidate ) ) {
                            $network = str_starts_with( $candidate, 'google' ) ? 'google' : ( str_starts_with( $candidate, 'linkedin' ) ? 'linkedin' : ( $candidate === 'x-twitter' ? 'twitter' : $candidate ) );
                            break;
                        }
                    }
                    if ( $network === null ) {
                        $this->engine->logNotCarriedOver( 'social_network', $id, "team slide link '{$slug}': Divi's team member offers Facebook, Twitter, Google and LinkedIn" );
                        continue;
                    }
                    // link_one's URL is used for every icon (the widget's own bug — see class docblock).
                    $social[ "{$network}Url" ] = $link_one_url;
                }
                if ( $social !== [] ) {
                    $member_attrs['social']['innerContent']['desktop']['value'] = $social;
                }
            }

            $member_id = $id . '-item-' . ( $idx + 1 );
            $children[] = [
                'id'       => $member_id . '-group',
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => $member_id,
                        'name'     => 'divi/team-member',
                        'settings' => $member_attrs,
                        'elements' => [],
                    ],
                ],
            ];
        }

        $block_settings = $this->groupCarouselModuleSettings( $settings );

        $this->engine->logConverted( 'group-carousel' );
        $this->logUnmappedSettings( $id, $settings, [
            'team_slides', 'title_tag',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'autoplay', 'autoplay_delay', 'autoplay_interaction',
            'navigation', 'pagination', 'space_between', 'slider_style',
            'team_link', 'helo_show_social',
            // wfc--team-slider's own layout and colour controls (team-slider.php).
            'content_bg', 'tabackground',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group-carousel',
            'settings' => $block_settings,
            'elements' => $children,
        ];
    }
}
