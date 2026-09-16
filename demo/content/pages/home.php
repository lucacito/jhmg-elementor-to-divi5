<?php
/**
 * Home: hero with fancy text and a dual button, stats, amenities, a spaces carousel,
 * member testimonials, and a closing call to action.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, button, col, color, heading, icon, item, link, row, text, widget};

return static function ( Context $ctx ): array {
    $counter = static fn ( int $to, string $title, string $suffix = '' ): array => col( [
        widget( 'counter', [
            'starting_number' => 0,
            'ending_number'   => $to,
            'suffix'          => $suffix,
            'title'           => $title,
            '__globals__'     => [ 'number_color' => color( 'secondary' ), 'title_color' => color( 'secondary' ) ],
        ] ),
    ], 22 );

    $amenity = static fn ( string $icon_class, string $title, string $body ): array => col( [
        widget( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'icon',
            'eael_infobox_icon_new'    => icon( $icon_class ),
            'eael_infobox_title'       => $title,
            'eael_infobox_text'        => $body,
        ] ),
    ], 31 );

    $testimonial = static fn ( string $photo, string $name, string $role, string $quote ): array => col( [
        widget( 'eael-testimonial', [
            'image'                          => $ctx->image( $photo ),
            'eael_testimonial_name'          => $name,
            'eael_testimonial_company_title' => $role,
            'eael_testimonial_description'   => "<p>{$quote}</p>",
        ] ),
    ], 31 );

    return [
        'title'      => 'Home',
        'slug'       => 'home',
        'front_page' => true,
        'elements'   => [
            band( [
                row( [
                    col( [
                        widget( 'eael-fancy-text', [
                            'eael_fancy_text_prefix'          => 'A calmer place to',
                            'eael_fancy_text_strings'         => [
                                item( 'fx01', [ 'eael_fancy_text_strings_text_field' => 'do your best work' ] ),
                                item( 'fx02', [ 'eael_fancy_text_strings_text_field' => 'meet your clients' ] ),
                                item( 'fx03', [ 'eael_fancy_text_strings_text_field' => 'grow a small team' ] ),
                            ],
                            'eael_fancy_text_suffix'          => '',
                            'eael_fancy_text_transition_type' => 'typing',
                        ] ),
                        text( '<p>Ferncourt is a coworking space for freelancers and small teams: bright desks, quiet rooms, good coffee, and neighbours worth knowing.</p>' ),
                        widget( 'elementskit-dual-button', [
                            'ekit_button_one_text' => 'See memberships',
                            'ekit_button_one_link' => link( $ctx->url( '/memberships/' ) ),
                            'ekit_button_two_text' => 'Book a tour',
                            'ekit_button_two_link' => link( $ctx->url( '/contact/' ) ),
                        ] ),
                    ], 55 ),
                    col( [
                        widget( 'image', [ 'image' => $ctx->image( 'hero-lounge.jpg' ), 'image_size' => 'large' ] ),
                    ], 40 ),
                ], 48, [ 'flex_align_items' => 'center' ] ),
            ], 'secondary' ),

            band( [
                heading( 'Ferncourt in numbers', 'h2', 'secondary', 'center' ),
                row( [
                    $counter( 240, 'Members', '+' ),
                    $counter( 86, 'Desks' ),
                    $counter( 9, 'Meeting rooms' ),
                    $counter( 48, 'Events a year' ),
                ] ),
            ], 'primary' ),

            band( [
                heading( 'Everything you need to focus' ),
                row( [
                    $amenity( 'fas fa-wifi', 'Fast, reliable wifi', 'Gigabit fibre on every floor, with a wired port at every fixed desk.' ),
                    $amenity( 'fas fa-door-closed', 'Rooms that close', 'Nine meeting rooms and four phone booths, bookable from your phone.' ),
                    $amenity( 'fas fa-coffee', 'Good coffee, all day', 'Local roasters, a proper espresso machine, and oat milk that never runs out.' ),
                ] ),
            ] ),

            band( [
                heading( 'Spaces to suit the day' ),
                text( '<p>Hot desks by the windows, fixed desks for regulars, and private offices for teams of up to four.</p>' ),
                widget( 'image-carousel', [
                    'carousel'       => array_map(
                        static fn ( string $file ): array => $ctx->image( $file ),
                        [ 'desks-window.jpg', 'meeting-room-large.jpg', 'lounge-sofas.jpg', 'phone-booth.jpg', 'coffee-bar.jpg' ]
                    ),
                    'slides_to_show' => '3',
                    'navigation'     => 'both',
                ] ),
                button( 'Explore the spaces', $ctx->url( '/spaces/' ) ),
            ], 'secondary' ),

            band( [
                heading( 'What members say' ),
                row( [
                    $testimonial( 'member-1.jpg', 'Priya Raman', 'Brand designer', 'I moved my studio here two years ago. The quiet rooms alone paid for the membership.' ),
                    $testimonial( 'member-2.jpg', 'Tomasz Nowak', 'Software consultant', 'Good wifi, good coffee, and nobody minds when I take calls in the booth all afternoon.' ),
                    $testimonial( 'member-3.jpg', 'Amara Okafor', 'Founder, Okafor Studio', 'We started on two hot desks. Eighteen months later we have our own office down the hall.' ),
                ] ),
            ] ),

            band( [
                // The converter carries the CTA's title, subtitle and button, not eael_cta_content
                // (docs/known-issues.md), so the sentence lives in the subtitle, which EAEL also renders.
                widget( 'eael-cta-box', [
                    'eael_cta_type'      => 'cta-basic',
                    'eael_cta_title'     => 'Try Ferncourt for a day',
                    'eael_cta_sub_title' => 'Your first day pass is on us. Bring your laptop and see if it fits.',
                    // Stored empty so EAEL does not render its placeholder paragraph.
                    'eael_cta_content'   => '',
                    'eael_cta_btn_text'  => 'Claim a free day',
                    'eael_cta_btn_link'  => link( $ctx->url( '/contact/' ) ),
                ] ),
            ], 'primary' ),
        ],
        // Fancy text converts to a static heading with its first rotating string, by design.
        'survive'       => [
            'A calmer place to', 'do your best work',
            'bright desks, quiet rooms, good coffee', 'See memberships', 'Book a tour',
            'Ferncourt in numbers', 'Members', 'Desks', 'Meeting rooms', 'Events a year',
            'Everything you need to focus', 'Fast, reliable wifi', 'Gigabit fibre on every floor', 'Rooms that close', 'Good coffee, all day',
            // Gallery and carousel images travel as attachment IDs, not URLs, so they are checked by the render test.
            'Spaces to suit the day', 'hero-lounge.jpg', 'Explore the spaces',
            'What members say', 'Priya Raman', 'Brand designer', 'The quiet rooms alone paid for the membership', 'Tomasz Nowak', 'Amara Okafor', 'Eighteen months later',
            'Try Ferncourt for a day', 'Your first day pass is on us', 'Claim a free day',
        ],
        // Counters convert to prefix . number . suffix.
        // Divi's number counter has no suffix: 240+ converts to 240 and reports the '+'.
        'survive_exact' => [ '240', '86', '9', '48' ],
    ];
};
