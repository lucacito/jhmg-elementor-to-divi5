<?php
/**
 * Plugin Name:       JHMG Converter For Elementor to Divi 5 — Pro
 * Description:       Pro add-on: full Elementor kit ZIP import, global headers & footers to the Divi Theme Builder, and global styles.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Requires Plugins:  jhmg-converter-for-elementor-to-divi
 * Author:            Lucas Lopvet
 * License:           GPLv2 or later
 * Text Domain:       jhmg-converter-for-elementor-to-divi-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'EDCP_PLUGIN_FILE', __FILE__ );
define( 'EDCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EDCP_PLUGIN_VERSION', '1.0.0' );
define( 'EDCP_PRODUCT_SLUG', 'elementor-to-divi5-pro' );
// Overridable for local/dev license servers: define EDCP_API_BASE in wp-config.php.
defined( 'EDCP_API_BASE' ) || define( 'EDCP_API_BASE', 'https://divi5lab.com' );

require_once EDCP_PLUGIN_DIR . 'includes/class-autoloader.php';

\ElementorDivi5Converter\Pro\Plugin::instance()->init();
