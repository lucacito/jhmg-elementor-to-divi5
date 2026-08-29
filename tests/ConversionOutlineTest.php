<?php
// tests/ConversionOutlineTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionOutline;

class ConversionOutlineTest extends TestCase {

    public function test_an_empty_tree_produces_an_empty_outline(): void {
        $this->assertSame( [], ConversionOutline::build( [] ) );
    }

    public function test_it_classifies_structural_blocks(): void {
        $blocks = [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [
                    'name'     => 'divi/column',
                    'elements' => [ [ 'name' => 'divi/heading' ] ],
                ] ],
            ] ],
        ] ];

        $outline = ConversionOutline::build( $blocks );

        $this->assertSame( 'section', $outline[0]['type'] );
        $this->assertSame( 'row', $outline[0]['children'][0]['type'] );
        $this->assertSame( 'column', $outline[0]['children'][0]['children'][0]['type'] );
        $this->assertSame( 'module', $outline[0]['children'][0]['children'][0]['children'][0]['type'] );
    }

    public function test_it_labels_modules_readably(): void {
        $outline = ConversionOutline::build( [
            [ 'name' => 'divi/heading' ],
            [ 'name' => 'divi/image' ],
            [ 'name' => 'divi/call-to-action' ],
        ] );

        $this->assertSame( 'Heading', $outline[0]['label'] );
        $this->assertSame( 'Image', $outline[1]['label'] );
        $this->assertSame( 'Call To Action', $outline[2]['label'] );
    }

    public function test_it_preserves_sibling_order(): void {
        $outline = ConversionOutline::build( [
            [ 'name' => 'divi/heading' ],
            [ 'name' => 'divi/text' ],
            [ 'name' => 'divi/button' ],
        ] );

        $this->assertSame(
            [ 'Heading', 'Text', 'Button' ],
            array_column( $outline, 'label' )
        );
    }

    public function test_every_node_carries_the_unsupported_flag(): void {
        $outline = ConversionOutline::build( [ [ 'name' => 'divi/heading' ] ] );

        $this->assertArrayHasKey( 'unsupported', $outline[0] );
        $this->assertFalse( $outline[0]['unsupported'] );
    }

    public function test_a_block_without_a_name_is_skipped(): void {
        $outline = ConversionOutline::build( [
            [ 'settings' => [] ],
            [ 'name' => 'divi/heading' ],
        ] );

        $this->assertCount( 1, $outline );
        $this->assertSame( 'Heading', $outline[0]['label'] );
    }

    public function test_non_array_entries_are_skipped(): void {
        $outline = ConversionOutline::build( [ 'garbage', null, [ 'name' => 'divi/text' ] ] );

        $this->assertCount( 1, $outline );
    }

    public function test_deeply_nested_modules_are_reached(): void {
        $blocks = [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [
                    'name'     => 'divi/column',
                    'elements' => [ [
                        'name'     => 'divi/accordion',
                        'elements' => [ [ 'name' => 'divi/accordion-item' ] ],
                    ] ],
                ] ],
            ] ],
        ] ];

        $outline = ConversionOutline::build( $blocks );
        $module  = $outline[0]['children'][0]['children'][0]['children'][0];

        $this->assertSame( 'Accordion', $module['label'] );
        $this->assertSame( 'Accordion Item', $module['children'][0]['label'] );
    }
}
