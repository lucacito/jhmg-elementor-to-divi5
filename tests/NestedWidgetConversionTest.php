<?php
// tests/NestedWidgetConversionTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's nested-accordion and nested-tabs — its default accordion and tabs
 * since 3.15 — were unregistered, so current exports lost them entirely.
 *
 * Both keep only titles in their repeater (`items` / `tabs`) and hold each
 * panel's body as a separate child container in the widget's own `elements`
 * array, index-aligned with that repeater. Divi cannot mirror that: 5.7.4's
 * generated module.json declares `"childrenName": []` on both
 * divi/accordion-item and divi/tab, so a panel body has to be flattened into
 * their single `content` attribute.
 */
final class NestedWidgetConversionTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function container( string $id, array $children ): array {
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => [ 'content_width' => 'full' ],
            'elements' => $children,
        ];
    }

    private function textWidget( string $id, string $html ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => 'text-editor',
            'settings'   => [ 'editor' => $html ],
            'elements'   => [],
        ];
    }

    private function convert( array $element ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ $element ] );

        return [ $result['divi']['elements'][0], $result ];
    }

    private function nestedAccordion( array $items, array $bodies ): array {
        return [
            'id'         => 'na',
            'elType'     => 'widget',
            'widgetType' => 'nested-accordion',
            'settings'   => [ 'items' => $items, 'title_tag' => 'h3' ],
            'elements'   => $bodies,
        ];
    }

    private function nestedTabs( array $tabs, array $bodies ): array {
        return [
            'id'         => 'nt',
            'elType'     => 'widget',
            'widgetType' => 'nested-tabs',
            'settings'   => [ 'tabs' => $tabs ],
            'elements'   => $bodies,
        ];
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function test_nested_widgets_are_no_longer_unsupported(): void {
        foreach ( [ 'nested-accordion', 'nested-tabs' ] as $slug ) {
            $engine = new ConverterEngine();
            $result = $engine->convert( [ [
                'id'         => 'x',
                'elType'     => 'widget',
                'widgetType' => $slug,
                'settings'   => [],
                'elements'   => [],
            ] ] );

            $this->assertSame( [], $result['unsupported'], "'{$slug}' must be registered" );
        }
    }

    // -------------------------------------------------------------------------
    // Title/body pairing
    // -------------------------------------------------------------------------

    public function test_accordion_pairs_each_title_with_its_index_aligned_body(): void {
        [ $block ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'One' ], [ 'item_title' => 'Two' ] ],
            [
                $this->container( 'b1', [ $this->textWidget( 't1', '<p>First</p>' ) ] ),
                $this->container( 'b2', [ $this->textWidget( 't2', '<p>Second</p>' ) ] ),
            ]
        ) );

        $this->assertSame( 'divi/accordion', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/accordion-item', $block['elements'][0]['name'] );

        $this->assertSame( 'One', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>First</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Two', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Second</p>', $block['elements'][1]['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_tabs_pairs_each_title_with_its_index_aligned_body(): void {
        [ $block ] = $this->convert( $this->nestedTabs(
            [ [ 'tab_title' => 'Alpha' ], [ 'tab_title' => 'Beta' ] ],
            [
                $this->container( 'b1', [ $this->textWidget( 't1', '<p>A</p>' ) ] ),
                $this->container( 'b2', [ $this->textWidget( 't2', '<p>B</p>' ) ] ),
            ]
        ) );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/tab', $block['elements'][0]['name'] );
        $this->assertSame( 'Alpha', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>B</p>', $block['elements'][1]['settings']['content']['innerContent']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // Body flattening
    // -------------------------------------------------------------------------

    public function test_a_body_with_several_widgets_is_concatenated(): void {
        [ $block ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'One' ] ],
            [ $this->container( 'b1', [
                [
                    'id'         => 'h',
                    'elType'     => 'widget',
                    'widgetType' => 'heading',
                    'settings'   => [ 'title' => 'Policy', 'header_size' => 'h4' ],
                    'elements'   => [],
                ],
                $this->textWidget( 't', '<p>Thirty days.</p>' ),
            ] ) ]
        ) );

        $this->assertSame(
            '<h4>Policy</h4><p>Thirty days.</p>',
            $block['elements'][0]['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_nested_containers_inside_a_body_are_walked_through(): void {
        [ $block ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'One' ] ],
            [ $this->container( 'outer', [
                $this->container( 'inner', [ $this->textWidget( 't', '<p>Deep</p>' ) ] ),
            ] ) ]
        ) );

        $this->assertSame(
            '<p>Deep</p>',
            $block['elements'][0]['settings']['content']['innerContent']['desktop']['value']
        );
    }

    /**
     * Divi's accordion items and tabs hold no child modules, so a widget with no
     * inline equivalent cannot be carried over. It must be reported rather than
     * dropped in silence.
     */
    public function test_a_body_widget_with_no_inline_equivalent_is_reported(): void {
        [ $block, $result ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'One' ] ],
            [ $this->container( 'b1', [ [
                'id'         => 'form',
                'elType'     => 'widget',
                'widgetType' => 'form',
                'settings'   => [],
                'elements'   => [],
            ] ] ) ]
        ) );

        $content = $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'];

        $this->assertStringContainsString( 'form', $content );
        $this->assertNotEmpty( $result['report']['warnings'] );
        $this->assertStringContainsString( 'form', implode( ' ', $result['report']['warnings'] ) );
    }

    // -------------------------------------------------------------------------
    // Mismatched counts
    // -------------------------------------------------------------------------

    public function test_a_title_without_a_body_still_produces_an_item(): void {
        [ $block ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'One' ], [ 'item_title' => 'Two' ] ],
            [ $this->container( 'b1', [ $this->textWidget( 't1', '<p>First</p>' ) ] ) ]
        ) );

        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'Two', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'content', $block['elements'][1]['settings'] );
    }

    public function test_a_surplus_body_panel_is_reported(): void {
        [ , $result ] = $this->convert( $this->nestedAccordion(
            [ [ 'item_title' => 'Only one' ] ],
            [
                $this->container( 'b1', [ $this->textWidget( 't1', '<p>First</p>' ) ] ),
                $this->container( 'b2', [ $this->textWidget( 't2', '<p>Orphan</p>' ) ] ),
            ]
        ) );

        $this->assertStringContainsString(
            'surplus panels',
            implode( ' ', $result['report']['warnings'] )
        );
    }

    // -------------------------------------------------------------------------
    // StyleMapper pass
    // -------------------------------------------------------------------------

    public function test_wrapper_carries_style_mapper_output(): void {
        $element = $this->nestedAccordion( [ [ 'item_title' => 'One' ] ], [] );
        $element['settings']['margin'] = [ 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px' ];

        [ $block ] = $this->convert( $element );

        $spacing = $block['settings']['module']['decoration']['spacing']['desktop']['value']['margin'] ?? null;

        $this->assertIsArray( $spacing, 'The wrapper must run a StyleMapper pass' );
        $this->assertSame( '10px', $spacing['top'] );
    }
}
