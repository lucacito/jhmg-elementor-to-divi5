<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

final class ConversionReportTest extends TestCase {

    public function test_report_counts_converted_elements(): void {
        $engine = new ConverterEngine();

        $result = $engine->convert( [
            [
                'id'       => 'section-1',
                'elType'   => 'section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => 'col-1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'h-1',
                                'elType'     => 'widget',
                                'widgetType' => 'e-heading',
                                'settings'   => [ 'title' => 'Hello', 'tag' => 'h2' ],
                                'elements'   => [],
                            ],
                            [
                                'id'         => 'txt-1',
                                'elType'     => 'widget',
                                'widgetType' => 'e-paragraph',
                                'settings'   => [ 'paragraph' => 'Body text.' ],
                                'elements'   => [],
                            ],
                            [
                                'id'         => 'btn-1',
                                'elType'     => 'widget',
                                'widgetType' => 'e-button',
                                'settings'   => [ 'text' => 'Click', 'link' => [] ],
                                'elements'   => [],
                            ],
                            [
                                'id'         => 'img-1',
                                'elType'     => 'widget',
                                'widgetType' => 'e-image',
                                'settings'   => [ 'image' => [ 'src' => 'https://example.com/x.jpg', 'alt' => 'Alt' ] ],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $report = $result['report'];

        $this->assertSame( 1, $report['converted']['section'] );
        $this->assertSame( 1, $report['converted']['column'] );
        $this->assertSame( 1, $report['converted']['heading'] );
        $this->assertSame( 1, $report['converted']['text'] );
        $this->assertSame( 1, $report['converted']['button'] );
        $this->assertSame( 1, $report['converted']['image'] );
    }

    public function test_report_logs_unsupported_widgets(): void {
        $engine = new ConverterEngine();

        $result = $engine->convert( [
            [
                'id'         => 'widget-bad',
                'elType'     => 'widget',
                'widgetType' => 'e-carousel',
                'settings'   => [],
                'elements'   => [],
            ],
        ] );

        $this->assertCount( 1, $result['unsupported'] );
        $this->assertSame( 'e-carousel', $result['unsupported'][0]['widgetType'] );
    }

    public function test_report_warns_on_empty_column(): void {
        $engine = new ConverterEngine();

        $result = $engine->convert( [
            [
                'id'       => 'section-1',
                'elType'   => 'section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => 'empty-col',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'widget-unsupported',
                                'elType'     => 'widget',
                                'widgetType' => 'e-form',
                                'settings'   => [],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $warnings = $result['report']['warnings'];
        $this->assertNotEmpty( $warnings );

        $hasEmptyColumnWarning = array_filter( $warnings, static fn( $w ) => str_contains( $w, 'Empty column' ) );
        $this->assertNotEmpty( $hasEmptyColumnWarning, 'Expected warning about empty column' );
    }

    public function test_report_logs_skipped_background_settings(): void {
        $engine = new ConverterEngine();

        $result = $engine->convert( [
            [
                'id'       => 'bg-section',
                'elType'   => 'section',
                'settings' => [
                    'background_color' => '#ff0000',
                    'custom_padding'   => [ 'top' => '40', 'unit' => 'px' ],
                ],
                'elements' => [
                    [
                        'id'       => 'col-1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'h-1',
                                'elType'     => 'widget',
                                'widgetType' => 'e-heading',
                                'settings'   => [ 'title' => 'Hi', 'tag' => 'h2' ],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        // background_color is now mapped by the section converter via StyleMapper,
        // so it should NOT appear in skipped settings.
        $skipped = $result['report']['skipped_settings'];
        $hasBackgroundColor = array_filter( $skipped, static fn( $s ) => str_contains( $s, 'background_color' ) );
        $this->assertEmpty( $hasBackgroundColor, 'background_color should be mapped, not skipped' );

        // custom_padding is not a standard key handled by StyleMapper (uses 'padding'), so it stays skipped.
        $hasPadding = array_filter( $skipped, static fn( $s ) => str_contains( $s, 'custom_padding' ) );
        $this->assertNotEmpty( $hasPadding, 'Expected skipped_settings entry for custom_padding' );
    }

    public function test_report_warns_on_image_missing_alt(): void {
        $engine = new ConverterEngine();

        $result = $engine->convert( [
            [
                'id'       => 'section-1',
                'elType'   => 'section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => 'col-1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            [
                                'id'         => 'img-noalt',
                                'elType'     => 'widget',
                                'widgetType' => 'e-image',
                                'settings'   => [ 'image' => [ 'src' => 'https://example.com/x.jpg' ] ],
                                'elements'   => [],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $warnings = $result['report']['warnings'];
        $hasAltWarning = array_filter( $warnings, static fn( $w ) => str_contains( $w, 'missing alt text' ) );
        $this->assertNotEmpty( $hasAltWarning, 'Expected warning about missing alt text' );
    }

    public function test_exporter_saves_conversion_report_meta(): void {
        $engine = new ConverterEngine();

        $payload   = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/heading.json' ), true );
        $converted = $engine->convert( $payload );

        $exporter = new DiviExporter();
        $post_id  = wp_insert_post( [ 'post_type' => 'page', 'post_content' => '' ] );
        $this->assertIsInt( $post_id );

        $exporter->save( $post_id, $converted );

        $raw    = get_post_meta( $post_id, '_edc_conversion_report', true );
        $this->assertIsString( $raw );

        $report = json_decode( $raw, true );
        $this->assertIsArray( $report );

        $this->assertArrayHasKey( 'converted', $report );
        $this->assertArrayHasKey( 'warnings', $report );
        $this->assertArrayHasKey( 'skipped_settings', $report );
        $this->assertArrayHasKey( 'unsupported', $report );

        $this->assertSame( 1, $report['converted']['heading'] );
        $this->assertSame( 1, $report['converted']['section'] );
    }
}
