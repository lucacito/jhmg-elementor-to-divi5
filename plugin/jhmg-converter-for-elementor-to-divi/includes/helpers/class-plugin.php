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
            // Deliberately not gated on DiviRequirement here: this hook runs at
            // plugins_loaded, before the theme is loaded, so Divi's version is
            // not readable yet. Each screen and handler consults the requirement
            // when it actually runs, on an admin hook, by which time it is.
            add_action( 'admin_notices', [ \ElementorDivi5Converter\Helpers\DiviRequirement::class, 'render_notice' ] );

            ( new \ElementorDivi5Converter\Admin\AdminPage() )->init();
            ( new \ElementorDivi5Converter\Admin\DirectConversionPage() )->init();
            ( new \ElementorDivi5Converter\Admin\PriceDropNotice() )->init();
            ( new \ElementorDivi5Converter\Admin\ReviewPrompt() )->init();
            ( new \ElementorDivi5Converter\History\ImportRollback() )->init();
            ( new \ElementorDivi5Converter\Telemetry\CoverageTelemetry() )->init();
        }

        // Source (b) for global colours and typography: the Elementor kit
        // installed on this site. Registered as the gap-filling half of
        // `edc_kit_globals` — it only supplies IDs no other provider knows, so
        // Pro's uploaded kit always wins on a shared ID regardless of which
        // callback runs first. Without a provider here, an unresolved global has
        // nowhere left to look and is reported rather than invented.
        add_filter( 'edc_kit_globals', [ $this, 'fill_kit_globals_from_installed_kit' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );

        // Extension point for the Pro add-on (and future companions).
        do_action( 'edc_loaded', $this );
    }

    /**
     * Adds the installed Elementor kit's globals underneath whatever a
     * higher-authority provider (Pro's uploaded kit) already supplied.
     *
     * Union semantics — `+` keeps the left operand's keys — so an ID both kits
     * define keeps the incoming value. Pro's own callback overrides in the same
     * key-wise way, which is what makes the pair order-independent: neither
     * plugin has to care whether it hooked first.
     *
     * @param mixed $kit Value from earlier `edc_kit_globals` providers.
     */
    public function fill_kit_globals_from_installed_kit( $kit ) {
        $installed = \ElementorDivi5Converter\Conversion\ConversionPreflight::installedKitGlobals();

        if ( empty( $installed['colors'] ) && empty( $installed['typography'] ) && empty( $installed['buttons'] ) ) {
            return $kit;
        }

        if ( ! is_array( $kit ) ) {
            return $installed;
        }

        return array_merge( $kit, [
            'colors'     => ( $kit['colors'] ?? [] ) + $installed['colors'],
            'typography' => ( $kit['typography'] ?? [] ) + $installed['typography'],
            'buttons'    => ( $kit['buttons'] ?? [] ) + $installed['buttons'],
        ] );
    }

    public function enqueue_frontend_styles(): void {
        if ( ! is_singular() ) {
            return;
        }
        $post_id = get_the_ID();
        if ( ! $post_id || get_post_meta( $post_id, '_et_pb_use_builder', true ) !== 'on' ) {
            return;
        }
        wp_enqueue_style(
            'edc-frontend',
            EDC_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            EDC_PLUGIN_VERSION
        );
    }
}
