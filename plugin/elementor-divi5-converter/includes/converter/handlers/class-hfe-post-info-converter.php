<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts HFE Post Info widget → divi/text.
 *
 * The widget renders a list of dynamic post meta items (date, author, category,
 * etc.). Divi 5 has no direct equivalent block, so we emit a divi/text noting
 * the conversion loss. The `icon_list` repeater structure is preserved as a
 * plain-text list when possible.
 */
class HfePostInfoConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];

        $items     = $settings['icon_list'] ?? [];
        $parts     = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $type   = $item['type'] ?? '';
            $prefix = is_string( $item['text_prefix'] ?? '' ) ? ( $item['text_prefix'] ?? '' ) : '';
            if ( $type !== '' ) {
                $parts[] = trim( $prefix . ' ' . $type );
            }
        }

        $text = ! empty( $parts ) ? implode( ' | ', $parts ) : '';

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'icon_list', 'view', 'separator',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $text ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
