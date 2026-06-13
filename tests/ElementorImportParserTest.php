<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Parsers\ElementorImportParser;

final class ElementorImportParserTest extends TestCase {
    private ElementorImportParser $parser;
    private string $fixture_dir;
    private string $tmp_dir;

    protected function setUp(): void {
        $this->parser      = new ElementorImportParser();
        $this->fixture_dir = __DIR__ . '/../fixtures/elementor-import';
        $this->tmp_dir     = sys_get_temp_dir();
    }

    // -------------------------------------------------------------------------
    // JSON: raw array format
    // -------------------------------------------------------------------------

    public function test_parses_raw_array_json(): void {
        $items = $this->parser->parse( $this->fixture_dir . '/raw-array.json', 'raw-array.json' );

        $this->assertCount( 1, $items );
        $this->assertSame( 'Raw Array', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertNotEmpty( $items[0]['elements'] );
        $this->assertSame( 's1', $items[0]['elements'][0]['id'] );
    }

    public function test_raw_array_title_comes_from_file_name(): void {
        $items = $this->parser->parse( $this->fixture_dir . '/raw-array.json', 'my-home-page.json' );

        $this->assertSame( 'My Home Page', $items[0]['title'] );
    }

    // -------------------------------------------------------------------------
    // JSON: Elementor template export format
    // -------------------------------------------------------------------------

    public function test_parses_template_export_json(): void {
        $items = $this->parser->parse( $this->fixture_dir . '/template-export.json', 'template-export.json' );

        $this->assertCount( 1, $items );
        $this->assertSame( 'My Landing Page', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertNotEmpty( $items[0]['elements'] );
    }

    public function test_template_export_extracts_content_key(): void {
        $items = $this->parser->parse( $this->fixture_dir . '/template-export.json', 'template-export.json' );

        $this->assertSame( 's1', $items[0]['elements'][0]['id'] );
    }

    // -------------------------------------------------------------------------
    // JSON: object with post metadata
    // -------------------------------------------------------------------------

    public function test_parses_metadata_json(): void {
        $items = $this->parser->parse( $this->fixture_dir . '/with-metadata.json', 'with-metadata.json' );

        $this->assertCount( 1, $items );
        $this->assertSame( 'About Us', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertSame( 'about-us', $items[0]['post_name'] );
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function test_throws_on_missing_file(): void {
        $this->expectException( \RuntimeException::class );
        $this->parser->parse( '/tmp/does-not-exist-edc-test.json', 'test.json' );
    }

    public function test_throws_on_invalid_json(): void {
        $tmp = $this->tmp_dir . '/edc-test-invalid.json';
        file_put_contents( $tmp, 'this is not json {{{' );

        try {
            $this->expectException( \RuntimeException::class );
            $this->parser->parse( $tmp, 'invalid.json' );
        } finally {
            @unlink( $tmp );
        }
    }

    public function test_throws_on_unrecognizable_json_object(): void {
        $tmp = $this->tmp_dir . '/edc-test-empty.json';
        file_put_contents( $tmp, '{"foo":"bar","baz":123}' );

        try {
            $this->expectException( \RuntimeException::class );
            $this->parser->parse( $tmp, 'empty.json' );
        } finally {
            @unlink( $tmp );
        }
    }

    // -------------------------------------------------------------------------
    // ZIP: Elementor Kit format (with manifest)
    // -------------------------------------------------------------------------

    public function test_parses_zip_with_manifest(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $zip_path = $this->createKitZip( [
            'manifest.json' => json_encode( [
                'name'  => 'My Kit',
                'pages' => [
                    'home-page' => [
                        'post_title' => 'Home Page',
                        'post_type'  => 'page',
                        'post_name'  => 'home-page',
                        'post_status' => 'publish',
                    ],
                ],
            ] ),
            'content/home-page.json' => json_encode( [
                'title'   => 'Home Page',
                'type'    => 'page',
                'content' => [
                    [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
                ],
            ] ),
        ] );

        try {
            $items = $this->parser->parse( $zip_path, 'export.zip' );

            $this->assertCount( 1, $items );
            $this->assertSame( 'Home Page', $items[0]['title'] );
            $this->assertSame( 'page', $items[0]['post_type'] );
            $this->assertSame( 'home-page', $items[0]['post_name'] );
            $this->assertSame( 's1', $items[0]['elements'][0]['id'] );
        } finally {
            @unlink( $zip_path );
        }
    }

    public function test_parses_zip_with_multiple_pages(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $elements = [ [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ] ];

        $zip_path = $this->createKitZip( [
            'manifest.json' => json_encode( [
                'pages' => [
                    'page-a' => [ 'post_title' => 'Page A', 'post_type' => 'page', 'post_name' => 'page-a' ],
                    'page-b' => [ 'post_title' => 'Page B', 'post_type' => 'page', 'post_name' => 'page-b' ],
                ],
            ] ),
            'content/page-a.json' => json_encode( [ 'content' => $elements ] ),
            'content/page-b.json' => json_encode( [ 'content' => $elements ] ),
        ] );

        try {
            $items = $this->parser->parse( $zip_path, 'export.zip' );

            $this->assertCount( 2, $items );
            $this->assertSame( 'Page A', $items[0]['title'] );
            $this->assertSame( 'Page B', $items[1]['title'] );
        } finally {
            @unlink( $zip_path );
        }
    }

    public function test_parses_zip_fallback_without_manifest(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $elements = [ [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ] ];

        $zip_path = $this->createKitZip( [
            'about.json' => json_encode( [
                'title'   => 'About',
                'content' => $elements,
            ] ),
            'contact.json' => json_encode( $elements ),
        ] );

        try {
            $items = $this->parser->parse( $zip_path, 'export.zip' );

            $this->assertCount( 2, $items );
            $titles = array_column( $items, 'title' );
            $this->assertContains( 'About', $titles );
        } finally {
            @unlink( $zip_path );
        }
    }

    public function test_zip_detection_by_magic_bytes(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $elements = [ [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ] ];
        $zip_path = $this->createKitZip( [
            'page.json' => json_encode( [ 'content' => $elements ] ),
        ] );

        // Rename to a non-.zip extension to verify magic-byte detection.
        $fake_path = $zip_path . '.json';
        rename( $zip_path, $fake_path );

        try {
            $items = $this->parser->parse( $fake_path, 'archive.json' );
            $this->assertNotEmpty( $items );
        } finally {
            @unlink( $fake_path );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createKitZip( array $files ): string {
        $path = $this->tmp_dir . '/edc-test-' . uniqid() . '.zip';
        $zip  = new ZipArchive();
        $zip->open( $path, ZipArchive::CREATE );

        foreach ( $files as $name => $content ) {
            $zip->addFromString( $name, $content );
        }

        $zip->close();
        return $path;
    }
}
