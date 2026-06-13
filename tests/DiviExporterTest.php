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
        $this->assertStringContainsString( '<!-- wp:divi/heading', $post->post_content );

        // _et_pb_old_content is a D4 concept (stores pre-builder content backup); must be absent on D5 pages.
        $old_content = get_post_meta( $post_id, '_et_pb_old_content', true );
        $this->assertSame( '', $old_content );

        // Divi checks _et_pb_use_divi_5 === 'on'; '1' would fail that check.
        $use_divi_5 = get_post_meta( $post_id, '_et_pb_use_divi_5', true );
        $this->assertSame( 'on', $use_divi_5 );

        // Dynamic asset caches must be absent after save so Divi regenerates them.
        $cached_modules = get_post_meta( $post_id, '_divi_dynamic_assets_cached_modules', true );
        $this->assertSame( '', $cached_modules );

        $serialized = get_post_meta( $post_id, '_edc_divi_data', true );
        $this->assertIsString( $serialized );

        $decoded = json_decode( $serialized, true );
        $this->assertSame( $converted, $decoded );
    }
}
