<?php
// tests/ImportRollbackTest.php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;

class ImportRollbackTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options']   = [];
        $GLOBALS['__test_postmeta']  = [];
        $GLOBALS['__test_trashed']      = [];
        $GLOBALS['__test_trash_fails']  = [];
        $GLOBALS['__test_caps']         = true;
        $_GET                           = [];
    }

    private function seed( array $post_ids, array $owned ): ImportHistory {
        $h = new ImportHistory();
        $h->record( 'run1', array_map(
            fn( $id ) => [ 'success' => true, 'post_id' => $id, 'unsupported' => [] ],
            $post_ids
        ) );
        foreach ( $owned as $id ) {
            update_post_meta( $id, '_edc_import_source', 'file_upload' );
        }
        return $h;
    }

    public function test_trashes_posts_the_plugin_created(): void {
        $h = $this->seed( [ 10, 11 ], [ 10, 11 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10, 11 ], $GLOBALS['__test_trashed'] );
        $this->assertSame( 2, $out['trashed'] );
        $this->assertSame( 0, $out['skipped'] );
    }

    public function test_skips_posts_the_plugin_does_not_own(): void {
        // 11 has no _edc_import_source meta — the user may have replaced it.
        $h = $this->seed( [ 10, 11 ], [ 10 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'], 'must never touch a post it did not create' );
        $this->assertSame( 1, $out['trashed'] );
        $this->assertSame( 1, $out['skipped'] );
    }

    public function test_marks_the_run_rolled_back(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        ( new ImportRollback( $h ) )->rollback( 'run1' );
        $this->assertTrue( $h->find( 'run1' )['rolled_back'] );
    }

    public function test_unknown_run_does_nothing(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'nope' );
        $this->assertSame( [], $GLOBALS['__test_trashed'] );
        $this->assertSame( 0, $out['trashed'] );
    }

    public function test_request_requires_a_valid_nonce(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = 'forged';
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [], $GLOBALS['__test_trashed'], 'a forged nonce must not trash anything' );
    }

    public function test_request_requires_manage_options(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $GLOBALS['__test_caps'] = false;
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [], $GLOBALS['__test_trashed'] );
    }

    public function test_valid_request_performs_the_rollback(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'] );
    }

    public function test_uses_trash_not_delete(): void {
        $src = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-rollback.php'
        );
        $this->assertStringContainsString( 'wp_trash_post', $src );
        $this->assertStringNotContainsString( 'wp_delete_post', $src );
    }

    public function test_a_failed_trash_is_reported_as_skipped_not_trashed(): void {
        $h = $this->seed( [ 10, 11 ], [ 10, 11 ] );
        $GLOBALS['__test_trash_fails'] = [ 11 ];

        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'], 'a post whose trash call failed must not be reported as trashed' );
        $this->assertSame( 1, $out['trashed'] );
        $this->assertSame( 1, $out['skipped'] );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState( false )]
    public function test_trash_unavailable_skips_every_post_and_does_not_mark_rolled_back(): void {
        define( 'EMPTY_TRASH_DAYS', 0 );

        $h = $this->seed( [ 10, 11 ], [ 10, 11 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [], $GLOBALS['__test_trashed'], 'must never fall through to a permanent delete' );
        $this->assertSame( 0, $out['trashed'] );
        $this->assertSame( 2, $out['skipped'] );
        $this->assertTrue( $out['trash_unavailable'] );
        $this->assertFalse( $h->find( 'run1' )['rolled_back'], 'a run that was never actually undone must not be marked rolled back' );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState( false )]
    public function test_trash_available_when_empty_trash_days_is_nonzero(): void {
        define( 'EMPTY_TRASH_DAYS', 30 );

        $h = $this->seed( [ 10 ], [ 10 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'] );
        $this->assertSame( 1, $out['trashed'] );
        $this->assertFalse( $out['trash_unavailable'] );
    }

    // --- admin notice reporting the rollback outcome (Finding A) ---

    public function test_notice_reports_trashed_count_and_mentions_restoring(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );

        $rollback = new ImportRollback( $h );
        $rollback->maybe_handle_request();

        ob_start();
        $rollback->render_notice();
        $html = ob_get_clean();

        $this->assertStringContainsString( '1', $html );
        $this->assertMatchesRegularExpression( '/trash/i', $html );
        $this->assertMatchesRegularExpression( '/restor/i', $html, 'must tell the user the trashed pages can be restored' );
    }

    public function test_notice_reports_skipped_count_because_no_longer_owned(): void {
        // 11 has no _edc_import_source meta, so it is skipped as not plugin-owned.
        $h = $this->seed( [ 10, 11 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );

        $rollback = new ImportRollback( $h );
        $rollback->maybe_handle_request();

        ob_start();
        $rollback->render_notice();
        $html = ob_get_clean();

        $this->assertStringContainsString( '1', $html );
        $this->assertMatchesRegularExpression( '/skip/i', $html );
        $this->assertMatchesRegularExpression( '/no longer/i', $html, 'must explain the skip as no-longer-plugin-owned' );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState( false )]
    public function test_notice_reports_trash_unavailable(): void {
        define( 'EMPTY_TRASH_DAYS', 0 );

        $h = $this->seed( [ 10, 11 ], [ 10, 11 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );

        $rollback = new ImportRollback( $h );
        $rollback->maybe_handle_request();

        ob_start();
        $rollback->render_notice();
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression( '/trash/i', $html );
        $this->assertMatchesRegularExpression( '/disabled|empt(y|ies)/i', $html, 'must explain that this site\'s trash is disabled' );
        $this->assertStringNotContainsString( '0 page', $html, 'should not report a mechanical "0 pages trashed" when nothing ran' );
    }

    public function test_notice_shows_only_once(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );

        $rollback = new ImportRollback( $h );
        $rollback->maybe_handle_request();

        ob_start();
        $rollback->render_notice();
        $first = ob_get_clean();

        ob_start();
        $rollback->render_notice();
        $second = ob_get_clean();

        $this->assertNotSame( '', $first, 'the first render after a rollback must show the notice' );
        $this->assertSame( '', $second, 'the notice must not render again on the next page load' );
    }

    public function test_no_notice_when_nothing_was_rolled_back(): void {
        $rollback = new ImportRollback( $this->seed( [ 10 ], [ 10 ] ) );

        ob_start();
        $rollback->render_notice();
        $html = ob_get_clean();

        $this->assertSame( '', $html );
    }
}
