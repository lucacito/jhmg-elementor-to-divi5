<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ColumnConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_column_' );
        $settings = $element['settings'] ?? [];

        // Use structure-aware conversion so inner sections/containers become
        // nested divi/row blocks instead of being dispatched as full sections.
        $children   = $this->convertStructureChildren( $element['elements'] ?? [] );
        $style      = ( new StyleMapper() )->map( 'column', $settings );
        $divi_attrs = $style['divi_attrs'];

        $this->engine->logConverted( 'column' );
        $this->logUnmappedSettings( $id, $settings, $style['handled_keys'] );

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty column after conversion: {$id}" );
        }

        // When the Elementor column has background/overlay styling AND contains
        // widget children, replicate the "widget-wrap background" pattern using a
        // divi/group.  In Elementor, background + padding are applied to the inner
        // widget-wrap div (not the column outer div); divi/group is the direct Divi 5
        // equivalent — it is a module wrapper that supports background, spacing, and
        // border while containing other modules.
        //
        // Visually: divi/column (width only) → divi/group (bg + padding) → modules.
        if ( $this->hasBackgroundStyling( $settings ) && ! empty( $children ) ) {
            $children   = [ $this->wrapInGroup( $id, $divi_attrs, $children ) ];
            $divi_attrs = $this->stripDecorationFromAttrs( $divi_attrs );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => $divi_attrs,
            'elements' => $children,
        ];
    }

    /**
     * Returns true when the Elementor column settings carry image-based background
     * or an overlay (color or image).  Plain background_color alone is handled
     * adequately as a column background and does not warrant a group wrapper.
     */
    private function hasBackgroundStyling( array $settings ): bool {
        if ( ( $settings['background_background'] ?? '' ) !== 'classic' ) {
            return false;
        }

        $bg_image = $settings['background_image'] ?? null;
        if ( is_array( $bg_image ) && ! empty( $bg_image['url'] ) ) {
            return true;
        }

        $overlay_image = $settings['background_overlay_image'] ?? null;
        if ( is_array( $overlay_image ) && ! empty( $overlay_image['url'] ) ) {
            return true;
        }

        $overlay_color = $settings['background_overlay_color'] ?? '';
        return is_string( $overlay_color ) && $overlay_color !== '';
    }

    /**
     * Builds a divi/group block that carries the background and padding attrs
     * extracted from the column's mapped Divi attributes.
     *
     * The group gets `module.decoration.background` and `module.decoration.spacing`
     * (padding) because in Elementor both are applied at the widget-wrap level —
     * the background visually encompasses the padded content area.
     */
    private function wrapInGroup( string $col_id, array $divi_attrs, array $children ): array {
        $group_decoration = [];

        foreach ( [ 'background', 'spacing' ] as $key ) {
            if ( isset( $divi_attrs['module']['decoration'][ $key ] ) ) {
                $group_decoration[ $key ] = $divi_attrs['module']['decoration'][ $key ];
            }
        }

        $group_attrs = empty( $group_decoration )
            ? []
            : [ 'module' => [ 'decoration' => $group_decoration ] ];

        return [
            'id'       => $col_id . '-group',
            'name'     => 'divi/group',
            'settings' => $group_attrs,
            'elements' => $children,
        ];
    }

    /**
     * Returns $divi_attrs with background and spacing removed from
     * `module.decoration`, leaving only structural settings (width, alignment,
     * border, etc.) on the column itself.
     */
    private function stripDecorationFromAttrs( array $divi_attrs ): array {
        unset( $divi_attrs['module']['decoration']['background'] );
        unset( $divi_attrs['module']['decoration']['spacing'] );

        if ( isset( $divi_attrs['module']['decoration'] ) && empty( $divi_attrs['module']['decoration'] ) ) {
            unset( $divi_attrs['module']['decoration'] );
        }
        if ( isset( $divi_attrs['module'] ) && empty( $divi_attrs['module'] ) ) {
            unset( $divi_attrs['module'] );
        }

        return $divi_attrs;
    }
}
