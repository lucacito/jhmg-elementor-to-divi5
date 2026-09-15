<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Data Table → divi/code holding an HTML table.
 *
 * EAEL 6.x keeps the header in `eael_data_table_header_cols_data` and the whole
 * body in ONE flat repeater, `eael_data_table_content_rows`: an entry whose
 * `eael_data_table_content_row_type` is 'row' (the default, so often absent)
 * starts a table row, and each following 'col' entry is a cell in that row.
 * The nested `eael_data_table_body_rows` shape is still read for older exports.
 *
 * The Advanced Data Table stores its table as HTML and has its own converter.
 */
class EaelDataTableConverter extends BaseElementorConverter {

    private const CELL_STYLE = 'border:1px solid #ddd;padding:8px;';

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $html = '<table style="width:100%;border-collapse:collapse;">'
            . $this->header( $settings )
            . $this->body( (string) $id, $settings )
            . '</table>';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_data_table_header_cols_data', 'eael_data_table_content_rows',
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

    private function header( array $settings ): string {
        $cols = $settings['eael_data_table_header_cols_data'] ?? $settings['eael_data_table_header_cols'] ?? [];
        if ( ! is_array( $cols ) || empty( $cols ) ) {
            return '';
        }

        $html = '<thead><tr>';
        foreach ( $cols as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = $item['eael_data_table_header_col'] ?? $item['eael_dt_header_col'] ?? '';
            $span = (int) ( $item['eael_data_table_header_col_span'] ?? $item['eael_dt_header_col_span'] ?? 1 );

            $html .= '<th style="' . self::CELL_STYLE . 'background:#f5f5f5;"' . $this->span( 'colspan', $span ) . '>'
                . esc_html( is_string( $text ) ? $text : '' ) . '</th>';
        }

        return $html . '</tr></thead>';
    }

    private function body( string $id, array $settings ): string {
        $entries = $settings['eael_data_table_content_rows'] ?? [];
        $rows    = is_array( $entries ) && ! empty( $entries )
            ? $this->flatRows( $id, $entries )
            : $this->legacyRows( $settings );

        if ( empty( $rows ) ) {
            return '';
        }

        return '<tbody><tr>' . implode( '</tr><tr>', $rows ) . '</tr></tbody>';
    }

    /** @return string[] Each body row's cells as HTML. */
    private function flatRows( string $id, array $entries ): array {
        $rows  = [];
        $cells = null;

        foreach ( $entries as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            if ( ( $item['eael_data_table_content_row_type'] ?? 'row' ) !== 'col' ) {
                if ( $cells !== null ) {
                    $rows[] = $cells;
                }
                $cells = '';
                continue;
            }
            // A 'col' before any 'row' still belongs to a first row.
            $cells = ( $cells ?? '' ) . $this->flatCell( $id, $item );
        }

        if ( $cells !== null ) {
            $rows[] = $cells;
        }

        return $rows;
    }

    private function flatCell( string $id, array $item ): string {
        // 'textarea' is the plain-text cell type.
        $type = $item['eael_data_table_content_type'] ?? 'textarea';

        if ( $type === 'editor' ) {
            $content = $item['eael_data_table_content_row_content'] ?? '';
            $inner   = wp_kses_post( is_string( $content ) ? $content : '' );
        } elseif ( $type === 'icon' || $type === 'template' ) {
            // Icon cells have no text; template cells render a saved Elementor template.
            $inner = '';
            if ( $type === 'template' ) {
                $this->engine->logWarning( "Data table {$id} has a cell that renders a saved Elementor template; the template was not carried over." );
            }
        } else {
            $title = $item['eael_data_table_content_row_title'] ?? '';
            $inner = esc_html( is_string( $title ) ? $title : '' );
            $link  = $item['eael_data_table_content_row_title_link'] ?? [];
            $url   = is_array( $link ) && is_string( $link['url'] ?? null ) ? $link['url'] : '';
            if ( $url !== '' && $inner !== '' ) {
                $inner = '<a href="' . esc_url( $url ) . '">' . $inner . '</a>';
            }
        }

        $colspan = (int) ( $item['eael_data_table_content_row_colspan'] ?? 1 );
        $rowspan = (int) ( $item['eael_data_table_content_row_rowspan'] ?? 1 );

        return '<td style="' . self::CELL_STYLE . '"' . $this->span( 'colspan', $colspan ) . $this->span( 'rowspan', $rowspan ) . '>'
            . $inner . '</td>';
    }

    /** @return string[] Each body row's cells as HTML, from the pre-6.x nested shape. */
    private function legacyRows( array $settings ): array {
        $body = $settings['eael_data_table_body_rows'] ?? [];
        $rows = [];

        foreach ( is_array( $body ) ? $body : [] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $cells = $row['eael_dt_body_col_rows'] ?? [];
            if ( ! is_array( $cells ) || empty( $cells ) ) {
                // Flat legacy row: the row itself is one cell.
                $text   = $row['eael_dt_body_row_value'] ?? '';
                $rows[] = '<td style="' . self::CELL_STYLE . '">' . esc_html( is_string( $text ) ? $text : '' ) . '</td>';
                continue;
            }

            $html = '';
            foreach ( $cells as $cell ) {
                if ( ! is_array( $cell ) ) {
                    continue;
                }
                $text  = $cell['eael_dt_body_col'] ?? '';
                $html .= '<td style="' . self::CELL_STYLE . '"'
                    . $this->span( 'colspan', (int) ( $cell['eael_dt_body_col_span'] ?? 1 ) )
                    . $this->span( 'rowspan', (int) ( $cell['eael_dt_body_row_span'] ?? 1 ) ) . '>'
                    . esc_html( is_string( $text ) ? $text : '' ) . '</td>';
            }
            $rows[] = $html;
        }

        return $rows;
    }

    private function span( string $attribute, int $count ): string {
        return $count > 1 ? " {$attribute}=\"{$count}\"" : '';
    }
}
