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

        // Animation Addons' wcf--countdown widget (countdown.php:154) uses the same
        // Elementor DATE_TIME picker under its own key.
        $due_date_raw = $settings['due_date'] ?? $settings['countdown_timer_due_date'] ?? '';
        $due_date     = is_string( $due_date_raw ) ? $due_date_raw : '';

        // divi/countdown-timer reads content.advanced.dateTime
        // (countdown-timer/module.json, CountdownTimerModule.php:361) through
        // strtotime(); its own picker stores "Y-m-d H:i".
        // module.advanced.countdownDate is not a declared module attribute at all
        // (fixtures/divi-schema/modules.json) and was never read: the timer sat at
        // zero. EaelCountdownConverter already gets this right; this class did not.
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
            'due_date', 'label_days', 'label_hours', 'label_minutes', 'label_seconds',
            'show_days', 'show_hours', 'show_minutes', 'show_seconds',
            // wcf--countdown's own labels/style controls — no Divi equivalent.
            'countdown_timer_due_date', 'countdown_style',
            'countdown_timer_days_label', 'countdown_timer_hours_label',
            'countdown_timer_minutes_label', 'countdown_timer_seconds_label',
            'countdown_timer_label_heading', 'show_separator', 'separator_content',
            'separator_size', 'separator_offset_popover_toggle', 'border_radius',
            'time_expire_title', 'time_expire_desc',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/countdown-timer',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
