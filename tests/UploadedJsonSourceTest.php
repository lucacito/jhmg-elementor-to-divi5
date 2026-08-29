<?php
// tests/UploadedJsonSourceTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Conversion\UploadedJsonSource;

class UploadedJsonSourceTest extends TestCase {

    private function fixture(): string {
        return dirname( __DIR__ ) . '/fixtures/elementor/hero-page.json';
    }

    public function test_it_is_a_conversion_source(): void {
        $source = new UploadedJsonSource( $this->fixture(), 'hero-page.json' );
        $this->assertInstanceOf( ConversionSource::class, $source );
    }

    public function test_it_yields_items_with_elements(): void {
        $items = ( new UploadedJsonSource( $this->fixture(), 'hero-page.json' ) )->items();

        $this->assertNotEmpty( $items );
        $this->assertArrayHasKey( 'elements', $items[0] );
        $this->assertIsArray( $items[0]['elements'] );
        $this->assertNotEmpty( $items[0]['elements'] );
    }

    public function test_it_stamps_an_upload_source_ref_carrying_the_file_name(): void {
        $items = ( new UploadedJsonSource( $this->fixture(), 'hero-page.json' ) )->items();

        $this->assertSame( 'upload', $items[0]['source_ref']['kind'] );
        $this->assertSame( 'hero-page.json', $items[0]['source_ref']['file'] );
        $this->assertNull( $items[0]['source_ref']['post_id'] );
    }

    public function test_it_preserves_every_field_the_parser_produced(): void {
        $parser = new class extends \ElementorDivi5Converter\Parsers\ElementorImportParser {
            public function parse( string $file_path, string $file_name = '' ): array {
                return [ [
                    'title'         => 'My Header',
                    'post_type'     => 'page',
                    'post_name'     => 'my-header',
                    'template_type' => 'header',
                    'elements'      => [ [ 'elType' => 'section' ] ],
                ] ];
            }
        };

        $items = ( new UploadedJsonSource( '/nonexistent.json', 'x.json', $parser ) )->items();

        $this->assertSame( 'My Header', $items[0]['title'] );
        $this->assertSame( 'my-header', $items[0]['post_name'] );
        $this->assertSame( 'header', $items[0]['template_type'] );
    }

    public function test_an_unreadable_file_yields_no_items_rather_than_throwing(): void {
        $items = ( new UploadedJsonSource( '/definitely/not/here.json', 'nope.json' ) )->items();
        $this->assertSame( [], $items );
    }
}
