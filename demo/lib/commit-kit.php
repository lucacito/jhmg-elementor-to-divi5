<?php
/**
 * Commits imported kit pages (any page IDs) as Divi drafts and records them in
 * demo/output/kit-converted.json for demo/tools/kit-shots.cjs. Run with Divi active.
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/commit-kit.php <page-id> [<page-id> ...]
 */

namespace Ferncourt\Demo;

use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use WP_CLI;

require_once __DIR__ . '/site-context.php';

if ( get_template() !== 'Divi' ) {
    WP_CLI::error( 'Activate Divi first: converted drafts are previewed with it.' );
}

$post_ids = array_values( array_filter( array_map( 'intval', $args ?? [] ) ) );
if ( $post_ids === [] ) {
    WP_CLI::error( 'Pass the page IDs to convert.' );
}

$plan    = ( new ConversionPreflight() )->runUnlimited( new InstalledPostSource( $post_ids ) );
$results = ( new ConversionCommitter() )->commit( $plan, [ 'post_status' => 'draft' ] );

$converted = [];
foreach ( $results as $index => $result ) {
    $post_id = $post_ids[ $index ];
    if ( ! $result['success'] ) {
        WP_CLI::error( "page {$post_id}: {$result['error']}" );
    }
    $slug        = (string) get_post_field( 'post_name', $post_id );
    $converted[] = [ 'slug' => $slug, 'source_id' => $post_id, 'draft_id' => $result['post_id'] ];
    $report      = $plan->items()[ $index ]['report'] ?? [];
    WP_CLI::log( sprintf( '%s: page %d -> Divi draft %d (%d warnings, %d unsupported)', $slug, $post_id, $result['post_id'], count( $report['warnings'] ?? [] ), count( $plan->items()[ $index ]['unsupported'] ?? [] ) ) );
}

file_put_contents( OUTPUT_DIR . '/kit-converted.json', wp_json_encode( $converted, JSON_PRETTY_PRINT ) . "\n" );
WP_CLI::success( count( $converted ) . ' kit pages committed as Divi drafts.' );
