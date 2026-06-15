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
        $inner    = $this->serializeElements( $elements );
        return "<!-- wp:divi/placeholder -->{$inner}<!-- /wp:divi/placeholder -->";
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
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        switch ( $name ) {
            case 'divi/section':
                return $this->wrapBlock( 'divi/section', $settings, $this->serializeSectionChildren( $children ) );

            case 'divi/row':
                return $this->wrapBlock( 'divi/row', $settings, $this->serializeRowChildren( $children ) );

            case 'divi/column':
                return $this->wrapBlock( 'divi/column', $settings, $this->serializeElements( $children ) );

            default:
                // Any module with children wraps them; leaf modules self-close.
                if ( ! empty( $children ) ) {
                    return $this->wrapBlock( $name, $settings, $this->serializeElements( $children ) );
                }
                return $this->selfClosingBlock( $name, $settings );
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
    // Block serialization helpers
    // -------------------------------------------------------------------------

    /**
     * Wrap children in an open/close block comment pair.
     */
    private function wrapBlock( string $block_name, array $attrs, string $inner ): string {
        $json = $this->encodeAttrs( $this->withBuilderVersion( $attrs ) );

        return "<!-- wp:{$block_name} {$json} -->{$inner}<!-- /wp:{$block_name} -->";
    }

    /**
     * Produce a self-closing block comment.
     */
    private function selfClosingBlock( string $block_name, array $attrs ): string {
        $json = $this->encodeAttrs( $this->withBuilderVersion( $attrs ) );

        return "<!-- wp:{$block_name} {$json} /-->";
    }

    private function encodeAttrs( array $attrs ): string {
        if ( empty( $attrs ) ) {
            return '{}';
        }

        return wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Injects builderVersion into block attrs so Divi's FlexboxMigration
     * (hooked on the_content) skips our blocks and does not overwrite
     * display:'grid' with display:'block'.
     */
    private function withBuilderVersion( array $attrs ): array {
        $version = defined( 'ET_BUILDER_VERSION' ) ? ET_BUILDER_VERSION : '5.0.0-public-alpha.18.2';
        return array_merge( [ 'builderVersion' => $version ], $attrs );
    }
}
