<?php
// tests/JobsPendingViewTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\JobsPage;
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\JobService;

require_once __DIR__ . '/support/InMemoryJobStore.php';
require_once __DIR__ . '/support/FakePendingSource.php';

class JobsPendingViewTest extends TestCase {

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

    private function seed( int $n, string $type = 'page', int $from = 100 ): void {
        for ( $i = 0; $i < $n; $i++ ) {
            $id = $from + $i;
            $GLOBALS['__test_posts'][ $id ] = (object) [ 'ID' => $id, 'post_title' => "Page $id", 'post_type' => $type, 'post_status' => 'publish', 'post_modified' => '2026-10-01 10:00:00' ];
            update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        }
    }

    private function pro(): void {
        add_filter( 'edc_direct_conversion_limit', fn() => PHP_INT_MAX );
    }

    private function section( array $args = [] ): string {
        return $this->page->render_pending_section( array_merge( [ 'types' => [ 'page', 'post' ], 'search' => '', 'paged' => 1 ], $args ) );
    }

    // ---- the list ------------------------------------------------------------

    public function test_it_lists_pending_pages_with_a_checkbox_and_a_link_to_edit_each(): void {
        $this->seed( 2 );

        $html = $this->section();

        $this->assertStringContainsString( 'Page 100', $html );
        $this->assertStringContainsString( 'Page 101', $html );
        $this->assertStringContainsString( 'name="post_ids[]" value="100"', $html );
        $this->assertStringContainsString( 'post=100', $html );
    }

    public function test_converted_pages_are_not_listed(): void {
        $this->seed( 2 );
        ConversionFlag::mark( 100 );

        $html = $this->section();

        $this->assertStringNotContainsString( 'Page 100<', $html );
        $this->assertStringContainsString( 'Page 101', $html );
    }

    public function test_it_is_a_collapsible_section_open_by_default(): void {
        $this->seed( 1 );

        $this->assertMatchesRegularExpression( '/<details[^>]*class="edc-card edc-pending"[^>]*\sopen/', $this->section() );
    }

    public function test_titles_are_escaped(): void {
        $this->seed( 1 );
        $GLOBALS['__test_posts'][100]->post_title = '<script>alert(1)</script>';

        $this->assertStringNotContainsString( '<script>', $this->section() );
    }

    public function test_post_type_chips_show_how_many_are_pending_of_each(): void {
        $this->seed( 3, 'page' );
        $this->seed( 2, 'post', 200 );

        $html = $this->section();

        $this->assertMatchesRegularExpression( '/data-edc-type="page"[^>]*>[^<]*page[^<]*<[^>]*>3</i', $html );
        $this->assertMatchesRegularExpression( '/data-edc-type="post"[^>]*>[^<]*post[^<]*<[^>]*>2</i', $html );
        $this->assertStringContainsString( 'data-edc-type=""', $html, 'an All chip' );
    }

    public function test_choosing_a_post_type_narrows_the_list(): void {
        $this->seed( 2, 'page' );
        $this->seed( 1, 'post', 200 );

        $html = $this->section( [ 'types' => [ 'post' ] ] );

        $this->assertStringContainsString( 'Page 200', $html );
        $this->assertStringNotContainsString( 'Page 100<', $html );
    }

    public function test_search_narrows_by_title(): void {
        $this->seed( 3 );

        $html = $this->section( [ 'search' => '101' ] );

        $this->assertStringContainsString( 'Page 101', $html );
        $this->assertStringNotContainsString( 'Page 100<', $html );
    }

    public function test_the_list_is_paginated(): void {
        $this->seed( 120 );

        $page1 = $this->section();
        $page3 = $this->section( [ 'paged' => 3 ] );

        $this->assertStringContainsString( 'Page 100<', $page1 );
        $this->assertStringNotContainsString( 'Page 150<', $page1 );
        $this->assertStringContainsString( 'data-edc-pending-page="2"', $page1 );
        $this->assertStringContainsString( 'Page 219<', $page3 );
        $this->assertStringContainsString( 'Page 200<', $page3 );
    }

    public function test_when_nothing_is_pending_it_says_so(): void {
        $html = $this->section();

        $this->assertStringContainsString( 'Nothing is waiting', $html );
        $this->assertStringNotContainsString( 'data-edc-action="convert-selected"', $html );
    }

