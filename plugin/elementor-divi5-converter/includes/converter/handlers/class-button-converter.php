<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ButtonConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $text     = $this->getSettingValue( $settings, 'text', '' );
        $link     = $this->preserveResponsiveValue( $settings['link'] ?? [] );

        $this->engine->logConverted( 'button' );

        return [
            'id'       => $element['id'] ?? uniqid( 'divi_button_' ),
            'name'     => 'divi/button',
            'settings' => [
                'text'   => $text,
                'link'   => $link,
                'module' => $this->normalizeSettings( $settings ),
            ],
            'elements' => [],
        ];
    }
}
