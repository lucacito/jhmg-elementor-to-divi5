<?php
/**
 * Render probe: the core-widget cases the Ferncourt pages avoid, laid out the way
 * Elementor's Kit Library kits are (legacy sections and columns). Seeded only by
 * demo/verify.sh render; removed by the reset that follows. demo/tests/render.spec.ts
 * asserts what a viewer sees on the converted draft.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, button, column, heading, icon, item, link, section, slider, widget};

return static function ( Context $ctx ): array {
    $social = static fn ( string $id, string $class, string $url ): array => item( $id, [
        'social_icon' => icon( $class, 'fa-brands' ),
        'link'        => link( $url, true ),
    ] );

    return [
        'title'    => 'Render probe: core widgets',
        'slug'     => 'render-probe-core-widgets',
        'elements' => [
            // Ceramic Studio's hero: a 90vh section, the left column carries a cover image
            // and holds only a spacer, the right column the text.
            section( [
                column( [ widget( 'spacer', [ 'space' => slider( 50 ) ] ) ], 50, [
                    'background_background' => 'classic',
                    'background_image'      => $ctx->image( 'hero-lounge.jpg' ),
                    'background_position'   => 'center center',
                    'background_repeat'     => 'no-repeat',
                    'background_size'       => 'cover',
                ] ),
                column( [
                    heading( 'Probe hero heading', 'h1' ),
                    widget( 'heading', [
                        'title'                     => 'eramic',
                        'header_size'               => 'span',
                        'typography_typography'     => 'custom',
                        'typography_font_size'      => slider( 12, 'vw' ),
                        'typography_font_weight'    => '700',
                        'typography_letter_spacing' => slider( -2 ),
                        'title_color'               => '#C8643B',
                    ] ),
                    button( 'Probe button', $ctx->url( '/contact/' ) ),
                    widget( 'counter', [ 'starting_number' => 0, 'ending_number' => 58, 'suffix' => '%', 'title' => 'Percent probe' ] ),
                    widget( 'counter', [ 'starting_number' => 0, 'ending_number' => 240, 'suffix' => '+', 'title' => 'Plus probe' ] ),
                ], 50 ),
            ], [
                'layout'           => 'full_width',
                'height'           => 'min-height',
                'custom_height'    => slider( 90, 'vh' ),
                'column_position'  => 'stretch',
                'content_position' => 'middle',
            ] ),

            band( [
                widget( 'image-carousel', [
                    'carousel'       => [
                        $ctx->image( 'desks-open-plan.jpg' ),
                        $ctx->image( 'desks-window.jpg' ),
                        $ctx->image( 'desks-plants.jpg' ),
                        $ctx->image( 'office-private-1.jpg' ),
                    ],
                    'thumbnail_size' => 'full',
                    'slides_to_show' => '4',
                ] ),
                widget( 'image-gallery', [
                    'wp_gallery'      => [ $ctx->image( 'desks-open-plan.jpg' ), $ctx->image( 'desks-window.jpg' ), $ctx->image( 'desks-plants.jpg' ) ],
                    'gallery_columns' => 3,
                ] ),
                widget( 'social-icons', [
                    'social_icon_list' => [
                        $social( 'ps1', 'fab fa-instagram', 'https://www.instagram.com/' ),
                        $social( 'ps2', 'fab fa-linkedin', 'https://www.linkedin.com/' ),
                    ],
                ] ),
                widget( 'google_maps', [ 'address' => 'Downtown Portland, Oregon', 'zoom' => slider( 14 ), 'height' => slider( 360 ) ] ),
            ] ),
        ],
        'survive'  => [ 'Probe hero heading', 'eramic', 'Probe button', 'Percent probe', 'Plus probe' ],
    ];
};
