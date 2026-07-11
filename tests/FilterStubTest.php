<?php
// tests/FilterStubTest.php
use PHPUnit\Framework\TestCase;

class FilterStubTest extends TestCase {
    protected function setUp(): void { edc_test_reset_hooks(); }

    public function test_apply_filters_passthrough_when_no_filter(): void {
        $this->assertNull( apply_filters( 'edc_kit_globals', null ) );
        $this->assertFalse( apply_filters( 'edc_pro_active', false ) );
    }

    public function test_registered_filter_transforms_value(): void {
        add_filter( 'edc_pro_active', fn( $v ) => true );
        $this->assertTrue( apply_filters( 'edc_pro_active', false ) );
    }

    public function test_filter_receives_extra_args(): void {
        add_filter( 'edc_x', fn( $v, $extra ) => $v . $extra, 10, 2 );
        $this->assertSame( 'ab', apply_filters( 'edc_x', 'a', 'b' ) );
    }

    public function test_do_action_invokes_callbacks(): void {
        $called = null;
        add_action( 'edc_loaded', function ( $arg ) use ( &$called ) { $called = $arg; } );
        do_action( 'edc_loaded', 'plugin-instance' );
        $this->assertSame( 'plugin-instance', $called );
    }
}
