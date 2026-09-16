<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use function Ferncourt\Demo\{conversion_problems, load_document};

/** The render probe converts cleanly and every block is one Divi renders. */
final class ProbeDocumentTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        demo_test_seed_site();
    }

    public function test_core_widgets_probe_converts_cleanly(): void {
        $doc  = load_document( dirname( __DIR__, 2 ) . '/content/probes/core-widgets.php', demo_test_context() );
        $item = ( new ConversionPreflight() )->runUnlimited( new DocumentSource( [ $doc ] ) )->items()[0];

        $this->assertSame( [], conversion_problems( $item, $doc['survive'], $doc['survive_exact'] ) );
        DiviModuleSchema::assertBlocksValid( $item['blocks']['elements'] ?? [], 'probes/core-widgets' );
    }
}
