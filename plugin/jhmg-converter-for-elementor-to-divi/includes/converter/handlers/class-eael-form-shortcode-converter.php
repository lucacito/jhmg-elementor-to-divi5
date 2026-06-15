<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generic shortcode wrapper for EAEL form integration widgets.
 *
 * Each third-party form plugin (Ninja Forms, WPForms, Gravity Forms,
 * Fluent Forms, WeForms) is handled by instantiating this converter with the
 * appropriate shortcode tag and settings key.
 *
 * Usage (via ConverterRegistry closure):
 *   new EaelFormShortcodeConverter( $engine, 'wpforms', 'eael_wpforms_id' )
 */
class EaelFormShortcodeConverter extends BaseElementorConverter {
    private string $shortcode_tag;
    private string $id_key;

    public function __construct(
        \ElementorDivi5Converter\Converter\ConverterEngine $engine,
        string $shortcode_tag,
        string $id_key
    ) {
        parent::__construct( $engine );
        $this->shortcode_tag = $shortcode_tag;
        $this->id_key        = $id_key;
    }

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $form_id  = (int) ( $settings[ $this->id_key ] ?? 0 );
        $shortcode = $form_id > 0
            ? '[' . $this->shortcode_tag . ' id="' . $form_id . '"]'
            : '<!-- ' . $this->shortcode_tag . ': no form ID found -->';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ $this->id_key ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $shortcode ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
