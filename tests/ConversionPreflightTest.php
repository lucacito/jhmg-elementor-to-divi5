<?php
// tests/ConversionPreflightTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionSource;

/** A source that yields exactly what it was handed. */
class FakeConversionSource implements ConversionSource {
    private array $items;
    public function __construct( array $items ) { $this->items = $items; }
    public function items(): array { return $this->items; }
}

class ConversionPreflightTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function heading_item( string $title = 'Home' ): array {
        return [
            'title'         => $title,
            'post_type'     => 'page',
            'post_name'     => 'home',
            'template_type' => '',
            'elements'      => [ [
                'elType'   => 'section',
                'elements' => [ [
                    'elType'   => 'column',
                    'elements' => [ [
                        'elType'     => 'widget',
                        'widgetType' => 'heading',
                        'settings'   => [ 'title' => 'Hello' ],
                    ] ],
                ] ],
            ] ],
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => 7, 'file' => null ],
        ];
    }

    public function test_it_returns_a_conversion_plan(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [] ) );

        $this->assertInstanceOf( ConversionPlan::class, $plan );
        $this->assertSame( 0, $plan->count() );
    }

    public function test_it_converts_an_item_into_blocks_and_serialized_content(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $item = $plan->items()[0];

        $this->assertSame( 'Home', $item['title'] );
        $this->assertSame( '', $item['error'] );
        $this->assertNotEmpty( $item['blocks'] );
        $this->assertStringContainsString( 'wp:divi/', $item['content'] );
    }

    public function test_it_carries_the_report_and_the_outline(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $item = $plan->items()[0];

        $this->assertArrayHasKey( 'converted', $item['report'] );
        $this->assertArrayHasKey( 'quality', $item['report'] );
        $this->assertNotEmpty( $item['outline'] );
        $this->assertSame( 'section', $item['outline'][0]['type'] );
    }

    public function test_it_preserves_the_source_ref(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $this->assertSame( 'installed', $plan->items()[0]['source_ref']['kind'] );
        $this->assertSame( 7, $plan->items()[0]['source_ref']['post_id'] );
    }

    /**
     * Deep-copy the in-memory post store before/after: $GLOBALS['__test_posts']
     * holds stdClass objects, so a plain array snapshot copies the array by
     * value but the objects inside it by handle — an in-place property mutation
     * (e.g. via wp_update_post()) would be invisible to assertSame() on such a
     * snapshot even though it is exactly what this test exists to catch.
     * Casting each post to an array forces a real value snapshot instead.
     */
    private function snapshotPosts(): array {
        return array_map( static fn( $post ) => (array) $post, $GLOBALS['__test_posts'] );
    }

    public function test_a_preflight_run_writes_nothing(): void {
        $GLOBALS['__test_posts'][ 900 ] = (object) [ 'ID' => 900, 'post_type' => 'page' ];
        update_post_meta( 900, '_elementor_data', '[]' );

        $posts_before = $this->snapshotPosts();
        $meta_before  = $GLOBALS['__test_postmeta'];

        ( new ConversionPreflight() )->run( new FakeConversionSource( [ $this->heading_item() ] ) );

        $this->assertSame( $posts_before, $this->snapshotPosts(), 'preflight created or changed a post' );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'], 'preflight wrote post meta' );
    }

    public function test_each_item_gets_a_fresh_report(): void {
        // Raise the cap: the default limit is 1, and this test needs both
        // items to actually run through the engine to compare their reports.
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 2 );

        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
        ] ) );

        // With a shared engine the second item's counts would include the first's.
        $this->assertSame(
            array_sum( $plan->items()[0]['report']['converted'] ),
            array_sum( $plan->items()[1]['report']['converted'] ),
            'report state leaked between items'
        );
    }

    public function test_an_item_arriving_with_an_error_passes_through_unconverted(): void {
        $bad = [
            'title' => 'Gone', 'post_type' => 'page', 'post_name' => '',
            'template_type' => '', 'elements' => [], 'error' => 'That page no longer exists.',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 42, 'file' => null ],
        ];

        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [ $bad ] ) );

        $this->assertSame( 'That page no longer exists.', $plan->items()[0]['error'] );
        $this->assertSame( '', $plan->items()[0]['content'] );
        $this->assertTrue( $plan->hasFailures() );
    }

    public function test_the_default_limit_is_one_and_extra_items_are_dropped(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
            $this->heading_item( 'Three' ),
        ] ) );

        $this->assertSame( 1, $plan->limit() );
        $this->assertSame( 1, $plan->count() );
        $this->assertTrue( $plan->truncated() );
        $this->assertSame( 'One', $plan->items()[0]['title'] );
    }

    public function test_a_filtered_limit_raises_the_cap(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 10 );

        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
        ] ) );

        $this->assertSame( 10, $plan->limit() );
        $this->assertSame( 2, $plan->count() );
        $this->assertFalse( $plan->truncated() );
    }

    public function test_a_selection_at_exactly_the_limit_is_not_truncated(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $this->assertFalse( $plan->truncated() );
    }

    public function test_a_filtered_limit_below_one_is_clamped_to_one(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 0 );

        $this->assertSame( 1, ConversionPreflight::limit() );
    }
}
