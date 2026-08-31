<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catch-all converter for widgets with no meaningful Divi 5 equivalent.
 *
 * Emits a divi/code block whose HTML comment identifies the original widget
 * type, so editors can find and replace these manually after import. A warning
 * is logged via the engine.
 *
 * This is also the registry's default for any widget it does not recognise —
 * see ConverterRegistry::defaultConverter(). Before that, an unregistered
 * widget produced no block at all and simply vanished from the page, leaving
 * only a line in the report to explain a hole in the layout.
 *
 * Whatever text the widget carried is emitted alongside the marker. It will not
 * look like the original, but an editor can see what was there and where, which
 * an empty placeholder never allowed.
 */
class GenericFallbackConverter extends BaseElementorConverter {

    /**
     * Elementor setting keys that commonly hold a widget's visible text, most
     * specific first. Deliberately a fixed list: this converter runs for widget
     * types nobody has mapped, so it cannot know their schema and must not guess
     * at arbitrary keys — a stray match would paste a CSS class or an icon name
     * into the page as if it were content.
     */
    private const TEXT_KEYS = [
        'title', 'title_text', 'heading', 'heading_title',
        'text', 'editor', 'content', 'description', 'description_text',
        'caption', 'sub_title', 'subtitle',
    ];

    private string $widget_type;
    private bool $count_as_converted;

    /**
     * @param bool $count_as_converted Whether this placeholder counts towards the
     *   report's widget-coverage percentage. True for the widget types explicitly
     *   registered to this converter — someone decided a placeholder is the right
     *   answer for them, and they have always counted. False when the registry
     *   falls back here for an unrecognised widget: that one is also logged as
     *   unsupported, and counting it both ways would drive coverage towards 100%
     *   precisely as a page filled up with widgets nothing could convert.
     */
    public function __construct(
        \ElementorDivi5Converter\Converter\ConverterEngine $engine,
        string $widget_type,
        bool $count_as_converted = true
    ) {
        parent::__construct( $engine );
        $this->widget_type        = $widget_type;
        $this->count_as_converted = $count_as_converted;
    }

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $this->engine->logWarning(
            "Widget '{$this->widget_type}' has no Divi 5 equivalent; replaced with empty placeholder block (id: {$id})."
        );

        if ( $this->count_as_converted ) {
            $this->engine->logConverted( 'code' );
        }

        $marker = '<!-- elementor widget: ' . esc_html( $this->widget_type ) . ' (not convertible) -->';
        $text   = $this->extractText( $settings );

        if ( $text !== '' ) {
            $marker .= '<div class="edc-unconverted-widget" data-elementor-widget="'
                . esc_attr( $this->widget_type ) . '">' . $text . '</div>';
        }

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $marker ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }

    /**
     * The widget's visible text, or '' when it carries none.
     *
     * Only the first matching key is used. Concatenating every match would
     * duplicate content on widgets that store the same string twice (a title and
     * a title_text, say), and the ordering of TEXT_KEYS puts the most
     * title-like key first.
     */
    private function extractText( array $settings ): string {
        foreach ( self::TEXT_KEYS as $key ) {
            if ( ! isset( $settings[ $key ] ) ) {
                continue;
            }

            $value = $this->getSettingValue( $settings, $key, '' );
            if ( ! is_string( $value ) ) {
                continue;
            }

            $value = trim( $value );
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }
}
