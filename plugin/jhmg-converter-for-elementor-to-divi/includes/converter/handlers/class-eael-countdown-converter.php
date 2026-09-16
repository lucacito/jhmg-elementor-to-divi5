<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelCountdownConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_countdown_' );
        $settings = $element['settings'] ?? [];

        // EAEL stores the due date as a string like "2025-12-31 23:59".
        $due_date = is_string( $settings['eael_countdown_due_time'] ?? '' )
            ? ( $settings['eael_countdown_due_time'] ?? '' )
            : '';

        // divi/countdown-timer reads content.advanced.dateTime
        // (countdown-timer/module.json, CountdownTimerModule.php:361) through
        // strtotime(); its own picker stores "Y-m-d H:i". EAEL 6.6.7 Countdown.php
        // stores a DATE_TIME control the same way, so normalise rather than copy.
        // module.advanced.countdownDate was never read: the timer sat at zero.
        $block_settings = [];
        if ( $due_date !== '' ) {
            $timestamp = strtotime( $due_date );
            if ( $timestamp === false ) {
                $this->engine->logWarning( "Countdown {$id}: could not read the due date '{$due_date}'; set it in the Divi module." );
            } else {
                $block_settings['content']['advanced']['dateTime']['desktop']['value'] = gmdate( 'Y-m-d H:i', $timestamp );
            }
        }

        $this->engine->logConverted( 'countdown-timer' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_countdown_due_time', 'eael_countdown_type',
            'eael_evergreen_counter_hours', 'eael_evergreen_counter_minutes',
            'eael_countdown_days', 'eael_countdown_hours',
            'eael_countdown_minutes', 'eael_countdown_seconds',
            'eael_section_countdown_layout',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/countdown-timer',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
