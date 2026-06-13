<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

final class ConverterFixtureTest extends TestCase {
    private function loadFixture( string $fixture ): array {
        $json = file_get_contents( __DIR__ . "/../fixtures/elementor/{$fixture}.json" );
        $this->assertIsString( $json, "Failed to load fixture: {$fixture}" );

        $data = json_decode( $json, true );
        $this->assertIsArray( $data, "Fixture is invalid JSON: {$fixture}" );

        return $data;
    }

    private function loadExpected( string $fixture ): array {
        $json = file_get_contents( __DIR__ . "/../fixtures/divi/{$fixture}.json" );
        $this->assertIsString( $json, "Failed to load expected fixture: {$fixture}" );

        $data = json_decode( $json, true );
        $this->assertIsArray( $data, "Expected fixture is invalid JSON: {$fixture}" );

        return $data;
    }

    public static function fixtureProvider(): array {
        return [
            [ 'heading' ],
            [ 'text' ],
            [ 'image' ],
            [ 'button' ],
            [ 'divider' ],
            [ 'video' ],
            [ 'spacer' ],
            [ 'icon' ],
            [ 'image-box' ],
            [ 'accordion' ],
            [ 'tabs' ],
            [ 'simple-container' ],
            [ 'nested-container' ],
            [ 'real-elementor' ],
            [ 'column-overlay' ],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function test_converter_matches_expected_fixture( string $fixture ): void {
        $engine = new ConverterEngine();
        $elementorPayload = $this->loadFixture( $fixture );
        $expected = $this->loadExpected( $fixture );

        $result = $engine->convert( $elementorPayload );

        // Compare only the structural output. The `report` key contains conversion
        // metrics that are not part of the expected fixture files.
        $structural = [
            'divi'        => $result['divi'],
            'unsupported' => $result['unsupported'],
        ];

        $this->assertSame(
            $expected,
            $structural,
            "Converter output did not match expected Divi fixture for {$fixture}."
        );
    }
}
