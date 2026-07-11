<?php
// tests/ProKitTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Pro\Kit\GlobalsStore;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

class ProKitTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        GlobalsStore::clear();
    }

    public function test_pro_wires_kit_globals_into_free_resolver(): void {
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();
        GlobalsStore::save( [ 'kitcolor1' => '#123456' ], [], 'test-kit' );
        $this->assertSame( '#123456', GlobalsResolver::resolveColor( 'kitcolor1' ) );
    }

    public function test_pro_supplies_theme_builder_exporter(): void {
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();
        $exporter = apply_filters( 'edc_theme_builder_exporter', null );
        $this->assertInstanceOf( \ElementorDivi5Converter\Pro\Exporters\DiviThemeBuilderExporter::class, $exporter );
    }
}
