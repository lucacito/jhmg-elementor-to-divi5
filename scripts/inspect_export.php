<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'file://' . dirname( $file ) . '/';
	}
}

require_once __DIR__ . '/../vendor/autoload.php';

// Point plugin constants so the plugin autoloader can resolve files without booting plugin.
if ( ! defined( 'EDC_PLUGIN_DIR' ) ) {
	define( 'EDC_PLUGIN_DIR', __DIR__ . '/../plugin/elementor-divi5-converter/' );
}

// Load plugin autoloader (this file registers PSR-like autoloading for the plugin namespace).
require_once EDC_PLUGIN_DIR . 'includes/helpers/class-autoloader.php';

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

$fixture = __DIR__ . '/../fixtures/elementor/simple-container.json';
$payload = json_decode( file_get_contents( $fixture ), true );
$engine = new ConverterEngine();
$converted = $engine->convert( $payload );
$exporter = new DiviExporter();
$meta = $exporter->export( $converted );

echo json_encode( [ 'meta_keys' => array_keys( $meta ), 'meta' => $meta, 'sample_converted' => $converted ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
