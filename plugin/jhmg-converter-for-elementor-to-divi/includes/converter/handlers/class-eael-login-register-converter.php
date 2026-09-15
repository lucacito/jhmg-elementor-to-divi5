<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelLoginRegisterConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        // Every form type EAEL offers (`default_form_type`: login, register,
        // lostpassword) becomes WooCommerce's account shortcode, which shows
        // login and registration together.
        $shortcode = '[woocommerce_my_account]';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'default_form_type', 'show_login_link', 'show_register_link', 'show_lost_password',
            'login_link_text', 'registration_link_text', 'hide_for_logged_in_user',
            'eael_show_login_content', 'eael_show_register_content',
            'eael_login_redirect_url', 'eael_registration_redirect_url',
            'eael_login_form_title', 'eael_register_form_title',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $shortcode ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
