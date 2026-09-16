<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's header_size accepts span, p and div beside h1-h6. divi/heading
 * renders the level as the tag but styles only h1-h6 (heading/module.json title
 * selector), so Ceramic Studio's 12vw display words rendered as default text.
 * Approved mapping: a divi/text keeping the tag, typography on the body font.
 */
final class HeadingLevelTest extends TestCase {
    private function convert( array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [
            'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings, 'elements' => [],
        ] ] )['divi']['elements'][0];
    }

    public function test_span_heading_becomes_text_with_the_typography_on_the_body_font(): void {
        $block = $this->convert( [
            'title' => 'eramic', 'header_size' => 'span', 'align' => 'left',
            'typography_typography' => 'custom', 'typography_font_size' => [ 'unit' => 'vw', 'size' => 12 ],
            'typography_font_weight' => '700', 'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
            'title_color' => '#C8643B', '_margin' => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '-40', 'left' => '0', 'isLinked' => '' ],
        ] );

        $this->assertSame( 'divi/text', $block['name'] );
        $this->assertSame( '<span>eramic</span>', $block['settings']['content']['innerContent']['desktop']['value'] );
        $font = $block['settings']['content']['decoration']['bodyFont']['body']['font']['desktop']['value'];
        $this->assertSame( '12vw', $font['size'] );
        $this->assertSame( '700', $font['weight'] );
        $this->assertSame( '-2px', $font['letterSpacing'] );
        $this->assertSame( '#C8643B', $font['color'] );
        $this->assertSame( 'left', $font['textAlign'] );
        $this->assertArrayNotHasKey( 'title', $block['settings'] );
        $this->assertSame( '-40px', $block['settings']['module']['decoration']['spacing']['desktop']['value']['margin']['bottom'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'span heading' );
    }

    public function test_p_and_div_keep_their_tag(): void {
        $this->assertSame( '<p>Hi</p>', $this->convert( [ 'title' => 'Hi', 'header_size' => 'p' ] )['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( '<div>Hi</div>', $this->convert( [ 'title' => 'Hi', 'header_size' => 'div' ] )['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_real_heading_levels_are_unchanged(): void {
        $block = $this->convert( [ 'title' => 'Hi', 'header_size' => 'h3' ] );
        $this->assertSame( 'divi/heading', $block['name'] );
        $this->assertSame( 'h3', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_report_counts_a_span_heading_as_text(): void {
        $report = ( new ConverterEngine() )->convert( [ [
            'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'x', 'header_size' => 'span' ], 'elements' => [],
        ] ] )['report'];
        $this->assertSame( 1, $report['converted']['text'] );
        $this->assertArrayNotHasKey( 'heading', $report['converted'] );
    }
}
