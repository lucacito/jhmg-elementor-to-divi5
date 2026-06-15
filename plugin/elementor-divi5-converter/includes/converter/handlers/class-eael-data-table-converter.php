<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Data Table (and Advanced Data Table) → divi/code with an
 * HTML table built from the repeater data.
 */
class EaelDataTableConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $header_cols = $settings['eael_data_table_header_cols'] ?? [];
        $body_rows   = $settings['eael_data_table_body_rows'] ?? [];

        $html = '<table style="width:100%;border-collapse:collapse;">';

        // Header.
        if ( ! empty( $header_cols ) ) {
            $html .= '<thead><tr>';
            foreach ( $header_cols as $col ) {
                if ( ! is_array( $col ) ) {
                    continue;
                }
                $text = is_string( $col['eael_dt_header_col'] ?? '' ) ? esc_html( $col['eael_dt_header_col'] ?? '' ) : '';
                $span = (int) ( $col['eael_dt_header_col_span'] ?? 1 );
                $html .= '<th style="border:1px solid #ddd;padding:8px;background:#f5f5f5;"'
                    . ( $span > 1 ? ' colspan="' . $span . '"' : '' ) . '>' . $text . '</th>';
            }
            $html .= '</tr></thead>';
        }

        // Body.
        if ( ! empty( $body_rows ) ) {
            $html .= '<tbody>';
            foreach ( $body_rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $cells = $row['eael_dt_body_col_rows'] ?? [];
                if ( ! is_array( $cells ) || empty( $cells ) ) {
                    // Flat row format: each row item is a cell.
                    $text = is_string( $row['eael_dt_body_row_value'] ?? '' ) ? esc_html( $row['eael_dt_body_row_value'] ?? '' ) : '';
                    $html .= '<tr><td style="border:1px solid #ddd;padding:8px;">' . $text . '</td></tr>';
                    continue;
                }
                $html .= '<tr>';
                foreach ( $cells as $cell ) {
                    if ( ! is_array( $cell ) ) {
                        continue;
                    }
                    $text     = is_string( $cell['eael_dt_body_col'] ?? '' ) ? esc_html( $cell['eael_dt_body_col'] ?? '' ) : '';
                    $col_span = (int) ( $cell['eael_dt_body_col_span'] ?? 1 );
                    $row_span = (int) ( $cell['eael_dt_body_row_span'] ?? 1 );
                    $html .= '<td style="border:1px solid #ddd;padding:8px;"'
                        . ( $col_span > 1 ? ' colspan="' . $col_span . '"' : '' )
                        . ( $row_span > 1 ? ' rowspan="' . $row_span . '"' : '' ) . '>' . $text . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
        }

        $html .= '</table>';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_data_table_header_cols', 'eael_data_table_body_rows',
            'eael_data_table_responsive', 'eael_data_table_search',
            'eael_data_table_sort', 'eael_data_table_pagination',
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
