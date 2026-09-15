<?php

namespace ElementorDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {
    public static function register(): void {
        spl_autoload_register( [ self::class, 'autoload' ] );
    }

    public static function autoload( string $class ): void {
        $prefix = 'ElementorDivi5Converter\\';

        if ( str_starts_with( $class, $prefix ) ) {
            $relative_class = ltrim( substr( $class, strlen( $prefix ) ), '\\' );
            $parts = explode( '\\', $relative_class );
            $class_name = array_pop( $parts );
            $directory = EDC_PLUGIN_DIR . 'includes/';

            if ( ! empty( $parts ) ) {
                $directory .= implode( '/', array_map( 'strtolower', $parts ) ) . '/';
            }

            // Split before capitals and around digits, so EaelContactForm7Converter
            // maps to class-eael-contact-form-7-converter.php. The old rule ignored
            // digits, and that class could never be loaded.
            $file_name = 'class-' . strtolower( preg_replace( [ '/([a-z])([A-Z0-9])/', '/([0-9])([A-Za-z])/' ], '$1-$2', $class_name ) ) . '.php';
            $path = $directory . $file_name;

            if ( ! file_exists( $path ) && empty( $parts ) ) {
                $path = EDC_PLUGIN_DIR . 'includes/helpers/' . $file_name;
            }

            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }
}

Autoloader::register();
