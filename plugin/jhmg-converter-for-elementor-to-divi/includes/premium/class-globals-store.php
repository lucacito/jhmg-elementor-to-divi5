<?php

namespace ElementorDivi5Converter\Premium;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GlobalsStore {

    private const OPTION_KEY = 'edc_kit_globals';

    public static function save( array $colors, array $typography, string $source_name, string $zip_path = '', array $pages = [] ): void {
        $existing = self::load();
        if ( $existing && ! empty( $existing['zip_path'] ) && $existing['zip_path'] !== $zip_path ) {
            self::safe_delete_file( $existing['zip_path'] );
        }
        update_option( self::OPTION_KEY, [
            'colors'      => $colors,
            'typography'  => $typography,
            'loaded_from' => $source_name,
            'loaded_at'   => time(),
            'zip_path'    => $zip_path,
            'pages'       => $pages,
        ], false );
    }

    public static function load(): ?array {
        $data = get_option( self::OPTION_KEY, null );
        return is_array( $data ) ? $data : null;
    }

    public static function clear(): void {
        $data = self::load();
        if ( $data && ! empty( $data['zip_path'] ) ) {
            self::safe_delete_file( $data['zip_path'] );
        }
        delete_option( self::OPTION_KEY );
    }

    private static function safe_delete_file( string $path ): void {
        if ( $path === '' || ! file_exists( $path ) ) {
            return;
        }
        $upload_basedir = wp_upload_dir()['basedir'];
        $real_target    = realpath( $path );
        $real_base      = realpath( $upload_basedir );
        if ( $real_target && $real_base && strncmp( $real_target, $real_base, strlen( $real_base ) ) === 0 ) {
            wp_delete_file( $path );
        }
    }

    public static function has_kit(): bool {
        return self::load() !== null;
    }

    public static function get_meta(): array {
        $data = self::load();
        if ( ! $data ) {
            return [];
        }
        return [
            'loaded_from' => $data['loaded_from'] ?? '',
            'loaded_at'   => $data['loaded_at']   ?? 0,
        ];
    }
}
