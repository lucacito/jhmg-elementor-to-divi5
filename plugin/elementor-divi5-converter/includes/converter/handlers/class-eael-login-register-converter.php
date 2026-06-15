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

        $show_login    = ( $settings['eael_show_login_content'] ?? 'yes' ) === 'yes';
        $show_register = ( $settings['eael_show_register_content'] ?? 'yes' ) === 'yes';

        if ( $show_register ) {
            $shortcode = '[woocommerce_my_account]';
        } elseif ( $show_login ) {
            $shortcode = '[woocommerce_my_account]';
        } else {
            $shortcode = '[woocommerce_my_account]';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
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
