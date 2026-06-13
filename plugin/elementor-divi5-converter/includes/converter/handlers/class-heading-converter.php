<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeadingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $title = $this->getSettingValue( $settings, 'title' );
        $tag = $this->getSettingValue( $settings, 'tag', 'h2' );
        $link = $this->preserveResponsiveValue( $settings['link'] ?? [] );

        return [
            'id' => $element['id'] ?? uniqid( 'divi_text_' ),
            'name' => 'divi/text',
            'settings' => [
                'innerContent' => $title,
                'tagName' => $tag,
                'link' => $link,
                'module' => $this->normalizeSettings( $settings ),
            ],
            'elements' => [],
        ];
    }
}
