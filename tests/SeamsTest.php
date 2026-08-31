<?php
// tests/SeamsTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;
use ElementorDivi5Converter\Admin\BatchImporter;

class SeamsTest extends TestCase {
    protected function setUp(): void { edc_test_reset_hooks(); }

    public function test_globals_resolver_uses_kit_globals_filter(): void {
        add_filter( 'edc_kit_globals', fn( $v ) => [ 'colors' => [ 'abc123' => '#ff0000' ], 'typography' => [] ] );
        $this->assertSame( '#ff0000', GlobalsResolver::resolveColor( 'abc123' ) );
    }

    /**
     * There is no built-in palette to fall back to. The resolver used to answer
     * with one specific site's colours and type presets for any ID it did not
     * know — including Elementor's universal system IDs — which repainted
     * unrelated sites and reported the conversion as clean.
     */
    public function test_globals_resolver_returns_null_when_no_kit_knows_the_id(): void {
        $this->assertNull( GlobalsResolver::resolveTypography( 'f8733ea' ) );
        $this->assertNull( GlobalsResolver::resolveColor( 'primary' ) );
        $this->assertNull( GlobalsResolver::resolveColor( 'accent' ) );
    }

    public function test_globals_resolver_reads_typography_from_kit_globals_filter(): void {
        add_filter( 'edc_kit_globals', fn( $v ) => [
            'colors'     => [],
            'typography' => [ 'abc123' => [ 'family' => 'Inter', 'size' => '18px' ] ],
        ] );

        $preset = GlobalsResolver::resolveTypography( 'abc123' );

        $this->assertIsArray( $preset );
        $this->assertSame( 'Inter', $preset['family'] );
    }

    public function test_batch_importer_degrades_header_to_page_without_exporter(): void {
        $importer = new BatchImporter(); // no exporter filter registered -> null
        $results  = $importer->import(
            [ [ 'title' => 'My Header', 'template_type' => 'header', 'elements' => [] ] ],
            [ 'post_type' => 'page', 'post_status' => 'draft', 'convert_headers' => true, 'convert_footers' => true ]
        );
        $this->assertCount( 1, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertStringContainsString( 'Pro', implode( ' ', $results[0]['report']['warnings'] ?? [] ) );
    }

    public function test_batch_importer_uses_exporter_from_filter(): void {
        $fake = new class {
            public array $calls = [];
            public function saveHeader( string $t, array $c ): array {
                $this->calls[] = 'header';
                return [ 'post_id' => 1, 'template_id' => 2, 'theme_builder_id' => 3, 'success' => true, 'error' => '' ];
            }
            public function saveFooter( string $t, array $c ): array {
                $this->calls[] = 'footer';
                return [ 'post_id' => 1, 'template_id' => 2, 'theme_builder_id' => 3, 'success' => true, 'error' => '' ];
            }
        };
        add_filter( 'edc_theme_builder_exporter', fn( $v ) => $fake );
        $importer = new BatchImporter();
        $importer->import(
            [ [ 'title' => 'H', 'template_type' => 'header', 'elements' => [] ] ],
            [ 'post_type' => 'page', 'post_status' => 'draft', 'convert_headers' => true, 'convert_footers' => false ]
        );
        $this->assertSame( [ 'header' ], $fake->calls );
    }

    public function test_direct_conversion_limit_defaults_to_one_without_a_filter(): void {
        $this->assertSame( 1, \ElementorDivi5Converter\Conversion\ConversionPreflight::limit() );
    }

    public function test_pro_raises_the_direct_conversion_limit(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => PHP_INT_MAX );

        $this->assertSame( PHP_INT_MAX, \ElementorDivi5Converter\Conversion\ConversionPreflight::limit() );
    }
}
