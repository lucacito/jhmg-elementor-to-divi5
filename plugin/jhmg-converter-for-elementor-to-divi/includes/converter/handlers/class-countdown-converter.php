<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CountdownConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_countdown_' );
        $settings = $element['settings'] ?? [];

        $due_date = is_string( $settings['due_date'] ?? '' ) ? ( $settings['due_date'] ?? '' ) : '';

        $block_settings = [];
        if ( $due_date !== '' ) {
            $block_settings['module'] = [
                'advanced' => [
                    'countdownDate' => [ 'desktop' => [ 'value' => $due_date ] ],
                ],
            ];
        }

        $this->engine->logConverted( 'countdown-timer' );
        $this->logUnmappedSettings( $id, $settings, [
            'due_date', 'label_days', 'label_hours', 'label_minutes', 'label_seconds',
            'show_days', 'show_hours', 'show_minutes', 'show_seconds',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/countdown-timer',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
