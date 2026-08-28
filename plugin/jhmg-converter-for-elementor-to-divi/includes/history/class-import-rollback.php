<?php
/**
 * Undo an import by moving the posts it created to the trash.
 *
 * Two deliberate safety properties:
 *  - It trashes, never deletes — and only when trash is actually available.
 *    On a site configured with `EMPTY_TRASH_DAYS` set to 0, WordPress core's
 *    own `wp_trash_post()` permanently deletes instead of trashing, so this
 *    class detects that condition first and skips the entire run rather than
 *    ever calling into that permanent-delete path. A `wp_trash_post()` call
 *    that itself reports failure is likewise counted as skipped, never as
 *    trashed.
 *  - It only touches posts still carrying the `_edc_import_source` meta this
 *    plugin wrote. A post the user has since replaced or adopted by hand is
 *    skipped, so a stale batch record can never sweep away real work.
 */

namespace ElementorDivi5Converter\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportRollback {

    const QUERY_ACTION = 'edc_rollback';
    const NONCE_ACTION = 'edc_rollback_import';

    private ImportHistory $history;

    public function __construct( ?ImportHistory $history = null ) {
        $this->history = $history ?? new ImportHistory();
    }

    /** Transient key prefix; the current user's ID is appended so the notice is per-user. */
    const NOTICE_TRANSIENT_PREFIX = 'edc_rollback_notice_';

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    }

    /** @return array{trashed:int, skipped:int, trash_unavailable:bool} */
    public function rollback( string $import_id ): array {
        $run = $this->history->find( $import_id );

        if ( $run === null ) {
            return [ 'trashed' => 0, 'skipped' => 0, 'trash_unavailable' => false ];
        }

        if ( ! self::trash_available() ) {
            // Never fall through to core's permanent-delete path: skip the
            // whole run, and leave it eligible to be undone once trash is
            // available again, rather than marking it rolled back.
            return [
                'trashed'           => 0,
                'skipped'           => count( $run['post_ids'] ?? [] ),
                'trash_unavailable' => true,
            ];
        }

        $trashed = 0;
        $skipped = 0;

        foreach ( $run['post_ids'] ?? [] as $post_id ) {
            if ( get_post_meta( (int) $post_id, '_edc_import_source', true ) === '' ) {
                $skipped++;
                continue;
            }

            if ( wp_trash_post( (int) $post_id ) ) {
                $trashed++;
            } else {
                $skipped++;
            }
        }

        $this->history->mark_rolled_back( $import_id );

        return [ 'trashed' => $trashed, 'skipped' => $skipped, 'trash_unavailable' => false ];
    }

    /**
     * Whether `wp_trash_post()` would actually trash rather than permanently
     * delete. Core treats `EMPTY_TRASH_DAYS === 0` as "no trash" and deletes
     * outright; an undefined constant matches core's own default of 30 days,
     * so trash is available in that case.
     */
    public static function trash_available(): bool {
        return ! ( defined( 'EMPTY_TRASH_DAYS' ) && (int) EMPTY_TRASH_DAYS === 0 );
    }

    public function maybe_handle_request(): void {
        if ( empty( $_GET[ self::QUERY_ACTION ] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        $result = $this->rollback( sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) );

        // Short-lived: only needs to survive the redirect back to this screen.
        set_transient( $this->notice_transient_key(), $result, MINUTE_IN_SECONDS );
    }

    /**
     * Reports the outcome of the last rollback the current user triggered, once.
     * Hooked to `admin_notices`; reads and immediately clears its transient so
     * the notice cannot reappear on a later page load.
     */
    public function render_notice(): void {
        $result = get_transient( $this->notice_transient_key() );

        if ( ! is_array( $result ) ) {
            return;
        }

        delete_transient( $this->notice_transient_key() );

        // Every interpolation in notice_markup() is escaped at the point of use.
        echo $this->notice_markup( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param array{trashed:int, skipped:int, trash_unavailable:bool} $result
     */
    public function notice_markup( array $result ): string {
        if ( ! empty( $result['trash_unavailable'] ) ) {
            $body = esc_html__(
                'Undo did not run: this site is configured to empty the Trash immediately, so nothing could be trashed. No pages were changed.',
                'jhmg-converter-for-elementor-to-divi'
            );

            return '<div class="notice notice-warning is-dismissible edc-rollback-notice"><p>' . $body . '</p></div>';
        }

        $trashed = (int) ( $result['trashed'] ?? 0 );
        $skipped = (int) ( $result['skipped'] ?? 0 );
        $parts   = [];

        if ( $trashed > 0 ) {
            $parts[] = esc_html(
                sprintf(
                    /* translators: %d: number of pages moved to the Trash. */
                    _n(
                        '%d page was moved to the Trash. You can restore it from Posts → Trash if needed.',
                        '%d pages were moved to the Trash. You can restore them from Posts → Trash if needed.',
                        $trashed,
                        'jhmg-converter-for-elementor-to-divi'
                    ),
                    $trashed
                )
            );
        }

        if ( $skipped > 0 ) {
            $parts[] = esc_html(
                sprintf(
                    /* translators: %d: number of pages skipped. */
                    _n(
                        '%d page was skipped because it is no longer owned by this plugin (already gone, or replaced since the import).',
                        '%d pages were skipped because they are no longer owned by this plugin (already gone, or replaced since the import).',
                        $skipped,
                        'jhmg-converter-for-elementor-to-divi'
                    ),
                    $skipped
                )
            );
        }

        if ( empty( $parts ) ) {
            $parts[] = esc_html__( 'Nothing to undo: this import had already been undone or had no pages left to trash.', 'jhmg-converter-for-elementor-to-divi' );
        }

        return '<div class="notice notice-success is-dismissible edc-rollback-notice"><p>'
            . implode( ' ', $parts )
            . '</p></div>';
    }

    private function notice_transient_key(): string {
        return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
    }
}
