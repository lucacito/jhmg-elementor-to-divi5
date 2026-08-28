<?php
// tests/ImportRollbackTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;

class ImportRollbackTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options']   = [];
        $GLOBALS['__test_postmeta']  = [];
        $GLOBALS['__test_trashed']   = [];
        $GLOBALS['__test_caps']      = true;
        $_GET                        = [];
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
}
