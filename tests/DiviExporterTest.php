<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

final class DiviExporterTest extends TestCase {
    public function test_exporter_saves_divi_meta_to_post(): void {
        $engine = new ConverterEngine();

        $elementorPayload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/simple-container.json' ), true );
        $this->assertIsArray( $elementorPayload );

        $converted = $engine->convert( $elementorPayload );
        $this->assertIsArray( $converted );

        $exporter = new DiviExporter();

        $post_id = wp_insert_post( [ 'post_type' => 'page', 'post_content' => '' ] );
        $this->assertIsInt( $post_id );

        $result = $exporter->save( $post_id, $converted );
        $this->assertTrue( $result );

        $use_builder = get_post_meta( $post_id, '_et_pb_use_builder', true );
        $this->assertSame( 'on', $use_builder );

        $post = get_post( $post_id );
        $this->assertNotNull( $post );
        $this->assertStringContainsString( '<!-- wp:divi/section', $post->post_content );
        $this->assertStringContainsString( '<!-- wp:divi/text', $post->post_content );

        $old_content = get_post_meta( $post_id, '_et_pb_old_content', true );
        $this->assertSame( $post->post_content, $old_content );

        $serialized = get_post_meta( $post_id, '_edc_divi_data', true );
        $this->assertIsString( $serialized );

        $decoded = json_decode( $serialized, true );
        $this->assertSame( $converted, $decoded );
    }
}
