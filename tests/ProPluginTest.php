<?php

use PHPUnit\Framework\TestCase;

class ProPluginTest extends TestCase {
    protected function setUp(): void { edc_test_reset_hooks(); }

    public function test_pro_plugin_class_exists_and_registers_pro_active(): void {
        $pro = \ElementorDivi5Converter\Pro\Plugin::instance();
        $pro->register_hooks();
        $this->assertTrue( apply_filters( 'edc_pro_active', false ) );
    }

    public function test_constants_defined(): void {
        $this->assertSame( 'elementor-to-divi5-pro', EDCP_PRODUCT_SLUG );
        $this->assertSame( '1.0.0', EDCP_PLUGIN_VERSION );
    }
}
