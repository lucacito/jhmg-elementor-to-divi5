<?php
// tests/ConversionPlanTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class ConversionPlanTest extends TestCase {

    public function test_item_fills_every_key_with_a_default(): void {
        $item = ConversionPlan::item( [ 'title' => 'Home' ] );

        $this->assertSame( 'Home', $item['title'] );
        $this->assertSame( 'page', $item['post_type'] );
        $this->assertSame( '', $item['post_name'] );
        $this->assertSame( '', $item['template_type'] );
        $this->assertSame( [], $item['blocks'] );
        $this->assertSame( '', $item['content'] );
        $this->assertSame( [], $item['report'] );
        $this->assertSame( [], $item['unsupported'] );
        $this->assertSame( [], $item['outline'] );
        $this->assertSame( '', $item['error'] );
        $this->assertSame(
            [ 'kind' => 'upload', 'post_id' => null, 'file' => null ],
            $item['source_ref']
        );
    }

    public function test_item_keeps_supplied_values(): void {
        $item = ConversionPlan::item( [
            'title'      => 'About',
            'post_type'  => 'post',
            'content'    => '<!-- wp:divi/placeholder -->x<!-- /wp:divi/placeholder -->',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 42, 'file' => null ],
        ] );

        $this->assertSame( 'post', $item['post_type'] );
        $this->assertSame( 42, $item['source_ref']['post_id'] );
        $this->assertStringContainsString( 'divi/placeholder', $item['content'] );
    }

    public function test_plan_exposes_items_limit_and_truncation(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [ 'title' => 'A' ] ) ], 1, true );

        $this->assertCount( 1, $plan->items() );
        $this->assertSame( 1, $plan->count() );
        $this->assertSame( 1, $plan->limit() );
        $this->assertTrue( $plan->truncated() );
    }

    public function test_plan_defaults_to_limit_one_and_not_truncated(): void {
        $plan = new ConversionPlan( [] );

        $this->assertSame( 1, $plan->limit() );
        $this->assertFalse( $plan->truncated() );
        $this->assertSame( 0, $plan->count() );
    }

    public function test_has_failures_is_true_when_any_item_carries_an_error(): void {
        $ok   = ConversionPlan::item( [ 'title' => 'A' ] );
        $bad  = ConversionPlan::item( [ 'title' => 'B', 'error' => 'no Elementor data' ] );

        $this->assertFalse( ( new ConversionPlan( [ $ok ] ) )->hasFailures() );
        $this->assertTrue( ( new ConversionPlan( [ $ok, $bad ] ) )->hasFailures() );
    }

    public function test_to_array_round_trips_the_plan(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [ 'title' => 'A' ] ) ], 5, false );

        $this->assertSame(
            [ 'items', 'limit', 'truncated' ],
            array_keys( $plan->toArray() )
        );
        $this->assertSame( 5, $plan->toArray()['limit'] );
    }
}
