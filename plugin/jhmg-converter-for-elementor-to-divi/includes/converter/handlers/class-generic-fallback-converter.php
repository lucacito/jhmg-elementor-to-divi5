<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catch-all converter for widgets with no meaningful Divi 5 equivalent.
 *
 * Emits an empty divi/code block containing an HTML comment that identifies
 * the original widget type, so editors can find and replace these manually
 * after import. A warning is logged via the engine.
 */
class GenericFallbackConverter extends BaseElementorConverter {
    private string $widget_type;

    public function __construct(
        \ElementorDivi5Converter\Converter\ConverterEngine $engine,
        string $widget_type
    ) {
        parent::__construct( $engine );
        $this->widget_type = $widget_type;
    }

    public function convert( array $element ): array {
        $id = $element['id'] ?? uniqid( 'divi_code_' );

        $this->engine->logWarning(
            "Widget '{$this->widget_type}' has no Divi 5 equivalent; replaced with empty placeholder block (id: {$id})."
        );
        $this->engine->logConverted( 'code' );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => '<!-- elementor widget: ' . esc_html( $this->widget_type ) . ' (not convertible) -->' ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
