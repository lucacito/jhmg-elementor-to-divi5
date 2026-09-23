<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SearchConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_search_' );
        $settings = $element['settings'] ?? [];

        $placeholder = is_string( $settings['placeholder'] ?? '' ) ? ( $settings['placeholder'] ?? '' ) : '';

        // divi/search's 'search' attribute (SearchModule.php, fixtures/divi-schema/
        // modules.json) declares only module.advanced.{showButton,excludePages,…} —
        // no innerContent group. The placeholder text lives on the separate
        // 'searchPlaceholder' attribute. There is no button-label field at all
        // (the button is icon-only), so 'button_text' has no equivalent.
        $block_settings = [];
        if ( $placeholder !== '' ) {
            $block_settings['searchPlaceholder']['innerContent']['desktop']['value'] = $placeholder;
        }

        $this->engine->logConverted( 'search' );
        $this->logUnmappedSettings( $id, $settings, [
            'placeholder', 'button_text', 'skin', 'size',
            // wcf--blog--search--form's own preset/filter controls (search-form.php)
            // — divi/search is a plain search box with no filter presets.
            'preset', 'enable_ajax_search', 'show_search_filter', 'show_date_filter',
            'show_cat_filter', 'filter_icon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/search',
            'settings' => $block_settings,
            'elements' => [],
        ];
    }
}
