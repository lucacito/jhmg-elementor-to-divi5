<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--theme-post-content" widget (Animation Addons for
 * Elementor, post-content.php) to divi/post-content.
 *
 * The widget has no content settings at all — register_controls() only
 * offers a TAB_STYLE section (text_color/typography); render() always prints
 * the current post's the_content(). divi/post-content (PostContentModule.php,
 * fixtures/divi-schema/modules.json) is the same: a dynamic, settings-free
 * module that reads the current post at render time.
 */
class WcfThemePostContentConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_post_content_' );
        $settings = $element['settings'] ?? [];

        $this->engine->logConverted( 'post-content' );
        $this->logUnmappedSettings( $id, $settings, [
            'text_color', 'typography_typography', 'enable_inline_style',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/post-content',
            'settings' => [],
            'elements' => [],
        ];
    }
}
