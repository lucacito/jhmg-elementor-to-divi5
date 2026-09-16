<?php
/**
 * Seeds every document under demo/content/probes as a published page, for the
 * render check. Skips a probe that already exists. Run with Hello Elementor or Divi.
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/seed-probes.php
 */

namespace Ferncourt\Demo;

use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';
require_once __DIR__ . '/documents.php';

$ctx = site_context();
foreach ( glob( CONTENT_DIR . '/probes/*.php' ) as $file ) {
    $name = 'probes/' . basename( $file, '.php' );
    if ( document_post_id( $name ) !== 0 ) {
        WP_CLI::log( "{$name}: already seeded" );
        continue;
    }
    $post_id = insert_document( load_document( $file, $ctx ), $name );
    WP_CLI::log( "{$name}: page {$post_id}" );
}
WP_CLI::success( 'Probes seeded.' );
