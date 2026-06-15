<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Tests that Elementor Flexbox Container layouts are converted to Divi 5
 * flex rows rather than collapsing children into a single full-width column.
 *
 * Rules under test:
 *   - Flex-row container with container children → multi-column Divi row.
 *   - Children preserve relative widths from _inline_size / width settings.
 *   - Nested flex containers stay nested (inner flex row → nested divi/row).
 *   - Flex settings (justify-content, align-items, gap, wrap) land on the row.
 *   - Flex-column containers and plain-widget containers use the original path.
 *   - Full-width children only when Elementor specifies 100% width.
 *   - Widget siblings of container children are auto-wrapped in a column.
 */
final class FlexContainerConversionTest extends TestCase {
    private ConverterEngine $engine;

    protected function setUp(): void {
        $this->engine = new ConverterEngine();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function container( string $id, array $children, array $settings = [] ): array {
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => $settings,
            'elements' => $children,
        ];
    }

    private function widget( string $id, string $type = 'heading', array $settings = [] ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => array_merge( [ 'title' => 'Test' ], $settings ),
            'elements'   => [],
        ];
    }

    private function convert( array $elementorData ): array {
        return $this->engine->convert( $elementorData )['divi']['elements'];
    }

    // -------------------------------------------------------------------------
    // Test 1 — Two-column flex section stays two columns
    // -------------------------------------------------------------------------

    /**
     * Elementor: Container(flex row) → [Container(left), Container(right)]
     * Divi 5:    Section → Row → [Column(left), Column(right)]
     *
     * Must NOT produce Section → Row → Column → [nested rows stacked].
     */
    public function test_two_column_flex_container_produces_two_columns(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'left',  [ $this->widget( 'img', 'image' ) ] ),
                $this->container( 'right', [ $this->widget( 'h1', 'heading' ), $this->widget( 'txt', 'text-editor' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $this->assertCount( 1, $result, 'One section' );
        $section = $result[0];
        $this->assertSame( 'divi/section', $section['name'] );

        $this->assertCount( 1, $section['elements'], 'One row' );
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );

        // Two columns — not one.
        $this->assertCount( 2, $row['elements'], 'Two columns, not one' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'] );
        $this->assertSame( 'divi/column', $row['elements'][1]['name'] );
        $this->assertSame( 'left',  $row['elements'][0]['id'] );
        $this->assertSame( 'right', $row['elements'][1]['id'] );
    }

    /**
     * Elementor's .e-con.e-flex CSS sets --flex-direction: column as the default,
     * so containers with no explicit direction stack children vertically.
     * The outer section gets a single-column row wrapping the two inner containers.
     */
    public function test_default_flex_direction_produces_column_stack(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ] ),
        ] );

        $row = $result[0]['elements'][0];
        // Default (missing) direction = column: children are stacked, not side-by-side.
        // ensureColumnChildren wraps both inner rows in a single wrapper column.
        $this->assertCount( 1, $row['elements'], 'Column-direction container: single wrapper column' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 2 — Content inside each column is preserved
    // -------------------------------------------------------------------------

    public function test_widget_children_inside_flex_columns_are_preserved(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'left',  [ $this->widget( 'img', 'image' ) ] ),
                $this->container( 'right', [
                    $this->widget( 'h1', 'heading' ),
                    $this->widget( 'txt', 'text-editor' ),
                ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row   = $result[0]['elements'][0];
        $left  = $row['elements'][0];
        $right = $row['elements'][1];

        $this->assertCount( 1, $left['elements'],  'Left column has one module (image)' );
        $this->assertSame( 'divi/image', $left['elements'][0]['name'] );

        $this->assertCount( 2, $right['elements'], 'Right column has two modules' );
        $this->assertSame( 'divi/heading', $right['elements'][0]['name'] );
        $this->assertSame( 'divi/text',   $right['elements'][1]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 3 — Nested flex containers stay nested
    // -------------------------------------------------------------------------

    /**
     * Elementor:
     *   Container A (flex row)
     *     Container B (left, flex row)
     *       Container B1 → Heading
     *       Container B2 → Text
     *     Container C (right)
     *       Image
     *
     * Expected Divi:
     *   Section → Row → [
     *     Column(B) → Row(nested) → [Column(B1), Column(B2)],
     *     Column(C) → Image
     *   ]
     */
    public function test_nested_flex_containers_stay_nested(): void {
        $result = $this->convert( [
            $this->container( 'A', [
                $this->container( 'B', [
                    $this->container( 'B1', [ $this->widget( 'wh', 'heading' ) ] ),
                    $this->container( 'B2', [ $this->widget( 'wt', 'text-editor' ) ] ),
                ], [ 'flex_direction' => 'row' ] ),
                $this->container( 'C', [ $this->widget( 'wi', 'image' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $outer_row = $result[0]['elements'][0];
        $this->assertCount( 2, $outer_row['elements'], 'Outer row has two columns' );

        $col_b = $outer_row['elements'][0];
        $this->assertSame( 'divi/column', $col_b['name'] );
        $this->assertSame( 'B', $col_b['id'] );

        // Column B must contain a nested row (not raw columns directly).
        $this->assertCount( 1, $col_b['elements'], 'Column B contains one nested row' );
        $nested_row = $col_b['elements'][0];
        $this->assertSame( 'divi/row', $nested_row['name'] );
        $this->assertCount( 2, $nested_row['elements'], 'Nested row has two sub-columns' );
        $this->assertSame( 'B1', $nested_row['elements'][0]['id'] );
        $this->assertSame( 'B2', $nested_row['elements'][1]['id'] );

        $col_c = $outer_row['elements'][1];
        $this->assertSame( 'C', $col_c['id'] );
        $this->assertCount( 1, $col_c['elements'] );
        $this->assertSame( 'divi/image', $col_c['elements'][0]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 4 — Flex settings land on the row
    // -------------------------------------------------------------------------

    public function test_justify_content_is_set_on_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [
                'flex_direction'      => 'row',
                'flex_justify_content' => 'space-between',
            ] ),
        ] );

        $row = $result[0]['elements'][0];
        $justify = $row['settings']['module']['decoration']['layout']['desktop']['value']['justifyContent'] ?? null;
        $this->assertSame( 'space-between', $justify, 'justifyContent landed on row settings' );
    }

    public function test_align_items_is_set_on_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [
                'flex_direction'   => 'row',
                'flex_align_items' => 'center',
            ] ),
        ] );

        $row = $result[0]['elements'][0];
        $align = $row['settings']['module']['decoration']['layout']['desktop']['value']['alignItems'] ?? null;
        $this->assertSame( 'center', $align, 'alignItems landed on row settings' );
    }

    public function test_flex_gap_is_set_on_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [
                'flex_direction' => 'row',
                'flex_gap'       => [ 'row' => '15', 'column' => '30', 'unit' => 'px' ],
            ] ),
        ] );

        $row    = $result[0]['elements'][0];
        $layout = $row['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];

        $this->assertSame( '30px', $layout['columnGap'] ?? null, 'columnGap set from flex_gap.column' );
        $this->assertSame( '15px', $layout['rowGap']    ?? null, 'rowGap set from flex_gap.row' );
    }

    public function test_flex_wrap_is_set_on_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [ 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ] ),
        ] );

        $row  = $result[0]['elements'][0];
        $wrap = $row['settings']['module']['decoration']['layout']['desktop']['value']['flexWrap'] ?? null;
        $this->assertSame( 'wrap', $wrap, 'flexWrap landed on row settings' );
    }

    public function test_column_direction_is_set_on_row(): void {
        // row-reverse is a non-default direction — must be written explicitly.
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [ 'flex_direction' => 'row-reverse' ] ),
        ] );

        $row = $result[0]['elements'][0];
        $dir = $row['settings']['module']['decoration']['layout']['desktop']['value']['flexDirection'] ?? null;
        $this->assertSame( 'row-reverse', $dir );
    }

    /**
     * Default flex-direction = 'row' must NOT be written to avoid overriding
     * Divi's own default. Divi rows are already row-direction.
     */
    public function test_default_row_direction_is_not_written(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row = $result[0]['elements'][0];
        $dir = $row['settings']['module']['decoration']['layout']['desktop']['value']['flexDirection'] ?? null;
        $this->assertNull( $dir, 'row direction must not be written (already the Divi default)' );
    }

    // -------------------------------------------------------------------------
    // Test 5 — Flex-item sizing preserved
    // -------------------------------------------------------------------------

    public function test_inline_size_50_maps_to_half_column(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ], [ '_inline_size' => [ 'size' => 50, 'unit' => '%' ] ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ], [ '_inline_size' => [ 'size' => 50, 'unit' => '%' ] ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row   = $result[0]['elements'][0];
        $col_a = $row['elements'][0];
        $col_b = $row['elements'][1];

        $type_a = $col_a['settings']['module']['advanced']['type']['desktop']['value'] ?? null;
        $type_b = $col_b['settings']['module']['advanced']['type']['desktop']['value'] ?? null;

        $this->assertSame( '1_2', $type_a, 'Left column is 1/2' );
        $this->assertSame( '1_2', $type_b, 'Right column is 1/2' );
    }

    public function test_inline_size_33_maps_to_third_column(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'c1', [ $this->widget( 'w1' ) ], [ '_inline_size' => [ 'size' => 33, 'unit' => '%' ] ] ),
                $this->container( 'c2', [ $this->widget( 'w2' ) ], [ '_inline_size' => [ 'size' => 34, 'unit' => '%' ] ] ),
                $this->container( 'c3', [ $this->widget( 'w3' ) ], [ '_inline_size' => [ 'size' => 33, 'unit' => '%' ] ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row = $result[0]['elements'][0];
        $this->assertCount( 3, $row['elements'] );
        $this->assertSame( '1_3', $row['elements'][0]['settings']['module']['advanced']['type']['desktop']['value'] );
        $this->assertSame( '1_3', $row['elements'][2]['settings']['module']['advanced']['type']['desktop']['value'] );
    }

    public function test_column_structure_string_is_set_on_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'c1', [ $this->widget( 'w1' ) ], [ '_inline_size' => [ 'size' => 50, 'unit' => '%' ] ] ),
                $this->container( 'c2', [ $this->widget( 'w2' ) ], [ '_inline_size' => [ 'size' => 50, 'unit' => '%' ] ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row       = $result[0]['elements'][0];
        $structure = $row['settings']['module']['advanced']['columnStructure']['desktop']['value'] ?? null;
        $this->assertSame( '1_2,1_2', $structure, 'columnStructure is set on the row' );
    }

    /**
     * Children without an explicit size must NOT get a forced 100% width.
     * They should have empty column settings (Divi auto-distributes widths).
     */
    public function test_children_without_size_have_no_forced_width(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'col-a', [ $this->widget( 'w1' ) ] ),
                $this->container( 'col-b', [ $this->widget( 'w2' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row = $result[0]['elements'][0];
        foreach ( $row['elements'] as $col ) {
            $type = $col['settings']['module']['advanced']['type']['desktop']['value'] ?? null;
            $this->assertNull( $type, 'Auto-sized column must not have a forced width fraction' );
        }
    }

    // -------------------------------------------------------------------------
    // Test 6 — Flex-column direction uses original stacking behaviour
    // -------------------------------------------------------------------------

    /**
     * A container with flex_direction = 'column' stacks its children vertically.
     * Even if those children are containers, they should NOT become side-by-side
     * Divi columns — the outer container maps via the standard path.
     */
    public function test_flex_column_direction_does_not_produce_multi_column(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'row1', [ $this->widget( 'w1' ) ] ),
                $this->container( 'row2', [ $this->widget( 'w2' ) ] ),
            ], [ 'flex_direction' => 'column' ] ),
        ] );

        $section = $result[0];
        $row     = $section['elements'][0];

        // The outer container becomes a section/row with a SINGLE column wrapping
        // the two inner rows (since the outer is a flex-column, not a flex-row).
        // The inner containers each become divi/row blocks via convertInnerAsRow.
        $this->assertSame( 'divi/row', $row['name'] );
        // Children are inner rows, so ensureColumnChildren wraps them in one column.
        $this->assertCount( 1, $row['elements'], 'Single wrapper column for flex-column stacking' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 7 — Widget siblings get auto-wrapped in columns
    // -------------------------------------------------------------------------

    /**
     * When a flex-row container has a mix of container and widget children,
     * the widget children must be auto-wrapped in their own column so the
     * row contains only divi/column blocks.
     */
    public function test_widget_sibling_in_flex_row_is_auto_wrapped(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->widget( 'direct-widget', 'heading' ),
                $this->container( 'sub', [ $this->widget( 'inner', 'image' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row = $result[0]['elements'][0];

        $this->assertCount( 2, $row['elements'], 'Two columns: one auto-wrapped widget, one container' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'], 'Auto-wrapped widget is a column' );
        $this->assertSame( 'divi/column', $row['elements'][1]['name'] );

        // The widget must be inside the auto-column, not a direct row child.
        $this->assertSame( 'divi/heading', $row['elements'][0]['elements'][0]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 8 — Three-column grid
    // -------------------------------------------------------------------------

    public function test_three_column_flex_row_produces_three_columns(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'c1', [ $this->widget( 'w1' ) ] ),
                $this->container( 'c2', [ $this->widget( 'w2' ) ] ),
                $this->container( 'c3', [ $this->widget( 'w3' ) ] ),
            ], [ 'flex_direction' => 'row' ] ),
        ] );

        $row = $result[0]['elements'][0];
        $this->assertCount( 3, $row['elements'], 'Three columns from three child containers' );
        foreach ( $row['elements'] as $col ) {
            $this->assertSame( 'divi/column', $col['name'] );
        }
    }

    // -------------------------------------------------------------------------
    // Test 9 — Regression: existing fixtures not broken
    // -------------------------------------------------------------------------

    /**
     * A container with only widget children (no container children) must still
     * produce the original Section → Row → Column → Module structure.
     * This is the simple-container fixture pattern.
     */
    public function test_single_widget_container_still_produces_one_column(): void {
        $result = $this->convert( [
            $this->container( 'c1', [
                $this->widget( 'h1', 'heading' ),
            ] ),
        ] );

        $section = $result[0];
        $row     = $section['elements'][0];

        $this->assertCount( 1, $row['elements'], 'One auto-wrapping column' );
        $col = $row['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );
        $this->assertSame( 'divi/heading', $col['elements'][0]['name'] );
    }

    /**
     * A container that is inside an old-style section column becomes a nested
     * divi/row (existing behaviour, not affected by flex changes).
     */
    public function test_inner_container_in_section_column_becomes_nested_row(): void {
        $result = $this->convert( [
            [
                'id'       => 's1',
                'elType'   => 'section',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => 'c1',
                        'elType'   => 'column',
                        'settings' => [],
                        'elements' => [
                            $this->container( 'inner', [
                                [
                                    'id'       => 'ic1',
                                    'elType'   => 'column',
                                    'settings' => [],
                                    'elements' => [ $this->widget( 'w1' ) ],
                                ],
                                [
                                    'id'       => 'ic2',
                                    'elType'   => 'column',
                                    'settings' => [],
                                    'elements' => [ $this->widget( 'w2' ) ],
                                ],
                            ] ),
                        ],
                    ],
                ],
            ],
        ] );

        $col        = $result[0]['elements'][0]['elements'][0];
        $nested_row = $col['elements'][0];
        $this->assertSame( 'divi/row', $nested_row['name'] );
        $this->assertSame( 'inner', $nested_row['id'] );
        $this->assertCount( 2, $nested_row['elements'] );
    }

    // -------------------------------------------------------------------------
    // Test 10 — Grid container uses the grid path, not the flex-row path
    // -------------------------------------------------------------------------

    /**
     * A grid container must NOT be treated as a flex-row (producing a multi-column
     * flex row from container children). Instead it must produce a divi/row with
     * display:grid in its layout settings and one divi/column per grid item.
     */
    public function test_grid_container_is_not_treated_as_flex_row(): void {
        $result = $this->convert( [
            $this->container( 'parent', [
                $this->container( 'c1', [ $this->widget( 'w1' ) ] ),
                $this->container( 'c2', [ $this->widget( 'w2' ) ] ),
            ], [ 'container_type' => 'grid' ] ),
        ] );

        $row = $result[0]['elements'][0];

        // Two grid-item columns, one per child container.
        $this->assertCount( 2, $row['elements'], 'Grid container produces one column per grid item' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'] );
        $this->assertSame( 'divi/column', $row['elements'][1]['name'] );

        // Row must carry display:grid — not treated as a flex row.
        $display = $row['settings']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;
        $this->assertSame( 'grid', $display, 'Row layout mode must be grid' );
    }
}
