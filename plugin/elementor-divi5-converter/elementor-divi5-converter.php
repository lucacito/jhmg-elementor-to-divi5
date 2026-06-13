<?php
/**
 * Plugin Name: Elementor to Divi 5 Converter
 * Plugin URI:  https://github.com/jhmg/elementor-to-divi5
 * Description: Converts Elementor page and widget data into Divi 5 layout structure.
 * Version:     0.1.0
 * Author:      JHMG
 * License:     GPLv2 or later
 * Text Domain: elementor-divi5-converter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EDC_PLUGIN_FILE', __FILE__ );
define( 'EDC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

defined( 'EDC_PLUGIN_VERSION' ) || define( 'EDC_PLUGIN_VERSION', '0.1.0' );

require_once EDC_PLUGIN_DIR . 'includes/helpers/class-autoloader.php';

\ElementorDivi5Converter\Plugin::instance()->init();
