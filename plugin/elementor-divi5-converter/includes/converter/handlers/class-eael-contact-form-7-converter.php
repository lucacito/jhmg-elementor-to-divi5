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

        // EAEL stores the CF7 form ID in `eael_contact_form_id`.
        $form_id = (int) ( $settings['eael_contact_form_id'] ?? 0 );

        $block_settings = [];
        if ( $form_id > 0 ) {
            $block_settings['module'] = [
                'advanced' => [
                    'formId' => [ 'desktop' => [ 'value' => $form_id ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'contact-form-7' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_contact_form_id', 'eael_contact_form_title',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/contact-form-7',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
