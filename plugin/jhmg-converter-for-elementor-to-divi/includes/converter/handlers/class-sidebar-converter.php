<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SidebarConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $sidebar_id = $settings['sidebar'] ?? '';
        if ( ! is_string( $sidebar_id ) ) {
            $sidebar_id = '';
        }

        $shortcode = $sidebar_id !== ''
            ? '[dynamic_sidebar id="' . esc_attr( $sidebar_id ) . '"]'
            : '[dynamic_sidebar]';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ 'sidebar' ] );

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
