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
        // Start from StyleMapper attrs (may include content.decoration) then inject
        // innerContent alongside it — array_merge would clobber the whole content key.
        $attrs = $style['divi_attrs'];
        $attrs['content']['innerContent']['desktop']['value'] = (string) $content;

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
