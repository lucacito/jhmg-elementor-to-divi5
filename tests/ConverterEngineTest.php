<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

final class ConverterEngineTest extends TestCase {
    public function test_can_convert_elementor_section_tree_to_divi_structure(): void {
        $engine = new ConverterEngine();

        $elementorData = [
            [
                'id' => 'section-1',
                'elType' => 'section',
                'elements' => [
                    [
                        'id' => 'column-1',
                        'elType' => 'column',
                        'elements' => [
                            [
                                'id' => 'heading-1',
                                'elType' => 'widget',
                                'widgetType' => 'e-heading',
                                'settings' => [
                                    'title' => [
                                        '$$type' => 'string',
                                        'value' => 'Hello World',
                                    ],
                                ],
                                'elements' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $engine->convert( $elementorData );

        $this->assertArrayHasKey( 'divi', $result );
        $this->assertCount( 1, $result['divi']['elements'] );
        $section = $result['divi']['elements'][0];
        $this->assertSame( 'divi/section', $section['name'] );
        $this->assertCount( 1, $section['elements'] );

        // SectionConverter now wraps all column children in an explicit divi/row.
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );
        $this->assertCount( 1, $row['elements'] );

        $column = $row['elements'][0];
        $this->assertSame( 'divi/column', $column['name'] );
        $this->assertCount( 1, $column['elements'] );

        $heading = $column['elements'][0];
        $this->assertSame( 'divi/text', $heading['name'] );
        $this->assertSame( '<h2>Hello World</h2>', $heading['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_logs_unsupported_elementor_widget_types(): void {
        $engine = new ConverterEngine();

        $elementorData = [
            [
                'id' => 'widget-unknown',
                'elType' => 'widget',
                'widgetType' => 'e-unknown',
                'settings' => [],
                'elements' => [],
            ],
        ];

        $result = $engine->convert( $elementorData );

        $this->assertEmpty( $result['divi']['elements'] );
        $this->assertCount( 1, $result['unsupported'] );
        $this->assertSame( 'e-unknown', $result['unsupported'][0]['widgetType'] );
        $this->assertSame( 'widget-unknown', $result['unsupported'][0]['id'] );
    }
}
