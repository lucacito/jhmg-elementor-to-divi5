<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--typewriter" widget (Animation Addons for Elementor,
 * typewriter.php) to divi/heading.
 *
 * The widget shows a static prefix ('typewriter_normal_text') followed by a
 * JS-cycling list of words ('typewriter_animated_text'). Divi's heading
 * module can't animate through a word list, so the cycling itself is logged
 * as not carried over; the static prefix plus the first cycling word (what's
 * actually visible on page load, before any animation runs) still convert to
 * a real divi/heading.
 */
class WcfTypewriterConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $prefix = is_string( $settings['typewriter_normal_text'] ?? null ) ? ( $settings['typewriter_normal_text'] ?? '' ) : '';
        $tag    = is_string( $settings['html_tag'] ?? null ) ? ( $settings['html_tag'] ?? 'h2' ) : 'h2';

        $words = is_array( $settings['typewriter_animated_text'] ?? null ) ? $settings['typewriter_animated_text'] : [];
        $first_word = '';
        foreach ( $words as $word ) {
            if ( is_array( $word ) && is_string( $word['list_text'] ?? null ) && ( $word['list_text'] ?? '' ) !== '' ) {
                $first_word = $word['list_text'];
                break;
            }
        }

        $text = trim( $prefix . ' ' . $first_word );

        if ( count( $words ) > 1 ) {
            $this->engine->logNotCarriedOver( 'typewriter_cycle', (string) $id, 'the JS word-cycling animation has no Divi equivalent; only the first word was carried over' );
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'typewriter_normal_text', 'typewriter_animated_text', 'html_tag', 'align',
            'typewriter_color', 'typewriter_typography_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => [
                'title' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $text ] ],
                    'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => $tag ] ] ] ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
