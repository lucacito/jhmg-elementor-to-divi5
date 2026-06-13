<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TextEditorConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id      = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        // 'paragraph' is used in our fixtures; real Elementor text-editor uses 'editor'.
        $content = $this->getSettingValue( $settings, 'paragraph', $this->getSettingValue( $settings, 'editor', '' ) );

        $style = ( new StyleMapper() )->map( 'text-editor', $settings );
        $attrs = array_merge(
            [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => (string) $content ],
                    ],
                ],
            ],
            $style['divi_attrs']
        );

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'paragraph', 'editor' ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
