<?php

namespace ElementorDivi5Converter\Exporters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Serializes internal Divi structure to Divi 5 WordPress block format.
 *
 * Divi 5 stores page content as WordPress blocks (Gutenberg comment delimiters) with
 * JSON attributes using the `{"desktop":{"value":...}}` responsive-attribute pattern.
 * Each leaf module is a self-closing block; structural modules (section/row/column)
 * wrap their children with open/close comment pairs.
 */
class DiviBlockSerializer {
    public function serialize( array $divi_data ): string {
        $elements = $this->resolveElements( $divi_data );
        return $this->serializeElements( $elements );
    }

    private function resolveElements( array $divi_data ): array {
        if ( isset( $divi_data['divi']['elements'] ) && is_array( $divi_data['divi']['elements'] ) ) {
            return $divi_data['divi']['elements'];
        }

        if ( isset( $divi_data['elements'] ) && is_array( $divi_data['elements'] ) ) {
            return $divi_data['elements'];
        }

        return $divi_data;
    }

    private function serializeElements( array $elements ): string {
        $output = '';

        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $output .= $this->serializeElement( $element );
        }

        return $output;
    }

    private function serializeElement( array $element ): string {
        $name     = $element['name'] ?? '';
        $children = $element['elements'] ?? [];

        switch ( $name ) {
            case 'divi/section':
                return $this->wrapBlock( 'divi/section', [], $this->serializeSectionChildren( $children ) );

            case 'divi/row':
                return $this->wrapBlock( 'divi/row', [], $this->serializeRowChildren( $children ) );

            case 'divi/column':
                return $this->wrapBlock( 'divi/column', [], $this->serializeElements( $children ) );

            case 'divi/text':
                return $this->selfClosingBlock( 'divi/text', $this->textAttrs( $element ) );

            case 'divi/button':
                return $this->selfClosingBlock( 'divi/button', $this->buttonAttrs( $element ) );

            case 'divi/image':
                return $this->selfClosingBlock( 'divi/image', $this->imageAttrs( $element ) );

            default:
                return $this->serializeElements( $children );
        }
    }

    // -------------------------------------------------------------------------
    // Structural child handling (mirror the old serializer's auto-wrap logic)
    // -------------------------------------------------------------------------

    private function serializeSectionChildren( array $children ): string {
        $output = '';

        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/row' ) {
                $output .= $this->serializeElement( $child );
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/column' ) {
                $output .= $this->serializeElement( [
                    'name'     => 'divi/row',
                    'settings' => [],
                    'elements' => [ $child ],
                ] );
                continue;
            }

            $output .= $this->serializeElement( [
                'name'     => 'divi/row',
                'settings' => [],
                'elements' => [
                    [
                        'name'     => 'divi/column',
                        'settings' => [],
                        'elements' => [ $child ],
                    ],
                ],
            ] );
        }

        return $output;
    }

    private function serializeRowChildren( array $children ): string {
        $output = '';

        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/column' ) {
                $output .= $this->serializeElement( $child );
                continue;
            }

            $output .= $this->serializeElement( [
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => [ $child ],
            ] );
        }

        return $output;
    }

    // -------------------------------------------------------------------------
    // Module attribute builders
    // -------------------------------------------------------------------------

    private function textAttrs( array $element ): array {
        $settings = $element['settings'] ?? [];
        $raw      = $settings['innerContent'] ?? '';
        $tag      = $settings['tagName'] ?? '';

        $html = ( $tag !== '' && is_string( $tag ) )
            ? sprintf( '<%1$s>%2$s</%1$s>', $tag, $raw )
            : (string) $raw;

        return [
            'content' => [
                'innerContent' => [
                    'desktop' => [
                        'value' => $html,
                    ],
                ],
            ],
        ];
    }

    private function buttonAttrs( array $element ): array {
        $settings    = $element['settings'] ?? [];
        $text        = is_string( $settings['text'] ?? '' ) ? $settings['text'] : '';
        $link        = $settings['link'] ?? [];
        $url         = ( is_array( $link ) && ! empty( $link['url'] ) ) ? $link['url'] : '';
        $new_window  = ( is_array( $link ) && ! empty( $link['isExternal'] ) );
        $nofollow    = ( is_array( $link ) && ! empty( $link['nofollow'] ) );

        $inner = [];

        if ( $text !== '' ) {
            $inner['text'] = [ 'desktop' => [ 'value' => $text ] ];
        }

        if ( $url !== '' ) {
            $inner['linkUrl'] = [ 'desktop' => [ 'value' => $url ] ];
        }

        if ( $new_window ) {
            $inner['linkTarget'] = [ 'desktop' => [ 'value' => 'on' ] ];
        }

        if ( $nofollow ) {
            $inner['linkRel'] = [ 'desktop' => [ 'value' => 'nofollow' ] ];
        }

        return [ 'button' => [ 'innerContent' => $inner ] ];
    }

    private function imageAttrs( array $element ): array {
        $settings = $element['settings'] ?? [];
        $src      = is_string( $settings['src'] ?? '' ) ? $settings['src'] : '';

        $inner = [];

        if ( $src !== '' ) {
            $inner['src'] = [ 'desktop' => [ 'value' => $src ] ];
        }

        return [ 'image' => [ 'innerContent' => $inner ] ];
    }

    // -------------------------------------------------------------------------
    // Block serialization helpers
    // -------------------------------------------------------------------------

    /**
     * Wrap children in an open/close block comment pair.
     */
    private function wrapBlock( string $block_name, array $attrs, string $inner ): string {
        $json = $this->encodeAttrs( $attrs );

        return "<!-- wp:{$block_name} {$json} -->{$inner}<!-- /wp:{$block_name} -->";
    }

    /**
     * Produce a self-closing block comment.
     */
    private function selfClosingBlock( string $block_name, array $attrs ): string {
        $json = $this->encodeAttrs( $attrs );

        return "<!-- wp:{$block_name} {$json} /-->";
    }

    private function encodeAttrs( array $attrs ): string {
        if ( empty( $attrs ) ) {
            return '{}';
        }

        return wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }
}
