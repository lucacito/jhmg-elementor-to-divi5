<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's free Kit Library kits (core widgets, sections and columns, Elementor
 * 3.7.2) through the converter, with the kit's own globals installed the way
 * ConversionPreflight reads them. Real-world shapes the hand-written fixtures miss.
 */
final class KitPagesSchemaTest extends TestCase {
    private const KITS = [ 'ceramic-studio', 'painting-company' ];

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    /** @return array<string, array{string, string}> */
    public static function pages(): array {
        $sets = [];
        foreach ( self::KITS as $kit ) {
            $zip = new ZipArchive();
            if ( $zip->open( __DIR__ . "/../references/kits/{$kit}.zip" ) !== true ) {
                continue;
            }
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $entry = $zip->getNameIndex( $i );
                if ( preg_match( '#^content/page/(\d+)\.json$#', $entry, $m ) ) {
                    $sets[ "{$kit}/{$m[1]}" ] = [ $kit, $entry ];
                }
            }
            $zip->close();
        }
        return $sets;
    }

    #[DataProvider('pages')]
    public function test_kit_page_converts_to_attributes_divi_renders( string $kit, string $entry ): void {
        $zip = new ZipArchive();
        $this->assertTrue( $zip->open( __DIR__ . "/../references/kits/{$kit}.zip" ) );
        $page     = json_decode( (string) $zip->getFromName( $entry ), true );
        $settings = json_decode( (string) $zip->getFromName( 'site-settings.json' ), true );
        $zip->close();

        // The kit's globals, installed as the active kit (see demo_test_seed_site()).
        update_option( 'elementor_active_kit', 900 );
        update_post_meta( 900, '_elementor_page_settings', $settings['settings'] ?? [] );
        add_filter( 'edc_kit_globals', static fn( $kit_globals ) => ConversionPreflight::installedKitGlobals() );

        $engine = new ConverterEngine();
        $engine->setGlobalColors( ConversionPreflight::elementorGlobalColors() );
        $result = $engine->convert( $page['content'] ?? [] );

        $this->assertSame( [], $result['unsupported'], 'core widgets only' );
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "{$kit} {$entry}" );
    }
}
