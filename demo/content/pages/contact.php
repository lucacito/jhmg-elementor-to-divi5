<?php
/**
 * Contact: the tour booking form, address and hours, a map, social links, and a visitor FAQ.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, col, color, heading, icon, item, link, row, slider, text, widget};

return static function ( Context $ctx ): array {
    $social = static fn ( string $id, string $icon_class, string $url ): array => item( $id, [
        'social_icon' => icon( $icon_class, 'fa-brands' ),
        'link'        => link( $url, true ),
    ] );

    return [
        'title'         => 'Contact',
        'slug'          => 'contact',
        'elements'      => [
            band( [
                row( [
                    col( [
                        heading( 'Come and see us', 'h1' ),
                        text( '<p>Tell us when suits you and we will show you around, set you up at a desk, and make you a coffee. Tours take about twenty minutes.</p>' ),
                        widget( 'eael-contact-form-7', [ 'contact_form_list' => (string) $ctx->formId() ] ),
                    ], 55 ),
                    col( [
                        widget( 'icon-list', [
                            'icon_list' => [
                                item( 'il1', [ 'text' => 'Fern Court, Downtown', 'selected_icon' => icon( 'fas fa-map-marker-alt' ) ] ),
                                item( 'il2', [ 'text' => 'Monday to Friday, 8am to 7pm', 'selected_icon' => icon( 'fas fa-clock' ) ] ),
                                item( 'il3', [ 'text' => 'Members: open 24/7', 'selected_icon' => icon( 'fas fa-key' ) ] ),
                                item( 'il4', [ 'text' => 'hello@ferncourt.test', 'selected_icon' => icon( 'fas fa-envelope' ), 'link' => link( 'mailto:hello@ferncourt.test' ) ] ),
                            ],
                            // Elementor defaults list text to the kit's secondary color, which is our cream background.
                            '__globals__' => [ 'text_color' => color( 'text' ), 'icon_color' => color( 'primary' ) ],
                        ] ),
                        widget( 'google_maps', [
                            'address' => 'Downtown Portland, Oregon',
                            'zoom'    => slider( 14 ),
                            'height'  => slider( 360 ),
                        ] ),
                        widget( 'social-icons', [
                            'social_icon_list' => [
                                $social( 'so1', 'fab fa-instagram', 'https://www.instagram.com/' ),
                                $social( 'so2', 'fab fa-linkedin', 'https://www.linkedin.com/' ),
                                $social( 'so3', 'fab fa-facebook', 'https://www.facebook.com/' ),
                            ],
                        ] ),
                    ], 40 ),
                ], 48 ),
            ] ),

            band( [
                heading( 'Before you visit' ),
                widget( 'elementskit-accordion', [
                    'ekit_accordion_items' => [
                        item( 'ac1', [ 'acc_title' => 'Where do I leave my bike?', 'acc_content' => '<p>Covered racks are by the side entrance on Fern Lane, with showers on the ground floor.</p>', 'ekit_acc_is_active' => 'yes' ] ),
                        item( 'ac2', [ 'acc_title' => 'Can I visit without booking?', 'acc_content' => '<p>Yes, between 10am and 4pm on weekdays. Ask for a tour at the front desk.</p>', 'ekit_acc_is_active' => 'no' ] ),
                        item( 'ac3', [ 'acc_title' => 'Is the building accessible?', 'acc_content' => '<p>Step-free entrance, a lift to every floor, and accessible toilets on each level.</p>', 'ekit_acc_is_active' => 'no' ] ),
                    ],
                ] ),
            ], 'secondary' ),
        ],
        'survive'       => [
            'Come and see us', 'Tours take about twenty minutes',
            'Fern Court, Downtown', 'Monday to Friday, 8am to 7pm', 'Members: open 24/7', 'hello@ferncourt.test',
            // The map converts to an embed iframe with the address URL-encoded.
            'Downtown%20Portland%2C%20Oregon',
            'Before you visit', 'Where do I leave my bike?', 'Can I visit without booking?', 'between 10am and 4pm on weekdays',
            'Is the building accessible?', 'Step-free entrance',
        ],
        // The Contact Form 7 converter keeps the form by ID.
        'survive_exact' => [ (string) $ctx->formId() ],
    ];
};
