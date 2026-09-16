<?php
/**
 * Build checks 3 and 6 for one converted document. Pure PHP: shared by the offline
 * harness and by demo/lib/check-conversions.php inside WordPress.
 */

namespace Ferncourt\Demo;

/**
 * @param array    $item          One ConversionPlan item.
 * @param string[] $survive       Each must appear inside some converted value.
 * @param string[] $survive_exact Each must equal some converted value.
 * @return string[] Empty when the document converted cleanly and kept its content.
 */
function conversion_problems( array $item, array $survive, array $survive_exact ): array {
    $problems = [];

    if ( ( $item['error'] ?? '' ) !== '' ) {
        $problems[] = 'error: ' . $item['error'];
    }
    foreach ( $item['unsupported'] ?? [] as $unsupported ) {
        $problems[] = 'unsupported: ' . ( is_string( $unsupported ) ? $unsupported : json_encode( $unsupported ) );
    }
    foreach ( $item['report']['warnings'] ?? [] as $warning ) {
        $problems[] = 'warning: ' . ( is_string( $warning ) ? $warning : json_encode( $warning ) );
    }

    // Check the block tree, not the serialized markup, which JSON-escapes characters.
    $values = leaf_values( $item['blocks'] ?? [] );
    $text   = implode( "\n", $values );

    foreach ( $survive as $needle ) {
        if ( strpos( $text, $needle ) === false ) {
            $problems[] = "lost text: {$needle}";
        }
    }
    foreach ( $survive_exact as $value ) {
        if ( ! in_array( $value, $values, true ) ) {
            $problems[] = "lost value: {$value}";
        }
    }

    return $problems;
}

/** @return string[] Every string and number in a block tree, as strings. */
function leaf_values( mixed $node ): array {
    if ( is_string( $node ) ) {
        return [ $node ];
    }
    if ( is_int( $node ) || is_float( $node ) ) {
        return [ (string) $node ];
    }
    if ( ! is_array( $node ) ) {
        return [];
    }

    $values = [];
    foreach ( $node as $child ) {
        array_push( $values, ...leaf_values( $child ) );
    }
    return $values;
}
