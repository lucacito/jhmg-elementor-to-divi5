<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

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

        // Map the container's own styles (background, padding, min-height, etc.)
        // to the divi/section block attributes.
        $style         = ( new StyleMapper() )->map( 'section', $settings );
        $section_attrs = $style['divi_attrs'];
        $style_keys    = $style['handled_keys'];

        // In Divi 5, min-height and content alignment belong on the row, not the section.
        [ $section_attrs, $row_sizing_layout ] = $this->extractRowSizingLayout( $section_attrs );

        // CSS Grid container → section with a grid-mode row.
        if ( $this->isGridContainer( $settings ) ) {
            $columns      = $this->convertGridChildren( $children );
            $row_settings = $this->applyBoxedWidthToRow(
                $this->deepMergeSettings(
                    $this->rowGridSettingsFromContainer( $settings ),
                    $row_sizing_layout
                ),
                $settings
            );

            $this->engine->logConverted( 'section' );
            $this->logUnmappedSettings( $id, $settings, $style_keys );

            return [
                'id'       => $id,
                'name'     => 'divi/section',
                'settings' => $section_attrs,
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
            $row_settings = $this->applyBoxedWidthToRow(
                $this->deepMergeSettings(
                    $this->deepMergeSettings(
                        $this->rowSettingsFromColumns( $columns ),
                        $this->rowFlexSettingsFromContainer( $settings )
                    ),
                    $row_sizing_layout
                ),
                $settings
            );

            $this->engine->logConverted( 'section' );
            $this->logUnmappedSettings( $id, $settings, $style_keys );

            return [
                'id'       => $id,
                'name'     => 'divi/section',
                'settings' => $section_attrs,
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
        $converted = $this->convertStructureChildren( $children );

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings, $style_keys );

        // When all converted children are already divi/row blocks (e.g. a flex-column
        // wrapper whose only child was an inner flex-row container), pass them through
        // directly as section children — no extra row+column wrapping needed.
        if ( ! empty( $converted ) && $this->allNamedAs( $converted, 'divi/row' ) ) {
            // Lift a child row's background up to the section when the section has none.
            // This covers the common Elementor "wrapper → hero" pattern where an outer
            // container provides no background but its single inner container carries the
            // hero image and/or gradient overlay. In Divi 5 backgrounds must be on the
            // section to be full-bleed; leaving them on the row clips them to the row box.
            if ( empty( $section_attrs['module']['decoration']['background'] ) && count( $converted ) === 1 ) {
                $child_bg  = $converted[0]['settings']['module']['decoration']['background'] ?? [];
                $child_css = $converted[0]['settings']['css'] ?? [];
                if ( ! empty( $child_bg ) ) {
                    $section_attrs = $this->deepMergeSettings( $section_attrs, [
                        'module' => [ 'decoration' => [ 'background' => $child_bg ] ],
                    ] );
                    unset( $converted[0]['settings']['module']['decoration']['background'] );
                    // Also lift custom ::before CSS (gradient overlay) when present.
                    if ( ! empty( $child_css ) ) {
                        $section_attrs = $this->deepMergeSettings( $section_attrs, [ 'css' => $child_css ] );
                        unset( $converted[0]['settings']['css'] );
                    }
                }
            }

            return [
                'id'       => $id,
                'name'     => 'divi/section',
                'settings' => $section_attrs,
                'elements' => $converted,
            ];
        }

        $row_elements = $this->ensureColumnChildren( $id, $converted );
        $row_settings = $this->applyBoxedWidthToRow(
            $this->deepMergeSettings(
                $this->rowSettingsFromColumns( $row_elements ),
                $row_sizing_layout
            ),
            $settings
        );

        return [
            'id'       => $id,
            'name'     => 'divi/section',
            'settings' => $section_attrs,
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
