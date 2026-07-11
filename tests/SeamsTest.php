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

    public function test_globals_resolver_falls_back_to_static_map_without_filter(): void {
        // 'f8733ea' is in the static TYPOGRAPHY_MAP; no filter registered.
        $t = GlobalsResolver::resolveTypography( 'f8733ea' );
        $this->assertIsArray( $t );
        $this->assertSame( 'Roboto', $t['family'] );
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
}
