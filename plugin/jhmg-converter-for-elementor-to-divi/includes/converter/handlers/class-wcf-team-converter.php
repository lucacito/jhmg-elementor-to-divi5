<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts "wcf--team" (Animation Addons for Elementor, team.php) — a single
 * team member, not a carousel — to divi/team-member.
 *
 * Same target and social-link handling as EaelTeamMemberConverter, with this
 * widget's own field names: member_name/member_designation/member_description/
 * member_image/details_link, and a team_social_icons repeater of
 * {social_icon: ICONS value, link} — the same item shape SocialIconsConverter
 * already reads, restricted here to divi/team-member's four networks.
 */
class WcfTeamConverter extends BaseElementorConverter {
    private const NETWORK_MAP = [ 'facebook', 'twitter', 'x-twitter', 'linkedin', 'linkedin-in', 'google', 'google-plus' ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_team_' );
        $settings = $element['settings'] ?? [];

        $name        = is_string( $settings['member_name'] ?? null ) ? $settings['member_name'] : '';
        $position    = is_string( $settings['member_designation'] ?? null ) ? $settings['member_designation'] : '';
        $description = is_string( $settings['member_description'] ?? null ) ? $settings['member_description'] : '';

        $image_raw = $settings['member_image'] ?? [];
        $image_url = is_array( $image_raw ) && is_string( $image_raw['url'] ?? null ) ? $image_raw['url'] : '';

        $block_settings = [];
        if ( $name !== '' ) {
            $block_settings['name']['innerContent']['desktop']['value'] = $name;
        }
        if ( $position !== '' ) {
            $block_settings['position']['innerContent']['desktop']['value'] = $position;
        }
        if ( $description !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $description;
        }
        if ( $image_url !== '' ) {
            $image = [ 'url' => $image_url ];
            if ( is_array( $image_raw ) && is_string( $image_raw['alt'] ?? null ) && $image_raw['alt'] !== '' ) {
                $image['alt'] = $image_raw['alt'];
            }
            $block_settings['image']['innerContent']['desktop']['value'] = $image;
        }

        $social = [];
        $raw_social = ( $settings['show_social_icons'] ?? '' ) === 'yes' && is_array( $settings['team_social_icons'] ?? null )
            ? $settings['team_social_icons']
            : [];
        foreach ( $raw_social as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $icon = $item['social_icon'] ?? null;
            $slug = is_array( $icon ) && is_string( $icon['value'] ?? null ) ? $icon['value'] : '';
            $url  = is_array( $item['link'] ?? null ) && is_string( $item['link']['url'] ?? null ) ? $item['link']['url'] : '';
            if ( $url === '' ) {
                continue;
            }
            $network = null;
            foreach ( self::NETWORK_MAP as $candidate ) {
                if ( str_contains( $slug, 'fa-' . $candidate ) ) {
                    $network = str_starts_with( $candidate, 'google' ) ? 'google' : ( str_starts_with( $candidate, 'linkedin' ) ? 'linkedin' : ( $candidate === 'x-twitter' ? 'twitter' : $candidate ) );
                    break;
                }
            }
            if ( $network === null ) {
                $this->engine->logNotCarriedOver( 'social_network', $id, "team member link '{$slug}': Divi's team member offers Facebook, Twitter, Google and LinkedIn" );
                continue;
            }
            $social[ "{$network}Url" ] = $url;
        }
        if ( $social !== [] ) {
            $block_settings['social']['innerContent']['desktop']['value'] = $social;
        }

        $this->engine->logConverted( 'team-member' );
        $this->logUnmappedSettings( $id, $settings, [
            'member_name', 'member_designation', 'member_description', 'member_image',
            'details_link', 'show_social_icons', 'team_social_icons',
            'element_list', 'name_tag', 'opacity', 'opacity_hover',
            'background_hover_transition', 'hover_animation', 'social_position',
            'name_color', 'name_h_color', 'designation_color', 'description_color',
            'icon_color', 'icon_bg_color', 'hover_icon_color', 'hover_icon_bg_color',
            'hover_icon_border_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/team-member',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
