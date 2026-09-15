<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Tooltip → divi/text.
 *
 * Divi 5 has no tooltip module. The trigger text/icon is rendered as the
 * visible element and the tooltip content is appended in parentheses so no
 * information is lost.
 */
class EaelTooltipConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];

        $legacy_trigger = $settings['eael_tooltip_trigger_text'] ?? '';

        if ( is_string( $legacy_trigger ) && $legacy_trigger !== '' ) {
            // Older exports: trigger text plus the tooltip in `eael_tooltip_content`.
            $trigger_text = $legacy_trigger;
            $tooltip_text = $settings['eael_tooltip_content'] ?? '';
        } else {
            // EAEL 6.x: `eael_tooltip_content` is the visible text and
            // `eael_tooltip_hover_content` the tooltip. Icon and image triggers
            // have no text to carry over.
            $type         = $settings['eael_tooltip_type'] ?? 'text';
            $trigger_text = match ( $type ) {
                'shortcode'     => $settings['eael_tooltip_shortcode_content'] ?? '',
                'icon', 'image' => '',
                default         => $settings['eael_tooltip_content'] ?? '',
            };
            $tooltip_text = $settings['eael_tooltip_hover_content'] ?? '';
        }

        $trigger_text = is_string( $trigger_text ) ? $trigger_text : '';
        $tooltip_text = is_string( $tooltip_text ) ? $tooltip_text : '';

        $parts = array_filter( [ $trigger_text, $tooltip_text !== '' ? '(' . $tooltip_text . ')' : '' ] );
        $body  = implode( ' ', $parts );

        $block_settings = [];
        if ( $body !== '' ) {
            $block_settings['content'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $body ] ],
            ];
        }

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_tooltip_type', 'eael_tooltip_hover_content', 'eael_tooltip_shortcode_content',
            'eael_tooltip_trigger_text', 'eael_tooltip_content',
            'eael_tooltip_target_element_type', 'eael_tooltip_trigger_icon',
            'eael_tooltip_placement', 'eael_tooltip_animation',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
