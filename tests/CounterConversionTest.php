<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * divi/number-counter animates number.innerContent as a number and appends its own
 * percent sign while number.advanced.enablePercentSign is on, which is the default
 * (number-counter/module.json). "240+" therefore showed as "233%" mid-animation and
 * "58%" as "58%%". Divi has no other prefix or suffix; those are reported.
 */
final class CounterConversionTest extends TestCase {
    private function convert( array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 'c1', 'elType' => 'widget', 'widgetType' => 'counter', 'settings' => $settings, 'elements' => [] ] ] );
    }

    public function test_percent_suffix_uses_divis_percent_sign(): void {
        $block = $this->convert( [ 'starting_number' => 0, 'ending_number' => 58, 'suffix' => '%', 'title' => 'Freelancers' ] )['divi']['elements'][0];
        $this->assertSame( '58', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
        $this->assertSame( 'Freelancers', $block['settings']['title']['innerContent']['desktop']['value'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'counter' );
    }

    public function test_other_affixes_are_dropped_and_reported(): void {
        $result = $this->convert( [ 'ending_number' => '240', 'prefix' => '$', 'suffix' => '+', 'title' => 'Members' ] );
        $block  = $result['divi']['elements'][0];
        $this->assertSame( '240', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'off', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
        $this->assertSame( [ [ 'kind' => 'counter_affix', 'element_id' => 'c1', 'detail' => "prefix '$', suffix '+'" ] ], $result['report']['not_carried_over'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_a_number_without_suffix_shows_no_percent_sign(): void {
        $block = $this->convert( [ 'ending_number' => 1200 ] )['divi']['elements'][0];
        $this->assertSame( '1200', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'off', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
    }

    public function test_title_colour_and_typography_reach_the_title_font(): void {
        $block = $this->convert( [ 'ending_number' => 5, 'title' => 'x', 'title_color' => '#1F2421', 'title_typography_typography' => 'custom', 'title_typography_font_size' => [ 'unit' => 'px', 'size' => 14 ], 'number_color' => '#2F4F3A' ] )['divi']['elements'][0];
        $this->assertSame( '#1F2421', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '14px', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '#2F4F3A', $block['settings']['number']['decoration']['font']['font']['desktop']['value']['color'] );
    }
}
