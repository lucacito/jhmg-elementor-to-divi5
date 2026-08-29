<?php
// tests/ConversionCommitterTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class ConversionCommitterTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function plan_item( array $overrides = [] ): array {
        return ConversionPlan::item( array_merge( [
            'title'      => 'Home',
            'post_type'  => 'page',
            'blocks'     => [ 'elements' => [ [ 'name' => 'divi/section', 'settings' => [] ] ] ],
            'content'    => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->',
            'report'     => [ 'converted' => [ 'heading' => 1 ], 'warnings' => [] ],
            'source_ref' => [ 'kind' => 'upload', 'post_id' => null, 'file' => 'x.json' ],
        ], $overrides ) );
    }

    public function test_it_creates_a_post_and_reports_success(): void {
        $plan    = new ConversionPlan( [ $this->plan_item() ] );
        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertCount( 1, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertGreaterThan( 0, $results[0]['post_id'] );
        $this->assertSame( 'Home', $results[0]['title'] );
        $this->assertSame( '', $results[0]['error'] );
    }

    public function test_an_explicit_post_type_option_overrides_the_items_own_type(): void {
        // The item itself is a 'page', but the caller (today's admin screen
        // always passes an explicit option) wins over the item's own type.
        $plan = new ConversionPlan( [ $this->plan_item( [ 'post_type' => 'page' ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan, [
            'post_type' => 'post', 'post_status' => 'publish',
        ] );

        $post = $GLOBALS['__test_posts'][ $results[0]['post_id'] ];
        $this->assertSame( 'post', $post->post_type );
        $this->assertSame( 'publish', $post->post_status );
    }

    public function test_with_no_post_type_option_the_items_own_type_is_used(): void {
        // Direct conversion passes no post_type option, so a converted blog
        // post correctly stays a post instead of silently becoming a page.
        $plan = new ConversionPlan( [ $this->plan_item( [ 'post_type' => 'post' ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan );

        $post = $GLOBALS['__test_posts'][ $results[0]['post_id'] ];
        $this->assertSame( 'post', $post->post_type );
        $this->assertSame( 'draft', $post->post_status );
    }

    public function test_an_uploaded_item_is_stamped_file_upload(): void {
        $plan    = new ConversionPlan( [ $this->plan_item() ] );
        $results = ( new ConversionCommitter() )->commit( $plan );
        $id      = $results[0]['post_id'];

        $this->assertSame( 'file_upload', get_post_meta( $id, '_edc_import_source', true ) );
        $this->assertSame( '', get_post_meta( $id, '_edc_source_post_id', true ) );
    }

    public function test_an_installed_item_is_stamped_direct_with_its_source_post_id(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan );
        $id      = $results[0]['post_id'];

        $this->assertSame( 'direct', get_post_meta( $id, '_edc_import_source', true ) );
        $this->assertSame( 77, (int) get_post_meta( $id, '_edc_source_post_id', true ) );
    }

    public function test_it_never_writes_to_the_source_post(): void {
        $GLOBALS['__test_posts'][ 77 ] = (object) [
            'ID' => 77, 'post_title' => 'Original', 'post_type' => 'page', 'post_content' => 'elementor',
        ];
        update_post_meta( 77, '_elementor_data', '[{"elType":"section"}]' );

        $source_post_before = clone $GLOBALS['__test_posts'][ 77 ];
        $source_meta_before = $GLOBALS['__test_postmeta'][ 77 ];

        $plan = new ConversionPlan( [ $this->plan_item( [
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ] ) ] );
        ( new ConversionCommitter() )->commit( $plan );

        $this->assertEquals( $source_post_before, $GLOBALS['__test_posts'][ 77 ] );
        $this->assertSame( $source_meta_before, $GLOBALS['__test_postmeta'][ 77 ] );
    }

    public function test_an_item_carrying_an_error_is_reported_as_a_failure_and_creates_no_post(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [ 'error' => 'No Elementor content found.' ] ) ] );

        $posts_before = count( $GLOBALS['__test_posts'] );
        $results      = ( new ConversionCommitter() )->commit( $plan );

        $this->assertFalse( $results[0]['success'] );
        $this->assertSame( 'No Elementor content found.', $results[0]['error'] );
        $this->assertSame( 0, $results[0]['post_id'] );
        $this->assertCount( $posts_before, $GLOBALS['__test_posts'] );
    }

    public function test_a_header_without_the_pro_exporter_degrades_to_a_page_with_a_warning(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertTrue( $results[0]['success'] );
        $this->assertStringContainsString( 'Pro', implode( ' ', $results[0]['report']['warnings'] ?? [] ) );
    }

    public function test_a_header_uses_the_theme_builder_exporter_when_one_is_present(): void {
        $fake = new class {
            public array $calls = [];
            public function saveHeader( string $t, array $c ): array {
                $this->calls[] = 'header';
                return [ 'post_id' => 31, 'template_id' => 32, 'theme_builder_id' => 33, 'success' => true, 'error' => '' ];
            }
            public function saveFooter( string $t, array $c ): array {
                $this->calls[] = 'footer';
                return [ 'post_id' => 41, 'template_id' => 42, 'theme_builder_id' => 43, 'success' => true, 'error' => '' ];
            }
        };
        add_filter( 'edc_theme_builder_exporter', fn( $v ) => $fake );

        $plan    = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );
        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertSame( [ 'header' ], $fake->calls );
        $this->assertSame( 31, $results[0]['post_id'] );
        $this->assertSame( 32, $results[0]['template_id'] );
        $this->assertSame( 'header', $results[0]['template_type'] );
    }

    public function test_convert_headers_false_imports_the_header_as_a_plain_page(): void {
        $fake = new class {
            public array $calls = [];
            public function saveHeader( string $t, array $c ): array { $this->calls[] = 'header'; return []; }
            public function saveFooter( string $t, array $c ): array { $this->calls[] = 'footer'; return []; }
        };
        add_filter( 'edc_theme_builder_exporter', fn( $v ) => $fake );

        $plan    = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );
        $results = ( new ConversionCommitter() )->commit( $plan, [ 'convert_headers' => false ] );

        $this->assertSame( [], $fake->calls );
        $this->assertCount( 1, $results );
        $this->assertTrue( $results[0]['success'], 'the header must still be imported, just as a plain page' );
        $post = $GLOBALS['__test_posts'][ $results[0]['post_id'] ];
        $this->assertSame( 'page', $post->post_type );
    }
}
