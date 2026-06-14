<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Parsers\ElementorImportParser;
use ElementorDivi5Converter\Admin\BatchImporter;
use ElementorDivi5Converter\Exporters\DiviThemeBuilderExporter;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Tests for Elementor header template → Divi Theme Builder header conversion.
 */
final class HeaderTemplateConversionTest extends TestCase {
    private ElementorImportParser $parser;
    private string $tmp_dir;

    protected function setUp(): void {
        $this->parser  = new ElementorImportParser();
        $this->tmp_dir = sys_get_temp_dir();

        $GLOBALS['__test_posts']        = [];
        $GLOBALS['__test_postmeta']     = [];
        $GLOBALS['__test_next_post_id'] = 3000;
    }

    // -------------------------------------------------------------------------
    // Parser: header template detection
    // -------------------------------------------------------------------------

    public function test_detects_header_type_from_elementor_template_export(): void {
        $json = json_encode( [
            'version' => '0.4',
            'title'   => 'Site Header',
            'type'    => 'header',
            'content' => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );

        $this->assertSame( 'header', $item['template_type'] );
    }

    public function test_detects_header_type_case_insensitive(): void {
        $json = json_encode( [
            'title'   => 'My Header',
            'type'    => 'Header',
            'content' => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );
        $this->assertSame( 'header', $item['template_type'] );
    }

    public function test_detects_header_from_et_header_layout_post_type(): void {
        $json = json_encode( [
            'title'     => 'TB Header',
            'post_type' => 'et_header_layout',
            'elements'  => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );
        $this->assertSame( 'header', $item['template_type'] );
    }

    public function test_normal_page_does_not_get_template_type(): void {
        $json = json_encode( [
            'title'   => 'About',
            'type'    => 'page',
            'content' => [
                [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
            ],
        ] );

        $item = $this->parseJson( $json );
        $this->assertArrayNotHasKey( 'template_type', $item );
    }

    public function test_template_type_preserved_in_zip_manifest(): void {
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->markTestSkipped( 'ZipArchive not available.' );
        }

        $zip_path = $this->createZip( [
            'manifest.json' => json_encode( [
                'templates' => [
                    'site-header' => [
                        'post_title' => 'Site Header',
                        'post_type'  => 'header',
                        'post_name'  => 'site-header',
                    ],
                ],
            ] ),
            'content/site-header.json' => json_encode( [
                'title'   => 'Site Header',
                'type'    => 'header',
                'content' => [
                    [ 'id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [] ],
                ],
            ] ),
        ] );

        try {
            $items = $this->parser->parse( $zip_path, 'export.zip' );
            $header_items = array_filter( $items, fn( $i ) => ( $i['template_type'] ?? '' ) === 'header' );
            $this->assertNotEmpty( $header_items );
        } finally {
            @unlink( $zip_path );
        }
    }

    // -------------------------------------------------------------------------
    // BatchImporter: header routing
    // -------------------------------------------------------------------------

    public function test_header_item_creates_et_header_layout_post(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['success'], $result[0]['error'] ?? '' );
        $this->assertSame( 'My Header', $result[0]['title'] );
        $this->assertSame( 'header', $result[0]['template_type'] );
        $this->assertGreaterThan( 0, $result[0]['post_id'] );

        $post = get_post( $result[0]['post_id'] );
        $this->assertNotNull( $post );
        $this->assertSame( 'et_header_layout', $post->post_type );
    }

    public function test_header_import_creates_et_template_post(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $this->assertArrayHasKey( 'template_id', $result[0] );
        $template_id = $result[0]['template_id'];
        $this->assertGreaterThan( 0, $template_id );

        $template_post = get_post( $template_id );
        $this->assertNotNull( $template_post );
        $this->assertSame( 'et_template', $template_post->post_type );
    }

    public function test_header_template_post_has_correct_meta(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $template_id     = $result[0]['template_id'];
        $header_layout_id = $result[0]['post_id'];

        $this->assertSame( '1', get_post_meta( $template_id, '_et_default', true ) );
        $this->assertSame( '1', get_post_meta( $template_id, '_et_enabled', true ) );
        $this->assertSame( '1', get_post_meta( $template_id, '_et_header_layout_enabled', true ) );
        $this->assertSame( $header_layout_id, (int) get_post_meta( $template_id, '_et_header_layout_id', true ) );
    }

    public function test_header_template_linked_to_theme_builder(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $tb_id       = $result[0]['theme_builder_id'];
        $template_id = $result[0]['template_id'];
        $this->assertGreaterThan( 0, $tb_id );

        $linked = get_post_meta( $tb_id, '_et_template' );
        $this->assertContains( $template_id, array_map( 'intval', $linked ) );
    }

    public function test_header_layout_post_has_divi_builder_meta(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $header_id = $result[0]['post_id'];
        $this->assertSame( 'on', get_post_meta( $header_id, '_et_pb_use_builder', true ) );
        $this->assertSame( 'on', get_post_meta( $header_id, '_et_pb_use_divi_5', true ) );
        $this->assertSame( 'on', get_post_meta( $header_id, '_et_pb_show_page_creation', true ) );
    }

    public function test_header_layout_and_template_posts_are_published(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $header_post   = get_post( $result[0]['post_id'] );
        $template_post = get_post( $result[0]['template_id'] );

        $this->assertSame( 'publish', $header_post->post_status );
        $this->assertSame( 'publish', $template_post->post_status );
    }

    public function test_header_import_result_includes_conversion_report(): void {
        $importer = new BatchImporter();
        $result   = $importer->import( [ $this->headerItem( 'My Header' ) ] );

        $this->assertArrayHasKey( 'report', $result[0] );
    }

    public function test_convert_headers_false_imports_header_as_regular_page(): void {
        $importer = new BatchImporter();
        $result   = $importer->import(
            [ $this->headerItem( 'My Header' ) ],
            [ 'convert_headers' => false ]
        );

        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['success'] );
        $this->assertArrayNotHasKey( 'template_type', $result[0] );

        $post = get_post( $result[0]['post_id'] );
        $this->assertSame( 'page', $post->post_type );
    }

    public function test_regular_page_not_affected_by_header_routing(): void {
        $importer = new BatchImporter();
        $items    = [
            $this->pageItem( 'About' ),
            $this->headerItem( 'Site Header' ),
        ];
        $results = $importer->import( $items );

        $this->assertCount( 2, $results );

        $page_result   = $results[0];
        $header_result = $results[1];

        $this->assertArrayNotHasKey( 'template_type', $page_result );
        $this->assertSame( 'header', $header_result['template_type'] );
        $this->assertSame( 'page', get_post( $page_result['post_id'] )->post_type );
        $this->assertSame( 'et_header_layout', get_post( $header_result['post_id'] )->post_type );
    }

    // -------------------------------------------------------------------------
    // NavMenuConverter
    // -------------------------------------------------------------------------

    public function test_nav_menu_converter_maps_to_divi_menu(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [
                'id'         => 's1',
                'elType'     => 'section',
                'settings'   => [],
                'elements'   => [
                    [
                        'id'       => 'col1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'w1',
                                'elType'     => 'widget',
                                'widgetType' => 'nav-menu',
                                'settings'   => [ 'menu' => 42 ],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $section   = $result['divi']['elements'][0];
        $row       = $section['elements'][0];
        $column    = $row['elements'][0];
        $menu_block = $column['elements'][0];

        $this->assertSame( 'divi/menu', $menu_block['name'] );
        $this->assertSame( '42', $menu_block['settings']['menu']['innerContent']['desktop']['value']['menuId'] );
    }

    public function test_nav_menu_converter_handles_empty_menu(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [
                'id'         => 's1',
                'elType'     => 'section',
                'settings'   => [],
                'elements'   => [
                    [
                        'id'       => 'col1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'w1',
                                'elType'     => 'widget',
                                'widgetType' => 'nav-menu',
                                'settings'   => [],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $column     = $result['divi']['elements'][0]['elements'][0]['elements'][0];
        $menu_block = $column['elements'][0];

        $this->assertSame( 'divi/menu', $menu_block['name'] );
        $this->assertSame( [], $menu_block['settings'] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function headerItem( string $title = 'Site Header' ): array {
        return [
            'title'         => $title,
            'post_type'     => 'page',
            'post_name'     => '',
            'template_type' => 'header',
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
        $tmp = $this->tmp_dir . '/edc-header-test-' . uniqid() . '.json';
        file_put_contents( $tmp, $json );
        try {
            $items = $this->parser->parse( $tmp, 'test.json' );
            return $items[0];
        } finally {
            @unlink( $tmp );
        }
    }

    private function createZip( array $files ): string {
        $path = $this->tmp_dir . '/edc-header-test-' . uniqid() . '.zip';
        $zip  = new ZipArchive();
        $zip->open( $path, ZipArchive::CREATE );
        foreach ( $files as $name => $content ) {
            $zip->addFromString( $name, $content );
        }
        $zip->close();
        return $path;
    }
}
