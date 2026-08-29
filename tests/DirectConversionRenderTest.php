<?php
// tests/DirectConversionRenderTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\AdminPage;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Admin\ElementorPageRepository;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\History\ImportHistory;

class DirectConversionRenderTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['__test_transients'] = [];
        $GLOBALS['__test_redirects'] = [];
        $GLOBALS['__test_caps'] = true;
        $_POST = [];
        $_GET  = [];
    }

    private function repo_with( array $rows ): ElementorPageRepository {
        return new ElementorPageRepository( fn( array $args ) => $rows );
    }

    private function row( int $id, string $title ): object {
        return (object) [
            'ID' => $id, 'post_title' => $title, 'post_type' => 'page',
            'post_status' => 'publish', 'post_modified' => '2026-08-01 09:00:00',
        ];
    }

    /** Seeds a real Elementor-built post so selected_post_ids() will accept it. */
    private function seed( int $id, string $title = 'Home' ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID' => $id, 'post_title' => $title, 'post_name' => 'home',
            'post_type' => 'page', 'post_status' => 'publish',
            'post_modified' => '2026-08-01 00:00:00',
        ];
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $id, '_elementor_data', wp_json_encode( [ [
            'elType'   => 'section',
            'elements' => [ [
                'elType'   => 'column',
                'elements' => [ [
                    'elType' => 'widget', 'widgetType' => 'heading',
                    'settings' => [ 'title' => 'Hi' ],
                ] ],
            ] ],
        ] ] ) );
        return $id;
    }

    public function test_the_picker_lists_each_elementor_page(): void {
        $page = new DirectConversionPage( $this->repo_with( [
            $this->row( 11, 'Home' ), $this->row( 12, 'About' ),
        ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'Home', $html );
        $this->assertStringContainsString( 'About', $html );
        $this->assertStringContainsString( 'value="11"', $html );
    }

    public function test_the_picker_offers_radios_at_the_free_limit(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'type="radio"', $html );
        $this->assertStringNotContainsString( 'type="checkbox"', $html );
    }

    public function test_the_picker_offers_checkboxes_when_the_limit_is_raised(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 50 );
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'type="checkbox"', $html );
    }

    public function test_the_picker_carries_the_check_nonce_and_action(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( DirectConversionPage::CHECK_ACTION, $html );
        $this->assertStringContainsString( DirectConversionPage::CHECK_NONCE, $html );
    }

    public function test_an_empty_picker_explains_itself(): void {
        $html = ( new DirectConversionPage( $this->repo_with( [] ) ) )->render_picker();

        $this->assertStringNotContainsString( 'type="radio"', $html );
        $this->assertStringContainsString( 'Elementor', $html );
    }

    public function test_a_converted_page_is_badged(): void {
        $GLOBALS['__test_posts'][ 99 ] = (object) [ 'ID' => 99, 'post_type' => 'page' ];
        update_post_meta( 99, '_edc_source_post_id', 11 );

        $html = ( new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) ) )->render_picker();

        $this->assertStringContainsString( 'edc-badge-converted', $html );
    }

    public function test_the_search_term_reaches_the_repository_query(): void {
        $seen = null;
        $repo = new ElementorPageRepository( function ( array $args ) use ( &$seen ) {
            $seen = $args;
            return [ $this->row( 11, 'Home' ) ];
        } );

        ( new DirectConversionPage( $repo ) )->render_picker( [ 'search' => 'contact' ] );

        $this->assertSame( 'contact', $seen['s'] ?? null, 'the search term must reach the repository query args' );
    }

    public function test_the_search_box_renders_and_preserves_its_value(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker( [ 'search' => 'About Us <script>' ] );

        $this->assertStringContainsString( 'name="edc_s"', $html );
        $this->assertStringContainsString( 'value="About Us &lt;script&gt;"', $html, 'the search value must be escaped and preserved' );
    }

    public function test_no_pager_appears_when_a_single_page_of_rows_is_returned(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringNotContainsString( 'edc-direct-pager', $html );
    }

    public function test_the_pager_appears_when_there_are_more_rows_than_a_page(): void {
        $rows = [];
        for ( $i = 1; $i <= ElementorPageRepository::PER_PAGE + 1; $i++ ) {
            $rows[] = $this->row( $i, 'Page ' . $i );
        }

        $page = new DirectConversionPage( $this->repo_with( $rows ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'edc-direct-pager', $html );
        $this->assertStringContainsString( 'Next', $html );
        // Only a full page of rows is shown; the extra probe row is trimmed.
        $this->assertStringNotContainsString( 'value="' . ( ElementorPageRepository::PER_PAGE + 1 ) . '"', $html );
    }

    public function test_the_pager_preserves_the_search_term_and_the_page_slug(): void {
        $rows = [];
        for ( $i = 1; $i <= ElementorPageRepository::PER_PAGE + 1; $i++ ) {
            $rows[] = $this->row( $i, 'Page ' . $i );
        }

        $page = new DirectConversionPage( $this->repo_with( $rows ) );

        $html = $page->render_picker( [ 'search' => 'contact', 'paged' => 2 ] );

        $this->assertStringContainsString( 'page=edc-converter', $html );
        $this->assertStringContainsString( 'edc_s=contact', $html );
        $this->assertStringContainsString( 'Previous', $html, 'page 2 of results must offer a way back to page 1' );
    }

    /**
     * Every other pager test above uses a stub repository that ignores
     * per_page/paged entirely and just returns whatever rows it was built
     * with — so none of them can catch an offset bug. This stub instead
     * behaves the way a real WP_Query would: it honours whatever args it is
     * handed, computing the offset from an explicit 'offset' key if present,
     * or from posts_per_page * (paged - 1) otherwise (WP_Query's own
     * fallback when no offset is given).
     *
     * render_picker() asks for 21 rows (PER_PAGE + 1) purely to detect
     * whether a next page exists. If that inflated count is also used to
     * derive the offset, the offset advances by 21 per page while only 20
     * rows are ever displayed — silently skipping one row (page 21 on a
     * real site) every time the user turns the page.
     */
    public function test_paging_never_skips_or_repeats_a_row(): void {
        $all = [];
        for ( $i = 1; $i <= 25; $i++ ) {
            $all[] = $this->row( $i, 'Page ' . $i );
        }

        $repo = new ElementorPageRepository( function ( array $args ) use ( $all ) {
            $per_page = (int) $args['posts_per_page'];
            $offset   = array_key_exists( 'offset', $args )
                ? (int) $args['offset']
                : $per_page * ( max( 1, (int) $args['paged'] ) - 1 );

            return array_slice( $all, $offset, $per_page );
        } );

        $page = new DirectConversionPage( $repo );

        $html_page_1 = $page->render_picker();
        $html_page_2 = $page->render_picker( [ 'paged' => 2 ] );

        for ( $i = 1; $i <= 20; $i++ ) {
            $this->assertStringContainsString( 'value="' . $i . '"', $html_page_1, "row $i must appear on page 1" );
            $this->assertStringNotContainsString( 'value="' . $i . '"', $html_page_2, "row $i must not repeat on page 2" );
        }

        for ( $i = 21; $i <= 25; $i++ ) {
            $this->assertStringNotContainsString( 'value="' . $i . '"', $html_page_1, "row $i must not leak onto page 1" );
            $this->assertStringContainsString( 'value="' . $i . '"', $html_page_2, "row $i must appear on page 2 — a skipped row here is exactly the offset bug this test exists to catch" );
        }
    }

    public function test_the_report_shows_the_outline_and_the_convert_button(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'   => 'Home',
            'outline' => [ [
                'type' => 'section', 'name' => 'divi/section', 'label' => 'Section',
                'unsupported' => false, 'children' => [],
            ] ],
            'report'  => [ 'converted' => [ 'heading' => 2 ], 'warnings' => [] ],
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'edc-outline', $html );
        $this->assertStringContainsString( 'Section', $html );
        $this->assertStringContainsString( DirectConversionPage::CONVERT_ACTION, $html );
        $this->assertStringContainsString( 'value="11"', $html );
    }

    public function test_the_report_names_unsupported_widgets(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'       => 'Home',
            'unsupported' => [ [ 'id' => 'a1', 'elType' => 'widget', 'widgetType' => 'slider_revolution' ] ],
            'source_ref'  => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'slider_revolution', $html );
    }

    public function test_the_report_surfaces_a_failed_item(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title' => 'Broken', 'error' => 'No Elementor content found.',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'No Elementor content found.', $html );
    }

    public function test_the_report_says_when_the_selection_was_truncated(): void {
        $plan = new ConversionPlan(
            [ ConversionPlan::item( [ 'title' => 'One', 'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ] ] ) ],
            1,
            true
        );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'Pro', $html );
    }

    public function test_the_report_never_promises_a_visual_preview(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title' => 'Home',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = strtolower( ( new DirectConversionPage() )->render_report( $plan ) );

        // The screen reports structure. Claiming a rendered preview would be the
        // one overpromise this design deliberately avoids.
        $this->assertStringNotContainsString( 'visual preview', $html );
    }

    public function test_the_report_states_the_source_page_is_left_untouched(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title' => 'Home',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        // This is the reassurance that makes people willing to try the button:
        // it must be stated, not merely true and left implicit.
        $this->assertStringContainsString( 'new Divi draft', $html );
        $this->assertStringContainsString( 'left exactly as it is', $html );
    }

    // ------------------------------------------------------------------
    // Handlers: handle_check() and handle_convert(), reached only through
    // maybe_handle_request() so the capability/nonce gate is exercised too.
    //
    // Both handlers end in `wp_safe_redirect(); exit;` in production, exactly
    // like AdminPage::handle_import(). A literal exit() inside this
    // (non-process-isolated) test run would kill the whole suite, so these
    // tests use a subclass that records the intended redirect instead of
    // exiting. Production is unaffected: DirectConversionPage::redirect() is
    // only ever overridden here, in tests.
    // ------------------------------------------------------------------

    /** @return DirectConversionPage&object{redirected_to: array} */
    private function non_exiting_page(): DirectConversionPage {
        return new class() extends DirectConversionPage {
            public array $redirected_to = [];
            protected function redirect( string $location ): void {
                $this->redirected_to[] = $location;
            }
        };
    }

    public function test_handle_check_redirects_to_the_direct_report_screen(): void {
        $id = $this->seed( 301 );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $this->assertNotEmpty( $page->redirected_to, 'handle_check must redirect' );
        $location = end( $page->redirected_to );
        $this->assertStringContainsString( 'page=edc-converter', $location );
        // Referencing the constant, not re-typing the literal: a one-side typo
        // between here and AdminPage's router would fail this line.
        $this->assertStringContainsString( 'action=' . AdminPage::VIEW_DIRECT_REPORT, $location );
    }

    /**
     * Closes the loop the previous test cannot: it is not enough that
     * handle_check() redirects to *a* string that happens to match the
     * constant — the actual router in AdminPage::render_page() must accept
     * that exact value and render the report screen, not silently fall
     * through to the list view.
     */
    public function test_the_redirect_action_is_exactly_what_the_router_branches_on(): void {
        $id = $this->seed( 310 );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $location = end( $page->redirected_to );
        parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );

        $this->assertSame( AdminPage::VIEW_DIRECT_REPORT, $query['action'] ?? null );

        // Feed that exact redirect back into the real router.
        $_GET = $query;

        ob_start();
        ( new AdminPage() )->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Conversion report', $html, 'the router must render the report screen for the action handle_check() redirects to' );
    }

    public function test_handle_check_stashes_the_verified_selection_for_the_report_screen(): void {
        $id = $this->seed( 302 );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $this->non_exiting_page()->maybe_handle_request();

        $stashed = get_transient( DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id() );
        $this->assertSame( [ $id ], $stashed, 'the report screen must be able to recover exactly what was checked' );
    }

    /**
     * End-to-end proof that the "truncated" notice is actually reachable: a
     * selection over the free limit must be stashed UNCAPPED by
     * handle_check() (so ConversionPreflight::run() — not the request
     * handler — is the thing that slices it and sets truncated()), and the
     * report screen the real router renders for that exact redirect must
     * show the notice text, not merely have the flag computed and dropped.
     *
     * If selected_post_ids() (which caps) were used here instead of
     * verified_post_ids(), the stash would hold only 1 id and this test
     * would fail on the assertCount() below before ever reaching the HTML
     * assertion.
     */
    public function test_a_selection_over_the_limit_reaches_the_report_as_truncated(): void {
        $a = $this->seed( 350 );
        $b = $this->seed( 351 );
        $c = $this->seed( 352 );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => [ (string) $a, (string) $b, (string) $c ],
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $stashed = get_transient( DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id() );
        $this->assertCount( 3, $stashed, 'the check handler must stash every verified id, not just the first one' );

        $plan = ( new DirectConversionPage() )->plan_for( $stashed );
        $this->assertTrue( $plan->truncated(), 'a 3-page selection against a 1-page limit must be reported as truncated' );

        // Now follow the exact redirect into the real router, the way a
        // browser would, and check the actual rendered markup.
        $location = end( $page->redirected_to );
        parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
        $_GET = $query;

        ob_start();
        ( new AdminPage() )->render_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Conversion report', $html );
        $this->assertStringContainsString(
            'Pro feature',
            $html,
            'the rendered report must actually show the truncation notice, not just compute the flag'
        );
    }

    /**
     * Mirrors handle_convert()'s existing empty-selection guard: a stale
     * form or tampered request must not be allowed to stash an empty
     * selection over a previously stashed valid one.
     */
    public function test_handle_check_refuses_an_empty_selection_without_clobbering_a_stashed_one(): void {
        $id = $this->seed( 360 );

        set_transient(
            DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id(),
            [ $id ],
            HOUR_IN_SECONDS
        );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => [],
        ];

        $page = $this->non_exiting_page();
        $this->expectException( \RuntimeException::class );

        try {
            $page->maybe_handle_request();
        } finally {
            $stashed = get_transient( DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id() );
            $this->assertSame(
                [ $id ],
                $stashed,
                'an empty check submission must not evict a previously stashed valid selection'
            );
            $this->assertEmpty( $page->redirected_to, 'an empty selection must not redirect to the report screen' );
        }
    }

    public function test_handle_convert_redirects_to_the_existing_batch_result_screen(): void {
        $id = $this->seed( 303 );

        $_POST = [
            'action'       => DirectConversionPage::CONVERT_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $this->assertNotEmpty( $page->redirected_to );
        $location = end( $page->redirected_to );
        $this->assertStringContainsString( 'action=batch_result', $location );
        $this->assertStringContainsString( 'import_id=', $location );
    }

    public function test_handle_convert_writes_a_batch_transient_the_result_screen_can_read(): void {
        $id = $this->seed( 304 );

        $_POST = [
            'action'       => DirectConversionPage::CONVERT_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $location = end( $page->redirected_to );
        preg_match( '/import_id=([a-z0-9-]+)/i', $location, $m );
        $this->assertNotEmpty( $m, 'redirect must carry an import_id' );

        $results = get_transient( 'edc_batch_' . $m[1] );
        $this->assertIsArray( $results );
        $this->assertTrue( $results[0]['success'] );
    }

    public function test_handle_convert_records_the_run_in_import_history_like_an_upload_would(): void {
        $id = $this->seed( 305 );

        $_POST = [
            'action'       => DirectConversionPage::CONVERT_ACTION,
            'edc_post_ids' => (string) $id,
        ];

        $page = $this->non_exiting_page();
        $page->maybe_handle_request();

        $location = end( $page->redirected_to );
        preg_match( '/import_id=([a-z0-9-]+)/i', $location, $m );

        // This is the guarantee that matters: Undo finds a run only via
        // ImportHistory. If a direct conversion did not land here, one-click
        // Undo would silently not exist for it.
        $run = ( new ImportHistory() )->find( $m[1] );
        $this->assertNotNull( $run, 'a direct conversion must be recorded in ImportHistory exactly as an upload is' );
        $this->assertNotEmpty( $run['post_ids'] );
        $this->assertFalse( $run['rolled_back'] );
    }

    public function test_handle_check_refuses_a_user_without_the_capability(): void {
        $GLOBALS['__test_caps'] = false;
        $this->seed( 306 );

        $_POST = [
            'action'       => DirectConversionPage::CHECK_ACTION,
            'edc_post_ids' => '306',
        ];

        // Uses the non-exiting seam: if the capability gate were ever
        // deleted, a real DirectConversionPage would run handle_check() for
        // real and hit the literal exit() in redirect(), killing the whole
        // (non-process-isolated) PHPUnit run instead of failing this one
        // test. The seam makes that failure loud and local.
        $page = $this->non_exiting_page();
        $this->expectException( \RuntimeException::class );
        $page->maybe_handle_request();
    }

    /**
     * ImportHistory is capped at MAX_RUNS entries; a junk run recorded for an
     * empty selection can evict a real, genuinely-undoable one. Converting
     * nothing must therefore record nothing.
     */
    public function test_handle_convert_refuses_an_empty_selection_without_recording_a_run(): void {
        $_POST = [
            'action'       => DirectConversionPage::CONVERT_ACTION,
            'edc_post_ids' => [],
        ];

        $history_before = ( new ImportHistory() )->all();

        $page = $this->non_exiting_page();
        $this->expectException( \RuntimeException::class );

        try {
            $page->maybe_handle_request();
        } finally {
            $this->assertSame(
                $history_before,
                ( new ImportHistory() )->all(),
                'an empty selection must not write (and so must not evict) any run'
            );
            $this->assertEmpty( $page->redirected_to, 'an empty selection must not redirect to a fabricated result screen' );
        }
    }
}
