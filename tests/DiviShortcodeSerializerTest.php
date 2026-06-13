<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviBlockSerializer;

final class DiviShortcodeSerializerTest extends TestCase {
    public function test_serializes_divi_data_to_block_format(): void {
        $engine = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/heading.json' ), true );
        $this->assertIsArray( $payload );

        $converted = $engine->convert( $payload );
        $this->assertIsArray( $converted );

        $serializer = new DiviBlockSerializer();
        $blocks = $serializer->serialize( $converted );

        $this->assertStringContainsString( '<!-- wp:divi/section', $blocks );
        $this->assertStringContainsString( '<!-- wp:divi/text', $blocks );
        $this->assertStringContainsString( '<h2>Hello World</h2>', $blocks );
        $this->assertStringNotContainsString( '{"divi"', $blocks );
        $this->assertStringNotContainsString( '[et_pb_', $blocks );
    }

    public function test_serializes_internal_divi_structure_to_exact_block_format(): void {
        $divi_data = [
            'divi' => [
                'elements' => [
                    [
                        'name' => 'divi/section',
                        'settings' => [
                            'module' => [],
                        ],
                        'elements' => [
                            [
                                'name' => 'divi/row',
                                'settings' => [
                                    'module' => [],
                                ],
                                'elements' => [
                                    [
                                        'name' => 'divi/column',
                                        'settings' => [
                                            'module' => [],
                                        ],
                                        'elements' => [
                                            [
                                                'name' => 'divi/text',
                                                'settings' => [
                                                    'innerContent' => 'Hello World',
                                                    'tagName' => 'h2',
                                                    'module' => [],
                                                ],
                                                'elements' => [],
                                            ],
                                            [
                                                'name' => 'divi/button',
                                                'settings' => [
                                                    'text' => 'Click Here',
                                                    'link' => [
                                                        'url' => 'https://example.com',
                                                    ],
                                                    'module' => [],
                                                ],
                                                'elements' => [],
                                            ],
                                            [
                                                'name' => 'divi/image',
                                                'settings' => [
                                                    'src' => 'https://example.com/sample.jpg',
                                                    'module' => [],
                                                ],
                                                'elements' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $serializer = new DiviBlockSerializer();
        $blocks = $serializer->serialize( $divi_data );

        $text_attrs   = '{"content":{"innerContent":{"desktop":{"value":"<h2>Hello World</h2>"}}}}';
        $button_attrs = '{"button":{"innerContent":{"desktop":{"value":{"text":"Click Here","linkUrl":"https://example.com"}}}}}';
        $image_attrs  = '{"image":{"innerContent":{"desktop":{"value":{"src":"https://example.com/sample.jpg"}}}}}';

        $expected = '<!-- wp:divi/section {} -->'
            . '<!-- wp:divi/row {} -->'
            . '<!-- wp:divi/column {} -->'
            . "<!-- wp:divi/text {$text_attrs} /-->"
            . "<!-- wp:divi/button {$button_attrs} /-->"
            . "<!-- wp:divi/image {$image_attrs} /-->"
            . '<!-- /wp:divi/column -->'
            . '<!-- /wp:divi/row -->'
            . '<!-- /wp:divi/section -->';

        $this->assertSame( $expected, $blocks );
    }
}
