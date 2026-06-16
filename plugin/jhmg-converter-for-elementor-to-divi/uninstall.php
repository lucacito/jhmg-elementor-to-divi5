<?php
/**
 * Fires on plugin deletion (not deactivation).
 * Removes all plugin options and uploaded kit files.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove premium activation flag.
delete_option( 'edc_premium_active' );

// Remove stored kit globals and delete the kit ZIP if present.
$kit = get_option( 'edc_kit_globals' );
if ( is_array( $kit ) && ! empty( $kit['zip_path'] ) ) {
    $zip_path = $kit['zip_path'];
    if ( is_string( $zip_path ) && file_exists( $zip_path ) ) {
        $upload_basedir = wp_upload_dir()['basedir'];
        $real_target    = realpath( $zip_path );
        $real_base      = realpath( $upload_basedir );
        if ( $real_target && $real_base && strncmp( $real_target, $real_base, strlen( $real_base ) ) === 0 ) {
            unlink( $real_target );
        }
    }
}
delete_option( 'edc_kit_globals' );
