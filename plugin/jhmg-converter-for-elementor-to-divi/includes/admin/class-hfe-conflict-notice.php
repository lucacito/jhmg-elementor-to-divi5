<?php

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Header Footer Elementor active + Divi active + a Divi Theme Builder header or
 * footer = every front-end page fatals: HFE's unsupported-theme layer hooks
 * get_header and calls remove_all_actions('wp_head') inside the window where
 * Divi's et_theme_builder_frontend_override_partial() has taken $wp_filter['wp_head']
 * out, so Divi restores an empty array and do_action() fails. Converter users
 * hit this the moment they import their first header with Pro.
 */
class HfeConflictNotice {
    const HFE_PLUGIN = 'header-footer-elementor/header-footer-elementor.php';

    public function init(): void {
        add_action( 'admin_notices', [ $this, 'render' ] );
    }

    public function applies(): bool {
        $active = (array) get_option( 'active_plugins', [] );
        if ( ! in_array( self::HFE_PLUGIN, $active, true ) ) {
            return false;
        }
        if ( get_template() !== 'Divi' ) {
            return false;
        }
        $layouts = get_posts( [
            'post_type'      => [ 'et_header_layout', 'et_footer_layout' ],
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        return ! empty( $layouts );
    }

    public function message(): string {
        return __( 'Deactivate Header Footer Elementor: with Divi active and a Divi Theme Builder header or footer in place, every page fails to load (HFE removes the wp_head actions while Divi is rendering its header). Your converted header and footer live in Divi → Theme Builder now.', 'jhmg-converter-for-elementor-to-divi' );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) || ! $this->applies() ) {
            return;
        }
        printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $this->message() ) );
    }
}
