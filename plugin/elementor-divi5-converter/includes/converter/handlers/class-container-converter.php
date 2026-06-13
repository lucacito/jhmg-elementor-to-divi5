<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContainerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_section_' );
        $settings = $element['settings'] ?? [];
        $children = $this->convertChildren( $element );

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings );

        // If every converted child is already a divi/column, place them directly in
        // the row. Otherwise wrap everything in one auto-generated column so that
        // widgets are never siblings of columns.
        $all_columns = ! empty( $children ) && array_reduce(
            $children,
            static fn( bool $carry, array $child ) => $carry && ( $child['name'] ?? '' ) === 'divi/column',
            true
        );

        $row_elements = $all_columns ? $children : [
            [
                'id'       => $id . '-col',
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => $children,
            ],
        ];

        $row_settings = $this->rowSettingsFromColumns( $row_elements );

        // A container nested inside another element (e.g. section→column→container)
        // must become a row, not a section — Divi does not allow sections inside columns.
        if ( $this->engine->getNestingDepth() > 1 ) {
            return [
                'id'       => $id,
                'name'     => 'divi/row',
                'settings' => $row_settings,
                'elements' => $row_elements,
            ];
        }

        return [
            'id'       => $id,
            'name'     => 'divi/section',
            'settings' => [],
            'elements' => [
                [
                    'id'       => $id . '-row',
                    'name'     => 'divi/row',
                    'settings' => $row_settings,
                    'elements' => $row_elements,
                ],
            ],
        ];
    }
}
