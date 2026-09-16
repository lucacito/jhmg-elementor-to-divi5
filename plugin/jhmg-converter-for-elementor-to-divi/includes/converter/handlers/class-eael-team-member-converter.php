<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelTeamMemberConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_team_' );
        $settings = $element['settings'] ?? [];

        $name        = is_string( $settings['eael_team_member_name'] ?? '' ) ? ( $settings['eael_team_member_name'] ?? '' ) : '';
        $position    = is_string( $settings['eael_team_member_job_title'] ?? '' ) ? ( $settings['eael_team_member_job_title'] ?? '' ) : '';
        $description = is_string( $settings['eael_team_member_description'] ?? '' ) ? ( $settings['eael_team_member_description'] ?? '' ) : '';

        $image_raw = $settings['eael_team_member_image'] ?? [];
        $image_url = '';
        if ( is_array( $image_raw ) ) {
            $image_url = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        }

        // divi/team-member (team-member/module.json, TeamMemberModule.php): name,
        // position and content are each an innerContent; the photo is
        // image.innerContent {url, alt} (line 98 reads url); social links are
        // social.innerContent {facebookUrl, twitterUrl, googleUrl, linkedinUrl}.
        // The old module.advanced.position/description and image src were never read.
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

        // EAEL 6.6.7 Team_Member.php: eael_team_member_social_profile_links repeater of
        // {social_new: ICONS value, link}. Divi offers four networks; the rest are reported.
        $social = [];
        foreach ( is_array( $settings['eael_team_member_social_profile_links'] ?? null ) ? $settings['eael_team_member_social_profile_links'] : [] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $class = is_array( $item['social_new'] ?? null ) ? ( $item['social_new']['value'] ?? '' ) : '';
            $url   = is_array( $item['link'] ?? null ) ? ( $item['link']['url'] ?? '' ) : '';
            if ( ! is_string( $class ) || ! is_string( $url ) || $url === '' ) {
                continue;
            }
            $network = null;
            foreach ( [ 'facebook', 'twitter', 'linkedin', 'google' ] as $candidate ) {
                if ( str_contains( $class, 'fa-' . $candidate ) ) {
                    $network = $candidate;
                    break;
                }
            }
            if ( $network === null ) {
                $this->engine->logNotCarriedOver( 'social_network', (string) $id, "team member link '{$class}': Divi's team member offers Facebook, Twitter, Google and LinkedIn" );
                continue;
            }
            $social[ "{$network}Url" ] = $url;
        }
        if ( $social !== [] ) {
            $block_settings['social']['innerContent']['desktop']['value'] = $social;
        }

        $this->engine->logConverted( 'team-member' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_team_member_name', 'eael_team_member_job_title',
            'eael_team_member_description', 'eael_team_member_image',
            'eael_team_member_name_tag', 'eael_team_member_job_title_tag',
            'eael_team_members_preset', 'eael_team_member_enable_social_profiles', 'eael_team_member_social_profile_links', 'eael_team_member_facebook_url',
            'eael_team_member_twitter_url', 'eael_team_member_linkedin_url',
            'eael_team_member_youtube_url', 'eael_team_member_instagram_url',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/team-member',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
