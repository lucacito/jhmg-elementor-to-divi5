<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContainerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $row_id = isset( $element['id'] ) ? $element['id'] . '-row' : uniqid( 'divi_row_' );
        $column_id = isset( $element['id'] ) ? $element['id'] . '-col' : uniqid( 'divi_column_' );

        $row = [
            'id' => $row_id,
            'name' => 'divi/row',
            'settings' => [
                'module' => $this->normalizeSettings( $element['settings'] ?? [] ),
            ],
            'elements' => [
                [
                    'id' => $column_id,
                    'name' => 'divi/column',
                    'settings' => [
                        'module' => [],
                    ],
                    'elements' => $this->convertChildren( $element ),
                ],
            ],
        ];

        return [
            'id' => $element['id'] ?? uniqid( 'divi_section_' ),
            'name' => 'divi/section',
            'settings' => [
                'module' => $this->normalizeSettings( $element['settings'] ?? [] ),
            ],
            'elements' => [ $row ],
        ];
    }
}
