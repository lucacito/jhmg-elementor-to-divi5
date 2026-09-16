<?php

namespace ElementorDivi5Converter\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor headings whose HTML tag is span, p or div.
 *
 * divi/heading renders headingLevel as the tag (server ModuleElements.php:1009)
 * but styles only h1-h6 (heading/module.json, title.selector), so a span
 * heading lost its size, weight and colour. Divi's text module styles
 * whatever it wraps through content.decoration.bodyFont.body (text/module.json),
 * so the heading becomes a text module that keeps the original tag: the page's
 * heading outline is unchanged and the typography renders.
 */
final class TextHeading {
    public static function isHeadingTag( string $tag ): bool {
        return (bool) preg_match( '/^h[1-6]$/i', $tag );
    }

    /**
     * @param array $attrs A divi/heading settings array: `title.decoration.font`
     *                     (and textShadow) plus any `module.*` decoration.
     */
    public static function block( string $id, string $tag, string $text, array $attrs ): array {
        $tag = in_array( strtolower( $tag ), [ 'span', 'p', 'div' ], true ) ? strtolower( $tag ) : 'span';

        $font = $attrs['title']['decoration']['font'] ?? [];
        unset( $attrs['title'] );

        $body = [];
        foreach ( [ 'font', 'textShadow' ] as $group ) {
            if ( ! empty( $font[ $group ] ) ) {
                $body[ $group ] = $font[ $group ];
            }
        }
        // headingLevel is a heading-only key; the body font has no use for it.
        foreach ( $body['font'] ?? [] as $bp => $states ) {
            foreach ( $states as $state => $value ) {
                unset( $body['font'][ $bp ][ $state ]['headingLevel'] );
            }
        }

        $attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => "<{$tag}>{$text}</{$tag}>" ] ] ];
        if ( ! empty( $body ) ) {
            $attrs['content']['decoration'] = [ 'bodyFont' => [ 'body' => $body ] ];
        }

        return [ 'id' => $id, 'name' => 'divi/text', 'settings' => $attrs, 'elements' => [] ];
    }
}
