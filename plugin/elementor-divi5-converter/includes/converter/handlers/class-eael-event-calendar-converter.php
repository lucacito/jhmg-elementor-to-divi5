<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelEventCalendarConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $shortcode = '[tribe_events]';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_event_cal_calendar_id', 'eael_event_cal_view_type',
            'eael_event_cal_categories', 'eael_event_cal_per_page',
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
