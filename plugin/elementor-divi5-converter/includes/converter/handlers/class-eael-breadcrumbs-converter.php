<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelBreadcrumbsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_breadcrumbs_' );
        $settings = $element['settings'] ?? [];

        $this->engine->logConverted( 'breadcrumbs' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_breadcrumbs_show_home', 'eael_breadcrumbs_separator',
            'alignment', 'text_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/breadcrumbs',
            'settings' => [],
            'elements' => [],
        ];
    }
}
