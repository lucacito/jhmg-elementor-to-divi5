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
        $this->assertStringContainsString( '<!-- wp:divi/heading', $blocks );
        $this->assertStringContainsString( 'Hello World', $blocks );
        $this->assertStringNotContainsString( '{"divi"', $blocks );
        $this->assertStringNotContainsString( '[et_pb_', $blocks );
    }

    public function test_serializes_internal_divi_structure_to_exact_block_format(): void {
        $divi_data = [
            'divi' => [
                'elements' => [
                    [
                        'name'     => 'divi/section',
                        'settings' => [],
                        'elements' => [
                            [
                                'name'     => 'divi/row',
                                'settings' => [],
                                'elements' => [
                                    [
                                        'name'     => 'divi/column',
                                        'settings' => [],
                                        'elements' => [
                                            [
                                                'name'     => 'divi/text',
                                                'settings' => [
                                                    'content' => [
                                                        'innerContent' => [
                                                            'desktop' => [ 'value' => '<h2>Hello World</h2>' ],
                                                        ],
                                                    ],
                                                ],
                                                'elements' => [],
                                            ],
                                            [
                                                'name'     => 'divi/button',
                                                'settings' => [
                                                    'button' => [
                                                        'innerContent' => [
                                                            'desktop' => [
                                                                'value' => [
                                                                    'text'    => 'Click Here',
                                                                    'linkUrl' => 'https://example.com',
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'elements' => [],
                                            ],
                                            [
                                                'name'     => 'divi/image',
                                                'settings' => [
                                                    'image' => [
                                                        'innerContent' => [
                                                            'desktop' => [
                                                                'value' => [ 'src' => 'https://example.com/sample.jpg' ],
                                                            ],
                                                        ],
                                                    ],
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

        $bv           = '{"builderVersion":"5.0.0-public-alpha.18.2"}';
        $text_attrs   = '{"builderVersion":"5.0.0-public-alpha.18.2","content":{"innerContent":{"desktop":{"value":"<h2>Hello World</h2>"}}}}';
        $button_attrs = '{"builderVersion":"5.0.0-public-alpha.18.2","button":{"innerContent":{"desktop":{"value":{"text":"Click Here","linkUrl":"https://example.com"}}}}}';
        $image_attrs  = '{"builderVersion":"5.0.0-public-alpha.18.2","image":{"innerContent":{"desktop":{"value":{"src":"https://example.com/sample.jpg"}}}}}';

        $expected = '<!-- wp:divi/placeholder -->'
            . "<!-- wp:divi/section {$bv} -->"
            . "<!-- wp:divi/row {$bv} -->"
            . "<!-- wp:divi/column {$bv} -->"
            . "<!-- wp:divi/text {$text_attrs} /-->"
            . "<!-- wp:divi/button {$button_attrs} /-->"
            . "<!-- wp:divi/image {$image_attrs} /-->"
            . '<!-- /wp:divi/column -->'
            . '<!-- /wp:divi/row -->'
            . '<!-- /wp:divi/section -->'
            . '<!-- /wp:divi/placeholder -->';

        $this->assertSame( $expected, $blocks );
    }

    public function test_accordion_serialized_as_wrap_block_with_items(): void {
        $engine  = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/accordion.json' ), true );
        $blocks  = ( new DiviBlockSerializer() )->serialize( $engine->convert( $payload ) );

        $this->assertStringContainsString( '<!-- wp:divi/accordion {', $blocks );
        $this->assertStringContainsString( 'builderVersion', $blocks );
        $this->assertStringContainsString( '<!-- /wp:divi/accordion -->', $blocks );
        $this->assertStringContainsString( 'divi/accordion-item', $blocks );
        $this->assertStringContainsString( 'Panel One', $blocks );
        $this->assertStringContainsString( 'Panel Two', $blocks );
    }

    public function test_tabs_serialized_as_wrap_block_with_tab_children(): void {
        $engine  = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/tabs.json' ), true );
        $blocks  = ( new DiviBlockSerializer() )->serialize( $engine->convert( $payload ) );

        $this->assertStringContainsString( '<!-- wp:divi/tabs {', $blocks );
        $this->assertStringContainsString( 'builderVersion', $blocks );
        $this->assertStringContainsString( '<!-- /wp:divi/tabs -->', $blocks );
        $this->assertStringContainsString( 'divi/tab', $blocks );
        $this->assertStringContainsString( 'First Tab', $blocks );
    }

    public function test_spacer_serialized_as_divider_with_hidden_line(): void {
        $engine  = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/spacer.json' ), true );
        $blocks  = ( new DiviBlockSerializer() )->serialize( $engine->convert( $payload ) );

        $this->assertStringContainsString( '<!-- wp:divi/divider', $blocks );
        $this->assertStringContainsString( '"show":"off"', $blocks );
        $this->assertStringContainsString( '"minHeight":"50px"', $blocks );
        $this->assertStringContainsString( '/-->', $blocks );
    }

    public function test_icon_serialized_with_color_and_size(): void {
        $engine  = new ConverterEngine();
        $payload = json_decode( file_get_contents( __DIR__ . '/../fixtures/elementor/icon.json' ), true );
        $blocks  = ( new DiviBlockSerializer() )->serialize( $engine->convert( $payload ) );

        $this->assertStringContainsString( '<!-- wp:divi/icon', $blocks );
        $this->assertStringContainsString( '#ff6600', $blocks );
        $this->assertStringContainsString( '40px', $blocks );
    }
}
