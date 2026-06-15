<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelEmbedpressConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $url = is_string( $settings['eael_embedpress_url'] ?? '' ) ? trim( $settings['eael_embedpress_url'] ?? '' ) : '';

        if ( $url !== '' ) {
            $html = '[embedpress]' . esc_url( $url ) . '[/embedpress]';
        } else {
            $html = '<!-- EmbedPress widget: no URL set -->';
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_embedpress_url', 'eael_embedpress_width', 'eael_embedpress_height',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $html ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
