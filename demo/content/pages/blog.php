<?php
/**
 * Blog: an intro and the Premium Addons blog listing of every post.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, heading, text, widget};

return static function ( Context $ctx ): array {
    return [
        'title'    => 'Blog',
        'slug'     => 'blog',
        'elements' => [
            band( [
                heading( 'Notes from Ferncourt', 'h1' ),
                text( '<p>News from the building, recaps of events, and stories from the people who work here.</p>' ),
                widget( 'premium-addon-blog', [
                    'premium_blog_skin'            => 'classic',
                    'premium_blog_grid'            => 'yes',
                    'premium_blog_layout'          => 'even',
                    'premium_blog_columns_number'  => '33.33%',
                    'premium_blog_number_of_posts' => 6,
                    'post_type_filter'             => 'post',
                    'premium_blog_excerpt'         => 'yes',
                    'premium_blog_excerpt_type'    => 'excerpt',
                    'premium_blog_excerpt_length'  => 22,
                    'premium_blog_paging'          => 'yes',
                ] ),
            ] ),
        ],
        'survive'  => [ 'Notes from Ferncourt', 'stories from the people who work here' ],
    ];
};
