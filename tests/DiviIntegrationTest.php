<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

final class DiviIntegrationTest extends TestCase {
    public function test_divi_recognizes_builder_page_after_export(): void {
        // If Divi core is available in the environment, the test will call
        // `et_pb_is_pagebuilder_used`. If not, fall back to asserting the
        // presence of Divi meta keys that indicate builder usage.

        $engine = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/simple-container.json' ), true );
        $converted = $engine->convert( $payload );

        $exporter = new DiviExporter();

        // Create a temporary post.
        $post_id = wp_insert_post( [ 'post_type' => 'page', 'post_content' => '' ] );
        $this->assertIsInt( $post_id );

        // Save converted structure as Divi meta (exporter uses update_post_meta).
        $saved = $exporter->save( $post_id, $converted );
        $this->assertTrue( $saved );

        $post = get_post( $post_id );
        $this->assertNotNull( $post );
        $this->assertStringContainsString( '<!-- wp:divi/section', $post->post_content );

        if ( function_exists( 'et_pb_is_pagebuilder_used' ) ) {
            $is_builder = et_pb_is_pagebuilder_used( $post_id );
            $this->assertTrue( $is_builder, 'Divi did not recognize the page as a builder page.' );
        } else {
            // Fall back: check required meta keys.
            $use_builder = get_post_meta( $post_id, '_et_pb_use_builder', true );
            $this->assertSame( 'on', $use_builder );
        }
    }

    public function test_exported_post_contains_divi5_block_format(): void {
        $engine = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/heading.json' ), true );
        $this->assertIsArray( $payload );

        $converted = $engine->convert( $payload );
        $this->assertIsArray( $converted );

        $exporter = new DiviExporter();
        $post_id = wp_insert_post( [ 'post_type' => 'page', 'post_content' => '' ] );
        $this->assertIsInt( $post_id );

        $saved = $exporter->save( $post_id, $converted );
        $this->assertTrue( $saved );

        $post = get_post( $post_id );
        $this->assertNotNull( $post );
        $this->assertStringContainsString( '<!-- wp:divi/section', $post->post_content );
        $this->assertStringContainsString( '<h2>Hello World</h2>', $post->post_content );

        $builder_version = get_post_meta( $post_id, '_et_builder_version', true );
        $this->assertIsString( $builder_version );
        $this->assertStringContainsString( 'VB|Divi|', $builder_version );
    }
}
