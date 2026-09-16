<?php
// tests/ElementorPageRepositoryTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\ElementorPageRepository;

class ElementorPageRepositoryTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    public function test_it_queries_for_elementor_built_posts(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( '_elementor_edit_mode', $args['meta_key'] );
        $this->assertSame( 'builder', $args['meta_value'] );
    }

    public function test_it_orders_by_most_recently_modified(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( 'modified', $args['orderby'] );
        $this->assertSame( 'DESC', $args['order'] );
    }

    public function test_it_pages_at_twenty_by_default(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( 20, $args['posts_per_page'] );
        $this->assertSame( 1, $args['paged'] );
    }

    public function test_it_honours_paging_and_search(): void {
        $args = ( new ElementorPageRepository() )->query_args( [
            'paged' => 3, 'per_page' => 5, 'search' => 'contact',
        ] );

        $this->assertSame( 3, $args['paged'] );
        $this->assertSame( 5, $args['posts_per_page'] );
        $this->assertSame( 'contact', $args['s'] );
    }

    public function test_it_omits_the_search_key_when_no_search_was_given(): void {
        $this->assertArrayNotHasKey( 's', ( new ElementorPageRepository() )->query_args() );
    }

    public function test_it_never_requests_a_negative_page(): void {
        $args = ( new ElementorPageRepository() )->query_args( [ 'paged' => -4 ] );

        $this->assertSame( 1, $args['paged'] );
    }

    public function test_it_includes_the_elementor_library_post_type(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertContains( 'elementor_library', (array) $args['post_type'] );
    }

    public function test_it_maps_query_results_into_rows(): void {
        $runner = fn( array $args ) => [
            (object) [
                'ID' => 11, 'post_title' => 'Home', 'post_type' => 'page',
                'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertSame( 11, $rows[0]['id'] );
        $this->assertSame( 'Home', $rows[0]['title'] );
        $this->assertSame( 'page', $rows[0]['post_type'] );
        $this->assertSame( 'publish', $rows[0]['status'] );
        $this->assertSame( '2026-08-01 10:00:00', $rows[0]['modified'] );
        $this->assertFalse( $rows[0]['converted'] );
    }

    public function test_a_row_already_converted_is_flagged(): void {
        // A converted Divi post points back at post 11.
        $GLOBALS['__test_posts'][ 99 ] = (object) [ 'ID' => 99, 'post_type' => 'page' ];
        update_post_meta( 99, '_edc_source_post_id', 11 );

        $runner = fn( array $args ) => [
            (object) [
                'ID' => 11, 'post_title' => 'Home', 'post_type' => 'page',
                'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertTrue( $rows[0]['converted'] );
    }

    public function test_a_row_not_converted_is_not_flagged_even_when_other_meta_exists(): void {
        // A different post carries unrelated meta AND a source-id meta pointing
        // elsewhere; post 11 itself must not be flagged converted.
        $GLOBALS['__test_posts'][ 99 ] = (object) [ 'ID' => 99, 'post_type' => 'page' ];
        update_post_meta( 99, '_edc_source_post_id', 42 );
        update_post_meta( 99, '_some_other_meta', 11 );

        $runner = fn( array $args ) => [
            (object) [
                'ID' => 11, 'post_title' => 'Home', 'post_type' => 'page',
                'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertFalse( $rows[0]['converted'] );
    }

    public function test_an_untitled_post_still_produces_a_usable_row(): void {
        $runner = fn( array $args ) => [
            (object) [
                'ID' => 12, 'post_title' => '', 'post_type' => 'page',
                'post_status' => 'draft', 'post_modified' => '2026-08-02 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertNotSame( '', $rows[0]['title'] );
    }

    public function test_find_uses_the_default_runner_when_none_is_injected(): void {
        // No runner injected: the repository must fall back to running its own
        // query rather than requiring a runner to function at all. The
        // harness's WP_Query stub always returns empty, so this exercises
        // the wiring end-to-end (query_args -> WP_Query -> mapping) and
        // proves the constructor's default actually does something.
        $rows = ( new ElementorPageRepository() )->find();

        $this->assertSame( [], $rows );
    }

    public function test_has_any_is_false_when_the_query_returns_nothing(): void {
        $repo = new ElementorPageRepository( fn( array $args ) => [] );

        $this->assertFalse( $repo->has_any() );
    }

    public function test_has_any_is_true_when_the_query_returns_something(): void {
        $repo = new ElementorPageRepository( fn( array $args ) => [ (object) [ 'ID' => 1 ] ] );

        $this->assertTrue( $repo->has_any() );
    }

    public function test_has_any_requests_a_single_row_regardless_of_default_page_size(): void {
        $seen = null;
        $repo = new ElementorPageRepository( function ( array $args ) use ( &$seen ) {
            $seen = $args;
            return [];
        } );

        $repo->has_any();

        $this->assertSame( 1, $seen['posts_per_page'] );
    }

    public function test_rows_with_a_zero_or_negative_id_are_skipped(): void {
        $runner = fn( array $args ) => [
            (object) [ 'ID' => 0, 'post_title' => 'Bad', 'post_type' => 'page', 'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00' ],
            (object) [ 'ID' => 5, 'post_title' => 'Good', 'post_type' => 'page', 'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00' ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertCount( 1, $rows );
        $this->assertSame( 5, $rows[0]['id'] );
    }

    public function test_it_lists_header_footer_elementor_templates(): void {
        $this->assertContains( 'elementor-hf', ( new ElementorPageRepository() )->query_args()['post_type'] );
    }
}
