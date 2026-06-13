<?php
$page_id = getenv( 'PAGE_ID' );
$fixture = getenv( 'FIXTURE_NAME' );

if ( false === $page_id || false === $fixture ) {
    fwrite( STDERR, "PAGE_ID and FIXTURE_NAME must be set\n" );
    exit(1);
}

$fixture_file = ABSPATH . "fixtures/elementor/{$fixture}.json";
if ( ! file_exists( $fixture_file ) ) {
    fwrite( STDERR, "Fixture file not found: {$fixture_file}\n" );
    exit(1);
}

$content = file_get_contents( $fixture_file );
if ( false === $content ) {
    fwrite( STDERR, "Failed to read fixture file: {$fixture_file}\n" );
    exit(1);
}

if ( false === update_post_meta( (int) $page_id, '_elementor_data', $content ) ) {
    fwrite( STDERR, "Failed to update _elementor_data for post {$page_id}\n" );
    exit(1);
}

echo "Updated _elementor_data for post {$page_id} from {$fixture}.json\n";
