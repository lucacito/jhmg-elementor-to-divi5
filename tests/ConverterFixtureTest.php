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
            [ 'simple-container' ],
            [ 'nested-container' ],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function test_converter_matches_expected_fixture( string $fixture ): void {
        $engine = new ConverterEngine();
        $elementorPayload = $this->loadFixture( $fixture );
        $expected = $this->loadExpected( $fixture );

        $result = $engine->convert( $elementorPayload );

        $this->assertSame(
            $expected,
            $result,
            "Converter output did not match expected Divi fixture for {$fixture}."
        );
    }
}
