<?php
// tests/ConversionFlagTest.php
//
// The "this page has been converted" flag on the SOURCE post: written by every
// conversion of an installed page, honoured when a caller asks to skip pages
// that are already done, and removed again when Undo takes the conversion away.
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;
use ElementorDivi5Converter\Jobs\ConversionFlag;

class ConversionFlagTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']       = [];
        $GLOBALS['__test_postmeta']    = [];
        $GLOBALS['__test_options']     = [];
        $GLOBALS['__test_trashed']     = [];
        $GLOBALS['__test_trash_fails'] = [];
        if ( ! defined( 'EDC_PLUGIN_VERSION' ) ) {
            define( 'EDC_PLUGIN_VERSION', '9.9.9-test' );
        }
    }

    private function seed_source( int $id = 77 ): void {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID' => $id, 'post_title' => 'About Us', 'post_name' => 'about-us',
            'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'elementor html',
        ];
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $id, '_elementor_data', '[{"id":"a","elType":"section"}]' );
    }

    private function item( array $over = [] ): array {
        return ConversionPlan::item( array_merge( [
            'title' => 'About Us', 'post_type' => 'page', 'post_name' => 'about-us',
            'blocks' => [ 'elements' => [ [ 'name' => 'divi/section', 'settings' => [] ] ] ],
            'report' => [ 'converted' => [ 'heading' => 1 ] ],
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ], $over ) );
    }

    private function commit( array $item_over = [], array $options = [] ): array {
        return ( new ConversionCommitter() )->commit( new ConversionPlan( [ $this->item( $item_over ) ] ), $options );
    }

    // ---- writing the flag -----------------------------------------------------

    public function test_converting_a_copy_flags_the_source_with_the_plugin_version_and_a_date(): void {
        $this->seed_source();

        $this->commit();

        $this->assertNotSame( '', get_post_meta( 77, '_edc_converted_at', true ) );
        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', get_post_meta( 77, '_edc_converted_at', true ) );
        $this->assertSame( EDC_PLUGIN_VERSION, get_post_meta( 77, '_edc_converted_version', true ) );
    }

    public function test_replacing_flags_the_same_post(): void {
        $this->seed_source();

        $this->commit( [], [ 'replace_original' => true ] );

        $this->assertNotSame( '', get_post_meta( 77, '_edc_converted_at', true ) );
    }

    public function test_an_uploaded_file_has_no_source_post_to_flag(): void {
        $GLOBALS['__test_postmeta'] = [];

        $r = $this->commit( [ 'source_ref' => [ 'kind' => 'upload', 'post_id' => null, 'file' => 'x.json' ] ] );

        $this->assertTrue( $r[0]['success'] );
        $this->assertSame( '', get_post_meta( $r[0]['post_id'], '_edc_converted_at', true ), 'the new draft is the output, not a converted source' );
    }

    public function test_a_failed_conversion_does_not_flag_the_source(): void {
        $this->seed_source();

        $this->commit( [ 'error' => 'No Elementor content found.' ] );

        $this->assertSame( '', get_post_meta( 77, '_edc_converted_at', true ) );
    }

    // ---- skipping converted pages ---------------------------------------------

    public function test_skip_converted_leaves_an_already_converted_page_alone(): void {
        $this->seed_source();
        $this->commit();
        $posts_before = count( $GLOBALS['__test_posts'] );

        $r = $this->commit( [], [ 'skip_converted' => true ] );

        $this->assertTrue( $r[0]['skipped'] );
        $this->assertFalse( $r[0]['success'] );
        $this->assertStringContainsString( 'already converted', strtolower( $r[0]['error'] ) );
        $this->assertCount( $posts_before, $GLOBALS['__test_posts'], 'no duplicate draft' );
    }

    public function test_skip_converted_also_protects_a_page_from_being_replaced_twice(): void {
        $this->seed_source();
        $this->commit( [], [ 'replace_original' => true ] );
        $backup_before = get_post_meta( 77, '_edc_replaced_backup', true );

        $r = $this->commit( [], [ 'replace_original' => true, 'skip_converted' => true ] );

        $this->assertTrue( $r[0]['skipped'] );
        $this->assertSame( $backup_before, get_post_meta( 77, '_edc_replaced_backup', true ), 'the Elementor backup must not be overwritten by the Divi version' );
    }

    public function test_skip_converted_converts_a_page_that_is_not_flagged(): void {
        $this->seed_source();

        $r = $this->commit( [], [ 'skip_converted' => true ] );

        $this->assertTrue( $r[0]['success'] );
        $this->assertEmpty( $r[0]['skipped'] ?? false );
    }

    public function test_without_the_option_a_converted_page_is_still_converted_again(): void {
        // The picker lets a user deliberately re-convert; only callers that ask
        // (the jobs runner) get the skip.
        $this->seed_source();
        $this->commit();

        $r = $this->commit();

        $this->assertTrue( $r[0]['success'] );
    }

    // ---- Undo takes the flag away ---------------------------------------------

    private function record_and_undo( array $results ): array {
        $h = new ImportHistory();
        $h->record( 'run1', $results );

        return ( new ImportRollback( $h ) )->rollback( 'run1' );
    }

    public function test_undoing_a_copy_unflags_its_source(): void {
        $this->seed_source();
        $r = $this->commit();

        $this->record_and_undo( $r );

        $this->assertSame( '', get_post_meta( 77, '_edc_converted_at', true ), 'the page is pending again' );
        $this->assertSame( '', get_post_meta( 77, '_edc_converted_version', true ) );
    }

    public function test_undoing_a_replace_unflags_the_restored_page(): void {
        $this->seed_source();
        $r = $this->commit( [], [ 'replace_original' => true ] );

        $this->record_and_undo( $r );

        $this->assertSame( '', get_post_meta( 77, '_edc_converted_at', true ) );
    }

    public function test_undoing_one_of_two_copies_keeps_the_source_flagged(): void {
        $this->seed_source();
        $first  = $this->commit();
        $second = $this->commit();

        $this->record_and_undo( $first );

        $this->assertNotSame( '', get_post_meta( 77, '_edc_converted_at', true ), 'a live copy still exists' );
        $this->assertNotSame( $first[0]['post_id'], $second[0]['post_id'] );
    }

    public function test_undo_does_not_unflag_a_page_the_user_marked_by_hand(): void {
        $this->seed_source();
        $r = $this->commit();
        update_post_meta( 77, '_edc_converted_manually', '1' );

        $this->record_and_undo( $r );

        $this->assertNotSame( '', get_post_meta( 77, '_edc_converted_at', true ) );
    }

    // ---- the flag helper itself -----------------------------------------------

    public function test_the_helper_reports_converted_and_manual_and_unconvertible(): void {
        $this->seed_source();
        $this->assertFalse( ConversionFlag::is_converted( 77 ) );

        ConversionFlag::mark_manual( 77 );
        $this->assertTrue( ConversionFlag::is_converted( 77 ) );
        $this->assertTrue( ConversionFlag::is_manual( 77 ) );

        ConversionFlag::clear( 77 );
        $this->assertFalse( ConversionFlag::is_converted( 77 ) );
        $this->assertFalse( ConversionFlag::is_manual( 77 ) );
    }

    public function test_an_invalid_post_id_is_never_reported_as_converted(): void {
        // get_post_meta() returns false (not '') for post 0 in real WordPress.
        $this->assertFalse( ConversionFlag::is_converted( 0 ) );
        $this->assertFalse( ConversionFlag::is_manual( 0 ) );
        $this->assertFalse( ConversionFlag::is_unconvertible( 0 ) );
    }
}
