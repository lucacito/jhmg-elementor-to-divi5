<?php
/**
 * Read-only summary of what this site's imports could not convert.
 *
 * Lives below the import form on the plugin's existing Tools page rather than
 * behind its own menu entry — it is a summary, not a destination.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CoveragePanel {

    private ImportHistory $history;

    public function __construct( ?ImportHistory $history = null ) {
        $this->history = $history ?? new ImportHistory();
    }

    public function render(): void {
        // Every interpolation in markup() is escaped at the point of use.
        echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function markup(): string {
        $runs = $this->history->all();
        if ( empty( $runs ) ) {
            return '';
        }

        $coverage = $this->history->coverage();

        if ( empty( $coverage ) ) {
            return '<div class="edc-card edc-card--success"><h2>'
                . esc_html__( 'Conversion coverage', 'jhmg-converter-for-elementor-to-divi' )
                . '</h2><p>'
                . esc_html__( 'Everything converted. No unsupported widgets across your recent imports.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p></div>';
        }

        $rows = '';
        foreach ( $coverage as $item ) {
            $rows .= sprintf(
                '<tr><td><code>%1$s</code></td><td>%2$d</td><td>%3$s</td></tr>',
                esc_html( $item['type'] ),
                (int) $item['runs'],
                esc_html( $item['last_seen'] )
            );
        }

        return sprintf(
            '<div class="edc-card"><h2>%1$s</h2><p class="description">%2$s</p>'
            . '<table class="widefat striped"><thead><tr><th>%3$s</th><th>%4$s</th><th>%5$s</th></tr></thead>'
            . '<tbody>%6$s</tbody></table></div>',
            esc_html__( 'Conversion coverage', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Elementor widgets from your recent imports that have no Divi 5 equivalent yet. These need rebuilding by hand.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Widget', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Imports affected', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Last seen', 'jhmg-converter-for-elementor-to-divi' ),
            $rows
        );
    }
}
