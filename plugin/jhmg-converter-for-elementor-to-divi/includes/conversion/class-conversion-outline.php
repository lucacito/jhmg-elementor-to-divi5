<?php
/**
 * Builds the structural outline the preview screen draws.
 *
 * The preview deliberately shows structure rather than pixels: Divi 5 keys its
 * CSS generation to a post ID, so rendering detached block markup produces an
 * unstyled skeleton that reads as broken output. A faithful outline answers the
 * question users actually have — did my layout survive — without pretending to
 * be a render.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionOutline {

    private const STRUCTURAL = [
        'divi/section' => 'section',
        'divi/row'     => 'row',
        'divi/column'  => 'column',
    ];

    /**
     * @param array $blocks      Converted Divi blocks (the ['divi']['elements'] tree).
     * @param array $unsupported Unsupported element entries from the engine.
     * @return array Outline nodes.
     */
    public static function build( array $blocks, array $unsupported = [] ): array {
        $nodes = [];

        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $name = (string) ( $block['name'] ?? '' );
            if ( $name === '' ) {
                continue;
            }

            $nodes[] = [
                'type'        => self::STRUCTURAL[ $name ] ?? 'module',
                'name'        => $name,
                'label'       => self::label( $name ),
                'unsupported' => false,
                'children'    => self::build( $block['elements'] ?? [], $unsupported ),
            ];
        }

        return $nodes;
    }

    /** 'divi/call-to-action' → 'Call To Action'. */
    private static function label( string $name ): string {
        $bare = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

        return ucwords( str_replace( [ '-', '_' ], ' ', $bare ) );
    }
}
