<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ColumnConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_column_' );
        $settings = $element['settings'] ?? [];
        $children = $this->convertChildren( $element );

        $this->engine->logConverted( 'column' );

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty column after conversion: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => [
                'module' => $this->normalizeSettings( $settings ),
            ],
            'elements' => $children,
        ];
    }
}
