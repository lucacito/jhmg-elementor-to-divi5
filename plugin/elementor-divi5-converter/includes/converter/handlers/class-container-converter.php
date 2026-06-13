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
 */
class ContainerConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_section_' );
        $settings = $element['settings'] ?? [];

        // Convert children with structure awareness: any nested container or
        // section among the direct children becomes a nested divi/row rather
        // than a full section.
        $children     = $this->convertStructureChildren( $element['elements'] ?? [] );
        $row_elements = $this->ensureColumnChildren( $id, $children );
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
