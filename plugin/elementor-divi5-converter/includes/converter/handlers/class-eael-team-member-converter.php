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

        $block_settings = [];

        if ( $name !== '' ) {
            $block_settings['name'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $name ] ],
            ];
        }

        if ( $position !== '' || $description !== '' ) {
            $block_settings['module'] = [
                'advanced' => array_filter( [
                    'position'    => $position !== '' ? [ 'desktop' => [ 'value' => $position ] ] : null,
                    'description' => $description !== '' ? [ 'desktop' => [ 'value' => $description ] ] : null,
                ] ),
            ];
        }

        if ( $image_url !== '' ) {
            $block_settings['image'] = [
                'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => $image_url ] ] ],
            ];
        }

        $this->engine->logConverted( 'team-member' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_team_member_name', 'eael_team_member_job_title',
            'eael_team_member_description', 'eael_team_member_image',
            'eael_team_member_name_tag', 'eael_team_member_job_title_tag',
            'eael_team_members_preset', 'eael_team_member_facebook_url',
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
