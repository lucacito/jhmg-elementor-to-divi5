<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_section_' );
        $settings = $element['settings'] ?? [];
        $children = $this->convertChildren( $element );

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings );

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty section after conversion: {$id}" );
        }

        // Build row columnStructure from the converted columns' type attrs.
        $row_settings = $this->rowSettingsFromColumns( $children );

        return [
            'id'       => $id,
            'name'     => 'divi/section',
            'settings' => [],
            'elements' => [
                [
                    'id'       => $id . '-row',
                    'name'     => 'divi/row',
                    'settings' => $row_settings,
                    'elements' => $children,
                ],
            ],
        ];
    }
}
