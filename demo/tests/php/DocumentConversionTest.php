<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use function Ferncourt\Demo\{conversion_problems, load_document};

/**
 * Every page and template on the demo site, run through the real converter:
 * build checks 3 and 6, before Docker is involved.
 */
final class DocumentConversionTest extends TestCase {

    /** Every document the site has, as paths under demo/content without ".php". */
    private const DOCUMENTS = [
        'pages/home',
        'pages/spaces',
        'pages/memberships',
        'pages/about',
        'pages/events',
        'pages/blog',
        'pages/contact',
        'templates/header',
        'templates/footer',
    ];

    protected function setUp(): void {
        edc_test_reset_hooks();
        demo_test_seed_site();
    }

    /** @return array<string, array{string}> */
    public static function documents(): array {
        $sets = [];
        foreach ( self::DOCUMENTS as $name ) {
            $sets[ $name ] = [ $name ];
        }
        return $sets;
    }

    public function test_the_site_has_exactly_these_documents(): void {
        $content = dirname( __DIR__, 2 ) . '/content';
        $found   = [];
        foreach ( [ 'pages', 'templates' ] as $dir ) {
            foreach ( glob( "{$content}/{$dir}/*.php" ) as $file ) {
                $found[] = $dir . '/' . basename( $file, '.php' );
            }
        }

        $expected = self::DOCUMENTS;
        sort( $expected );
        sort( $found );
        $this->assertSame( $expected, $found );
    }

    #[DataProvider( 'documents' )]
    public function test_document_converts_cleanly_and_keeps_its_content( string $name ): void {
        $file = dirname( __DIR__, 2 ) . "/content/{$name}.php";
        $this->assertFileExists( $file );

        $doc  = load_document( $file, demo_test_context() );
        $item = ( new ConversionPreflight() )->runUnlimited( new DocumentSource( [ $doc ] ) )->items()[0];

        DiviModuleSchema::assertBlocksValid( $item['blocks']['elements'] ?? [], $name );

        $this->assertSame( [], conversion_problems( $item, $doc['survive'], $doc['survive_exact'] ) );
    }
}
