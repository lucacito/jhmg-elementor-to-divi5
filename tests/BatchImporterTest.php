<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\BatchImporter;

final class BatchImporterTest extends TestCase {
    protected function setUp(): void {
        // Reset the in-memory post store before each test.
        $GLOBALS['__test_posts']        = [];
        $GLOBALS['__test_postmeta']     = [];
        $GLOBALS['__test_next_post_id'] = 2000;
    }

    private function headingItem( string $title = 'Test Page', string $post_type = 'page' ): array {
        return [
            'title'     => $title,
            'post_type' => $post_type,
            'post_name' => '',
            'elements'  => [
                [
                    'id'         => 's1',
                    'elType'     => 'section',
                    'settings'   => [],
                    'elements'   => [
                        [
                            'id'       => 'col-1',
                            'elType'   => 'column',
                            'settings' => [],
                            'elements' => [
                                [
                                    'id'         => 'h1',
                                    'elType'     => 'widget',
                                    'widgetType' => 'heading',
                                    'settings'   => [ 'title' => $title, 'header_size' => 'h2' ],
                                    'elements'   => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_imports_single_item_and_returns_result(): void {
        $importer = new BatchImporter();
        $results  = $importer->import( [ $this->headingItem( 'Home Page' ) ] );

        $this->assertCount( 1, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'Home Page', $results[0]['title'] );
        $this->assertGreaterThan( 0, $results[0]['post_id'] );
        $this->assertSame( '', $results[0]['error'] );
    }

    public function test_creates_post_in_wordpress(): void {
        $importer = new BatchImporter();
        $results  = $importer->import( [ $this->headingItem( 'About' ) ] );

        $post_id = $results[0]['post_id'];
        $post    = get_post( $post_id );

        $this->assertNotNull( $post );
        $this->assertSame( 'About', $post->post_title );
    }

    public function test_respects_post_type_option(): void {
        $importer = new BatchImporter();
        $results  = $importer->import(
            [ $this->headingItem( 'News' ) ],
            [ 'post_type' => 'post' ]
        );

        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'post', $post->post_type );
    }

    public function test_respects_post_status_option(): void {
        $importer = new BatchImporter();
        $results  = $importer->import(
            [ $this->headingItem( 'Draft' ) ],
            [ 'post_status' => 'draft' ]
        );

        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'draft', $post->post_status );
    }

    public function test_result_contains_conversion_report(): void {
        $importer = new BatchImporter();
        $results  = $importer->import( [ $this->headingItem( 'Reported Page' ) ] );

        $this->assertArrayHasKey( 'report', $results[0] );
        $this->assertArrayHasKey( 'converted', $results[0]['report'] );
    }

    public function test_marks_post_with_import_source_meta(): void {
        $importer = new BatchImporter();
        $results  = $importer->import( [ $this->headingItem() ] );

        $post_id = $results[0]['post_id'];
        $source  = get_post_meta( $post_id, '_edc_import_source', true );
        $this->assertSame( 'file_upload', $source );
    }

    public function test_imports_multiple_items(): void {
        $items = [
            $this->headingItem( 'Page One' ),
            $this->headingItem( 'Page Two' ),
            $this->headingItem( 'Page Three' ),
        ];

        $importer = new BatchImporter();
        $results  = $importer->import( $items );

        $this->assertCount( 3, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertTrue( $results[1]['success'] );
        $this->assertTrue( $results[2]['success'] );

        // Each should get a unique post ID.
        $ids = array_column( $results, 'post_id' );
        $this->assertCount( 3, array_unique( $ids ) );
    }

    public function test_post_type_option_overrides_item_post_type(): void {
        // The "Create as" dropdown selection (option) is the user's explicit intent
        // and always wins, regardless of what post_type the export file suggests.
        $item            = $this->headingItem( 'Article' );
        $item['post_type'] = 'page';

        $importer = new BatchImporter();
        $results  = $importer->import( [ $item ], [ 'post_type' => 'post' ] );

        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'post', $post->post_type );
    }

    public function test_empty_title_falls_back_to_imported_page(): void {
        $item          = $this->headingItem();
        $item['title'] = '';

        $importer = new BatchImporter();
        $results  = $importer->import( [ $item ] );

        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'Imported Page', $post->post_title );
    }

    public function test_preserves_post_name_when_set(): void {
        $item            = $this->headingItem( 'Services' );
        $item['post_name'] = 'services-page';

        $importer = new BatchImporter();
        $results  = $importer->import( [ $item ] );

        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'services-page', $post->post_name );
    }

    public function test_unsupported_elements_recorded_in_result(): void {
        $item = [
            'title'     => 'With Unsupported',
            'post_type' => 'page',
            'post_name' => '',
            'elements'  => [
                [
                    'id'         => 'w1',
                    'elType'     => 'widget',
                    'widgetType' => 'e-unknown-xyz',
                    'settings'   => [],
                    'elements'   => [],
                ],
            ],
        ];

        $importer = new BatchImporter();
        $results  = $importer->import( [ $item ] );

        $this->assertTrue( $results[0]['success'] );
        $this->assertNotEmpty( $results[0]['unsupported'] );
        $this->assertSame( 'e-unknown-xyz', $results[0]['unsupported'][0]['widgetType'] );
    }
}
