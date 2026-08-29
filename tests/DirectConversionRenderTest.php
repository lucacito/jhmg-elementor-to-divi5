<?php
// tests/DirectConversionRenderTest.php
use PHPUnit\Framework\TestCase;
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
        $this->assertStringContainsString( 'action=direct_report', $location );
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

        $this->expectException( \RuntimeException::class );
        ( new DirectConversionPage() )->maybe_handle_request();
    }
}
