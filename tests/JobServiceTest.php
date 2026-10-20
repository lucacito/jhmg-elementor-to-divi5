<?php
// tests/JobServiceTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\JobService;

require_once __DIR__ . '/support/InMemoryJobStore.php';
require_once __DIR__ . '/support/FakePendingSource.php';

class JobServiceTest extends TestCase {

    private InMemoryJobStore $store;
    /** @var array[] Every call the fake converter received. */
    private array $calls = [];
    /** @var array<int,array> Per-post canned result overrides. */
    private array $canned = [];
    private float $now = 1000.0;

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']       = [];
        $GLOBALS['__test_postmeta']    = [];
        $GLOBALS['__test_options']     = [];
        $GLOBALS['__test_trashed']     = [];
        $GLOBALS['__test_trash_fails'] = [];
        $this->store  = new InMemoryJobStore();
        $this->calls  = [];
        $this->canned = [];
        $this->now    = 1000.0;
    }

    private function seed( int $id, string $type = 'page', string $title = '' ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID' => $id, 'post_title' => $title ?: "Page $id", 'post_type' => $type, 'post_status' => 'publish',
        ];
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );

        return $id;
    }

    private function seedMany( int $n, int $from = 100 ): array {
        $ids = [];
        for ( $i = 0; $i < $n; $i++ ) { $ids[] = $this->seed( $from + $i ); }

        return $ids;
    }

    private function service( float $tick = 0.0 ): JobService {
        $convert = function ( int $post_id, array $options ): array {
            $this->calls[] = [ 'post_id' => $post_id, 'options' => $options ];
            $this->now += 0;
            if ( isset( $this->canned[ $post_id ] ) ) {
                $c = $this->canned[ $post_id ];
                if ( $c instanceof \Throwable ) { throw $c; }

                return $c;
            }
            ConversionFlag::mark( $post_id );

            return [ 'title' => "Page $post_id", 'post_id' => $post_id + 5000, 'success' => true, 'error' => '',
                'report' => [ 'converted' => [ 'heading' => 2, 'text' => 1 ], 'warnings' => [ 'w' ] ], 'unsupported' => [] ];
        };
        $clock = function () use ( $tick ): float { $this->now += $tick; return $this->now; };

        return new JobService( $this->store, new FakePendingSource(), $convert, $clock );
    }

    private function allowMany(): void {
        add_filter( 'edc_direct_conversion_limit', fn() => PHP_INT_MAX );
    }

    // ---- creating jobs ---------------------------------------------------------

    public function test_a_job_from_selected_posts_snapshots_their_titles_and_types(): void {
        $this->seed( 11, 'page', 'Home' );
        $this->seed( 12, 'post', 'News' );
        $this->allowMany();

        $r = $this->service()->create( [ 'post_ids' => [ 11, 12 ], 'mode' => 'copy', 'created_by' => 3 ] );

        $this->assertTrue( $r['ok'] );
        $job = $this->store->get_job( $r['job_id'] );
        $this->assertSame( 'pending', $job['status'] );
        $this->assertSame( 2, $job['total'] );
        $this->assertSame( 'copy', $job['mode'] );
        $this->assertSame( [ 11, 12 ], $job['scope']['post_ids'] );
        $items = $this->store->list_items( $r['job_id'], null, 10, 0 );
        $this->assertSame( [ 'Home', 'News' ], array_column( $items, 'title' ) );
        $this->assertSame( [ 'page', 'post' ], array_column( $items, 'post_type' ) );
    }

    public function test_posts_that_do_not_exist_are_left_out(): void {
        $this->seed( 11 );

        $r = $this->service()->create( [ 'post_ids' => [ 11, 9999 ] ] );

        $this->assertSame( 1, $this->store->get_job( $r['job_id'] )['total'] );
    }

    public function test_a_job_with_nothing_to_convert_is_refused(): void {
        $r = $this->service()->create( [ 'post_ids' => [ 9999 ] ] );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'empty', $r['code'] );
        $this->assertSame( 0, $this->store->count_jobs( [] ) );
    }

    public function test_free_can_create_a_one_item_job_but_more_needs_pro(): void {
        $this->seedMany( 3 );

        $one = $this->service()->create( [ 'post_ids' => [ 100 ] ] );
        $many = $this->service()->create( [ 'post_ids' => [ 100, 101, 102 ] ] );

        $this->assertTrue( $one['ok'] );
        $this->assertFalse( $many['ok'] );
        $this->assertSame( 'pro_required', $many['code'] );
        $this->assertSame( 3, $many['count'] );
        $this->assertSame( 1, $this->store->count_jobs( [] ), 'a refused job must not be stored' );
    }

    public function test_convert_all_remaining_needs_pro_when_more_than_the_limit_is_pending(): void {
        $this->seedMany( 5 );

        $r = $this->service()->create( [ 'post_types' => [ 'page' ], 'remaining' => true ] );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'pro_required', $r['code'] );
        $this->assertSame( 5, $r['count'], 'the upsell quotes how many pages are waiting' );
    }

    public function test_remaining_snapshots_every_pending_post_across_pages_of_the_source(): void {
        $this->allowMany();
        $this->seedMany( 1300 );
        $this->seed( 5000, 'post' );
        ConversionFlag::mark( 105 ); // already converted: not pending

        $r = $this->service()->create( [ 'post_types' => [ 'page' ], 'remaining' => true, 'mode' => 'copy', 'confirmed' => true ] );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 1299, $this->store->get_job( $r['job_id'] )['total'] );
        $this->assertNull( $this->store->items[ $r['job_id'] ][5000] ?? null, 'a post type outside the scope is not included' );
        $this->assertNull( $this->store->items[ $r['job_id'] ][105] ?? null );
    }

    public function test_a_job_of_fifty_or_fewer_defaults_to_copy_without_confirmation(): void {
        $this->allowMany();
        $this->seedMany( 50 );

        $r = $this->service()->create( [ 'post_ids' => range( 100, 149 ) ] );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 'copy', $this->store->get_job( $r['job_id'] )['mode'] );
    }

    public function test_above_fifty_a_mode_must_be_chosen_explicitly(): void {
        $this->allowMany();
        $this->seedMany( 51 );

        $r = $this->service()->create( [ 'post_ids' => range( 100, 150 ), 'confirmed' => true ] );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'choose_mode', $r['code'] );
        $this->assertSame( 0, $this->store->count_jobs( [] ) );
    }

    public function test_above_fifty_the_confirmation_is_required_for_either_mode(): void {
        $this->allowMany();
        $this->seedMany( 51 );

        foreach ( [ 'copy', 'replace' ] as $mode ) {
            $r = $this->service()->create( [ 'post_ids' => range( 100, 150 ), 'mode' => $mode ] );
            $this->assertFalse( $r['ok'], $mode );
            $this->assertSame( 'confirm_required', $r['code'], $mode );
        }
        $this->assertSame( 0, $this->store->count_jobs( [] ) );
    }

    public function test_above_fifty_a_confirmed_explicit_choice_creates_the_job(): void {
        $this->allowMany();
        $this->seedMany( 51 );

        $r = $this->service()->create( [ 'post_ids' => range( 100, 150 ), 'mode' => 'replace', 'confirmed' => true ] );

        $this->assertTrue( $r['ok'] );
        $this->assertSame( 'replace', $this->store->get_job( $r['job_id'] )['mode'] );
    }

    public function test_an_unknown_mode_is_rejected(): void {
        $this->seed( 11 );

        $r = $this->service()->create( [ 'post_ids' => [ 11 ], 'mode' => 'delete' ] );

        $this->assertFalse( $r['ok'] );
        $this->assertSame( 'choose_mode', $r['code'] );
    }

    // ---- running ---------------------------------------------------------------

    private function job( array $ids, string $mode = 'copy' ): int {
        $this->allowMany();
        $r = $this->service()->create( [ 'post_ids' => $ids, 'mode' => $mode, 'confirmed' => true ] );
        $this->assertTrue( $r['ok'], json_encode( $r ) );

        return $r['job_id'];
    }

    public function test_a_chunk_converts_pending_items_and_records_each_result(): void {
        $id = $this->job( $this->seedMany( 3 ) );

        $out = $this->service()->run_chunk( $id );

        $this->assertTrue( $out['done'] );
        $this->assertSame( 3, $out['converted'] );
        $items = $this->store->list_items( $id, null, 10, 0 );
        $this->assertSame( [ 'converted', 'converted', 'converted' ], array_column( $items, 'state' ) );
        $this->assertSame( [ 5100, 5101, 5102 ], array_column( $items, 'result_post_id' ) );
        $this->assertSame( 3, $items[0]['modules'], '2 headings + 1 text' );
        $this->assertSame( 1, $items[0]['warnings'] );
        $job = $this->store->get_job( $id );
        $this->assertSame( 'done', $job['status'] );
        $this->assertSame( 3, $job['converted'] );
        $this->assertNotNull( $job['finished_at'] );
    }

    public function test_the_converter_is_asked_to_skip_converted_pages_and_to_replace_only_in_replace_mode(): void {
        $copy    = $this->job( $this->seedMany( 1, 100 ), 'copy' );
        $replace = $this->job( $this->seedMany( 1, 200 ), 'replace' );

        $this->service()->run_chunk( $copy );
        $this->service()->run_chunk( $replace );

        $this->assertTrue( $this->calls[0]['options']['skip_converted'] );
        $this->assertFalse( $this->calls[0]['options']['replace_original'] );
        $this->assertTrue( $this->calls[1]['options']['replace_original'] );
    }

    public function test_a_failure_is_recorded_and_the_rest_still_run(): void {
        $ids = $this->seedMany( 3 );
        $this->canned[101] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => 'boom', 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );

        $out = $this->service()->run_chunk( $id );

        $this->assertSame( 2, $out['converted'] );
        $this->assertSame( 1, $out['failed'] );
        $failed = $this->store->list_items( $id, 'failed', 10, 0 );
        $this->assertSame( 'boom', $failed[0]['message'] );
        $this->assertSame( 'done_errors', $this->store->get_job( $id )['status'] );
    }

    public function test_an_exception_while_converting_is_a_failure_not_a_crash(): void {
        $ids = $this->seedMany( 2 );
        $this->canned[100] = new \RuntimeException( 'memory' );
        $id = $this->job( $ids );

        $this->service()->run_chunk( $id );

        $this->assertSame( 'memory', $this->store->list_items( $id, 'failed', 10, 0 )[0]['message'] );
        $this->assertSame( 1, $this->store->counts_by_state( $id )['converted'] );
    }

    public function test_an_already_converted_page_is_recorded_as_skipped_not_failed(): void {
        $ids = $this->seedMany( 1 );
        $this->canned[100] = [ 'title' => 'x', 'post_id' => 100, 'success' => false, 'skipped' => true, 'error' => 'Already converted on 2026-10-01', 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );

        $this->service()->run_chunk( $id );

        $this->assertSame( 'skipped', $this->store->list_items( $id, null, 1, 0 )[0]['state'] );
        $this->assertSame( 'done', $this->store->get_job( $id )['status'], 'a skip is not an error' );
    }

    public function test_a_page_without_usable_elementor_content_is_skipped_and_leaves_the_pending_list(): void {
        $ids = $this->seedMany( 1 );
        $this->canned[100] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => \ElementorDivi5Converter\Conversion\InstalledPostSource::no_content_message(), 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );

        $this->service()->run_chunk( $id );

        $this->assertSame( 'skipped', $this->store->list_items( $id, null, 1, 0 )[0]['state'] );
        $this->assertTrue( ConversionFlag::is_unconvertible( 100 ), 'otherwise it would sit in the pending count forever' );
        $this->assertSame( 0, ( new FakePendingSource() )->count( [ 'page' ] ) );
    }

    public function test_a_chunk_stops_at_its_time_budget_and_the_job_stays_running(): void {
        $id = $this->job( $this->seedMany( 10 ) );

        $out = $this->service( 3.0 )->run_chunk( $id, 8.0 ); // each clock read advances 3s

        $this->assertFalse( $out['done'] );
        $this->assertGreaterThan( 0, $out['processed'] );
        $this->assertLessThan( 10, $out['processed'] );
        $this->assertSame( 'running', $this->store->get_job( $id )['status'] );
        $this->assertSame( 10 - $out['processed'], $this->store->counts_by_state( $id )['pending'] );
        $this->assertNull( $this->store->get_job( $id )['locked_until'], 'the lock is released between chunks' );
    }

    public function test_repeated_chunks_finish_the_job_without_converting_anything_twice(): void {
        $id = $this->job( $this->seedMany( 10 ) );
        $svc = $this->service( 3.0 );

        $guard = 0;
        do { $out = $svc->run_chunk( $id, 8.0 ); } while ( ! $out['done'] && ++$guard < 20 );

        $this->assertTrue( $out['done'] );
        $this->assertSame( 10, $this->store->get_job( $id )['converted'] );
        $this->assertCount( 10, array_unique( array_column( $this->calls, 'post_id' ) ) );
        $this->assertCount( 10, $this->calls );
    }

    public function test_a_chunk_does_nothing_while_another_tab_holds_the_lock(): void {
        $id = $this->job( $this->seedMany( 2 ) );
        $this->store->claim_lock( $id, 60 );

        $out = $this->service()->run_chunk( $id );

        $this->assertTrue( $out['busy'] );
        $this->assertSame( [], $this->calls );
    }

    public function test_an_item_left_running_by_a_crashed_chunk_becomes_a_failure_not_a_retry_loop(): void {
        $id = $this->job( $this->seedMany( 2 ) );
        $this->store->set_item_result( $id, 100, [ 'state' => 'running' ] ); // the crashed chunk's page

        $this->service()->run_chunk( $id );

        $first = $this->store->list_items( $id, null, 10, 0 )[0];
        $this->assertSame( 'failed', $first['state'] );
        $this->assertStringContainsString( 'stopped', strtolower( $first['message'] ) );
        $this->assertSame( [ 101 ], array_column( $this->calls, 'post_id' ), 'the poison page is not run again' );
    }

    public function test_unsupported_widget_types_are_collected_on_the_job(): void {
        $ids = $this->seedMany( 2 );
        $this->canned[100] = [ 'title' => 'a', 'post_id' => 1, 'success' => true, 'error' => '', 'report' => [], 'unsupported' => [ [ 'widgetType' => 'lottie' ] ] ];
        $this->canned[101] = [ 'title' => 'b', 'post_id' => 2, 'success' => true, 'error' => '', 'report' => [], 'unsupported' => [ [ 'widgetType' => 'lottie' ], [ 'elType' => 'hotspot' ] ] ];
        $id = $this->job( $ids );

        $this->service()->run_chunk( $id );

        $this->assertSame( [ 'lottie', 'hotspot' ], $this->store->get_job( $id )['unsupported'] );
    }

    public function test_a_finished_job_is_recorded_in_import_history_so_undo_covers_it(): void {
        $ids = $this->seedMany( 2 );
        $id  = $this->job( $ids );
        foreach ( $ids as $i ) { update_post_meta( $i + 5000, '_edc_import_source', 'direct' ); update_post_meta( $i + 5000, '_edc_source_post_id', $i ); }
        foreach ( $ids as $i ) { $GLOBALS['__test_posts'][ $i + 5000 ] = (object) [ 'ID' => $i + 5000, 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'c' ]; }

        $this->service()->run_chunk( $id );

        $run = ( new ImportHistory() )->find( 'job-' . $id );
        $this->assertNotNull( $run );
        $this->assertSame( [ 5100, 5101 ], $run['post_ids'] );
        $out = ( new ImportRollback() )->rollback( 'job-' . $id );
        $this->assertSame( 2, $out['trashed'] );
        $this->assertFalse( ConversionFlag::is_converted( 100 ), 'Undo puts the sources back on the pending list' );
    }

    public function test_a_running_job_with_no_heartbeat_for_two_minutes_reads_as_interrupted(): void {
        $id = $this->job( $this->seedMany( 2 ) );
        $svc = $this->service();
        $this->store->update_job( $id, [ 'status' => 'running', 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 30 ) ] );
        $this->assertSame( 'running', $svc->effective_status( $this->store->get_job( $id ) ) );

        $this->store->update_job( $id, [ 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ] );
        $this->assertSame( 'interrupted', $svc->effective_status( $this->store->get_job( $id ) ) );
    }

    public function test_a_finished_jobs_label_follows_its_failure_count_not_a_stale_stored_status(): void {
        $id  = $this->job( $this->seedMany( 2 ) );
        $svc = $this->service();
        $this->store->update_job( $id, [ 'status' => 'done', 'failed' => 1 ] );
        $this->assertSame( 'done_errors', $svc->effective_status( $this->store->get_job( $id ) ) );

        $this->store->update_job( $id, [ 'status' => 'done_errors', 'failed' => 0 ] );
        $this->assertSame( 'done', $svc->effective_status( $this->store->get_job( $id ) ) );
    }

    // ---- retry -----------------------------------------------------------------

    public function test_retrying_failed_items_reruns_only_those(): void {
        $ids = $this->seedMany( 3 );
        $this->canned[101] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => 'boom', 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );
        $svc = $this->service();
        $svc->run_chunk( $id );
        unset( $this->canned[101] );
        $this->calls = [];

        $reset = $svc->retry( $id, null );
        $svc->run_chunk( $id );

        $this->assertSame( 1, $reset );
        $this->assertSame( [ 101 ], array_column( $this->calls, 'post_id' ) );
        $job = $this->store->get_job( $id );
        $this->assertSame( 'done', $job['status'] );
        $this->assertSame( 3, $job['converted'] );
        $this->assertSame( 0, $job['failed'] );
    }

    public function test_retry_can_target_one_item(): void {
        $ids = $this->seedMany( 3 );
        $this->canned[100] = $this->canned[101] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => 'boom', 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );
        $svc = $this->service();
        $svc->run_chunk( $id );

        $this->assertSame( 1, $svc->retry( $id, [ 101 ] ) );
        $this->assertSame( 1, $this->store->counts_by_state( $id )['failed'] );
        $this->assertSame( 'pending', $this->store->get_job( $id )['status'], 'a job with work to do is not finished' );
    }

    public function test_resuming_continues_with_the_pending_items_of_an_interrupted_job(): void {
        $id = $this->job( $this->seedMany( 4 ) );
        $svc = $this->service( 3.0 );
        $svc->run_chunk( $id, 4.0 );
        $done_first = count( $this->calls );
        $this->store->update_job( $id, [ 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 900 ) ] );
        $this->assertSame( 'interrupted', $svc->effective_status( $this->store->get_job( $id ) ) );

        $guard = 0;
        do { $out = $svc->run_chunk( $id, 8.0 ); } while ( ! $out['done'] && ++$guard < 10 );

        $this->assertSame( 4, $this->store->get_job( $id )['converted'] );
        $this->assertCount( 4, $this->calls, 'nothing converted twice' );
        $this->assertGreaterThan( 0, $done_first );
    }

    // ---- marking by hand -------------------------------------------------------

    public function test_marking_a_page_converted_flags_it_and_resolves_its_open_items(): void {
        $ids = $this->seedMany( 2 );
        $this->canned[100] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => 'boom', 'report' => [], 'unsupported' => [] ];
        $id = $this->job( $ids );
        $svc = $this->service();
        $svc->run_chunk( $id );
        $this->assertSame( 'done_errors', $this->store->get_job( $id )['status'] );

        $n = $svc->mark_converted( [ 100 ] );

        $this->assertSame( 1, $n );
        $this->assertTrue( ConversionFlag::is_manual( 100 ) );
        $this->assertSame( 'converted', $this->store->list_items( $id, null, 1, 0 )[0]['state'] );
        $job = $this->store->get_job( $id );
        $this->assertSame( 0, $job['failed'] );
        $this->assertSame( 'done', $job['status'], 'with nothing failed or pending the job is complete' );
    }

    public function test_a_hand_marked_page_leaves_the_pending_list(): void {
        $this->seed( 11 );
        $svc = $this->service();
        $this->assertSame( 1, ( new FakePendingSource() )->count( [ 'page' ] ) );

        $svc->mark_converted( [ 11 ] );

        $this->assertSame( 0, ( new FakePendingSource() )->count( [ 'page' ] ) );
    }

    public function test_marking_ignores_posts_that_do_not_exist(): void {
        $this->assertSame( 0, $this->service()->mark_converted( [ 424242 ] ) );
        $this->assertFalse( ConversionFlag::is_converted( 424242 ) );
    }

    public function test_unmarking_puts_a_hand_marked_page_back_on_the_pending_list(): void {
        $this->seed( 11 );
        $svc = $this->service();
        $svc->mark_converted( [ 11 ] );

        $this->assertSame( 1, $svc->unmark_converted( [ 11 ] ) );

        $this->assertFalse( ConversionFlag::is_converted( 11 ) );
        $this->assertSame( 1, ( new FakePendingSource() )->count( [ 'page' ] ) );
    }

    public function test_taking_back_a_mark_is_reflected_in_the_job_that_had_counted_it(): void {
        $ids = $this->seedMany( 2 );
        $this->canned[100] = [ 'title' => 'x', 'post_id' => 0, 'success' => false, 'error' => 'boom', 'report' => [], 'unsupported' => [] ];
        $id  = $this->job( $ids );
        $svc = $this->service();
        $svc->run_chunk( $id );
        $svc->mark_converted( [ 100 ] );
        $this->assertSame( 'done', $this->store->get_job( $id )['status'] );

        $svc->unmark_converted( [ 100 ] );

        $first = $this->store->list_items( $id, null, 1, 0 )[0];
        $this->assertSame( 'skipped', $first['state'], 'no longer counted as converted' );
        $this->assertStringContainsString( 'mark', strtolower( $first['message'] ) );
        $job = $this->store->get_job( $id );
        $this->assertSame( 1, $job['converted'] );
        $this->assertSame( 1, $job['skipped'] );
        $this->assertSame( 'done', $job['status'] );
        $this->assertSame( 1, ( new FakePendingSource() )->count( [ 'page' ] ), 'and the page is pending again' );
    }

    public function test_taking_back_a_mark_does_not_rewrite_a_genuine_conversion_in_another_job(): void {
        $ids = $this->seedMany( 1 );
        $id  = $this->job( $ids );
        $this->service()->run_chunk( $id ); // genuinely converted, result_post_id > 0
        ConversionFlag::clear( 100 );
        $this->service()->mark_converted( [ 100 ] ); // later marked by hand
        $before = $this->store->list_items( $id, null, 1, 0 )[0];

        $this->service()->unmark_converted( [ 100 ] );

        $this->assertSame( $before, $this->store->list_items( $id, null, 1, 0 )[0] );
    }

    public function test_unmarking_leaves_a_genuine_conversion_alone(): void {
        $this->seed( 11 );
        ConversionFlag::mark( 11 );

        $this->assertSame( 0, $this->service()->unmark_converted( [ 11 ] ) );
        $this->assertTrue( ConversionFlag::is_converted( 11 ) );
    }
}
