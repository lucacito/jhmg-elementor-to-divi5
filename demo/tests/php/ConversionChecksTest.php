<?php

use PHPUnit\Framework\TestCase;
use function Ferncourt\Demo\conversion_problems;

final class ConversionChecksTest extends TestCase {

    private function item( array $blocks, array $overrides = [] ): array {
        return array_merge( [ 'error' => '', 'unsupported' => [], 'report' => [ 'warnings' => [] ], 'blocks' => $blocks ], $overrides );
    }

    public function test_a_clean_item_that_kept_its_content_has_no_problems(): void {
        $blocks = [ 'name' => 'divi/section', 'elements' => [ [ 'settings' => [ 'title' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<h3>Day Pass</h3>' ] ] ], 'price' => 29 ] ] ] ];

        $this->assertSame( [], conversion_problems( $this->item( $blocks ), [ 'Day Pass' ], [ '29' ] ) );
    }

    public function test_reports_errors_unsupported_widgets_warnings_and_lost_content_in_that_order(): void {
        $item = $this->item( [ 'value' => 'Array' ], [
            'error'       => 'boom',
            'unsupported' => [ 'eael-woo-cart' ],
            'report'      => [ 'warnings' => [ 'Image missing alt text: abc1234' ] ],
        ] );

        $this->assertSame(
            [ 'error: boom', 'unsupported: eael-woo-cart', 'warning: Image missing alt text: abc1234', 'lost text: Day Pass', 'lost value: 58' ],
            conversion_problems( $item, [ 'Day Pass' ], [ '58' ] )
        );
    }

    public function test_exact_values_do_not_match_inside_longer_values(): void {
        $this->assertSame( [ 'lost value: 58' ], conversion_problems( $this->item( [ 'value' => '158 members' ] ), [], [ '58' ] ) );
    }
}
