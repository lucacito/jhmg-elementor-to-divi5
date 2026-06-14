<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts a root-level Elementor container (Flexbox Container widget) into a
 * Divi 5 section + row structure.
 *
 * A container that appears inside a column is never dispatched here — the
 * ColumnConverter intercepts it via convertStructureChildren() and converts it
 * directly to a divi/row using convertInnerAsRow(). This converter is therefore
 * only called for top-level containers, which always map to divi/section.
 *
 * Flex-row containers whose direct children are other containers produce a
 * multi-column row with proper flex layout settings instead of collapsing
 * everything into a single full-width column.
 */
class ContainerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_section_' );
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        // CSS Grid container → section with a grid-mode row.
        if ( $this->isGridContainer( $settings ) ) {
            $columns      = $this->convertGridChildren( $children );
            $row_settings = $this->rowGridSettingsFromContainer( $settings );

            $this->engine->logConverted( 'section' );
            $this->logUnmappedSettings( $id, $settings );

            return [
                'id'       => $id,
                'name'     => 'divi/section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $row_settings,
                        'elements' => $columns,
                    ],
                ],
            ];
        }

        // Flex-row container with container children → multi-column flex row.
        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            $columns      = $this->convertFlexRowChildren( $children );
            $row_settings = $this->deepMergeSettings(
                $this->rowSettingsFromColumns( $columns ),
                $this->rowFlexSettingsFromContainer( $settings )
            );

            $this->engine->logConverted( 'section' );
            $this->logUnmappedSettings( $id, $settings );

            return [
                'id'       => $id,
                'name'     => 'divi/section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $row_settings,
                        'elements' => $columns,
                    ],
                ],
            ];
        }

        // Default: convert children with structure awareness — any nested
        // container or section becomes a divi/row rather than a full section.
        $converted    = $this->convertStructureChildren( $children );
        $row_elements = $this->ensureColumnChildren( $id, $converted );
        $row_settings = $this->rowSettingsFromColumns( $row_elements );

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings );

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
