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

        $trigger_text  = is_string( $settings['eael_tooltip_trigger_text'] ?? '' ) ? ( $settings['eael_tooltip_trigger_text'] ?? '' ) : '';
        $tooltip_text  = is_string( $settings['eael_tooltip_content'] ?? '' ) ? ( $settings['eael_tooltip_content'] ?? '' ) : '';

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