    public function test_it_explains_where_failed_pages_went(): void {
        $this->seed( 1 );
        $id = $this->store->create_job( [ 'scope' => [], 'mode' => 'copy' ] );
        $this->store->add_items( $id, [ [ 'post_id' => 555, 'post_type' => 'page', 'title' => 'Broken' ], [ 'post_id' => 556, 'post_type' => 'page', 'title' => 'Broken 2' ] ] );

        $this->store->set_item_result( $id, 555, [ 'state' => 'failed', 'message' => 'x' ] );
        $one = strtolower( $this->section() );
        $this->assertStringContainsString( '1 page failed', $one );
        $this->assertStringContainsString( 'retry it from its job', $one );

        $this->store->set_item_result( $id, 556, [ 'state' => 'failed', 'message' => 'x' ] );
        $two = strtolower( $this->section() );
        $this->assertStringContainsString( '2 pages failed', $two );
        $this->assertStringContainsString( 'retry them from their job', $two );
    }

    // ---- free vs Pro ---------------------------------------------------------

    public function test_free_sees_an_inline_pro_upsell_that_quotes_the_pending_count(): void {
        $this->seed( 812 );

        $html = $this->section();

        $this->assertStringContainsString( 'Convert all 812 remaining with Pro', $html );
        $this->assertStringContainsString( 'divi5lab.com/plugins/elementor-to-divi-5', $html );
        $this->assertStringNotContainsString( 'data-edc-action="convert-all"', $html, 'the gated action must not be a live button' );
    }

    public function test_free_can_still_convert_a_single_selected_page(): void {
        $this->seed( 3 );

        $html = $this->section();

        $this->assertStringContainsString( 'data-edc-action="convert-selected"', $html );
        $this->assertStringContainsString( 'data-limit="1"', $html );
    }

    public function test_pro_gets_a_working_convert_all_button_with_the_count(): void {
        $this->pro();
        $this->seed( 812 );

        $html = $this->section();

        $this->assertStringContainsString( 'data-edc-action="convert-all"', $html );
        $this->assertStringContainsString( 'Convert all 812 remaining', $html );
        $this->assertStringContainsString( 'data-count="812"', $html );
        $this->assertStringNotContainsString( 'with Pro', $html );
    }

    public function test_one_pending_page_needs_no_upsell_even_on_free(): void {
        $this->seed( 1 );

        $html = $this->section();

        $this->assertStringContainsString( 'data-edc-action="convert-all"', $html );
        $this->assertStringNotContainsString( 'with Pro', $html );
    }

    // ---- the start panel -----------------------------------------------------

    public function test_the_start_panel_asks_for_a_mode_and_warns_before_replacing(): void {
        $this->pro();
        $this->seed( 3 );

        $html = $this->section();

        $this->assertStringContainsString( 'id="edc-start-panel"', $html );
        $this->assertMatchesRegularExpression( '/name="edc_mode"[^>]*value="copy"/', $html );
        $this->assertMatchesRegularExpression( '/name="edc_mode"[^>]*value="replace"/', $html );
        $this->assertStringContainsString( 'name="edc_confirm"', $html );
        $this->assertStringContainsString( 'backup', strtolower( $html ) );
        $this->assertStringContainsString( 'hidden', $html, 'the panel starts closed' );
    }

    public function test_the_replace_warning_is_accurate_about_what_undo_can_and_cannot_do(): void {
        $this->pro();
        $this->seed( 3 );

        $html = strtolower( $this->section() );

        $this->assertStringContainsString( 'undo restores', $html );
        $this->assertStringContainsString( 'edits you make in divi afterwards are lost', $html );
    }

    // ---- marking by hand -------------------------------------------------------

    public function test_each_pending_row_can_be_marked_converted(): void {
        $this->seed( 2 );

        $html = $this->section();

        $this->assertSame( 2, substr_count( $html, 'data-edc-action="mark-converted"' ) );
        $this->assertStringContainsString( 'data-post-id="100"', $html );
    }

    public function test_selected_pending_pages_can_be_marked_in_bulk(): void {
        $this->seed( 2 );

        $this->assertStringContainsString( 'data-edc-action="mark-selected"', $this->section() );
    }
}
