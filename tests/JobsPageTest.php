<?php
// tests/JobsPageTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\JobsPage;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\JobService;

require_once __DIR__ . '/support/InMemoryJobStore.php';
require_once __DIR__ . '/support/FakePendingSource.php';

class JobsPageTest extends TestCase {

    private InMemoryJobStore $store;
    private JobsPage $page;

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['__test_options']  = [];
        $this->store = new InMemoryJobStore();
        $pending     = new FakePendingSource();
        $this->page  = new JobsPage( $this->store, new JobService( $this->store, $pending, fn() => [] ), $pending );
    }

    private function job( string $status, array $over = [], array $items = [] ): int {
        $id = $this->store->create_job( [ 'scope' => [ 'type' => 'remaining', 'post_types' => [ 'page' ] ], 'mode' => 'copy' ] );
        $this->store->add_items( $id, $items ?: [ [ 'post_id' => 11, 'post_type' => 'page', 'title' => 'Home' ] ] );
        $this->store->update_job( $id, array_merge( [ 'status' => $status, 'heartbeat' => gmdate( 'Y-m-d H:i:s' ) ], $over ) );

        return $id;
    }

    private function html_for( int $id ): string {
        return $this->page->render_job( $this->store->get_job( $id ) );
    }

    // ---- collapsing ----------------------------------------------------------

    public function test_finished_jobs_are_collapsed(): void {
        foreach ( [ 'done', 'done_errors' ] as $status ) {
            $this->assertDoesNotMatchRegularExpression( '/<details[^>]*\sopen[\s>]/', $this->html_for( $this->job( $status ) ), $status );
        }
    }

    public function test_unfinished_jobs_are_expanded(): void {
        $stale = gmdate( 'Y-m-d H:i:s', time() - 900 );
        foreach ( [ [ 'running', [] ], [ 'pending', [] ], [ 'running', [ 'heartbeat' => $stale ] ] ] as [ $status, $over ] ) {
            $this->assertMatchesRegularExpression( '/<details[^>]*\sopen[\s>]/', $this->html_for( $this->job( $status, $over ) ), $status . json_encode( $over ) );
        }
    }

    public function test_a_stale_running_job_is_labelled_interrupted(): void {
        $id = $this->job( 'running', [ 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 900 ) ] );

        $html = $this->html_for( $id );

        $this->assertStringContainsString( 'Interrupted', $html );
        $this->assertStringContainsString( 'edc-job--interrupted', $html );
    }

    // ---- summary -------------------------------------------------------------

    public function test_the_summary_shows_progress_and_counts(): void {
        $id = $this->job( 'running', [ 'total' => 200, 'converted' => 150, 'skipped' => 5, 'failed' => 3 ] );

        $html = $this->html_for( $id );

        $this->assertStringContainsString( 'Job #' . $id, $html );
        $this->assertStringContainsString( '155 of 200', $html, 'converted + skipped are the finished pages' );
        $this->assertStringContainsString( '3 failed', $html );
        $this->assertStringContainsString( 'width:77%', $html );
    }

    public function test_the_summary_says_what_the_job_covers_and_how(): void {
        $remaining = $this->html_for( $this->job( 'done' ) );
        $this->assertStringContainsString( 'copies', strtolower( $remaining ) );

        $id = $this->store->create_job( [ 'scope' => [ 'type' => 'ids', 'post_ids' => [ 1 ], 'post_types' => [ 'page' ] ], 'mode' => 'replace' ] );
        $this->store->update_job( $id, [ 'status' => 'done' ] );
        $this->assertStringContainsString( 'replaced', strtolower( $this->html_for( $id ) ) );
    }

    // ---- job actions ---------------------------------------------------------

    public function test_an_interrupted_job_offers_resume(): void {
        $id = $this->job( 'running', [ 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 900 ) ] );

        $this->assertStringContainsString( 'data-edc-action="resume"', $this->html_for( $id ) );
    }

    public function test_a_finished_clean_job_offers_neither_resume_nor_retry(): void {
        $html = $this->html_for( $this->job( 'done', [ 'total' => 1, 'converted' => 1 ] ) );

        $this->assertStringNotContainsString( 'data-edc-action="resume"', $html );
        $this->assertStringNotContainsString( 'data-edc-action="retry-failed"', $html );
    }

    public function test_failures_offer_retry_failed_with_the_count(): void {
        $id = $this->job( 'done_errors', [ 'total' => 5, 'converted' => 3, 'failed' => 2 ] );

        $html = $this->html_for( $id );

        $this->assertStringContainsString( 'data-edc-action="retry-failed"', $html );
        $this->assertStringContainsString( 'Retry 2 failed', $html );
    }

    public function test_a_finished_job_offers_undo_until_it_is_undone(): void {
        $id = $this->job( 'done', [ 'total' => 1, 'converted' => 1 ] );
        ( new ImportHistory() )->record( 'job-' . $id, [ [ 'success' => true, 'post_id' => 5011, 'unsupported' => [] ] ] );
        $this->assertStringContainsString( 'edc_rollback=job-' . $id, $this->html_for( $id ) );

        ( new ImportHistory() )->mark_rolled_back( 'job-' . $id );
        $this->assertStringNotContainsString( 'edc_rollback=', $this->html_for( $id ) );
        $this->assertStringContainsString( 'Undone', $this->html_for( $id ) );
    }

    // ---- listing + filters ---------------------------------------------------

    public function test_the_list_is_newest_first(): void {
        $a = $this->job( 'done' );
        $b = $this->job( 'done' );

        $html = $this->page->render_jobs_list( [], 1 );

        $this->assertLessThan( strpos( $html, 'Job #' . $a ), strpos( $html, 'Job #' . $b ) );
    }

    public function test_the_list_can_be_filtered_by_status_and_post_type(): void {
        $done = $this->job( 'done' );
        $this->job( 'done_errors' );
        $post = $this->job( 'done', [], [ [ 'post_id' => 40, 'post_type' => 'post', 'title' => 'News' ] ] );

        $this->assertStringNotContainsString( 'Job #' . $done, $this->page->render_jobs_list( [ 'status' => 'done_errors' ], 1 ) );
        $by_type = $this->page->render_jobs_list( [ 'post_type' => 'post' ], 1 );
        $this->assertStringContainsString( 'Job #' . $post, $by_type );
        $this->assertStringNotContainsString( 'Job #' . $done, $by_type );
    }

    public function test_the_interrupted_filter_means_running_with_a_stale_heartbeat(): void {
        $live  = $this->job( 'running' );
        $stale = $this->job( 'running', [ 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 900 ) ] );

        $html = $this->page->render_jobs_list( [ 'status' => 'interrupted' ], 1 );

        $this->assertStringContainsString( 'Job #' . $stale, $html );
        $this->assertStringNotContainsString( 'Job #' . $live, $html );
        $this->assertStringContainsString( 'Job #' . $live, $this->page->render_jobs_list( [ 'status' => 'running' ], 1 ) );
    }

    public function test_the_list_can_be_filtered_by_date(): void {
        $this->job( 'done' );

        $this->assertStringContainsString( 'Job #', $this->page->render_jobs_list( [ 'date_from' => gmdate( 'Y-m-d' ) ], 1 ) );
        $this->assertStringNotContainsString( 'Job #', $this->page->render_jobs_list( [ 'date_from' => gmdate( 'Y-m-d', time() + 86400 ) ], 1 ) );
    }

    public function test_an_empty_list_says_so(): void {
        $this->assertStringContainsString( 'No conversion jobs yet', $this->page->render_jobs_list( [], 1 ) );
        $this->job( 'done' );
        $this->assertStringContainsString( 'No jobs match', $this->page->render_jobs_list( [ 'status' => 'pending' ], 1 ) );
    }

    public function test_filters_from_the_request_are_whitelisted(): void {
        $f = JobsPage::filters_from( [ 'status' => 'bogus', 'post_type' => 'pa<ge>', 'date_from' => '2026-13-45', 'date_to' => '2026-10-02' ] );

        $this->assertArrayNotHasKey( 'status', $f );
        $this->assertSame( 'page', $f['post_type'] );
        $this->assertArrayNotHasKey( 'date_from', $f, 'an impossible date is dropped' );
        $this->assertSame( '2026-10-02', $f['date_to'] );
        $this->assertSame( 'interrupted', JobsPage::filters_from( [ 'status' => 'interrupted' ] )['status'] );
    }

    // ---- per-item results ----------------------------------------------------

    private function items_html( array $item, string $mode = 'copy' ): string {
        $id = $this->job( 'done', [ 'mode' => $mode ], [ [ 'post_id' => $item['post_id'], 'post_type' => $item['post_type'] ?? 'page', 'title' => $item['title'] ?? 'Home' ] ] );
        $this->store->set_item_result( $id, $item['post_id'], $item );
        foreach ( [ $item['post_id'], $item['result_post_id'] ?? 0 ] as $pid ) {
            if ( $pid ) { $GLOBALS['__test_posts'][ $pid ] = (object) [ 'ID' => $pid, 'post_title' => 'x', 'post_type' => 'page', 'post_status' => 'draft' ]; }
        }

        return $this->page->render_items( $id, null, 1 );
    }

    public function test_a_converted_item_links_to_edit_view_and_the_divi_5_builder(): void {
        $html = $this->items_html( [ 'post_id' => 11, 'state' => 'converted', 'result_post_id' => 900, 'title' => 'Home' ] );

        $this->assertStringContainsString( 'Home', $html );
        $this->assertStringContainsString( 'post=900', $html, 'edit link points at the converted copy' );
        $this->assertStringContainsString( 'et_fb=1', $html, 'Divi 5 Visual Builder link' );
        $this->assertStringContainsString( 'Open in Divi 5 builder', $html );
    }

    public function test_the_builder_link_has_to_target_the_converted_page(): void {
        $html = $this->items_html( [ 'post_id' => 11, 'state' => 'converted', 'result_post_id' => 900 ] );

        $this->assertMatchesRegularExpression( '/p=900[^"]*et_fb=1|et_fb=1[^"]*p=900/', $html );
    }

    public function test_a_replaced_item_links_to_the_page_itself(): void {
        $html = $this->items_html( [ 'post_id' => 11, 'state' => 'converted', 'result_post_id' => 11 ], 'replace' );

        $this->assertStringContainsString( 'post=11', $html );
    }

    public function test_item_rows_show_post_type_and_state(): void {
        $html = $this->items_html( [ 'post_id' => 40, 'post_type' => 'post', 'state' => 'converted', 'result_post_id' => 41, 'title' => 'News' ] );

        $this->assertStringContainsString( '>post<', $html );
        $this->assertStringContainsString( 'edc-state--converted', $html );
    }

    public function test_a_failed_item_shows_its_error_and_a_retry_control(): void {
        $html = $this->items_html( [ 'post_id' => 11, 'state' => 'failed', 'message' => 'Out of memory' ] );

        $this->assertStringContainsString( 'Out of memory', $html );
        $this->assertStringContainsString( 'data-edc-action="retry-item"', $html );
        $this->assertStringContainsString( 'data-post-id="11"', $html );
        $this->assertStringNotContainsString( 'et_fb=1', $html, 'nothing was built, so there is nothing to open' );
    }

    public function test_item_titles_and_messages_are_escaped(): void {
        $html = $this->items_html( [ 'post_id' => 11, 'state' => 'failed', 'message' => '<img src=x onerror=alert(1)>', 'title' => '<script>alert(1)</script>' ] );

        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '<img src=x', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_items_are_paginated_and_filterable_by_state(): void {
        $items = [];
        for ( $i = 1; $i <= 120; $i++ ) { $items[] = [ 'post_id' => $i, 'post_type' => 'page', 'title' => "P$i" ]; }
        $id = $this->job( 'done', [], $items );
        $this->store->set_item_result( $id, 7, [ 'state' => 'failed', 'message' => 'x' ] );

        $page1 = $this->page->render_items( $id, null, 1 );
        $page3 = $this->page->render_items( $id, null, 3 );
        $failed = $this->page->render_items( $id, 'failed', 1 );

        $this->assertStringContainsString( 'P1<', $page1 );
        $this->assertStringNotContainsString( 'P51<', $page1 );
        $this->assertStringContainsString( 'P101<', $page3 );
        $this->assertStringContainsString( 'data-edc-page="2"', $page1, 'a next-page control' );
        $this->assertStringContainsString( 'P7<', $failed );
        $this->assertStringNotContainsString( 'P8<', $failed );
    }

    // ---- sticky summary bar --------------------------------------------------

    public function test_the_bar_reports_converted_pending_and_failed(): void {
        foreach ( [ 1, 2, 3, 4 ] as $i ) {
            $GLOBALS['__test_posts'][ $i ] = (object) [ 'ID' => $i, 'post_title' => "P$i", 'post_type' => 'page', 'post_status' => 'publish' ];
            update_post_meta( $i, '_elementor_edit_mode', 'builder' );
        }
        ConversionFlag::mark( 1 );
        $job = $this->job( 'done_errors', [], [ [ 'post_id' => 2, 'post_type' => 'page', 'title' => 'P2' ] ] );
        $this->store->set_item_result( $job, 2, [ 'state' => 'failed', 'message' => 'x' ] );

        $bar = $this->page->bar_data();

        $this->assertSame( 1, $bar['converted'] );
        $this->assertSame( 3, $bar['pending'], 'the fake source has no failed-exclusion; the SQL one does' );
        $this->assertSame( 1, $bar['failed'] );
        $html = $this->page->render_bar();
        $this->assertStringContainsString( 'edc-bar', $html );
        $this->assertStringContainsString( 'data-edc-bar="converted"', $html );
        $this->assertStringContainsString( 'data-edc-bar="pending"', $html );
        $this->assertStringContainsString( 'data-edc-bar="failed"', $html );
    }
}
