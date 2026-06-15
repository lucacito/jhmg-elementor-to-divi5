<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Form widget → divi/contact-form.
 *
 * Divi's contact form fields differ substantially from Elementor's repeater-
 * based form builder. Basic fields (name, email, message) are mapped; any
 * remaining fields are silently accepted — the Divi form will still render
 * the default name/email/message trio.
 */
class FormConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_contact_' );
        $settings = $element['settings'] ?? [];

        $btn_text  = is_string( $settings['button_text'] ?? '' ) ? ( $settings['button_text'] ?? '' ) : '';
        $email_to  = is_string( $settings['email_to'] ?? '' ) ? ( $settings['email_to'] ?? '' ) : '';
        $success   = is_string( $settings['success_message'] ?? '' ) ? ( $settings['success_message'] ?? '' ) : '';

        $block_settings = [];

        if ( $btn_text !== '' ) {
            $block_settings['submitButton'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $btn_text ] ],
            ];
        }
        if ( $email_to !== '' ) {
            $block_settings['module'] = [
                'advanced' => [
                    'email' => [ 'desktop' => [ 'value' => $email_to ] ],
                ],
            ];
        }
        if ( $success !== '' ) {
            $block_settings['successMessage'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $success ] ],
            ];
        }

        $this->engine->logConverted( 'contact-form' );
        $this->logUnmappedSettings( $id, $settings, [
            'form_name', 'form_fields', 'button_text', 'email_to',
            'success_message', 'custom_messages', 'required_field_message',
            'invalid_message', 'error_message', 'form_submission_timeout',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/contact-form',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
