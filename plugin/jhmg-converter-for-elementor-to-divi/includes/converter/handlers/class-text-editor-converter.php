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
        // 'paragraph' is used in our fixtures; real Elementor text-editor uses
        // 'editor'; Animation Addons' wcf--text (animated-text.php) uses 'text'.
        $content = $this->getSettingValue( $settings, 'paragraph', $this->getSettingValue( $settings, 'editor', $this->getSettingValue( $settings, 'text', '' ) ) );

        // Elementor's text editor falls back to the kit's Text typography (text-editor.php).
        $style = ( new StyleMapper() )->map( 'text-editor', $settings, [ 'elementor_defaults' => true ] );
        // Start from StyleMapper attrs (may include content.decoration) then inject
        // innerContent alongside it — array_merge would clobber the whole content key.
        $attrs = $style['divi_attrs'];
        $attrs['content']['innerContent']['desktop']['value'] = (string) $content;

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'paragraph', 'editor',
                // wcf--text's own key, plus its GSAP-only colour/link-heading
                // controls (animated-text.php) with no Divi equivalent —
                // 'heading_link' there is a section HEADING control, not real data.
                'text', 'title_color', 'heading_link', 'title_link_hover_color',
            ],
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
