<?php
/**
 * Creates the Divi Theme Builder global header and footer from the seeded Header
 * Footer Elementor templates. Run with Divi active: the exporter calls Divi's own
 * Theme Builder functions.
 *
 * Uses DiviThemeBuilderExporter directly, not ConversionCommitter: the committer
 * only sends elementor_library templates to the Theme Builder, and HFE templates
 * are elementor-hf posts (docs/known-issues.md).
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/theme-builder.php
 */

namespace Ferncourt\Demo;

use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use ElementorDivi5Converter\Pro\Exporters\DiviThemeBuilderExporter;
use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';

if ( get_template() !== 'Divi' ) {
    WP_CLI::error( 'Activate Divi first: the Theme Builder exporter needs it.' );
}

$exporter = new DiviThemeBuilderExporter();
$results  = [];

foreach ( [ 'header' => 'Ferncourt Header', 'footer' => 'Ferncourt Footer' ] as $slot => $title ) {
    $post_id = document_post_id( "templates/{$slot}" );
    if ( $post_id === 0 ) {
        WP_CLI::error( "No seeded {$slot} template. Run demo/build.sh." );
    }

    $item = ( new ConversionPreflight() )->runUnlimited( new InstalledPostSource( [ $post_id ] ) )->items()[0];
    if ( $item['error'] !== '' ) {
        WP_CLI::error( "{$slot}: {$item['error']}" );
    }

    $divi_data = [ 'divi' => $item['blocks'], 'report' => $item['report'], 'unsupported' => $item['unsupported'] ];
    $result    = $slot === 'header'
        ? $exporter->saveHeader( $title, $divi_data, $item['source_ref'] )
        : $exporter->saveFooter( $title, $divi_data, $item['source_ref'] );

    if ( ! $result['success'] ) {
        WP_CLI::error( "{$slot}: {$result['error']}" );
    }

    $results[ $slot ] = $result;
    WP_CLI::log( "{$title}: layout {$result['post_id']}, template {$result['template_id']}, theme builder {$result['theme_builder_id']}" );
}

// Pro 1.2.0 saves the header and the footer as two separate default templates, and leaves
// every area it does not set with no layout and not enabled, which Divi reads as "hide this
// area" — including the page body (docs/known-issues.md). Divi applies one template per page,
// so put both layouts on the header's template, keep the body enabled, and drop the other.
$template_id        = (int) $results['header']['template_id'];
$footer_template_id = (int) $results['footer']['template_id'];
$theme_builder_id   = (int) $results['header']['theme_builder_id'];

update_post_meta( $template_id, '_et_body_layout_id', 0 );
update_post_meta( $template_id, '_et_body_layout_enabled', '1' );
update_post_meta( $template_id, '_et_footer_layout_id', (int) $results['footer']['post_id'] );
update_post_meta( $template_id, '_et_footer_layout_enabled', '1' );

if ( $footer_template_id > 0 && $footer_template_id !== $template_id ) {
    delete_post_meta( $theme_builder_id, '_et_template', (string) $footer_template_id );
    wp_delete_post( $footer_template_id, true );
    WP_CLI::log( "Merged the footer into template {$template_id} and removed template {$footer_template_id}." );
}

WP_CLI::success( 'Divi Theme Builder header and footer created.' );
