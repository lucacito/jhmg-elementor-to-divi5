<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Tests that the Elementor → Divi 5 layout conversion follows the correct
 * section/row/column hierarchy based solely on the JSON element tree —
 * not on nesting-depth heuristics.
 *
 * Rules under test:
 *   - Section → Row → Column → Module          (no extra nesting)
 *   - Column → Inner Section/Container → Column → Module
 *     becomes: Column → Nested Row → Column → Module
 *   - No nested row is ever created when no inner section/container exists.
 */
final class LayoutConversionTest extends TestCase {
    private ConverterEngine $engine;

    protected function setUp(): void {
        $this->engine = new ConverterEngine();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function widget( string $id, string $type = 'heading', string $title = 'Hello' ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => [ 'title' => $title ],
            'elements'   => [],
        ];
    }

    private function column( string $id, array $children = [], array $settings = [] ): array {
        return [
            'id'       => $id,
            'elType'   => 'column',
            'settings' => $settings,
            'elements' => $children,
        ];
    }

    private function section( string $id, array $columns, bool $is_inner = false ): array {
        return [
            'id'       => $id,
            'elType'   => 'section',
            'isInner'  => $is_inner,
            'settings' => [],
            'elements' => $columns,
        ];
    }

    private function container( string $id, array $children ): array {
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => [],
            'elements' => $children,
        ];
    }

    private function convert( array $elementorData ): array {
        return $this->engine->convert( $elementorData )['divi']['elements'];
    }

    // -------------------------------------------------------------------------
    // Test 1 — Simple section / column / widget
    // -------------------------------------------------------------------------

    /**
     * Elementor:  Section → Column → Widget
     * Divi 5:     Section → Row → Column → Module
     *
     * The row wraps the column at the section level. There must be no extra
     * row inside the column.
     */
    public function test_simple_section_column_widget_has_no_extra_rows(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [ $this->widget( 'w1' ) ] ),
            ] ),
        ]);

        $this->assertCount( 1, $result, 'Exactly one section' );
        $section = $result[0];
        $this->assertSame( 'divi/section', $section['name'] );

        $this->assertCount( 1, $section['elements'], 'Section has exactly one row' );
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );

        $this->assertCount( 1, $row['elements'], 'Row has exactly one column' );
        $col = $row['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );

        // Column must contain the module directly — no nested row.
        $this->assertCount( 1, $col['elements'], 'Column has one module' );
        $this->assertSame( 'divi/heading', $col['elements'][0]['name'] );
        $this->assertNotSame( 'divi/row', $col['elements'][0]['name'], 'No nested row in plain column' );
    }

    // -------------------------------------------------------------------------
    // Test 2 — Section with multiple columns
    // -------------------------------------------------------------------------

    /**
     * Elementor:  Section → [Column-A, Column-B]
     * Divi 5:     Section → Row → [Column-A, Column-B]
     *
     * Both columns must land in the same single row. No extra rows anywhere.
     */
    public function test_section_multiple_columns_land_in_same_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [ $this->widget( 'w1', 'heading', 'Left' ) ] ),
                $this->column( 'c2', [ $this->widget( 'w2', 'heading', 'Right' ) ] ),
            ] ),
        ]);

        $section = $result[0];
        $this->assertSame( 'divi/section', $section['name'] );

        $this->assertCount( 1, $section['elements'], 'Exactly one row for all columns' );
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );

        $this->assertCount( 2, $row['elements'], 'Both columns in one row' );
        $this->assertSame( 'divi/column', $row['elements'][0]['name'] );
        $this->assertSame( 'divi/column', $row['elements'][1]['name'] );

        // Neither column should contain a row.
        foreach ( $row['elements'] as $col ) {
            foreach ( $col['elements'] as $child ) {
                $this->assertNotSame( 'divi/row', $child['name'], 'No nested row in plain columns' );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Test 3 — Column containing an inner section
    // -------------------------------------------------------------------------

    /**
     * Elementor:  Section → Column → Inner-Section → [Column-A, Column-B]
     * Divi 5:     Section → Row → Column → Nested-Row → [Column-A, Column-B]
     *
     * The nested row appears ONLY because the JSON contains an inner section
     * inside the column. The nested row id matches the inner section id.
     */
    public function test_column_with_inner_section_becomes_nested_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->section( 'inner1', [
                        $this->column( 'ic1', [ $this->widget( 'w1' ) ] ),
                        $this->column( 'ic2', [ $this->widget( 'w2' ) ] ),
                    ], true ),
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );
        $this->assertSame( 'c1', $col['id'] );

        $this->assertCount( 1, $col['elements'], 'Column has exactly one nested row' );
        $nested_row = $col['elements'][0];
        $this->assertSame( 'divi/row', $nested_row['name'] );
        $this->assertSame( 'inner1', $nested_row['id'], 'Nested row id matches inner section id' );

        $this->assertCount( 2, $nested_row['elements'], 'Nested row contains both inner columns' );
        $this->assertSame( 'divi/column', $nested_row['elements'][0]['name'] );
        $this->assertSame( 'ic1', $nested_row['elements'][0]['id'] );
        $this->assertSame( 'divi/column', $nested_row['elements'][1]['name'] );
        $this->assertSame( 'ic2', $nested_row['elements'][1]['id'] );
    }

    /**
     * Same scenario but driven by an Elementor container element instead of an
     * inner section — both must produce the same nested-row structure.
     */
    public function test_column_with_inner_container_becomes_nested_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->container( 'cnt1', [
                        $this->column( 'ic1', [ $this->widget( 'w1' ) ] ),
                        $this->column( 'ic2', [ $this->widget( 'w2' ) ] ),
                    ] ),
                ] ),
            ] ),
        ]);

        $col        = $result[0]['elements'][0]['elements'][0];
        $nested_row = $col['elements'][0];
        $this->assertSame( 'divi/row', $nested_row['name'] );
        $this->assertSame( 'cnt1', $nested_row['id'] );
        $this->assertCount( 2, $nested_row['elements'] );
    }

    // -------------------------------------------------------------------------
    // Test 4 — Multiple levels of nesting
    // -------------------------------------------------------------------------

    /**
     * Elementor:  Section → Column → Inner-Section → Column → Inner-Section → Column → Widget
     * Divi 5:     Section → Row → Col → Row → Col → Row → Col → Module
     *
     * Each inner section in the JSON produces exactly one new nesting level.
     */
    public function test_deeply_nested_inner_sections_each_produce_one_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->section( 'inner1', [
                        $this->column( 'c2', [
                            $this->section( 'inner2', [
                                $this->column( 'c3', [ $this->widget( 'w1' ) ] ),
                            ], true ),
                        ] ),
                    ], true ),
                ] ),
            ] ),
        ]);

        // Level 1: section → row → c1
        $outer_row = $result[0]['elements'][0];
        $this->assertSame( 'divi/row', $outer_row['name'] );
        $c1 = $outer_row['elements'][0];
        $this->assertSame( 'c1', $c1['id'] );

        // Level 2: c1 → nested-row(inner1) → c2
        $nested_row1 = $c1['elements'][0];
        $this->assertSame( 'divi/row', $nested_row1['name'] );
        $this->assertSame( 'inner1', $nested_row1['id'] );
        $c2 = $nested_row1['elements'][0];
        $this->assertSame( 'c2', $c2['id'] );

        // Level 3: c2 → nested-row(inner2) → c3 → widget
        $nested_row2 = $c2['elements'][0];
        $this->assertSame( 'divi/row', $nested_row2['name'] );
        $this->assertSame( 'inner2', $nested_row2['id'] );
        $c3 = $nested_row2['elements'][0];
        $this->assertSame( 'c3', $c3['id'] );
        $this->assertSame( 'divi/heading', $c3['elements'][0]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 5 — No accidental rows when no inner section exists
    // -------------------------------------------------------------------------

    /**
     * A column with multiple widgets must never gain an extra row wrapper.
     * The widgets are direct children of the column.
     */
    public function test_column_with_multiple_widgets_has_no_nested_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->widget( 'w1', 'heading', 'Title' ),
                    $this->widget( 'w2', 'text-editor', 'Body' ),
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );
        $this->assertCount( 2, $col['elements'], 'Both widgets are direct column children' );

        foreach ( $col['elements'] as $child ) {
            $this->assertNotSame( 'divi/row', $child['name'], 'No row wrapping plain widgets' );
        }
    }

    /**
     * A section containing columns (and nothing else) must have exactly one row
     * regardless of how many columns there are.
     */
    public function test_section_with_three_columns_has_exactly_one_row(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [ $this->widget( 'w1' ) ] ),
                $this->column( 'c2', [ $this->widget( 'w2' ) ] ),
                $this->column( 'c3', [ $this->widget( 'w3' ) ] ),
            ] ),
        ]);

        $section = $result[0];
        $this->assertCount( 1, $section['elements'], 'Exactly one row — not one row per column' );
        $this->assertSame( 'divi/row', $section['elements'][0]['name'] );
        $this->assertCount( 3, $section['elements'][0]['elements'] );
    }

    /**
     * Siblings of an inner section within the same column are also direct
     * column children — they must not be wrapped in extra rows.
     */
    public function test_widget_siblings_of_inner_section_remain_direct_column_children(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->widget( 'w1', 'heading', 'Before' ),
                    $this->section( 'inner1', [
                        $this->column( 'ic1', [ $this->widget( 'w2' ) ] ),
                    ], true ),
                    $this->widget( 'w3', 'heading', 'After' ),
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );
        $this->assertCount( 3, $col['elements'], 'Three direct children: widget, nested-row, widget' );
        $this->assertSame( 'divi/heading', $col['elements'][0]['name'] );
        $this->assertSame( 'divi/row',     $col['elements'][1]['name'] );
        $this->assertSame( 'divi/heading', $col['elements'][2]['name'] );
    }

    // -------------------------------------------------------------------------
    // Test 6 — Group wrapping for widget-wrap background pattern
    // -------------------------------------------------------------------------

    /**
     * When an Elementor column has background_background=classic with a background
     * image and/or overlay, and contains child widgets, the converter must produce:
     *   divi/column (no background) → divi/group (background + padding) → modules
     *
     * This mirrors the Elementor DOM structure where the inner widget-wrap div —
     * not the outer column div — carries the visual background and padding.
     */
    public function test_column_with_background_image_wraps_children_in_group(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->widget( 'w1', 'heading', 'Title' ),
                    $this->widget( 'w2', 'text-editor', 'Body' ),
                ], [
                    'background_background' => 'classic',
                    'background_image'      => [ 'url' => 'https://example.com/photo.jpg' ],
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );

        // Column must NOT carry background decoration itself.
        $col_bg = $col['settings']['module']['decoration']['background'] ?? null;
        $this->assertNull( $col_bg, 'Column settings must not contain background decoration' );

        // Column must contain exactly one group.
        $this->assertCount( 1, $col['elements'], 'Column wraps children in a single group' );
        $group = $col['elements'][0];
        $this->assertSame( 'divi/group', $group['name'], 'Wrapper is a divi/group' );
        $this->assertStringEndsWith( '-group', $group['id'] );

        // Group carries the background decoration.
        $group_bg = $group['settings']['module']['decoration']['background'] ?? null;
        $this->assertNotNull( $group_bg, 'Group must carry the background decoration' );
        $this->assertSame( 'https://example.com/photo.jpg', $group_bg['desktop']['value']['image']['url'] );

        // Original widgets are children of the group.
        $this->assertCount( 2, $group['elements'] );
        $this->assertSame( 'divi/heading', $group['elements'][0]['name'] );
        $this->assertSame( 'divi/text',   $group['elements'][1]['name'] );
    }

    /**
     * A column with background_background=classic but NO image or overlay color
     * (i.e., plain background_color only) must NOT get a group wrapper — the
     * color is applied directly to the column.
     */
    public function test_column_with_plain_background_color_only_has_no_group(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [
                    $this->widget( 'w1' ),
                ], [
                    'background_background' => 'classic',
                    'background_color'      => '#ff0000',
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );

        // No group — widget is a direct child.
        $this->assertCount( 1, $col['elements'] );
        $this->assertNotSame( 'divi/group', $col['elements'][0]['name'], 'Plain color column must not wrap in group' );
    }

    /**
     * An empty column with background styling must NOT produce a group — there
     * are no children to wrap, and an empty group block would be invalid.
     */
    public function test_column_with_background_but_no_children_has_no_group(): void {
        $result = $this->convert([
            $this->section( 's1', [
                $this->column( 'c1', [], [
                    'background_background' => 'classic',
                    'background_image'      => [ 'url' => 'https://example.com/photo.jpg' ],
                ] ),
            ] ),
        ]);

        $col = $result[0]['elements'][0]['elements'][0];
        $this->assertSame( 'divi/column', $col['name'] );
        $this->assertEmpty( $col['elements'], 'Empty column with background must have no children (no group)' );
    }
}
