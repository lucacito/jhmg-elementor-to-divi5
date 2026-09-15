<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Advanced Data Table → divi/code.
 *
 * Unlike the plain Data Table, EAEL stores this widget's table as ready-made
 * HTML: `ea_adv_data_table_static_html` for the 'static' source (the default)
 * and `ea_adv_data_table_csv_html` for an imported CSV. Other sources (Ninja
 * Tables, a database, a remote database, …) are read at render time from
 * outside the page, so there is nothing to carry over; that is reported
 * rather than left silently empty.
 */
class EaelAdvancedDataTableConverter extends BaseElementorConverter {

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $source = $settings['ea_adv_data_table_source'] ?? 'static';
        $source = is_string( $source ) ? $source : 'static';

        $table = match ( $source ) {
            'static' => $settings['ea_adv_data_table_static_html'] ?? '',
            'csv'    => $settings['ea_adv_data_table_csv_html'] ?? '',
            default  => '',
        };
        $table = is_string( $table ) ? wp_kses_post( $table ) : '';

        if ( $table !== '' && stripos( $table, '<table' ) === false ) {
            $table = '<table style="width:100%;border-collapse:collapse;">' . $table . '</table>';
        }

        if ( ! in_array( $source, [ 'static', 'csv' ], true ) ) {
            $this->engine->logWarning( "Advanced data table {$id} loads its rows from '{$source}', which lives outside the page; the table was not carried over." );
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'ea_adv_data_table_source', 'ea_adv_data_table_static_html', 'ea_adv_data_table_csv_html',
            'ea_adv_data_table_search', 'ea_adv_data_table_search_placeholder',
            'ea_adv_data_table_sort', 'ea_adv_data_table_pagination', 'ea_adv_data_table_items_per_page',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $table ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
