<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Content Ticker → divi/icon-list.
 *
 * The ticker heading is prepended as a non-linked list item; each ticker
 * item becomes a list entry. Animation is discarded — Divi has no ticker.
 */
class EaelContentTickerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        // EAEL 6.x: the label is `eael_ticker_tag_text`. Lite only offers the
        // 'dynamic' ticker (the default), which renders the latest posts live and
        // stores no items. Custom items (`eael_ticker_custom_contents`) are an
        // EAEL Pro feature; their per-item names below are unverified.
        $type     = $settings['eael_ticker_type'] ?? 'dynamic';
        $heading  = $settings['eael_ticker_tag_text'] ?? $settings['eael_ticker_heading'] ?? '';
        $heading  = is_string( $heading ) ? $heading : '';
        $items    = $settings['eael_ticker_custom_contents'] ?? $settings['eael_ticker_items'] ?? [];
        $items    = is_array( $items ) ? $items : [];
        $children = [];

        if ( $heading !== '' ) {
            $children[] = [
                'id'       => $id . '-heading',
                'name'     => 'divi/icon-list-item',
                'settings' => [
                    'module' => [ 'advanced' => [ 'text' => [ 'desktop' => [ 'value' => '<strong>' . $heading . '</strong>' ] ] ] ],
                ],
                'elements' => [],
            ];
        }

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title = is_string( $item['eael_ct_title'] ?? '' ) ? ( $item['eael_ct_title'] ?? '' ) : '';

            $link_raw = $item['eael_ct_link'] ?? [];
            $link_url = is_array( $link_raw ) ? ( is_string( $link_raw['url'] ?? '' ) ? ( $link_raw['url'] ?? '' ) : '' ) : '';

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['module'] = [
                    'advanced' => [ 'text' => [ 'desktop' => [ 'value' => $title ] ] ],
                ];
            }
            if ( $link_url !== '' ) {
                $child_attrs['link'] = [
                    'innerContent' => [ 'desktop' => [ 'value' => [ 'url' => $link_url ] ] ],
                ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/icon-list-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        if ( empty( $items ) && $type === 'dynamic' ) {
            $this->engine->logWarning( "Content ticker {$id} shows your latest posts live; the post feed was not carried over, only its label." );
        }

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_ticker_tag_text', 'eael_ticker_custom_contents',
            'eael_ticker_heading', 'eael_ticker_items', 'eael_ticker_type',
            'eael_ticker_navigation', 'eael_ticker_autoplay',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
