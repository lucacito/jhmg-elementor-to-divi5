<?php
/**
 * Undo an import by moving the posts it created to the trash.
 *
 * Two deliberate safety properties:
 *  - It trashes, never deletes. An undo that destroys content is not an undo;
 *    WP's trash is the user's second chance.
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

    /** @return array{trashed:int, skipped:int} */
    public function rollback( string $import_id ): array {
        $run = $this->history->find( $import_id );

        if ( $run === null ) {
            return [ 'trashed' => 0, 'skipped' => 0 ];
        }

        $trashed = 0;
        $skipped = 0;

        foreach ( $run['post_ids'] ?? [] as $post_id ) {
            if ( get_post_meta( (int) $post_id, '_edc_import_source', true ) === '' ) {
                $skipped++;
                continue;
            }

            wp_trash_post( (int) $post_id );
            $trashed++;
        }

        $this->history->mark_rolled_back( $import_id );

        return [ 'trashed' => $trashed, 'skipped' => $skipped ];
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
