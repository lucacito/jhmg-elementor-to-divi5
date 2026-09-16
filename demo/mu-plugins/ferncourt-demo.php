<?php
/**
 * Plugin Name: Ferncourt demo helpers
 * Description: Loads Header Footer Elementor only while Hello Elementor is active. Under Divi, HFE's theme compatibility and Divi's Theme Builder both take over get_header, and every page dies with a fatal error in wp_head.
 */

defined( 'ABSPATH' ) || exit;

const FERNCOURT_DEMO_HFE = 'header-footer-elementor/header-footer-elementor.php';

/**
 * Leaves HFE out of the active plugins list on every request made while another theme is
 * active. Only the read is filtered: HFE stays active in the database and comes back with
 * Hello Elementor. The converted Divi site does not need HFE (docs/known-issues.md).
 */
function ferncourt_demo_active_plugins( $plugins ) {
    if ( ! is_array( $plugins ) || get_option( 'stylesheet' ) === 'hello-elementor' ) {
        return $plugins;
    }

    return array_values( array_diff( $plugins, [ FERNCOURT_DEMO_HFE ] ) );
}

add_filter( 'option_active_plugins', 'ferncourt_demo_active_plugins' );
