<?php
// tests/ReplaceOriginalTest.php
//
// "Replace the original page" — an installed Elementor page is converted in
// place instead of copied. Covers the committer, the history record and Undo.
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;

class ReplaceOriginalTest extends TestCase {

    private const ELEMENTOR_JSON = '[{"id":"a1","elType":"section","settings":{"title":"He said \"hi\" \\\\ ok"}}]';

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']       = [];
        $GLOBALS['__test_postmeta']    = [];
        $GLOBALS['__test_options']     = [];
        $GLOBALS['__test_trashed']     = [];
        $GLOBALS['__test_trash_fails'] = [];
        $GLOBALS['__test_meta_write_fails'] = [];
    }

    private function seed_source( int $id = 77, string $type = 'page' ): void {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID'           => $id,
            'post_title'   => 'About Us',
            'post_name'    => 'about-us',
            'post_type'    => $type,
            'post_status'  => 'publish',
            'post_content' => 'elementor fallback content',
        ];
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $id, '_elementor_data', wp_slash( self::ELEMENTOR_JSON ) );
        update_post_meta( $id, '_elementor_version', '3.25.0' );
        update_post_meta( $id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
    }

    private function plan_item( array $overrides = [] ): array {
        return ConversionPlan::item( array_merge( [
            'title'      => 'About Us',
            'post_type'  => 'page',
            'post_name'  => 'about-us',
            'blocks'     => [ 'elements' => [ [ 'name' => 'divi/section', 'settings' => [] ] ] ],
            'report'     => [ 'converted' => [ 'heading' => 1 ], 'warnings' => [] ],
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ], $overrides ) );
    }

    private function replace( array $item_overrides = [], array $options = [] ): array {
        return ( new ConversionCommitter() )->commit(
            new ConversionPlan( [ $this->plan_item( $item_overrides ) ] ),
            array_merge( [ 'replace_original' => true ], $options )
        );
    }

    // ---- Committer ---------------------------------------------------------

    public function test_replace_converts_the_source_post_in_place(): void {
        $this->seed_source();
        $posts_before = count( $GLOBALS['__test_posts'] );

        $results = $this->replace();

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 77, $results[0]['post_id'], 'the result must point at the original page' );
        $this->assertTrue( $results[0]['replaced'] );
        $this->assertCount( $posts_before, $GLOBALS['__test_posts'], 'no copy may be created' );
        $this->assertSame( 'on', get_post_meta( 77, '_et_pb_use_divi_5', true ) );
        $this->assertNotSame( 'elementor fallback content', $GLOBALS['__test_posts'][77]->post_content );
    }

    public function test_replace_keeps_the_originals_title_slug_and_status(): void {
        $this->seed_source();

        $this->replace( [], [ 'post_status' => 'draft' ] );

        $post = $GLOBALS['__test_posts'][77];
        $this->assertSame( 'About Us', $post->post_title );
        $this->assertSame( 'about-us', $post->post_name );
        $this->assertSame( 'publish', $post->post_status, 'replacing must not silently unpublish the page' );
    }

    public function test_replace_stops_elementor_from_rendering_the_page(): void {
        $this->seed_source();

        $this->replace();

        $this->assertSame( '', get_post_meta( 77, '_elementor_edit_mode', true ) );
        $this->assertSame( '', get_post_meta( 77, '_elementor_data', true ) );
        $this->assertSame( 'default', get_post_meta( 77, '_wp_page_template', true ) );
    }

    public function test_replace_backs_up_the_elementor_version_before_changing_anything(): void {
        $this->seed_source();

        $this->replace();

        $backup = get_post_meta( 77, '_edc_replaced_backup', true );
        $this->assertIsArray( $backup );
        $this->assertSame( 'elementor fallback content', $backup['content'] );
        $this->assertSame( self::ELEMENTOR_JSON, $backup['meta']['_elementor_data'], 'backslashes and quotes must survive the backup' );
        $this->assertSame( 'builder', $backup['meta']['_elementor_edit_mode'] );
        $this->assertSame( 'elementor_canvas', $backup['meta']['_wp_page_template'] );
    }

    public function test_replace_is_marked_so_the_run_is_recognisable(): void {
        $this->seed_source();

        $this->replace();

        $this->assertSame( 'replaced', get_post_meta( 77, '_edc_import_source', true ) );
    }

    public function test_without_the_option_a_copy_is_still_created(): void {
        $this->seed_source();

        $results = ( new ConversionCommitter() )->commit( new ConversionPlan( [ $this->plan_item() ] ) );

        $this->assertNotSame( 77, $results[0]['post_id'] );
        $this->assertEmpty( $results[0]['replaced'] ?? false );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
    }

    public function test_an_uploaded_item_has_nothing_to_replace_and_is_created_normally(): void {
        $results = $this->replace( [
            'source_ref' => [ 'kind' => 'upload', 'post_id' => null, 'file' => 'x.json' ],
        ] );

        $this->assertTrue( $results[0]['success'] );
        $this->assertGreaterThanOrEqual( 1000, $results[0]['post_id'] );
        $this->assertEmpty( $results[0]['replaced'] ?? false );
    }

    public function test_an_elementor_library_template_is_never_replaced(): void {
        // Header/footer/popup templates live in their own post types and are
        // converted to a page or Theme Builder layout — overwriting the
        // Elementor template itself would break whatever still uses it.
        $this->seed_source( 77, 'elementor_library' );

        $results = $this->replace();

        $this->assertNotSame( 77, $results[0]['post_id'] );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
    }

    public function test_a_source_that_has_vanished_falls_back_to_a_copy(): void {
        $results = $this->replace(); // post 77 was never seeded

        $this->assertTrue( $results[0]['success'] );
        $this->assertNotSame( 77, $results[0]['post_id'] );
    }

    public function test_a_theme_builder_item_is_not_replaced(): void {
        $this->seed_source();

        $results = $this->replace( [ 'template_type' => 'header' ] );

        $this->assertNotSame( 77, $results[0]['post_id'] );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
    }

    public function test_replace_leaves_the_page_untouched_when_the_backup_cannot_be_written(): void {
        $this->seed_source();
        $GLOBALS['__test_meta_write_fails'] = [ '_edc_replaced_backup' ];

        $results = $this->replace();

        $this->assertFalse( $results[0]['success'] );
        $this->assertStringContainsString( 'backed up', $results[0]['error'] );
        $this->assertSame( 'elementor fallback content', $GLOBALS['__test_posts'][77]->post_content );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
        $this->assertSame( self::ELEMENTOR_JSON, get_post_meta( 77, '_elementor_data', true ) );
    }

    public function test_a_failure_while_converting_puts_the_original_back(): void {
        $this->seed_source();
        $exploding = new class extends \ElementorDivi5Converter\Exporters\DiviExporter {
            public function save( int $post_id, array $divi_data ) {
                throw new \RuntimeException( 'disk full' );
            }
        };

        $results = ( new ConversionCommitter( $exploding ) )->commit(
            new ConversionPlan( [ $this->plan_item() ] ),
            [ 'replace_original' => true ]
        );

        $this->assertFalse( $results[0]['success'] );
        $this->assertSame( 'disk full', $results[0]['error'] );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
        $this->assertSame( self::ELEMENTOR_JSON, get_post_meta( 77, '_elementor_data', true ) );
        $this->assertSame( 'elementor_canvas', get_post_meta( 77, '_wp_page_template', true ) );
        $this->assertSame( '', get_post_meta( 77, '_edc_replaced_backup', true ), 'a restored page must not keep a stale backup' );
    }

    // ---- History -----------------------------------------------------------

    public function test_history_records_which_posts_were_replaced(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [
            [ 'success' => true, 'post_id' => 77, 'replaced' => true,  'unsupported' => [] ],
            [ 'success' => true, 'post_id' => 90, 'unsupported' => [] ],
        ] );

        $run = $h->find( 'run1' );
        $this->assertSame( [ 77, 90 ], $run['post_ids'] );
        $this->assertSame( [ 77 ], $run['replaced'] );
    }

    // ---- Undo --------------------------------------------------------------

    private function converted_run( ImportHistory $h, array $extra_results = [] ): void {
        $this->seed_source();
        $results = $this->replace();
        $h->record( 'run1', array_merge( $results, $extra_results ) );
    }

    public function test_undo_restores_the_original_elementor_page(): void {
        $h = new ImportHistory();
        $this->converted_run( $h );

        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( 1, $out['restored'] );
        $this->assertSame( 0, $out['trashed'] );
        $this->assertSame( [], $GLOBALS['__test_trashed'], 'a replaced page is restored, never trashed' );
        $this->assertSame( 'elementor fallback content', $GLOBALS['__test_posts'][77]->post_content );
        $this->assertSame( 'builder', get_post_meta( 77, '_elementor_edit_mode', true ) );
        $this->assertSame( self::ELEMENTOR_JSON, get_post_meta( 77, '_elementor_data', true ) );
        $this->assertSame( 'elementor_canvas', get_post_meta( 77, '_wp_page_template', true ) );
    }

    public function test_undo_removes_the_divi_markers_the_replace_added(): void {
        $h = new ImportHistory();
        $this->converted_run( $h );

        ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( '', get_post_meta( 77, '_et_pb_use_divi_5', true ) );
        $this->assertSame( '', get_post_meta( 77, '_et_pb_use_builder', true ) );
        $this->assertSame( '', get_post_meta( 77, '_edc_replaced_backup', true ) );
        $this->assertSame( '', get_post_meta( 77, '_edc_import_source', true ) );
    }

    public function test_undo_puts_back_divi_meta_the_page_had_before(): void {
        $this->seed_source();
        update_post_meta( 77, '_et_pb_use_builder', 'off' );
        $h = new ImportHistory();
        $h->record( 'run1', $this->replace() );

        ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( 'off', get_post_meta( 77, '_et_pb_use_builder', true ) );
    }

    public function test_undo_in_a_mixed_run_restores_the_original_and_trashes_the_copy(): void {
        $h = new ImportHistory();
        $this->converted_run( $h, [
            [ 'success' => true, 'post_id' => 90, 'unsupported' => [] ],
        ] );
        update_post_meta( 90, '_edc_import_source', 'direct' );

        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( 1, $out['restored'] );
        $this->assertSame( 1, $out['trashed'] );
        $this->assertSame( [ 90 ], $GLOBALS['__test_trashed'] );
    }

    public function test_undo_leaves_a_replaced_page_alone_when_its_backup_is_gone(): void {
        $h = new ImportHistory();
        $this->converted_run( $h );
        delete_post_meta( 77, '_edc_replaced_backup' );
        $content_before = $GLOBALS['__test_posts'][77]->post_content;

        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( 0, $out['restored'] );
        $this->assertSame( 1, $out['skipped'] );
        $this->assertSame( $content_before, $GLOBALS['__test_posts'][77]->post_content );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState( false )]
    public function test_restoring_does_not_need_the_trash(): void {
        // EMPTY_TRASH_DAYS=0 only blocks trashing copies; putting an original
        // back deletes nothing, so a replace-only run is still undoable.
        define( 'EMPTY_TRASH_DAYS', 0 );
        $h = new ImportHistory();
        $this->converted_run( $h );

        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( 1, $out['restored'] );
        $this->assertFalse( $out['trash_unavailable'] );
        $this->assertTrue( $h->find( 'run1' )['rolled_back'] );
    }

    public function test_the_notice_reports_restored_pages(): void {
        $html = ( new ImportRollback( new ImportHistory() ) )->notice_markup( [
            'trashed' => 0, 'restored' => 2, 'skipped' => 0, 'trash_unavailable' => false,
        ] );

        $this->assertStringContainsString( '2 pages were restored', $html );
        $this->assertStringNotContainsString( 'Nothing to undo', $html );
    }

    // ---- Direct conversion screen -------------------------------------------

    private function seed_elementor_page( int $id ): void {
        $this->seed_source( $id );
        update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( [ [
            'elType'   => 'section',
            'elements' => [ [
                'elType'   => 'column',
                'elements' => [ [ 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Hi' ] ] ],
            ] ],
        ] ] ) ) );
    }

    public function test_the_report_offers_replace_as_an_unticked_checkbox(): void {
        $this->seed_elementor_page( 301 );
        $page = new DirectConversionPage();

        $html = $page->render_report( $page->plan_for( [ 301 ] ) );

        $this->assertStringContainsString( 'name="edc_replace_original"', $html );
        $this->assertDoesNotMatchRegularExpression( '/name="edc_replace_original"[^>]*checked/', $html, 'replacing is opt-in; the safe copy stays the default' );
        $this->assertStringContainsString( 'Creates a new Divi draft', $html );
    }

    public function test_the_report_offers_no_replace_when_nothing_can_be_converted(): void {
        $GLOBALS['__test_posts'][302] = (object) [ 'ID' => 302, 'post_title' => 'Empty', 'post_type' => 'page', 'post_status' => 'publish' ];
        update_post_meta( 302, '_elementor_edit_mode', 'builder' );
        $page = new DirectConversionPage();

        $html = $page->render_report( $page->plan_for( [ 302 ] ) );

        $this->assertStringNotContainsString( 'edc_replace_original', $html );
    }

    public function test_the_request_option_turns_replace_on_only_when_ticked(): void {
        $page = new DirectConversionPage();

        $this->assertSame( [ 'replace_original' => true ],  $page->convert_options( [ 'edc_replace_original' => '1' ] ) );
        $this->assertSame( [ 'replace_original' => false ], $page->convert_options( [] ) );
        $this->assertSame( [ 'replace_original' => false ], $page->convert_options( [ 'edc_replace_original' => '' ] ) );
        $this->assertSame( [ 'replace_original' => false ], $page->convert_options( [ 'edc_replace_original' => '0' ] ) );
    }

    public function test_convert_with_replace_converts_the_page_in_place_end_to_end(): void {
        $this->seed_elementor_page( 303 );

        $results = ( new DirectConversionPage() )->convert( [ 303 ], [ 'replace_original' => true ] );

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 303, $results[0]['post_id'] );
        $this->assertTrue( $results[0]['replaced'] );
        $this->assertSame( 'on', get_post_meta( 303, '_et_pb_use_divi_5', true ) );
        $this->assertSame( '', get_post_meta( 303, '_elementor_edit_mode', true ) );
        $this->assertSame( 'about-us', $GLOBALS['__test_posts'][303]->post_name );
    }

    public function test_the_results_screen_says_a_page_was_replaced(): void {
        $result = [
            'title' => 'About Us', 'post_id' => 77, 'success' => true, 'replaced' => true,
            'error' => '', 'report' => [], 'unsupported' => [],
        ];
        set_transient( 'edc_batch_replaced', [ $result ], 3600 );
        $_GET['import_id'] = 'replaced';

        ob_start();
        ( new ReflectionMethod( \ElementorDivi5Converter\Admin\AdminPage::class, 'render_batch_result' ) )
            ->invoke( new \ElementorDivi5Converter\Admin\AdminPage() );
        $html = (string) ob_get_clean();
        $_GET = [];

        $this->assertStringContainsString( 'Original page replaced', $html );
    }
}
