<?php
/**
 * Header Footer Elementor header: logo, the primary menu, and a "Book a tour" button.
 * HFE shows custom_image only when site_logo_fallback (labelled "Custom Image") is 'yes';
 * the converter reads custom_image either way.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{box, button, col, color, container, row, slider, widget};

return static function ( Context $ctx ): array {
    return [
        'title'    => 'Ferncourt Header',
        'slug'     => 'ferncourt-header',
        'template' => 'header',
        'elements' => [
            container( [
                row( [
                    col( [
                        widget( 'site-logo', [
                            'site_logo_fallback' => 'yes',
                            'custom_image'       => $ctx->image( 'logo.png' ),
                            'width'              => slider( 200 ),
                        ] ),
                    ], 20 ),
                    col( [
                        widget( 'navigation-menu', [ 'menu' => $ctx->menu(), 'layout' => 'horizontal' ] ),
                    ], 55, [ 'flex_align_items' => 'center' ] ),
                    col( [
                        button( 'Book a tour', $ctx->url( '/contact/' ) ),
                    ], 18, [ 'flex_align_items' => 'flex-end' ] ),
                ], 24, [ 'flex_align_items' => 'center', 'flex_wrap' => 'nowrap' ] ),
            ], [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'padding'               => box( 16, 24 ),
                'background_background' => 'classic',
                '__globals__'           => [ 'background_color' => color( 'secondary' ) ],
            ] ),
        ],
        'survive'  => [ 'logo.png', 'Book a tour' ],
    ];
};
