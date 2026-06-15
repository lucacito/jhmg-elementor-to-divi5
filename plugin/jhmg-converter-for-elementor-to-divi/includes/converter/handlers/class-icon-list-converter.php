<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Icon List widget into a divi/text block containing
 * an HTML unordered list.
 *
 * Each item's icon class is mapped to the closest Unicode character when the
 * icon is a well-known FontAwesome name; all others use a bullet (•).
 * Full Divi icon-list support would require a native Divi module for this, but
 * divi/text with HTML preserves the content and is editable in the builder.
 */
class IconListConverter extends BaseElementorConverter {

    private const ICON_MAP = [
        'fa-check-circle'     => '✓',
        'fa-check'            => '✓',
        'fa-times-circle'     => '✗',
        'fa-times'            => '✗',
        'fa-arrow-right'      => '→',
        'fa-arrow-left'       => '←',
        'fa-star'             => '★',
        'fa-circle'           => '●',
        'fa-dot-circle'       => '◉',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['icon_list'] ?? [];

        $html = $this->buildList( $items );

        $style_result = ( new StyleMapper() )->map( 'text-editor', $settings );
        $attrs        = $style_result['divi_attrs'];

        $attrs['content'] = [
            'innerContent' => [
                'desktop' => [ 'value' => $html ],
            ],
        ];

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'icon_list',
                'divider', 'divider_style', 'divider_color', 'divider_weight',
                'icon_color', 'icon_size', 'icon_align',
                'space_between', 'icon_indent',
                'text_color', 'text_indent',
            ],
            $style_result['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function buildList( array $items ): string {
        if ( empty( $items ) ) {
            return '';
        }

        $li_parts = [];
        foreach ( $items as $item ) {
            $text = is_string( $item['text'] ?? '' ) ? trim( $item['text'] ?? '' ) : '';
            if ( $text === '' ) {
                continue;
            }
            $bullet   = $this->iconSymbol( $item['selected_icon'] ?? $item['icon'] ?? '' );
            $li_parts[] = '<li>' . htmlspecialchars( $bullet, ENT_QUOTES, 'UTF-8' ) . ' ' . htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) . '</li>';
        }

        if ( empty( $li_parts ) ) {
            return '';
        }

        return '<ul style="list-style:none;padding-left:0;">' . implode( '', $li_parts ) . '</ul>';
    }

    private function iconSymbol( mixed $raw ): string {
        $value = '';
        if ( is_array( $raw ) ) {
            $value = is_string( $raw['value'] ?? '' ) ? ( $raw['value'] ?? '' ) : '';
        } elseif ( is_string( $raw ) ) {
            $value = $raw;
        }

        foreach ( self::ICON_MAP as $fa_class => $symbol ) {
            if ( str_contains( $value, $fa_class ) ) {
                return $symbol;
            }
        }

        return '•';
    }
}
