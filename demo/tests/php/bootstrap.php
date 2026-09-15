<?php
// Offline harness for the demo site: the repo's WordPress stubs and both converter
// plugins (tests/bootstrap.php), plus the demo library.

require __DIR__ . '/../../../tests/bootstrap.php';

// Called by converters this site uses. The repo bootstrap has no stub because no
// plugin test reaches these paths.
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text ) {
        return trim( strip_tags( (string) $text ) );
    }
}
if ( ! function_exists( 'do_shortcode' ) ) {
    function do_shortcode( $content ) {
        return (string) $content;
    }
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
    function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
        return false;
    }
}

require __DIR__ . '/../../lib/elementor.php';
require __DIR__ . '/../../lib/conversion-checks.php';
require __DIR__ . '/DocumentSource.php';
require __DIR__ . '/fixtures.php';
