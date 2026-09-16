<?php
/**
 * About: the story, the team, who works here, a member testimonial, and the space tour video.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, col, heading, icon, item, link, row, slider, text, widget};

return static function ( Context $ctx ): array {
    $member = static fn ( string $id, string $photo, string $name, string $role, string $bio ): array => col( [
        widget( 'eael-team-member', [
            'eael_team_member_image'                  => $ctx->image( $photo ),
            'eael_team_member_name'                   => $name,
            'eael_team_member_job_title'              => $role,
            'eael_team_member_description'            => $bio,
            'eael_team_member_enable_social_profiles' => 'yes',
            'eael_team_member_social_profile_links'   => [
                item( "{$id}s", [ 'social_new' => icon( 'fab fa-linkedin', 'fa-brands' ), 'link' => link( 'https://www.linkedin.com/', true ) ] ),
            ],
        ] ),
    ], 22 );

    $share = static fn ( int $percent, string $who ): array => col( [
        widget( 'eael-progress-bar', [
            'progress_bar_title' => $who,
            'progress_bar_value' => slider( $percent, '%' ),
        ] ),
    ], 30 );

    return [
        'title'         => 'About',
        'slug'          => 'about',
        'elements'      => [
            band( [
                row( [
                    col( [
                        widget( 'elementskit-heading', [
                            'ekit_heading_title'                    => 'Built by freelancers, for freelancers',
                            'ekit_heading_title_tag'                => 'h1',
                            'ekit_heading_sub_title_show'           => 'yes',
                            'ekit_heading_sub_title'                => 'Our story',
                            'ekit_heading_section_extra_title_show' => 'yes',
                            'ekit_heading_extra_title'              => 'Opened in 2019 in a former print works.',
                        ] ),
                        text( '<p>We spent ten years working from kitchen tables and noisy cafes. Ferncourt is the space we wanted: calm, full of daylight, and full of people doing interesting work.</p><p>Today more than 240 members work here, from solo illustrators to twelve-person software teams.</p>' ),
                    ], 55 ),
                    col( [
                        widget( 'image', [ 'image' => $ctx->image( 'about-story.jpg' ), 'image_size' => 'large' ] ),
                    ], 40 ),
                ], 48, [ 'flex_align_items' => 'center' ] ),
            ] ),

            band( [
                heading( 'The team' ),
                row( [
                    $member( 'tm1', 'team-1.jpg', 'Hannah Moore', 'Founder', 'Ran a design studio for ten years before opening Ferncourt.' ),
                    $member( 'tm2', 'team-2.jpg', 'Daniel Reyes', 'Community manager', 'Knows every member by name and most of them by coffee order.' ),
                    $member( 'tm3', 'team-3.jpg', 'Grace Liu', 'Operations lead', 'Keeps the building, the bookings and the wifi running.' ),
                    $member( 'tm4', 'team-4.jpg', 'Samuel Adeyemi', 'Events and partnerships', 'Plans the workshops, the socials and the Friday lunch.' ),
                ] ),
            ], 'secondary' ),

            band( [
                heading( 'Who works here' ),
                text( '<p>Most members work for themselves. The rest are small teams and remote employees who wanted colleagues again.</p>' ),
                row( [
                    $share( 58, 'Freelancers' ),
                    $share( 31, 'Small teams' ),
                    $share( 11, 'Remote employees' ),
                ] ),
            ] ),

            band( [
                widget( 'elementskit-testimonial', [
                    'ekit_testimonial_data' => [
                        item( 'kt1', [
                            'client_name'  => 'Leo Marchetti',
                            'designation'  => 'Photographer',
                            'review'       => 'Ferncourt feels like a studio with better neighbours. I have found three clients in the kitchen.',
                            'client_photo' => $ctx->image( 'member-4.jpg' ),
                            // Style 1 shows the logo slot large; unset, ElementsKit renders
                            // Elementor's placeholder image there.
                            'client_logo'        => $ctx->image( 'member-4.jpg' ),
                            'client_logo_active' => $ctx->image( 'member-4.jpg' ),
                        ] ),
                    ],
                ] ),
            ], 'secondary' ),

            band( [
                heading( 'Take the tour' ),
                text( '<p>Twenty seconds around the building, from the lounge to the coffee bar.</p>' ),
                widget( 'elementskit-video', [
                    'ekit_video_popup_video_type'  => 'self',
                    'ekit_video_self_url'          => 'yes',
                    'ekit_video_self_external_url' => $ctx->mediaUrl( 'tour.webm' ),
                    'self_poster_image'            => $ctx->image( 'tour-poster.jpg' ),
                ] ),
            ] ),
        ],
        'survive'       => [
            'Our story',
            'Built by freelancers, for freelancers', 'Opened in 2019 in a former print works',
            'kitchen tables and noisy cafes', 'more than 240 members', 'about-story.jpg',
            'The team', 'Hannah Moore', 'Founder', 'Ran a design studio for ten years',
            'Daniel Reyes', 'Community manager', 'Grace Liu', 'Operations lead', 'Samuel Adeyemi', 'Events and partnerships',
            'Who works here', 'Most members work for themselves', 'Freelancers', 'Small teams', 'Remote employees',
            'Leo Marchetti', 'Photographer', 'better neighbours',
            'Take the tour', 'tour.webm',
        ],
        // Counters convert to prefix . number . suffix.
        'survive_exact' => [ '58', '31', '11' ],
    ];
};
