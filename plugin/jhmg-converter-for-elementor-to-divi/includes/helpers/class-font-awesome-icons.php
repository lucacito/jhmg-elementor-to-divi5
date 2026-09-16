<?php

namespace ElementorDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor icons are FontAwesome classes ("fas fa-clock"); Divi 5 wants
 * {type, unicode, weight} (server IconModule.php:344-350: type "fa" selects the
 * FontAwesome font Divi ships). includes/data/fa-icons.php is generated from
 * Divi's own icon list by scripts/divi-module-schema.php.
 */
final class FontAwesomeIcons {
    private static ?array $icons = null;

    /** Divi's default icon (a solid star), for the cases nothing maps. */
    public const STAR = [ 'type' => 'fa', 'unicode' => '&#xf005;', 'weight' => '900' ];

    /** @param mixed $control An Elementor ICONS value {value, library} or a class string. */
    public static function fromControl( mixed $control ): ?array {
        if ( is_array( $control ) ) {
            if ( ( $control['library'] ?? '' ) === 'svg' || ! is_string( $control['value'] ?? null ) ) {
                return null;
            }
            return self::diviIcon( $control['value'] );
        }
        return is_string( $control ) ? self::diviIcon( $control ) : null;
    }

    public static function diviIcon( string $class ): ?array {
        if ( ! preg_match( '/(?:^|\s)fa-([a-z0-9-]+)/i', $class, $m ) ) {
            return null;
        }
        $entry = self::icons()[ strtolower( $m[1] ) ] ?? null;
        if ( $entry === null ) {
            return null;
        }

        $prefix = preg_match( '/^\s*(fab|fas|far|fal|fad|fa)\b/i', $class, $p ) ? strtolower( $p[1] ) : 'fas';
        $weight = match ( $prefix ) {
            'far', 'fal' => $entry['line'] ?? $entry['solid'] ?? '400',
            default      => $entry['solid'] ?? $entry['line'] ?? '400',
        };

        return [ 'type' => 'fa', 'unicode' => $entry['unicode'], 'weight' => (string) $weight ];
    }

    private static function icons(): array {
        if ( self::$icons === null ) {
            self::$icons = require dirname( __DIR__ ) . '/data/fa-icons.php';
        }
        return self::$icons;
    }
}
