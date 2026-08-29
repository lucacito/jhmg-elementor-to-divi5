<?php
/**
 * Renders a conversion outline as nested lists.
 *
 * Returns a string rather than echoing, so it can be asserted in tests without
 * output buffering.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OutlineRenderer {

    public static function render( array $outline ): string {
        if ( empty( $outline ) ) {
            return '<p class="edc-outline-empty">'
                . esc_html__( 'Nothing to show — this page converted to no Divi modules.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        return self::render_list( $outline );
    }

    private static function render_list( array $nodes ): string {
        $html = '<ul class="edc-outline">';

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $type    = (string) ( $node['type'] ?? 'module' );
            $classes = 'edc-outline-node edc-outline-node--' . $type;

            if ( ! empty( $node['unsupported'] ) ) {
                $classes .= ' edc-outline-node--unsupported';
            }

            $html .= '<li class="' . esc_attr( $classes ) . '">';
            $html .= '<span class="edc-outline-label">' . esc_html( (string) ( $node['label'] ?? '' ) ) . '</span>';

            if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                $html .= self::render_list( $node['children'] );
            }

            $html .= '</li>';
        }

        return $html . '</ul>';
    }
}
