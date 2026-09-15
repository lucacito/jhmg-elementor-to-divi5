<?php
/**
 * Plugin Name: Ferncourt demo helpers
 * Description: Keeps Header Footer Elementor's header and footer to the Elementor site, so switching to Divi on camera shows only Divi's Theme Builder layouts.
 */

defined( 'ABSPATH' ) || exit;

/**
 * HFE 2.8.8 skips rendering only on a strict `false` from these filters.
 */
function ferncourt_demo_hfe_only_with_hello( $enabled ) {
    return get_stylesheet() === 'hello-elementor' ? $enabled : false;
}

add_filter( 'enable_hfe_render_header', 'ferncourt_demo_hfe_only_with_hello' );
add_filter( 'enable_hfe_render_footer', 'ferncourt_demo_hfe_only_with_hello' );
