<?php
// tests/BatchImporterDelegationTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\BatchImporter;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class BatchImporterDelegationTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function items(): array {
        return [ [
            'title'     => 'Home',
            'post_type' => 'page',
            'post_name' => 'home',
            'elements'  => [ [
                'elType'   => 'section',
                'elements' => [ [
                    'elType'   => 'column',
                    'elements' => [ [
                        'elType' => 'widget', 'widgetType' => 'heading',
                        'settings' => [ 'title' => 'Hi' ],
                    ] ],
                ] ],
            ] ],
        ] ];
    }

    public function test_import_still_creates_a_post_and_returns_the_documented_shape(): void {
        // Free's limit is 1, and uploads must not be capped by it.
        $results = ( new BatchImporter() )->import( $this->items() );

        $this->assertCount( 1, $results );
        foreach ( [ 'title', 'post_id', 'success', 'error', 'report', 'unsupported' ] as $key ) {
            $this->assertArrayHasKey( $key, $results[0] );
        }
        $this->assertTrue( $results[0]['success'] );
    }

    public function test_import_is_not_capped_by_the_direct_conversion_limit(): void {
        $items = array_merge( $this->items(), $this->items(), $this->items() );

        $results = ( new BatchImporter() )->import( $items );

        $this->assertCount( 3, $results, 'the upload path must not inherit the direct-conversion cap' );
        foreach ( $results as $result ) {
            $this->assertTrue( $result['success'], 'every uploaded item must actually convert, not merely be counted' );
        }
        $this->assertCount(
            3,
            array_unique( array_column( $results, 'post_id' ) ),
            'three distinct posts must have actually been created'
        );
    }

    public function test_import_plan_commits_an_already_built_plan(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'   => 'From Plan',
            'blocks'  => [ 'elements' => [] ],
            'content' => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->',
        ] ) ] );

        $results = ( new BatchImporter() )->importPlan( $plan );

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'From Plan', $results[0]['title'] );
    }

    public function test_divi_exporter_save_no_longer_accepts_a_dry_run_argument(): void {
        $method = new ReflectionMethod( \ElementorDivi5Converter\Exporters\DiviExporter::class, 'save' );

        $this->assertSame( 2, $method->getNumberOfParameters() );
    }
}
