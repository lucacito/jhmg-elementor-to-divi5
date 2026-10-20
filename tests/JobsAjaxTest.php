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

    // ---- pending list --------------------------------------------------------

    public function test_pending_returns_the_table_html_for_the_requested_filters(): void {
        $this->seed( 11 );
        $this->seed( 12, 'post' );

        $r = $this->ajax->handle( 'pending', $this->req( [ 'types' => [ 'post' ], 'search' => '', 'paged' => 1 ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertStringContainsString( 'Page 12', $r['html'] );
        $this->assertStringNotContainsString( 'Page 11<', $r['html'] );
    }

    public function test_pending_ignores_post_types_outside_the_allowed_set(): void {
        $this->seed( 11 );

        $r = $this->ajax->handle( 'pending', $this->req( [ 'types' => [ 'elementor_library', 'shop_order' ] ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertStringContainsString( 'Page 11', $r['html'], 'unknown types are dropped and the default (all allowed) applies' );
    }

    public function test_pending_can_return_the_whole_section_so_it_recovers_from_empty(): void {
        // The section renders an empty state (no table to swap) when nothing is
        // pending; once a page becomes pending again it has to be re-rendered whole.
        $empty = $this->ajax->handle( 'pending', $this->req( [ 'section' => '1' ] ) );
        $this->assertStringContainsString( 'Nothing is waiting', $empty['html'] );

        $this->seed( 11 );
        $full = $this->ajax->handle( 'pending', $this->req( [ 'section' => '1' ] ) );

        $this->assertStringContainsString( 'id="edc-pending"', $full['html'] );
        $this->assertStringContainsString( 'Page 11', $full['html'] );
        $this->assertStringContainsString( 'id="edc-pending-table"', $full['html'] );
    }

    public function test_the_section_keeps_the_filters_the_user_chose(): void {
        $this->seed( 11 );
        $this->seed( 12, 'post' );

        $r = $this->ajax->handle( 'pending', $this->req( [ 'section' => '1', 'types' => [ 'post' ], 'search' => '' ] ) );

        $this->assertStringContainsString( 'Page 12', $r['html'] );
        $this->assertStringNotContainsString( 'Page 11<', $r['html'] );
    }

    // ---- create ----------------------------------------------------------------

    private function allowMany(): void {
        add_filter( 'edc_direct_conversion_limit', fn() => PHP_INT_MAX );
    }

    public function test_create_from_selected_posts_starts_a_job_and_returns_its_markup(): void {
        $this->allowMany();
        $this->seed( 11 );
        $this->seed( 12 );

        $r = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => [ 11, 12 ], 'mode' => 'copy' ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertGreaterThan( 0, $r['job_id'] );
        $this->assertStringContainsString( 'Job #' . $r['job_id'], $r['job_html'] );
        $this->assertSame( 2, $this->store->get_job( $r['job_id'] )['total'] );
        $this->assertSame( 'copy', $this->store->get_job( $r['job_id'] )['mode'] );
        $this->assertSame( (int) get_current_user_id(), $this->store->get_job( $r['job_id'] )['created_by'] );
    }

    public function test_create_drops_posts_that_are_not_pending_elementor_pages(): void {
        $this->allowMany();
        $this->seed( 11 );
        $GLOBALS['__test_posts'][13] = (object) [ 'ID' => 13, 'post_title' => 'Plain', 'post_type' => 'page', 'post_status' => 'publish' ]; // no Elementor meta
        $this->seed( 14, 'shop_order' ); // not an allowed type

        $r = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => [ 11, 13, 14 ], 'mode' => 'copy' ] ) );

        $this->assertSame( 1, $this->store->get_job( $r['job_id'] )['total'] );
    }

    public function test_create_does_not_requeue_an_already_converted_page(): void {
        $this->allowMany();
        $this->seed( 11 );
        ConversionFlag::mark( 11 );

        $r = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => [ 11 ], 'mode' => 'copy' ] ) );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'empty', $r['code'] );
    }

    public function test_free_creating_more_than_one_gets_the_upsell_details_not_a_job(): void {
        $this->seed( 11 );
        $this->seed( 12 );
        $this->seed( 13 );

        $r = $this->ajax->handle( 'create', $this->req( [ 'remaining' => 1, 'post_types' => [ 'page' ] ] ) );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'pro_required', $r['code'] );
        $this->assertSame( 3, $r['count'] );
        $this->assertStringContainsString( 'Convert all 3 remaining with Pro', $r['upsell'] );
        $this->assertSame( 0, $this->store->count_jobs( [] ) );
    }

    public function test_create_remaining_over_fifty_demands_a_confirmed_explicit_mode(): void {
        $this->allowMany();
        for ( $i = 0; $i < 51; $i++ ) { $this->seed( 100 + $i ); }

        $no_mode    = $this->ajax->handle( 'create', $this->req( [ 'remaining' => 1, 'post_types' => [ 'page' ] ] ) );
        $no_confirm = $this->ajax->handle( 'create', $this->req( [ 'remaining' => 1, 'post_types' => [ 'page' ], 'mode' => 'replace' ] ) );
        $ok         = $this->ajax->handle( 'create', $this->req( [ 'remaining' => 1, 'post_types' => [ 'page' ], 'mode' => 'replace', 'confirmed' => '1' ] ) );

        $this->assertSame( 'choose_mode', $no_mode['code'] );
        $this->assertSame( 'confirm_required', $no_confirm['code'] );
        $this->assertTrue( $ok['ok'] );
        $this->assertSame( 'replace', $this->store->get_job( $ok['job_id'] )['mode'] );
        $this->assertSame( 51, $this->store->get_job( $ok['job_id'] )['total'] );
    }

    public function test_a_confirmation_that_is_not_literally_true_does_not_count(): void {
        $this->allowMany();
        for ( $i = 0; $i < 51; $i++ ) { $this->seed( 100 + $i ); }

        foreach ( [ '0', '', 'false', 'no' ] as $falsy ) {
            $r = $this->ajax->handle( 'create', $this->req( [ 'remaining' => 1, 'post_types' => [ 'page' ], 'mode' => 'copy', 'confirmed' => $falsy ] ) );
            $this->assertSame( 'confirm_required', $r['code'], var_export( $falsy, true ) );
        }
    }

    // ---- run -------------------------------------------------------------------

    public function test_run_converts_a_chunk_and_returns_progress_and_the_bar(): void {
        $this->allowMany();
        $this->seed( 11 );
        $this->seed( 12 );
        $created = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => [ 11, 12 ], 'mode' => 'copy' ] ) );

        $r = $this->ajax->handle( 'run', $this->req( [ 'job_id' => $created['job_id'] ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertTrue( $r['summary']['done'] );
        $this->assertSame( 2, $r['summary']['converted'] );
        $this->assertSame( [ 11, 12 ], $this->calls );
        $this->assertSame( 100, $r['ui']['percent'] );
        $this->assertSame( 'done', $r['ui']['status'] );
        $this->assertSame( 2, $r['bar']['converted'] );
        $this->assertSame( 0, $r['bar']['pending'] );
    }

    public function test_run_for_a_missing_job_is_a_clean_error(): void {
        $r = $this->ajax->handle( 'run', $this->req( [ 'job_id' => 4242 ] ) );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'not_found', $r['code'] );
    }

    public function test_run_reports_busy_when_another_tab_holds_the_job(): void {
        $this->allowMany();
        $this->seed( 11 );
        $created = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => [ 11 ], 'mode' => 'copy' ] ) );
        $this->store->claim_lock( $created['job_id'], 60 );

        $r = $this->ajax->handle( 'run', $this->req( [ 'job_id' => $created['job_id'] ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertTrue( $r['busy'] );
        $this->assertSame( [], $this->calls );
    }

    // ---- retry (resume is just `run` on an unfinished job) --------------------

    private function failedJob( array $ids = [ 11, 12, 13 ], array $failed = [ 12, 13 ] ): int {
        $this->allowMany();
        foreach ( $ids as $id ) { $this->seed( $id ); }
        $created = $this->ajax->handle( 'create', $this->req( [ 'post_ids' => $ids, 'mode' => 'copy' ] ) );
        foreach ( $ids as $id ) {
            $this->store->set_item_result( $created['job_id'], $id, in_array( $id, $failed, true )
                ? [ 'state' => 'failed', 'message' => 'boom' ]
                : [ 'state' => 'converted', 'result_post_id' => $id + 5000 ] );
        }
        $this->store->sync_counts( $created['job_id'] );
        $this->store->update_job( $created['job_id'], [ 'status' => 'done_errors' ] );

        return $created['job_id'];
    }

    public function test_retry_requeues_every_failed_item_of_the_job(): void {
        $job = $this->failedJob();

        $r = $this->ajax->handle( 'retry', $this->req( [ 'job_id' => $job ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 2, $r['retried'] );
        $this->assertSame( 2, $this->store->counts_by_state( $job )['pending'] );
        $this->assertSame( 'pending', $r['ui']['status'], 'the job is unfinished again, so the browser can run it' );
    }

    public function test_retry_can_target_one_page(): void {
        $job = $this->failedJob();

        $r = $this->ajax->handle( 'retry', $this->req( [ 'job_id' => $job, 'post_ids' => [ 13 ] ] ) );

        $this->assertSame( 1, $r['retried'] );
        $this->assertSame( 1, $this->store->counts_by_state( $job )['failed'] );
    }

    public function test_retry_with_nothing_failed_is_harmless(): void {
        $job = $this->failedJob( [ 11 ], [] );

        $r = $this->ajax->handle( 'retry', $this->req( [ 'job_id' => $job ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 0, $r['retried'] );
    }

    public function test_retry_for_a_missing_job_is_a_clean_error(): void {
        $this->assertSame( 'not_found', $this->ajax->handle( 'retry', $this->req( [ 'job_id' => 9999 ] ) )['code'] );
    }

    public function test_retried_pages_actually_convert_on_the_next_run(): void {
        $job = $this->failedJob();
        $this->ajax->handle( 'retry', $this->req( [ 'job_id' => $job ] ) );

        $run = $this->ajax->handle( 'run', $this->req( [ 'job_id' => $job ] ) );

        $this->assertSame( [ 12, 13 ], $this->calls );
        $this->assertSame( 'done', $run['ui']['status'] );
    }

    // ---- marking by hand -------------------------------------------------------

    public function test_mark_flags_pages_so_they_leave_the_pending_list(): void {
        $this->seed( 11 );
        $this->seed( 12 );

        $r = $this->ajax->handle( 'mark', $this->req( [ 'post_ids' => [ 11 ] ] ) );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 1, $r['count'] );
        $this->assertTrue( ConversionFlag::is_manual( 11 ) );
        $this->assertSame( 1, $r['bar']['pending'] );
        $this->assertSame( 1, $r['bar']['converted'] );
    }

    public function test_mark_can_be_taken_back(): void {
        $this->seed( 11 );
        $this->ajax->handle( 'mark', $this->req( [ 'post_ids' => [ 11 ] ] ) );

        $r = $this->ajax->handle( 'mark', $this->req( [ 'post_ids' => [ 11 ], 'undo' => '1' ] ) );

        $this->assertSame( 1, $r['count'] );
        $this->assertFalse( ConversionFlag::is_converted( 11 ) );
        $this->assertSame( 1, $r['bar']['pending'] );
    }

    public function test_mark_ignores_posts_outside_the_allowed_types(): void {
        $this->seed( 11, 'shop_order' );

        $r = $this->ajax->handle( 'mark', $this->req( [ 'post_ids' => [ 11 ] ] ) );

        $this->assertSame( 0, $r['count'] );
        $this->assertFalse( ConversionFlag::is_converted( 11 ) );
    }

    public function test_marking_a_failed_page_completes_its_job(): void {
        $job = $this->failedJob( [ 11, 12 ], [ 12 ] );
        $this->store->set_item_result( $job, 11, [ 'state' => 'converted', 'result_post_id' => 5011 ] );
        $this->store->sync_counts( $job );

        $this->ajax->handle( 'mark', $this->req( [ 'post_ids' => [ 12 ] ] ) );

        $this->assertSame( 'done', $this->store->get_job( $job )['status'] );
        $this->assertSame( 0, $this->store->get_job( $job )['failed'] );
    }
}
