<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Tests that Elementor CSS Grid Containers are converted to Divi 5 Grid layout
 * mode rather than stacking children into a single full-width column.
 *
 * Rules under test:
 *   - Grid containers produce a divi/row with display:grid in layout settings.
 *   - Each grid child (widget or container) becomes its own divi/column.
 *   - Grid children are NOT collapsed into a single stacked column.
 *   - grid_columns_grid size maps to Divi gridColumnCount.
 *   - grid_gaps maps to Divi columnGap / rowGap.
 *   - Responsive column counts land on tablet / phone breakpoints.
 *   - A logo wall (N images in a grid) remains N grid-item columns.
 *   - Nested grid containers inside a grid item stay nested.
 */
final class GridContainerConversionTest extends TestCase {
    private ConverterEngine $engine;

    protected function setUp(): void {
        $this->engine = new ConverterEngine();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function gridContainer( string $id, array $children, array $extra_settings = [] ): array {
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => array_merge( [ 'container_type' => 'grid' ], $extra_settings ),
            'elements' => $children,
        ];
    }

    private function flexContainer( string $id, array $children, array $settings = [] ): array {
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => $settings,
            'elements' => $children,
        ];
    }

    private function widget( string $id, string $type = 'image', array $settings = [] ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => array_merge( [ 'image' => [ 'url' => 'https://example.com/logo.png' ] ], $settings ),
            'elements'   => [],
        ];
    }

    private function convert( array $elementorData ): array {
        return $this->engine->convert( $elementorData )['divi']['elements'];
    }

    // -------------------------------------------------------------------------
    // Test 1 — Logo wall / image grid remains a grid (not stacked columns)
    // -------------------------------------------------------------------------

    /**
     * 8 images inside an e-grid container must produce 8 grid-item columns
     * inside a divi/row with display:grid — NOT one full-width column with
     * 8 stacked images.
     */
    public function test_logo_wall_grid_produces_grid_items(): void {
        $images = [];
        for ( $i = 1; $i <= 8; $i++ ) {
            $images[] = $this->widget( "img{$i}" );
        }

        $result = $this->convert( [
            $this->gridContainer( 'logos', $images, [
                'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 4 ],
            ] ),
        ] );

        $this->assertCount( 1, $result, 'One section' );
        $section = $result[0];
        $this->assertSame( 'divi/section', $section['name'] );

        $this->assertCount( 1, $section['elements'], 'One row' );
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );

        // 8 grid-item columns, not 1 stacked column.
        $this->assertCount( 8, $row['elements'], 'Eight grid items, not one stacked column' );
        foreach ( $row['elements'] as $col ) {
            $this->assertSame( 'divi/column', $col['name'], 'Each grid item is a divi/column' );
        }

        // Each column must contain exactly one image module.
        foreach ( $row['elements'] as $col ) {
            $this->assertCount( 1, $col['elements'], 'Each grid item contains one module' );
            $this->assertSame( 'divi/image', $col['elements'][0]['name'] );
        }
    }

    // -------------------------------------------------------------------------
    // Test 2 — Grid does not become vertical stacking (no single wrapper column)
    // -------------------------------------------------------------------------

    /**
     * A grid container must produce one divi/column per child, never a single
     * wrapper column that stacks everything vertically.
     */
    public function test_grid_children_are_not_collapsed_into_single_column(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [
                $this->widget( 'w1' ),
                $this->widget( 'w2' ),
                $this->widget( 'w3' ),
                $this->widget( 'w4' ),
            ] ),
        ] );

        $row = $result[0]['elements'][0];

        $this->assertNotEquals( 1, count( $row['elements'] ), 'Must not collapse into one stacked column' );
        $this->assertCount( 4, $row['elements'], 'Four grid items for four children' );
        foreach ( $row['elements'] as $col ) {
            $this->assertSame( 'divi/column', $col['name'] );
        }
    }

    // -------------------------------------------------------------------------
    // Test 3 — Responsive column counts are preserved
    // -------------------------------------------------------------------------

    /**
     * Desktop: 4 columns, Tablet: 2 columns, Mobile: 1 column must all land
     * on the correct Divi breakpoint keys.
     */
    public function test_responsive_column_counts_are_preserved(): void {
        $images = [ $this->widget( 'a' ), $this->widget( 'b' ), $this->widget( 'c' ), $this->widget( 'd' ) ];

        $result = $this->convert( [
            $this->gridContainer( 'responsive-grid', $images, [
                'grid_columns_grid'        => [ 'unit' => 'fr', 'size' => 4 ],
                'grid_columns_grid_tablet' => [ 'unit' => 'fr', 'size' => 2 ],
                'grid_columns_grid_mobile' => [ 'unit' => 'fr', 'size' => 1 ],
            ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout'];

        // Desktop.
        $desktop = $layout['desktop']['value'] ?? [];
        $this->assertSame( 'grid',  $desktop['display']          ?? null, 'Desktop display is grid' );
        $this->assertSame( 'equal', $desktop['gridColumnWidths'] ?? null, 'Desktop uses equal column widths' );
        $this->assertSame( '4',     $desktop['gridColumnCount']  ?? null, 'Desktop: 4 columns' );

        // Tablet.
        $tablet = $layout['tablet']['value'] ?? [];
        $this->assertSame( 'grid',  $tablet['display']          ?? null, 'Tablet display is grid' );
        $this->assertSame( '2',     $tablet['gridColumnCount']  ?? null, 'Tablet: 2 columns' );

        // Phone.
        $phone = $layout['phone']['value'] ?? [];
        $this->assertSame( 'grid',  $phone['display']          ?? null, 'Phone display is grid' );
        $this->assertSame( '1',     $phone['gridColumnCount']  ?? null, 'Phone: 1 column' );
    }

    // -------------------------------------------------------------------------
    // Test 4 — display:grid is set on the row layout
    // -------------------------------------------------------------------------

    public function test_grid_container_sets_display_grid_on_row(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [ $this->widget( 'w1' ), $this->widget( 'w2' ) ] ),
        ] );

        $row     = $result[0]['elements'][0];
        $display = $row['settings']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;
        $this->assertSame( 'grid', $display, 'Row layout display must be "grid"' );
    }

    // -------------------------------------------------------------------------
    // Test 5 — Grid column count maps correctly
    // -------------------------------------------------------------------------

    public function test_three_column_grid_maps_column_count(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [
                $this->widget( 'a' ), $this->widget( 'b' ), $this->widget( 'c' ),
            ], [ 'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 3 ] ] ),
        ] );

        $row    = $result[0]['elements'][0];
        $layout = $row['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];

        $this->assertSame( 'equal', $layout['gridColumnWidths'] ?? null, 'gridColumnWidths is equal' );
        $this->assertSame( '3',     $layout['gridColumnCount']  ?? null, 'gridColumnCount is 3' );
    }

    public function test_four_column_grid_maps_column_count(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [
                $this->widget( 'a' ), $this->widget( 'b' ),
                $this->widget( 'c' ), $this->widget( 'd' ),
            ], [ 'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 4 ] ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];
        $this->assertSame( '4', $layout['gridColumnCount'] ?? null, 'gridColumnCount is 4' );
    }

    // -------------------------------------------------------------------------
    // Test 6 — Gaps are preserved
    // -------------------------------------------------------------------------

    public function test_grid_gaps_map_to_divi_gap_settings(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [ $this->widget( 'w1' ), $this->widget( 'w2' ) ], [
                'grid_gaps' => [ 'unit' => 'px', 'column' => '24', 'row' => '16' ],
            ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];

        $this->assertSame( '24px', $layout['columnGap'] ?? null, 'columnGap set from grid_gaps.column' );
        $this->assertSame( '16px', $layout['rowGap']    ?? null, 'rowGap set from grid_gaps.row' );
    }

    public function test_responsive_gaps_land_on_correct_breakpoints(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [ $this->widget( 'w1' ), $this->widget( 'w2' ) ], [
                'grid_gaps'        => [ 'unit' => 'px', 'column' => '20', 'row' => '20' ],
                'grid_gaps_tablet' => [ 'unit' => 'px', 'column' => '12', 'row' => '12' ],
                'grid_gaps_mobile' => [ 'unit' => 'px', 'column' => '8',  'row' => '8'  ],
            ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout'];

        $this->assertSame( '12px', $layout['tablet']['value']['columnGap'] ?? null, 'tablet columnGap' );
        $this->assertSame( '8px',  $layout['phone']['value']['columnGap']  ?? null, 'phone columnGap' );
    }

    // -------------------------------------------------------------------------
    // Test 7 — Container children become grid items (one column each)
    // -------------------------------------------------------------------------

    /**
     * Card-style grid: each child container becomes a divi/column grid item
     * containing the card's inner widgets.
     */
    public function test_container_children_each_become_a_grid_item_column(): void {
        $result = $this->convert( [
            $this->gridContainer( 'cards', [
                $this->flexContainer( 'card1', [ $this->widget( 'img1' ), $this->widget( 'h1', 'heading', [ 'title' => 'Card 1' ] ) ] ),
                $this->flexContainer( 'card2', [ $this->widget( 'img2' ), $this->widget( 'h2', 'heading', [ 'title' => 'Card 2' ] ) ] ),
                $this->flexContainer( 'card3', [ $this->widget( 'img3' ), $this->widget( 'h3', 'heading', [ 'title' => 'Card 3' ] ) ] ),
            ], [ 'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 3 ] ] ),
        ] );

        $row = $result[0]['elements'][0];
        $this->assertCount( 3, $row['elements'], 'Three grid-item columns' );

        foreach ( $row['elements'] as $col ) {
            $this->assertSame( 'divi/column', $col['name'] );
            $this->assertGreaterThan( 0, count( $col['elements'] ), 'Each grid item has children' );
        }
    }

    // -------------------------------------------------------------------------
    // Test 8 — Children do not receive forced 100% width
    // -------------------------------------------------------------------------

    /**
     * Grid items must not have a forced `1_1` (full-width) column type set.
     * The grid parent controls item sizing via CSS grid.
     */
    public function test_grid_item_columns_have_no_forced_width(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [
                $this->widget( 'w1' ), $this->widget( 'w2' ),
                $this->widget( 'w3' ), $this->widget( 'w4' ),
            ], [ 'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 4 ] ] ),
        ] );

        $row = $result[0]['elements'][0];
        foreach ( $row['elements'] as $col ) {
            $type = $col['settings']['module']['advanced']['type']['desktop']['value'] ?? null;
            $this->assertNull( $type, 'Grid item must not have a forced column width fraction' );
        }
    }

    // -------------------------------------------------------------------------
    // Test 9 — Grid as nested container (inner/child container)
    // -------------------------------------------------------------------------

    /**
     * A grid container that is a child of a flex section should produce a
     * nested divi/row with display:grid rather than a flat flex column.
     */
    public function test_nested_grid_container_produces_grid_row(): void {
        $result = $this->convert( [
            // Outer flex section.
            [
                'id'       => 'outer',
                'elType'   => 'container',
                'settings' => [],
                'elements' => [
                    // Inner grid container.
                    $this->gridContainer( 'inner-grid', [
                        $this->widget( 'w1' ), $this->widget( 'w2' ),
                        $this->widget( 'w3' ), $this->widget( 'w4' ),
                    ], [ 'grid_columns_grid' => [ 'unit' => 'fr', 'size' => 4 ] ] ),
                ],
            ],
        ] );

        // outer → section → grid-row directly (no extra column wrapper needed).
        $inner_row = $result[0]['elements'][0];
        $this->assertSame( 'divi/row', $inner_row['name'], 'Inner grid is a divi/row' );

        $display = $inner_row['settings']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;
        $this->assertSame( 'grid', $display, 'Inner grid row has display:grid' );

        $this->assertCount( 4, $inner_row['elements'], 'Inner grid has 4 grid-item columns' );
    }

    // -------------------------------------------------------------------------
    // Test 10 — auto_flow column direction is mapped
    // -------------------------------------------------------------------------

    public function test_grid_auto_flow_column_is_mapped(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [ $this->widget( 'w1' ) ], [
                'grid_auto_flow' => 'column',
            ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];
        $this->assertSame( 'column', $layout['gridAutoFlow'] ?? null, 'gridAutoFlow is column' );
    }

    /**
     * The default auto_flow value 'row' must NOT be written (it is the Divi
     * default and writing it would override nothing but adds noise).
     */
    public function test_default_auto_flow_row_is_not_written(): void {
        $result = $this->convert( [
            $this->gridContainer( 'g', [ $this->widget( 'w1' ) ], [
                'grid_auto_flow' => 'row',
            ] ),
        ] );

        $layout = $result[0]['elements'][0]['settings']['module']['decoration']['layout']['desktop']['value'] ?? [];
        $this->assertArrayNotHasKey( 'gridAutoFlow', $layout, 'Default row auto-flow must not be written' );
    }
}
