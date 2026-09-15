<?php
/**
 * Spaces: a filterable gallery of the building, amenities as flip boxes, and the
 * space types as tabs beside an image box.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, col, heading, icon, item, row, text, widget};

return static function ( Context $ctx ): array {
    $photo = static fn ( string $id, string $name, string $filter, string $file ): array => item( $id, [
        'eael_fg_gallery_item_name' => $name,
        'fg_item_cat'               => $filter,
        'eael_fg_gallery_img'       => $ctx->image( $file ),
        'eael_fg_gallery_lightbox'  => 'yes',
    ] );

    $flip = static fn ( string $icon_class, string $title, string $front, string $back ): array => col( [
        widget( 'eael-flip-box', [
            'eael_flipbox_img_or_icon' => 'icon',
            'eael_flipbox_icon_new'    => icon( $icon_class ),
            'eael_flipbox_front_title' => $title,
            'eael_flipbox_front_text'  => $front,
            'eael_flipbox_back_title'  => $title,
            'eael_flipbox_back_text'   => $back,
        ] ),
    ], 31 );

    return [
        'title'    => 'Spaces',
        'slug'     => 'spaces',
        'elements' => [
            band( [
                heading( 'Find your kind of space', 'h1' ),
                text( '<p>Three floors of a converted print works: open desks upstairs, quiet offices in the middle, and meeting rooms and the lounge on the ground floor.</p>' ),
                widget( 'eael-filterable-gallery', [
                    'filter_enable'          => 'yes',
                    'eael_fg_all_label_text' => 'All spaces',
                    'eael_fg_controls'       => [
                        item( 'fc01', [ 'eael_fg_control' => 'Hot desks' ] ),
                        item( 'fc02', [ 'eael_fg_control' => 'Private offices' ] ),
                        item( 'fc03', [ 'eael_fg_control' => 'Meeting rooms' ] ),
                        item( 'fc04', [ 'eael_fg_control' => 'Lounge' ] ),
                    ],
                    'eael_fg_gallery_items'  => [
                        $photo( 'fg01', 'Open plan desks', 'Hot desks', 'desks-open-plan.jpg' ),
                        $photo( 'fg02', 'Window desks', 'Hot desks', 'desks-window.jpg' ),
                        $photo( 'fg03', 'Studio office', 'Private offices', 'office-private-1.jpg' ),
                        $photo( 'fg04', 'Team office', 'Private offices', 'office-private-2.jpg' ),
                        $photo( 'fg05', 'The Long Room', 'Meeting rooms', 'meeting-room-large.jpg' ),
                        $photo( 'fg06', 'The Snug', 'Meeting rooms', 'meeting-room-small.jpg' ),
                        $photo( 'fg07', 'Lounge', 'Lounge', 'lounge-sofas.jpg' ),
                        $photo( 'fg08', 'Kitchen', 'Lounge', 'lounge-kitchen.jpg' ),
                    ],
                    'columns'                => '3',
                ] ),
            ] ),

            band( [
                heading( 'Included with every membership' ),
                row( [
                    $flip( 'fas fa-print', 'Print and scan', 'A proper office printer', '200 free pages a month on every membership.' ),
                    $flip( 'fas fa-phone', 'Phone booths', 'For calls that need quiet', 'Four soundproof booths, free to use, no booking.' ),
                    $flip( 'fas fa-bicycle', 'Bike storage', 'Covered and secure', 'Covered racks for 40 bikes, plus showers on the ground floor.' ),
                    $flip( 'fas fa-coffee', 'Kitchen', 'Tea, coffee and fruit', 'Free tea, coffee and fruit, with a fridge and dishwasher for your own food.' ),
                    $flip( 'fas fa-lock', 'Lockers', 'Leave your kit overnight', 'Personal lockers for Flex Desk members, included in the price.' ),
                    $flip( 'fas fa-calendar-alt', 'Event space', 'Room for 60', 'Host a launch or workshop for up to 60 people. Members get two free evenings a year.' ),
                ] ),
            ], 'secondary' ),

            band( [
                heading( 'Choose how you work' ),
                row( [
                    col( [
                        widget( 'tabs', [
                            'tabs' => [
                                item( 'tb01', [ 'tab_title' => 'Hot desk', 'tab_content' => '<p>Any free desk on the open floor. Arrive when you like, leave when you like, and take a locker if you need one.</p>' ] ),
                                item( 'tb02', [ 'tab_title' => 'Fixed desk', 'tab_content' => '<p>The same desk every day, with a lockable pedestal, a monitor, and a chair set up the way you like it.</p>' ] ),
                                item( 'tb03', [ 'tab_title' => 'Private office', 'tab_content' => '<p>A lockable room for one to four people, furnished and ready on day one, with your name on the door.</p>' ] ),
                            ],
                        ] ),
                    ], 58 ),
                    col( [
                        widget( 'image-box', [
                            'image'            => $ctx->image( 'desks-plants.jpg' ),
                            'title_text'       => 'Every desk, a window seat',
                            'description_text' => 'Floor-to-ceiling windows on three sides mean nobody works in a dark corner.',
                            'title_size'       => 'h3',
                        ] ),
                    ], 38 ),
                ], 32 ),
            ] ),
        ],
        'survive'  => [
            'Find your kind of space', 'converted print works',
            'desks-open-plan.jpg', 'office-private-1.jpg', 'meeting-room-small.jpg', 'lounge-kitchen.jpg',
            'Included with every membership',
            'Print and scan', '200 free pages a month', 'Phone booths', 'Four soundproof booths',
            'Bike storage', 'Covered racks for 40 bikes', 'Kitchen', 'Lockers', 'Personal lockers for Flex Desk members',
            'Event space', 'Members get two free evenings a year',
            'Choose how you work', 'Hot desk', 'Fixed desk', 'Private office', 'The same desk every day', 'ready on day one',
            'Every desk, a window seat', 'nobody works in a dark corner',
        ],
    ];
};
