<?php
/**
 * Build checks 3 and 6 inside WordPress: each seeded document's dry run has no error,
 * unsupported widget or warning, and keeps every string its file lists as must-survive.
 * ConversionPreflight writes nothing.
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/check-conversions.php
 */

namespace Ferncourt\Demo;

use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/conversion-checks.php';
require_once __DIR__ . '/site-context.php';

$ctx      = site_context();
$problems = 0;

foreach ( document_names() as $name ) {
    $post_id = document_post_id( $name );
    if ( $post_id === 0 ) {
        WP_CLI::warning( "{$name}: not seeded" );
        $problems++;
        continue;
    }

    $doc  = load_document( CONTENT_DIR . "/{$name}.php", $ctx );
    $item = ( new ConversionPreflight() )->runUnlimited( new InstalledPostSource( [ $post_id ] ) )->items()[0];

    $found = conversion_problems( $item, $doc['survive'], $doc['survive_exact'] );
    foreach ( $found as $problem ) {
        WP_CLI::warning( "{$name}: {$problem}" );
    }
    if ( ! $found ) {
        WP_CLI::log( "ok  {$name}" );
    }
    $problems += count( $found );
}

if ( $problems > 0 ) {
    WP_CLI::error( "{$problems} conversion problem(s). The site is not ready to record." );
}

WP_CLI::success( count( document_names() ) . ' documents convert cleanly and keep their content.' );
