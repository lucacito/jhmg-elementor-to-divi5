<?php
/**
 * Header Footer Elementor footer: the primary menu, social links, and the copyright line.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{box, col, color, container, gap, icon, item, link, row, widget};

return static function ( Context $ctx ): array {
    return [
        'title'    => 'Ferncourt Footer',
        'slug'     => 'ferncourt-footer',
        'template' => 'footer',
        'elements' => [
            container( [
                row( [
                    col( [
                        widget( 'navigation-menu', [ 'menu' => $ctx->menu(), 'layout' => 'horizontal' ] ),
                    ], 60 ),
                    col( [
                        widget( 'social-icons', [
                            'social_icon_list' => [
                                item( 'fs1', [ 'social_icon' => icon( 'fab fa-instagram', 'fa-brands' ), 'link' => link( 'https://www.instagram.com/', true ) ] ),
                                item( 'fs2', [ 'social_icon' => icon( 'fab fa-linkedin', 'fa-brands' ), 'link' => link( 'https://www.linkedin.com/', true ) ] ),
                            ],
                            'align'            => 'right',
                        ] ),
                    ], 35 ),
                ], 24, [ 'flex_align_items' => 'center' ] ),
                widget( 'copyright', [ 'shortcode' => 'Copyright [hfe_current_year] [hfe_site_title]. Calm space for focused work.' ] ),
            ], [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_gap'              => gap( 24 ),
                'padding'               => box( 48, 24 ),
                'background_background' => 'classic',
                '__globals__'           => [ 'background_color' => color( 'primary' ) ],
            ] ),
        ],
        'survive'  => [ 'Calm space for focused work' ],
    ];
};
