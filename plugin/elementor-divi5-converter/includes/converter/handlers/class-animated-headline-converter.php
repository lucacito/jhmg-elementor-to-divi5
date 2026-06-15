<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts Elementor Pro Animated Headline → divi/heading.
 *
 * The animated cycling text is flattened to static text; the first rotating
 * word is used when a highlighted word is not present.
 */
class AnimatedHeadlineConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $style  = is_string( $settings['headline_style'] ?? '' ) ? ( $settings['headline_style'] ?? '' ) : '';
        $before = is_string( $settings['before_text'] ?? '' ) ? ( $settings['before_text'] ?? '' ) : '';
        $after  = is_string( $settings['after_text'] ?? '' ) ? ( $settings['after_text'] ?? '' ) : '';

        // For rotating/typing styles the animated part is in 'rotating_text'.
        // For clip/highlight styles it lives in 'highlighted_text'.
        if ( $style === 'rotate' || $style === 'typing' || $style === 'slide' ) {
            $rotating_raw = is_string( $settings['rotating_text'] ?? '' ) ? ( $settings['rotating_text'] ?? '' ) : '';
            $words        = array_filter( array_map( 'trim', preg_split( '/[\n,]/', $rotating_raw ) ) );
            $highlight    = ! empty( $words ) ? reset( $words ) : '';
        } else {
            $highlight = is_string( $settings['highlighted_text'] ?? '' ) ? ( $settings['highlighted_text'] ?? '' ) : '';
        }

        $parts = array_filter( [ $before, $highlight, $after ] );
        $title = implode( ' ', $parts );

        $block_settings = [];
        if ( $title !== '' ) {
            $block_settings['title'] = [
                'innerContent' => [ 'desktop' => [ 'value' => $title ] ],
            ];
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'headline_style', 'before_text', 'after_text', 'highlighted_text',
            'rotating_text', 'tag', 'highlighted_shape',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
