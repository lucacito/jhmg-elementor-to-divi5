<?php
/**
 * Read-only summary of what this site's imports could not convert.
 *
 * Lives below the import form on the plugin's existing Tools page rather than
 * behind its own menu entry — it is a summary, not a destination.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;

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
                . '</p></div>'
                . $this->runs_table();
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
            . '<tbody>%6$s</tbody></table>%7$s</div>',
            esc_html__( 'Conversion coverage', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Elementor widgets from your recent imports that have no Divi 5 equivalent yet. These need rebuilding by hand.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Widget', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Imports affected', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Last seen', 'jhmg-converter-for-elementor-to-divi' ),
            $rows,
            $this->consent_line()
        ) . $this->runs_table();
    }

    /**
     * The opt-in lives here because this is the one screen where the user is
     * already looking at exactly the data being asked for.
     */
    private function consent_line(): string {
        $telemetry = new CoverageTelemetry( $this->history );
        $on        = $telemetry->has_consent();

        $url = add_query_arg( CoverageTelemetry::QUERY_ACTION, $on ? '0' : '1' )
            . '&_wpnonce=' . wp_create_nonce( CoverageTelemetry::NONCE_ACTION );

        return sprintf(
            '<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
            $on
                ? esc_html__( 'Sharing this list of widget names with divi5lab so these gaps get prioritised. No site address, no content, no personal data.', 'jhmg-converter-for-elementor-to-divi' )
                : esc_html__( 'Help prioritise these widgets? Sharing sends only the widget names above, once a week. No site address, no content, no personal data.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_url( $url ),
            $on
                ? esc_html__( 'Stop sharing', 'jhmg-converter-for-elementor-to-divi' )
                : esc_html__( 'Share these widget names', 'jhmg-converter-for-elementor-to-divi' )
        );
    }

    /** Recent runs, each undoable while it still owns the posts it created. */
    private function runs_table(): string {
        $rows            = '';
        $trash_available = ImportRollback::trash_available();

        foreach ( $this->history->all() as $run ) {
            if ( ! empty( $run['rolled_back'] ) ) {
                $undo = esc_html__( 'Undone', 'jhmg-converter-for-elementor-to-divi' );
            } elseif ( ! $trash_available ) {
                // Never offer a control that would refuse to work: on a site
                // configured with EMPTY_TRASH_DAYS = 0, wp_trash_post() would
                // permanently delete instead of trashing.
                $undo = esc_html__( 'Undo is unavailable because this site empties the trash immediately.', 'jhmg-converter-for-elementor-to-divi' );
            } else {
                $confirm_message = sprintf(
                    /* translators: %d: number of pages this import created. */
                    __( 'Move the %d page(s) this import created to the Trash?', 'jhmg-converter-for-elementor-to-divi' ),
                    count( $run['post_ids'] ?? [] )
                );

                $undo = sprintf(
                    '<a href="%1$s" class="button button-small" onclick="%2$s">%3$s</a>',
                    esc_url(
                        add_query_arg( ImportRollback::QUERY_ACTION, $run['id'] )
                        . '&_wpnonce=' . wp_create_nonce( ImportRollback::NONCE_ACTION )
                    ),
                    esc_attr( sprintf( "return confirm('%s');", addslashes( $confirm_message ) ) ),
                    esc_html__( 'Undo', 'jhmg-converter-for-elementor-to-divi' )
                );
            }

            $rows .= sprintf(
                '<tr><td>%1$s</td><td>%2$d</td><td>%3$s</td></tr>',
                esc_html( $run['at'] ?? '' ),
                count( $run['post_ids'] ?? [] ),
                $undo
            );
        }

        return sprintf(
            '<div class="edc-card"><h2>%1$s</h2><p class="description">%2$s</p>'
            . '<table class="widefat striped"><thead><tr><th>%3$s</th><th>%4$s</th><th></th></tr></thead>'
            . '<tbody>%5$s</tbody></table></div>',
            esc_html__( 'Recent imports', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Undo moves the pages an import created to the trash. Pages you have edited since are left alone.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'When', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Pages', 'jhmg-converter-for-elementor-to-divi' ),
            $rows
        );
    }
}
