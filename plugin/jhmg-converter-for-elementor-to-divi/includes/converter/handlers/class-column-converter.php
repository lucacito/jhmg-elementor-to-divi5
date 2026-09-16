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

        // Background, overlay and padding stay on the column. Divi 5 rows are
        // display:flex with the default align-items: stretch (.et_flex_row in
        // Divi's module CSS), so the column already fills the row's min-height and
        // a cover image has something to cover. Wrapping the children in a
        // divi/group carrying the background (the old "widget-wrap" mirror) gave
        // the image a box only as tall as the group's content — 12 px when the
        // column held a spacer. column/module.json declares
        // module.decoration.background and .spacing on the column itself.

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => $divi_attrs,
            'elements' => $children,
        ];
    }
}
