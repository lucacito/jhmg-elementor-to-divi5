<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelContactForm7Converter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_cf7_' );
        $settings = $element['settings'] ?? [];

        // EAEL 6.x stores the CF7 form's post ID in `contact_form_list`;
        // `eael_contact_form_id` is kept for older exports.
        $form_id = (int) ( $settings['contact_form_list'] ?? $settings['eael_contact_form_id'] ?? 0 );

        $block_settings = [];
        if ( $form_id > 0 ) {
            $block_settings[// contact-form-7/module.json declares the form ID on the `form` attribute; the
            // renderer reads form.advanced.formId (ContactForm7Module.php:378).
            'form'] = [
                'advanced' => [
                    'formId' => [ 'desktop' => [ 'value' => $form_id ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'contact-form-7' );
        $this->logUnmappedSettings( $id, $settings, [
            'contact_form_list', 'eael_contact_form_id', 'eael_contact_form_title',
            'form_title', 'form_title_text', 'form_description', 'form_description_text',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/contact-form-7',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
