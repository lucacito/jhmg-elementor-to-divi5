<?php
/**
 * Creates the Divi Theme Builder global header and footer from the seeded Header
 * Footer Elementor templates. Run with Divi active: the exporter calls Divi's own
 * Theme Builder functions.
 *
 * Uses DiviThemeBuilderExporter directly so it can name the layouts; since Pro
 * 1.2.1 ConversionCommitter would route these elementor-hf posts the same way.
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

WP_CLI::success( 'Divi Theme Builder header and footer created.' );
