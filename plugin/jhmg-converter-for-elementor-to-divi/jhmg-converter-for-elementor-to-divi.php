<?php
/**
 * Plugin Name: JHMG Converter For Elementor to Divi 5
 * Plugin URI:  https://jhmediagroup.com/plugin
 * Description: Converts Elementor page and widget data into Divi 5 layout structure.
 * Version:     3.0.2
 * Author:      Lucas Lopvet
 * Author URI:  https://jhmediagroup.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jhmg-converter-for-elementor-to-divi
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EDC_PLUGIN_FILE', __FILE__ );
define( 'EDC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

defined( 'EDC_PLUGIN_VERSION' ) || define( 'EDC_PLUGIN_VERSION', '3.0.2' );

require_once EDC_PLUGIN_DIR . 'includes/helpers/class-autoloader.php';

// Activation runs inside an admin request, so the theme is loaded and Divi's
// version is readable — unlike at plugins_loaded, where it is not yet.
register_activation_hook( __FILE__, [ \ElementorDivi5Converter\Helpers\DiviRequirement::class, 'on_activation' ] );

\ElementorDivi5Converter\Plugin::instance()->init();
