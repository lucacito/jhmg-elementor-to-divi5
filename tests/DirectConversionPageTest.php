<?php
// tests/DirectConversionPageTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class DirectConversionPageTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function seed( int $id, string $title = 'Home', bool $elementor = true ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID' => $id, 'post_title' => $title, 'post_name' => 'home',
            'post_type' => 'page', 'post_status' => 'publish',
            'post_modified' => '2026-08-01 00:00:00',
        ];
        if ( $elementor ) {
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
        }
        return $id;
    }

    public function test_it_reads_selected_ids_from_the_request(): void {
        $id = $this->seed( 201 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => [ (string) $id ] ] );

        $this->assertSame( [ 201 ], $ids );
    }

    public function test_it_accepts_a_single_non_array_id(): void {
        $id = $this->seed( 202 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => (string) $id ] );

        $this->assertSame( [ 202 ], $ids );
    }

    public function test_it_drops_a_post_that_is_not_elementor_built(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 10 );
        $bad  = $this->seed( 204, 'Plain', false );
        $good = $this->seed( 203 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $bad, (string) $good ],
        ] );

        $this->assertSame( [ 203 ], $ids, 'the rendered list must not be trusted as an allowlist' );
    }

    public function test_it_drops_ids_that_do_not_exist(): void {
        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => [ '999999' ] ] );

        $this->assertSame( [], $ids );
    }

    public function test_it_drops_garbage_input(): void {
        $this->seed( 1 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ 'abc', '-1', '0', '<script>', [ 'nested' ] ],
        ] );

        $this->assertSame( [], $ids );
    }

    public function test_it_caps_the_selection_at_the_free_limit(): void {
        $a = $this->seed( 205, 'A' );
        $b = $this->seed( 206, 'B' );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $a, (string) $b ],
        ] );

        $this->assertCount( 1, $ids, 'free converts one page per run' );
    }

    public function test_a_raised_limit_allows_more(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 10 );
        $a = $this->seed( 207, 'A' );
        $b = $this->seed( 208, 'B' );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $a, (string) $b ],
        ] );

        $this->assertCount( 2, $ids );
    }

    public function test_it_deduplicates_repeated_ids(): void {
        $id = $this->seed( 209 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $id, (string) $id ],
        ] );

        $this->assertSame( [ 209 ], $ids );
    }

    public function test_invalid_ids_do_not_consume_the_selection_limit(): void {
        $bad  = $this->seed( 220, 'Plain', false );
        $good = $this->seed( 221 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $bad, (string) $good ],
        ] );

        $this->assertSame( [ 221 ], $ids, 'verification must happen before the cap is applied' );
    }

    public function test_plan_for_produces_a_plan_without_writing(): void {
        $id = $this->seed( 210, 'Preview Me' );

        // Deep-copy the snapshot: a shallow array copy still shares the same
        // post objects, so assertSame() would compare them by identity and
        // an in-place mutation of a post object would slip through
        // undetected. Cloning each post and comparing with assertEquals()
        // (value equality) instead catches that.
        $posts_before = array_map( fn( $p ) => clone $p, $GLOBALS['__test_posts'] );
        $meta_before  = $GLOBALS['__test_postmeta'];

        $plan = ( new DirectConversionPage() )->plan_for( [ $id ] );

        $this->assertInstanceOf( ConversionPlan::class, $plan );
        $this->assertSame( 'Preview Me', $plan->items()[0]['title'] );
        $this->assertNotEmpty( $plan->items()[0]['outline'] );
        $this->assertEquals( $posts_before, $GLOBALS['__test_posts'] );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'] );
    }

    public function test_convert_creates_a_new_post_stamped_direct(): void {
        $id = $this->seed( 211, 'Convert Me' );

        $results = ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertTrue( $results[0]['success'] );
        $new_id = $results[0]['post_id'];
        $this->assertNotSame( $id, $new_id );
        $this->assertSame( 'direct', get_post_meta( $new_id, '_edc_import_source', true ) );
        $this->assertSame( $id, (int) get_post_meta( $new_id, '_edc_source_post_id', true ) );
    }

    public function test_convert_leaves_the_source_page_untouched(): void {
        $id = $this->seed( 212, 'Original' );

        $source_before = clone $GLOBALS['__test_posts'][ $id ];
        $meta_before   = $GLOBALS['__test_postmeta'][ $id ];

        ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertEquals( $source_before, $GLOBALS['__test_posts'][ $id ] );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'][ $id ] );
    }

    public function test_convert_defaults_to_a_draft(): void {
        $id      = $this->seed( 213 );
        $results = ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertSame( 'draft', $GLOBALS['__test_posts'][ $results[0]['post_id'] ]->post_status );
    }
}
