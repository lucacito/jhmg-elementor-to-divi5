<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;

final class KitTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        demo_test_seed_site();
    }

    public function test_the_converter_reads_every_brand_color_from_the_kit(): void {
        $colors = ConversionPreflight::elementorGlobalColors();

        $this->assertSame( '#2F4F3A', $colors['primary'] ?? null );
        $this->assertSame( '#F6F1E7', $colors['secondary'] ?? null );
        $this->assertSame( '#1F2421', $colors['text'] ?? null );
        $this->assertSame( '#C8643B', $colors['accent'] ?? null );
        $this->assertSame( '#FFFFFF', $colors['white'] ?? null );
    }

    public function test_the_converter_reads_the_brand_fonts_from_the_kit(): void {
        $flat = json_encode( ConversionPreflight::elementorGlobalTypography() );

        $this->assertStringContainsString( 'Fraunces', $flat );
        $this->assertStringContainsString( 'Inter', $flat );
    }
}
