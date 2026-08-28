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

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
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

        $this->rollback( sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) );
    }
}
