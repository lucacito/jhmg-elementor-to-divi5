<?php
/**
 * Build check 4: commits every seeded page as a Divi draft with ConversionCommitter,
 * the same path the admin screens use, and records which draft came from which page.
 * Run with Divi active. verify.sh's reset check removes the drafts afterwards.
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/commit-conversions.php
 */

namespace Ferncourt\Demo;

use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';

if ( get_template() !== 'Divi' ) {
    WP_CLI::error( 'Activate Divi first: converted drafts are previewed with it.' );
}

$slugs    = [];
$post_ids = [];
foreach ( document_names() as $name ) {
    if ( str_starts_with( $name, 'pages/' ) ) {
        $slugs[]    = substr( $name, strlen( 'pages/' ) );
        $post_ids[] = document_post_id( $name );
    }
}

$plan    = ( new ConversionPreflight() )->runUnlimited( new InstalledPostSource( $post_ids ) );
$results = ( new ConversionCommitter() )->commit( $plan, [ 'post_status' => 'draft' ] );

$converted = [];
foreach ( $results as $index => $result ) {
    if ( ! $result['success'] ) {
        WP_CLI::error( "{$slugs[ $index ]}: {$result['error']}" );
    }
    $converted[] = [ 'slug' => $slugs[ $index ], 'source_id' => $post_ids[ $index ], 'draft_id' => $result['post_id'] ];
    WP_CLI::log( "{$slugs[ $index ]}: page {$post_ids[ $index ]} -> Divi draft {$result['post_id']}" );
}

file_put_contents( OUTPUT_DIR . '/converted.json', wp_json_encode( $converted, JSON_PRETTY_PRINT ) . "\n" );
WP_CLI::success( count( $converted ) . ' pages committed as Divi drafts.' );
