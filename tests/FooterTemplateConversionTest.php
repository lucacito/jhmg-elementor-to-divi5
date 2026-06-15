<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Parsers\ElementorImportParser;
use ElementorDivi5Converter\Admin\BatchImporter;

/**
 * Tests for Elementor footer template → Divi Theme Builder footer conversion.
 */
final class FooterTemplateConversionTest extends TestCase {
    private ElementorImportParser $parser;
    private string $tmp_dir;

    protected function setUp(): void {
        $this->parser  = new ElementorImportParser();
        $this->tmp_dir = sys_get_temp_dir();

        $GLOBALS['__test_posts']        = [];
        $GLOBALS['__test_postmeta']     = [];
        $GLOBALS['__test_next_post_id'] = 4000;
    }

    // -------------------------------------------------------------------------
    // Parser: footer template detection
    // -------------------------------------------------------------------------

    public function test_detects_footer_type_from_elementor_template_export(): void {
        $json = json_encode( [
            'version' => '0.4',
            'title'   => 'Site Footer',
            'type'    => 'footer',
            'content' => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );

        $this->assertSame( 'footer', $item['template_type'] );
    }

    public function test_detects_footer_type_case_insensitive(): void {
        $json = json_encode( [
            'title'   => 'My Footer',
            'type'    => 'Footer',
            'content' => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );
        $this->assertSame( 'footer', $item['template_type'] );
    }

    public function test_detects_footer_from_et_footer_layout_post_type(): void {
        $json = json_encode( [
            'title'     => 'TB Footer',
            'post_type' => 'et_footer_layout',
            'elements'  => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );
        $this->assertSame( 'footer', $item['template_type'] );
    }

    public function test_footer_type_preserved_in_zip_manifest(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive not available.' );
        }

        $zip_path = $this->createZip( [
            'manifest.json' => json_encode( [
                'templates' => [
                    'site-footer' => [
                        'post_title' => 'Site Footer',
                        'post_type'  => 'footer',
                        'post_name'  => 'site-footer',
                    ],
                ],
            ] ),
            'content/site-footer.json' => json_encode( [
                'title'   => 'Site Footer',
                'type'    => 'footer',
                'content' => [
                    [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
                ],
            ] ),
        ] );

        try {
            $items        = $this->parser->parse( $zip_path, 'export.zip' );
            $footer_items = array_filter( $items, fn( $i ) => ( $i['template_type'] ?? '' ) === 'footer' );
            $this->assertNotEmpty( $footer_items );
        } finally {
            @unlink( $zip_path );
        }
    }

    // -------------------------------------------------------------------------
    // BatchImporter: footer routing
    // -------------------------------------------------------------------------

    public function test_footer_item_creates_et_footer_layout_post(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['success'], $result[0]['error'] ?? '' );
        $this->assertSame( 'My Footer', $result[0]['title'] );
        $this->assertSame( 'footer', $result[0]['template_type'] );
        $this->assertGreaterThan( 0, $result[0]['post_id'] );

        $post = get_post( $result[0]['post_id'] );
        $this->assertNotNull( $post );
        $this->assertSame( 'et_footer_layout', $post->post_type );
    }

    public function test_footer_import_creates_et_template_post(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $this->assertArrayHasKey( 'template_id', $result[0] );
        $template_id = $result[0]['template_id'];
        $this->assertGreaterThan( 0, $template_id );

        $template_post = get_post( $template_id );
        $this->assertNotNull( $template_post );
        $this->assertSame( 'et_template', $template_post->post_type );
    }

    public function test_footer_template_post_has_correct_meta(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $template_id      = $result[0]['template_id'];
        $footer_layout_id = $result[0]['post_id'];

        $this->assertSame( '1', get_post_meta( $template_id, '_et_default', true ) );
        $this->assertSame( '1', get_post_meta( $template_id, '_et_enabled', true ) );
        $this->assertSame( '1', get_post_meta( $template_id, '_et_footer_layout_enabled', true ) );
        $this->assertSame( $footer_layout_id, (int) get_post_meta( $template_id, '_et_footer_layout_id', true ) );
    }

    public function test_footer_template_linked_to_theme_builder(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $tb_id       = $result[0]['theme_builder_id'];
        $template_id = $result[0]['template_id'];
        $this->assertGreaterThan( 0, $tb_id );

        $linked = get_post_meta( $tb_id, '_et_template' );
        $this->assertContains( $template_id, array_map( 'intval', $linked ) );
    }

    public function test_footer_layout_and_template_posts_are_published(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $footer_post   = get_post( $result[0]['post_id'] );
        $template_post = get_post( $result[0]['template_id'] );

        $this->assertSame( 'publish', $footer_post->post_status );
        $this->assertSame( 'publish', $template_post->post_status );
    }

    public function test_footer_import_result_includes_conversion_report(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->footerItem( 'My Footer' ) ] );

        $this->assertArrayHasKey( 'report', $result[0] );
    }

    public function test_convert_footers_false_imports_footer_as_regular_page(): void {
        $importer = new BatchImporter();
        $result   = $importer->import(
            [ $this->footerItem( 'My Footer' ) ],
            [ 'convert_footers' => false ]
        );

        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['success'] );
        $this->assertArrayNotHasKey( 'template_type', $result[0] );

        $post = get_post( $result[0]['post_id'] );
        $this->assertSame( 'page', $post->post_type );
    }

    public function test_regular_page_not_affected_by_footer_routing(): void {
        $importer = new BatchImporter();
        $items    = [
            $this->pageItem( 'About' ),
            $this->footerItem( 'Site Footer' ),
        ];
        $results = $importer->import( $items );

        $this->assertCount( 2, $results );

        $page_result   = $results[0];
        $footer_result = $results[1];

        $this->assertArrayNotHasKey( 'template_type', $page_result );
        $this->assertSame( 'footer', $footer_result['template_type'] );
        $this->assertSame( 'page', get_post( $page_result['post_id'] )->post_type );
        $this->assertSame( 'et_footer_layout', get_post( $footer_result['post_id'] )->post_type );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function footerItem( string $title = 'Site Footer' ): array {
        return [
            'title'         => $title,
            'post_type'     => 'page',
            'post_name'     => '',
            'template_type' => 'footer',
            'elements'      => [
                [
                    'id'       => 's1',
                    'elType'   => 'section',
                    'settings' => [],
                    'elements' => [
                        [
                            'id'       => 'col1',
                            'elType'   => 'column',
                            'settings' => [],
                            'elements' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function pageItem( string $title = 'Page' ): array {
        return [
            'title'     => $title,
            'post_type' => 'page',
            'post_name' => '',
            'elements'  => [
                [
                    'id'       => 's1',
                    'elType'   => 'section',
                    'settings' => [],
                    'elements' => [
                        [
                            'id'       => 'col1',
                            'elType'   => 'column',
                            'settings' => [],
                            'elements' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function parseJson( string $json ): array {
        $tmp = $this->tmp_dir . '/edc-footer-test-' . uniqid() . '.json';
        file_put_contents( $tmp, $json );
        try {
            $items = $this->parser->parse( $tmp, 'test.json' );
            return $items[0];
        } finally {
            @unlink( $tmp );
        }
    }

    private function createZip( array $files ): string {
        $path = $this->tmp_dir . '/edc-footer-test-' . uniqid() . '.zip';
        $zip  = new ZipArchive();
        $zip->open( $path, ZipArchive::CREATE );
        foreach ( $files as $name => $content ) {
            $zip->addFromString( $name, $content );
        }
        $zip->close();
        return $path;
    }
}
