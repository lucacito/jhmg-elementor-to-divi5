<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TextEditorConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $content = $this->getSettingValue( $settings, 'paragraph', $this->getSettingValue( $settings, 'editor', '' ) );

        return [
            'id' => $element['id'] ?? uniqid( 'divi_text_' ),
            'name' => 'divi/text',
            'settings' => [
                'innerContent' => $content,
                'module' => $this->normalizeSettings( $settings ),
            ],
            'elements' => [],
        ];
    }
}
