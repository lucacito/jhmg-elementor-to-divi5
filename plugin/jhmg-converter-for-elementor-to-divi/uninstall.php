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
$edc_kit = get_option( 'edc_kit_globals' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( is_array( $edc_kit ) && ! empty( $edc_kit['zip_path'] ) ) {
    $edc_zip_path = $edc_kit['zip_path']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    if ( is_string( $edc_zip_path ) && file_exists( $edc_zip_path ) ) {
        $edc_upload_basedir = wp_upload_dir()['basedir']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $edc_real_target    = realpath( $edc_zip_path ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $edc_real_base      = realpath( $edc_upload_basedir ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        if ( $edc_real_target && $edc_real_base && strncmp( $edc_real_target, $edc_real_base, strlen( $edc_real_base ) ) === 0 ) {
            wp_delete_file( $edc_real_target );
        }
    }
}
delete_option( 'edc_kit_globals' );
