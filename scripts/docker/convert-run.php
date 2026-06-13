<?php
$page_id = getenv( 'PAGE_ID' );
if ( false === $page_id ) {
    fwrite( STDERR, "PAGE_ID must be supplied as an environment variable.\n" );
    exit(1);
}

require_once ABSPATH . 'wp-content/plugins/elementor-divi5-converter/includes/helpers/class-autoloader.php';

$page_id = (int) $page_id;
$json = get_post_meta( $page_id, '_elementor_data', true );
$payload = json_decode( $json, true );

if ( ! is_array( $payload ) ) {
    fwrite( STDERR, "Invalid Elementor payload for post {$page_id}.\n" );
    exit(1);
}

$engine = new \ElementorDivi5Converter\Converter\ConverterEngine();
$converted = $engine->convert( $payload );

$exporter = new \ElementorDivi5Converter\Exporters\DiviExporter();
$exporter->save( $page_id, $converted );

echo "Converter run complete for post {$page_id}\n";
