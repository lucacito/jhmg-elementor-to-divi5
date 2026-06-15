<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MenuAnchorConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id     = $element['id'] ?? uniqid( 'divi_anchor_' );
        $settings = $element['settings'] ?? [];
        $anchor   = is_string( $settings['anchor'] ?? ( $settings['menu_anchor'] ?? '' ) )
            ? ( $settings['anchor'] ?? ( $settings['menu_anchor'] ?? '' ) )
            : '';

        $content = $anchor !== ''
            ? '<div id="' . htmlspecialchars( $anchor, ENT_QUOTES, 'UTF-8' ) . '"></div>'
            : '';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [ 'anchor', 'menu_anchor' ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $content ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
