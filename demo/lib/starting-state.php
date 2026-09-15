<?php
/**
 * The state every take starts from (spec, "Recording flow"). Used by build check 5.
 * Run: demo/wp --user=admin eval-file /demo/lib/starting-state.php
 */

namespace Ferncourt\Demo;

use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';

global $wpdb;

$problems = [];

if ( get_stylesheet() !== 'hello-elementor' ) {
    $problems[] = 'active theme is ' . get_stylesheet() . ', not hello-elementor (so Divi is not inactive)';
}

// ConversionCommitter stamps every post it creates with _edc_import_source.
$converted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_edc_import_source'" );
if ( $converted > 0 ) {
    $problems[] = "{$converted} converted post(s) exist";
}

foreach ( document_names() as $name ) {
    $post_id = document_post_id( $name );
    if ( $post_id === 0 || get_post_status( $post_id ) !== 'publish' ) {
        $problems[] = "{$name} is not published";
    }
}

foreach ( [ 'et_header_layout', 'et_footer_layout' ] as $type ) {
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
        $type
    ) );
    if ( $count === 0 ) {
        $problems[] = "no published {$type} (Divi Theme Builder)";
    }
}

if ( $problems ) {
    foreach ( $problems as $problem ) {
        WP_CLI::warning( $problem );
    }
    WP_CLI::error( 'The site is not in its starting state.' );
}

WP_CLI::success( 'Starting state: Hello Elementor active, every page and template published, Theme Builder header and footer present, no converted posts.' );
