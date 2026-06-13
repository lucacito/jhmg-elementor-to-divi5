<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Parsers\ElementorDocumentParser;

final class ElementorDocumentParserTest extends TestCase {
    public function test_parse_returns_elements_for_json_string_post_meta(): void {
        $parser = new ElementorDocumentParser();

        $postMeta = [
            '_elementor_data' => json_encode([
                [
                    'id' => 'section-1',
                    'elType' => 'section',
                    'elements' => [],
                ],
            ]),
        ];

        $result = $parser->parse( $postMeta );

        $this->assertArrayHasKey( 'elements', $result );
        $this->assertCount( 1, $result['elements'] );
        $this->assertSame( 'section', $result['elements'][0]['elType'] );
    }

    public function test_parse_handles_post_meta_as_array_of_values(): void {
        $parser = new ElementorDocumentParser();

        $postMeta = [
            '_elementor_data' => [
                json_encode([
                    [
                        'id' => 'section-2',
                        'elType' => 'section',
                        'elements' => [],
                    ],
                ]),
            ],
        ];

        $result = $parser->parse( $postMeta );

        $this->assertArrayHasKey( 'elements', $result );
        $this->assertCount( 1, $result['elements'] );
        $this->assertSame( 'section', $result['elements'][0]['elType'] );
    }
}
