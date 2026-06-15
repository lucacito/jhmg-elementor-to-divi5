<?php

namespace ElementorDivi5Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void {
        // Initialize the plugin and register hooks.
        add_action( 'plugins_loaded', [ $this, 'register_hooks' ] );
    }

    public function register_hooks(): void {
        if ( is_admin() ) {
            ( new \ElementorDivi5Converter\Admin\AdminPage() )->init();
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );
    }

    public function enqueue_frontend_styles(): void {
        wp_enqueue_style(
            'edc-frontend',
            EDC_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            EDC_PLUGIN_VERSION
        );
    }
}
