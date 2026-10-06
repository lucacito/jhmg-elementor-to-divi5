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

    private function kitItemsFor( array $entry, array $content, string $key = '4521' ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $zip_path = $this->createKitZip( [
            'manifest.json'          => json_encode( [ 'pages' => [ $key => $entry ] ] ),
            'content/' . $key . '.json' => json_encode( $content ),
        ] );

        try {
            return $this->parser->parse( $zip_path, 'export.zip' );
        } finally {
            @unlink( $zip_path );
        }
    }

    private const SECTION = [ [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ] ];

    public function test_a_kit_entry_keyed_by_post_id_takes_its_title_from_the_manifest_title_field(): void {
        // Elementor's own kit manifests name the field `title`, not `post_title`.
        $items = $this->kitItemsFor( [ 'title' => 'About Us' ], [ 'content' => self::SECTION ] );

        $this->assertSame( 'About Us', $items[0]['title'] );
    }

    public function test_a_kit_entry_without_a_manifest_title_uses_the_content_files_title(): void {
        $items = $this->kitItemsFor( [], [ 'title' => 'Our Team', 'content' => self::SECTION ] );

        $this->assertSame( 'Our Team', $items[0]['title'] );
    }

    public function test_a_title_less_kit_entry_keyed_by_post_id_is_never_titled_with_the_bare_id(): void {
        // A page titled "4521" reads as a database row, and its slug "4521"
        // collides into "4521-2" on a second run.
        $items = $this->kitItemsFor( [], [ 'content' => self::SECTION ] );

        $this->assertNotSame( '4521', $items[0]['title'] );
        $this->assertSame( 'Imported page 4521', $items[0]['title'] );
    }

    public function test_a_title_less_kit_entry_keyed_by_slug_gets_a_readable_title(): void {
        $items = $this->kitItemsFor( [], [ 'content' => self::SECTION ], 'contact-us' );

        $this->assertSame( 'Contact Us', $items[0]['title'] );
    }

    public function test_a_numbered_file_is_not_titled_with_its_bare_number(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive extension not available.' );
        }

        $zip_path = $this->createKitZip( [ '4521.json' => json_encode( self::SECTION ) ] );

        try {
            $items = $this->parser->parse( $zip_path, 'export.zip' );
            $this->assertSame( 'Imported page 4521', $items[0]['title'] );
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
