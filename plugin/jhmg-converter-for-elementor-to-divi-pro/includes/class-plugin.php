<?php

namespace ElementorDivi5Converter\Pro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        return self::$instance ??= new self();
    }

    public function init(): void {
        // Priority 20: after the free plugin's own plugins_loaded hook (10).
        add_action( 'plugins_loaded', [ $this, 'register_hooks' ], 20 );
    }

    public function register_hooks(): void {
        if ( ! class_exists( \ElementorDivi5Converter\Plugin::class ) ) {
            add_action( 'admin_notices', [ $this, 'render_missing_free_notice' ] );
            return;
        }

        add_filter( 'edc_pro_active', '__return_true' );
        // Feature wiring (kit globals, theme-builder exporter, admin pages,
        // licensing) is registered here by later tasks.
    }

    public function render_missing_free_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'JHMG Converter Pro requires the free "JHMG Converter For Elementor to Divi 5" plugin. Please install and activate it.', 'jhmg-converter-for-elementor-to-divi-pro' );
        echo '</p></div>';
    }
}
