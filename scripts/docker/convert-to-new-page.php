<?php
/**
 * Creates a new Divi 5 page from an existing Elementor page.
 *
 * Usage (via WP-CLI eval-file):
 *   SOURCE_PAGE_ID=<id> wp eval-file /tmp/convert-to-new-page.php --allow-root
 *
 * Outputs the new page ID on stdout on success.
 */

$source_id = (int) getenv( 'SOURCE_PAGE_ID' );

if ( ! $source_id ) {
    fwrite( STDERR, "SOURCE_PAGE_ID must be set and non-zero\n" );
    exit( 1 );
}

require_once ABSPATH . 'wp-content/plugins/elementor-divi5-converter/includes/helpers/class-autoloader.php';

$source = get_post( $source_id );

if ( ! $source ) {
    fwrite( STDERR, "Source page {$source_id} not found\n" );
    exit( 1 );
}

$json = get_post_meta( $source_id, '_elementor_data', true );
$payload = json_decode( $json, true );

if ( ! is_array( $payload ) ) {
    fwrite( STDERR, "No valid Elementor data on post {$source_id}\n" );
    exit( 1 );
}

$new_id = wp_insert_post( [
    'post_type'   => 'page',
    'post_status' => 'publish',
    'post_title'  => 'Converted: ' . $source->post_title,
] );

if ( is_wp_error( $new_id ) ) {
    fwrite( STDERR, 'Failed to create page: ' . $new_id->get_error_message() . "\n" );
    exit( 1 );
}

$engine    = new \ElementorDivi5Converter\Converter\ConverterEngine();
$converted = $engine->convert( $payload );

$exporter = new \ElementorDivi5Converter\Exporters\DiviExporter();
$exporter->save( $new_id, $converted );

echo $new_id;
