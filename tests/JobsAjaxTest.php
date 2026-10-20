<?php
// tests/JobsAjaxTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\JobsAjax;
use ElementorDivi5Converter\Admin\JobsPage;
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\JobService;

require_once __DIR__ . '/support/InMemoryJobStore.php';
require_once __DIR__ . '/support/FakePendingSource.php';

class JobsAjaxTest extends TestCase {

    private InMemoryJobStore $store;
    private FakePendingSource $pending;
    private JobsAjax $ajax;
    /** @var array[] converter calls */
    private array $calls = [];

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['__test_options']  = [];
        $GLOBALS['__test_caps']     = true;
        $this->calls   = [];
        $this->store   = new InMemoryJobStore();
        $this->pending = new FakePendingSource();
        $service       = new JobService( $this->store, $this->pending, function ( int $id, array $o ): array {
            $this->calls[] = $id;
            ConversionFlag::mark( $id );

            return [ 'title' => 'x', 'post_id' => $id + 5000, 'success' => true, 'error' => '', 'report' => [], 'unsupported' => [] ];
        } );
        $this->ajax = new JobsAjax( new JobsPage( $this->store, $service, $this->pending ), $service, $this->store, $this->pending );
    }

    private function req( array $over = [] ): array {
        return array_merge( [ 'nonce' => wp_create_nonce( 'edc_jobs' ) ], $over );
    }

    private function seed( int $id, string $type = 'page' ): void {
        $GLOBALS['__test_posts'][ $id ] = (object) [ 'ID' => $id, 'post_title' => "Page $id", 'post_type' => $type, 'post_status' => 'publish' ];
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
    }

    // ---- the guard -----------------------------------------------------------

    public function test_every_action_refuses_a_user_without_the_capability(): void {
        $GLOBALS['__test_caps'] = false;

        foreach ( JobsAjax::ACTIONS as $action ) {
            $r = $this->ajax->handle( $action, $this->req() );
            $this->assertFalse( $r['ok'], $action );
            $this->assertSame( 'forbidden', $r['code'], $action );
            $this->assertSame( 403, $r['status'], $action );
        }
    }

    public function test_every_action_refuses_a_missing_or_wrong_nonce(): void {
        foreach ( JobsAjax::ACTIONS as $action ) {
            $this->assertSame( 'bad_nonce', $this->ajax->handle( $action, [] )['code'], $action . ' (none)' );
            $this->assertSame( 'bad_nonce', $this->ajax->handle( $action, [ 'nonce' => 'nope' ] )['code'], $action . ' (wrong)' );
        }
    }

    public function test_every_action_refuses_without_divi_5(): void {
        $GLOBALS['__test_divi_present'] = false;

        foreach ( JobsAjax::ACTIONS as $action ) {
            $this->assertSame( 'divi_required', $this->ajax->handle( $action, $this->req() )['code'], $action );
        }
    }

    public function test_an_unknown_action_is_refused(): void {
        $this->assertSame( 'unknown_action', $this->ajax->handle( 'drop_tables', $this->req() )['code'] );
    }

    public function test_the_script_config_carries_a_nonce_the_guard_accepts(): void {
        $cfg = JobsAjax::script_config();

        $this->assertSame( wp_create_nonce( 'edc_jobs' ), $cfg['nonce'] );
        $this->assertSame( 50, $cfg['bulkThreshold'] );
        $this->assertArrayHasKey( 'ajaxUrl', $cfg );
        $this->assertArrayHasKey( 'i18n', $cfg );
    }

    // ---- items + bar -----------------------------------------------------------

    public function test_items_returns_the_rendered_rows_and_the_job_summary(): void {
        $this->seed( 11 );
        $id = $this->store->create_job( [ 'scope' => [], 'mode' => 'copy' ] );
        $this->store->add_items( $id, [ [ 'post_id' => 11, 'post_type' => 'page', 'title' => 'Home' ] ] );

        $r = $this->ajax->handle( 'items', $this->req( [ 'job_id' => $id, 'state' => '', 'paged' => 1 ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertStringContainsString( 'Home', $r['html'] );
        $this->assertSame( 1, $r['summary']['total'] );
    }

    public function test_items_ignores_an_unknown_state_filter(): void {
        $id = $this->store->create_job( [ 'scope' => [], 'mode' => 'copy' ] );
        $this->store->add_items( $id, [ [ 'post_id' => 11, 'post_type' => 'page', 'title' => 'Home' ] ] );

        $r = $this->ajax->handle( 'items', $this->req( [ 'job_id' => $id, 'state' => "x' OR 1=1" ] ) );

        $this->assertStringContainsString( 'Home', $r['html'], 'treated as "all", never passed through' );
    }

    public function test_items_for_a_job_that_does_not_exist(): void {
        $r = $this->ajax->handle( 'items', $this->req( [ 'job_id' => 999 ] ) );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'not_found', $r['code'] );
    }

    public function test_bar_returns_the_current_numbers(): void {
        $this->seed( 1 );
        $this->seed( 2 );
        ConversionFlag::mark( 1 );

        $r = $this->ajax->handle( 'bar', $this->req() );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 1, $r['bar']['converted'] );
        $this->assertSame( 1, $r['bar']['pending'] );
        $this->assertSame( 0, $r['bar']['failed'] );
    }
}
