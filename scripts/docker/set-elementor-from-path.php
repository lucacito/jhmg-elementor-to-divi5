<?php
/**
 * Attaches Elementor widget data to a WordPress page from an arbitrary file path.
 *
 * Usage (via WP-CLI eval-file):
 *   JSON_PATH=/tmp/input.json PAGE_ID=<id> wp eval-file /tmp/set-elementor-from-path.php --allow-root
 *
 * Unlike set-elementor-data.php this accepts any path inside the container,
 * so visual-diff.js can docker-cp an arbitrary JSON before calling this.
 */

$page_id   = (int) getenv( 'PAGE_ID' );
$json_path = getenv( 'JSON_PATH' );

if ( ! $page_id || ! $json_path ) {
    fwrite( STDERR, "PAGE_ID and JSON_PATH env vars must be set\n" );
    exit( 1 );
}

if ( ! file_exists( $json_path ) ) {
    fwrite( STDERR, "JSON file not found: {$json_path}\n" );
    exit( 1 );
}

$content = file_get_contents( $json_path );
if ( $content === false ) {
    fwrite( STDERR, "Failed to read: {$json_path}\n" );
    exit( 1 );
}

if ( ! is_array( json_decode( $content, true ) ) ) {
    fwrite( STDERR, "File does not contain a JSON array: {$json_path}\n" );
    exit( 1 );
}

update_post_meta( $page_id, '_elementor_data', $content );
echo "Set _elementor_data on page {$page_id}\n";
