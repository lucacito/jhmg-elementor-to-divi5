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
            // wcf--breadcrumbs' own controls (breadcrumbs.php) — separator,
            // Yoast toggle and colours, all cosmetic; breadcrumbs render from
            // WordPress itself either way, not from widget content.
            'yoast_seo', 'warning_text', 'html_tag', 'html_description',
            'br_separator', 'sep_description', 'link_color', 'link_hover_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/breadcrumbs',
            'settings' => [],
            'elements' => [],
        ];
    }
}
