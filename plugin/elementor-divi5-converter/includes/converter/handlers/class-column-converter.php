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
        $children = $this->convertChildren( $element );

        $style = ( new StyleMapper() )->map( 'column', $settings );

        $this->engine->logConverted( 'column' );
        $this->logUnmappedSettings( $id, $settings, $style['handled_keys'] );

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty column after conversion: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => $style['divi_attrs'],
            'elements' => $children,
        ];
    }
}
