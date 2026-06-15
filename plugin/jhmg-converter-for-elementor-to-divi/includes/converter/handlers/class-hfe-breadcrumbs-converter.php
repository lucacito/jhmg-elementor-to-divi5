<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HfeBreadcrumbsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_breadcrumbs_' );
        $settings = $element['settings'] ?? [];

        $this->engine->logConverted( 'breadcrumbs' );
        $this->logUnmappedSettings( $id, $settings, [
            'alignment', 'text_color', 'text_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/breadcrumbs',
            'settings' => [],
            'elements' => [],
        ];
    }
}
