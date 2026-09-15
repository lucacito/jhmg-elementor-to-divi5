<?php
/**
 * Memberships: three pricing tables, a plan comparison table, and an FAQ.
 * The pricing tables have no subtitle: the converter drops eael_pricing_table_sub_title
 * (docs/known-issues.md).
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, col, heading, item, link, row, text, widget};

return static function ( Context $ctx ): array {
    $plan = static fn ( string $id, string $title, string $price, string $period, array $features, string $cta, bool $featured = false ): array => col( [
        widget( 'eael-pricing-table', [
            'eael_pricing_table_style'        => 'style-1',
            'eael_pricing_table_title'        => $title,
            'eael_pricing_table_price'        => $price,
            'eael_pricing_table_price_cur'    => '$',
            'eael_pricing_table_price_period' => $period,
            'eael_pricing_table_items'        => array_map(
                static fn ( int $n, string $feature ): array => item( "{$id}{$n}", [ 'eael_pricing_table_item' => $feature ] ),
                array_keys( $features ),
                $features
            ),
            'eael_pricing_table_btn'          => $cta,
            'eael_pricing_table_btn_link'     => link( $ctx->url( '/contact/' ) ),
            'eael_pricing_table_featured'     => $featured ? 'yes' : '',
        ] ),
    ], 31 );

    // EAEL's data table body is one flat repeater: a 'row' entry starts a row, and each
    // 'col' entry after it is a cell in that row.
    $table_row = static function ( string $id, array $cells ): array {
        $entries = [ item( "{$id}r", [ 'eael_data_table_content_row_type' => 'row' ] ) ];
        foreach ( $cells as $n => $cell ) {
            $entries[] = item( "{$id}c{$n}", [
                'eael_data_table_content_row_type'  => 'col',
                'eael_data_table_content_type'      => 'textarea',
                'eael_data_table_content_row_title' => $cell,
            ] );
        }
        return $entries;
    };

    $faq = static fn ( string $id, string $question, string $answer ): array => item( $id, [
        'eael_adv_accordion_tab_title'   => $question,
        'eael_adv_accordion_tab_content' => "<p>{$answer}</p>",
    ] );

    return [
        'title'         => 'Memberships',
        'slug'          => 'memberships',
        'elements'      => [
            band( [
                heading( 'Pick the membership that fits', 'h1', 'primary', 'center' ),
                text( '<p style="text-align:center">No joining fees. Change or cancel with a month of notice.</p>' ),
                row( [
                    $plan( 'pd', 'Day Pass', '29', 'day',
                        [ 'Any hot desk, 8am to 6pm', 'Tea and coffee included', 'Guest wifi' ],
                        'Buy a day pass' ),
                    $plan( 'pf', 'Flex Desk', '189', 'month',
                        [ 'Any hot desk, 24/7 access', '4 meeting room hours a month', 'Mail handling', 'Member events' ],
                        'Join Flex Desk', true ),
                    $plan( 'po', 'Private Office', '549', 'month',
                        [ 'Lockable office for up to 4 people', '10 meeting room hours a month', 'Business address', 'Member events' ],
                        'Ask about offices' ),
                ], 24, [ 'flex_align_items' => 'stretch' ] ),
            ] ),

            band( [
                heading( 'Compare the plans' ),
                widget( 'eael-data-table', [
                    'eael_data_table_header_cols_data' => [
                        item( 'dh1', [ 'eael_data_table_header_col' => 'Included' ] ),
                        item( 'dh2', [ 'eael_data_table_header_col' => 'Day Pass' ] ),
                        item( 'dh3', [ 'eael_data_table_header_col' => 'Flex Desk' ] ),
                        item( 'dh4', [ 'eael_data_table_header_col' => 'Private Office' ] ),
                    ],
                    'eael_data_table_content_rows'     => array_merge(
                        $table_row( 'd1', [ 'Desk access', 'Weekdays, 8am to 6pm', '24/7', '24/7' ] ),
                        $table_row( 'd2', [ 'Meeting room hours', 'Pay as you go', '4 a month', '10 a month' ] ),
                        $table_row( 'd3', [ 'Mail handling', 'Not included', 'Included', 'Included' ] ),
                        $table_row( 'd4', [ 'Business address', 'Not included', 'Not included', 'Included' ] ),
                        $table_row( 'd5', [ 'Lockable storage', 'Not included', 'Locker', 'Your own office' ] )
                    ),
                ] ),
            ], 'secondary' ),

            band( [
                heading( 'Questions about memberships' ),
                widget( 'eael-adv-accordion', [
                    'eael_adv_accordion_type' => 'accordion',
                    'eael_adv_accordion_tab'  => [
                        $faq( 'fq1', 'Can I pause my membership?', 'Yes. Pause for up to two months a year at no charge.' ),
                        $faq( 'fq2', 'Do you have a minimum term?', 'Day Pass and Flex Desk have none. Private offices start at three months.' ),
                        $faq( 'fq3', 'Can I bring a guest?', 'Members can bring one guest a day for free.' ),
                        $faq( 'fq4', 'Is there parking?', 'There is no car park, but there is covered bike storage and a train station five minutes away.' ),
                    ],
                ] ),
            ] ),
        ],
        'survive'       => [
            'Pick the membership that fits', 'No joining fees',
            'Day Pass', 'Flex Desk', 'Private Office',
            'Any hot desk, 8am to 6pm', '24/7 access', 'Business address',
            'Buy a day pass', 'Join Flex Desk', 'Ask about offices',
            'Compare the plans', 'Meeting room hours', 'Pay as you go', '10 a month', 'Lockable storage', 'Your own office',
            'Questions about memberships', 'Can I pause my membership?', 'Pause for up to two months a year',
            'Do you have a minimum term?', 'Can I bring a guest?', 'Is there parking?',
        ],
        // The pricing table converts to currency . price.
        'survive_exact' => [ '$29', '$189', '$549' ],
    ];
};
