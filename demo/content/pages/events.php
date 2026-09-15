<?php
/**
 * Events: a countdown to the next event, this week and this month as tabs, and the
 * latest posts. The converter carries the post grid's post count but not a category
 * filter (docs/known-issues.md), so the grid lists the latest posts and both versions
 * of the page match.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, col, heading, item, row, text, widget};

return static function ( Context $ctx ): array {
    $tax_night = $ctx->date( '+21 days 18:00' );
    $day       = $ctx->date( '+21 days 18:00', 'l j F' );

    return [
        'title'         => 'Events',
        'slug'          => 'events',
        'elements'      => [
            band( [
                row( [
                    col( [
                        heading( 'Next up: Freelance Tax Night', 'h1' ),
                        text( "<p>{$day}, 6pm in the Long Room. Two accountants, plain answers, and pizza. Free for members.</p>" ),
                    ], 45 ),
                    col( [
                        widget( 'eael-countdown', [
                            'eael_countdown_type'     => 'due_date',
                            'eael_countdown_due_time' => $tax_night,
                        ] ),
                    ], 50 ),
                ], 32, [ 'flex_align_items' => 'center' ] ),
            ], 'secondary' ),

            band( [
                heading( 'On the calendar' ),
                widget( 'eael-adv-tabs', [
                    'eael_adv_tabs_tab' => [
                        item( 'et1', [
                            'eael_adv_tabs_tab_title'   => 'This week',
                            'eael_adv_tabs_text_type'   => 'content',
                            'eael_adv_tabs_tab_content' => '<ul><li>Tuesday, 7am: Morning run club</li><li>Thursday, 5pm: Portfolio review</li><li>Friday, 12:30pm: Members lunch</li></ul>',
                        ] ),
                        item( 'et2', [
                            'eael_adv_tabs_tab_title'   => 'This month',
                            'eael_adv_tabs_text_type'   => 'content',
                            'eael_adv_tabs_tab_content' => '<ul><li>Freelance Tax Night</li><li>Workshop: Pricing your work</li><li>Ferncourt summer social</li></ul>',
                        ] ),
                    ],
                ] ),
            ] ),

            band( [
                heading( 'From the journal' ),
                widget( 'eael-post-grid', [
                    'post_type'              => 'post',
                    'posts_per_page'         => 3,
                    'eael_post_grid_columns' => 'eael-col-3',
                    'eael_show_excerpt'      => 'yes',
                ] ),
            ], 'secondary' ),
        ],
        'survive'       => [
            'Next up: Freelance Tax Night', '6pm in the Long Room',
            'On the calendar', 'This week', 'This month', 'Morning run club', 'Portfolio review',
            'Workshop: Pricing your work', 'Ferncourt summer social',
            'From the journal',
        ],
        // The countdown converter keeps EAEL's due date string as-is.
        'survive_exact' => [ $tax_night ],
    ];
};
