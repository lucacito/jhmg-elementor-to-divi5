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
        $this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', EDCP_PLUGIN_VERSION );
    }

    /**
     * The header and the constant both feed the update path: divi5lab compares
     * the constant it is sent against the released version, and WordPress shows
     * the header. If they disagree, customers are either offered an update they
     * already have or never offered one at all — and the second failure looks
     * exactly like an expired licence from their side.
     *
     * Asserted as a match rather than against a literal so a release bump does
     * not have to remember to edit a test, which is how the two drifted apart
     * in the first place.
     */
    public function test_the_version_constant_matches_the_plugin_header(): void {
        $main = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'
        );

        $this->assertSame( 1, preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $m ) );
        $this->assertSame(
            $m[1],
            EDCP_PLUGIN_VERSION,
            'Pro plugin header and EDCP_PLUGIN_VERSION must agree'
        );
    }

    public function test_pro_registers_the_direct_conversion_limit_filter(): void {
        $pro = \ElementorDivi5Converter\Pro\Plugin::instance();
        $pro->register_hooks();

        $this->assertSame( PHP_INT_MAX, apply_filters( 'edc_direct_conversion_limit', 1 ) );
    }
}
