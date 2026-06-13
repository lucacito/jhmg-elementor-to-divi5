<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        return [
            'id' => $element['id'] ?? uniqid( 'divi_section_' ),
            'name' => 'divi/section',
            'settings' => [
                'module' => $this->normalizeSettings( $element['settings'] ?? [] ),
            ],
            'elements' => $this->convertChildren( $element ),
        ];
    }
}
