<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Ceramic Studio's hero (docs/known-issues.md): a 90vh section whose left column
 * carries a cover image and holds only a spacer rendered as a 12 px strip because
 * the background was moved onto an empty divi/group. Divi 5 rows are flex with
 * align-items: stretch, so the column itself fills the row; the background belongs
 * on it (column/module.json declares module.decoration.background).
 */
final class ColumnBackgroundTest extends TestCase {
    private function hero(): array {
        return [ [
            'id' => 'sec', 'elType' => 'section',
            'settings' => [ 'layout' => 'full_width', 'height' => 'min-height', 'custom_height' => [ 'unit' => 'vh', 'size' => '90' ], 'column_position' => 'stretch', 'content_position' => 'middle' ],
            'elements' => [
                [ 'id' => 'left', 'elType' => 'column',
                  'settings' => [ '_column_size' => 50, 'background_background' => 'classic', 'background_image' => [ 'url' => 'https://x.test/hero.jpg', 'id' => 20 ], 'background_position' => 'center center', 'background_repeat' => 'no-repeat', 'background_size' => 'cover' ],
                  'elements' => [ [ 'id' => 'sp', 'elType' => 'widget', 'widgetType' => 'spacer', 'settings' => [ 'space' => [ 'unit' => 'px', 'size' => 50 ] ], 'elements' => [] ] ] ],
                [ 'id' => 'right', 'elType' => 'column', 'settings' => [ '_column_size' => 50 ],
                  'elements' => [ [ 'id' => 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Hi' ], 'elements' => [] ] ] ],
            ],
        ] ];
    }

    public function test_column_keeps_its_cover_background_and_no_group_is_added(): void {
        $section = ( new ConverterEngine() )->convert( $this->hero() )['divi']['elements'][0];
        $row     = $section['elements'][0];
        $left    = $row['elements'][0];

        $this->assertSame( 'divi/column', $left['name'] );
        $this->assertSame( 'https://x.test/hero.jpg', $left['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] );
        $this->assertSame( 'cover', $left['settings']['module']['decoration']['background']['desktop']['value']['image']['size'] );
        $this->assertSame( [ 'divi/divider' ], array_column( $left['elements'], 'name' ), 'the spacer is the column\'s only child; no divi/group' );
        DiviModuleSchema::assertBlocksValid( [ $section ], 'hero' );
    }

    public function test_row_gets_min_height_and_stretches_its_columns(): void {
        $row = ( new ConverterEngine() )->convert( $this->hero() )['divi']['elements'][0]['elements'][0];

        $this->assertSame( '90vh', $row['settings']['module']['decoration']['sizing']['desktop']['value']['minHeight'] );
        $this->assertSame( 'stretch', $row['settings']['module']['decoration']['layout']['desktop']['value']['alignItems'] );
    }

    /**
     * Elementor's "Full Width" layout stretches the columns across the viewport; Divi's
     * row is 80% wide and at most 1080px by default (style-static.min.css .et_pb_row).
     */
    public function test_a_full_width_section_gives_its_row_the_full_width(): void {
        $row = ( new ConverterEngine() )->convert( $this->hero() )['divi']['elements'][0]['elements'][0];

        $this->assertSame( '100%', $row['settings']['module']['decoration']['sizing']['desktop']['value']['width'] );
        $this->assertSame( '100%', $row['settings']['module']['decoration']['sizing']['desktop']['value']['maxWidth'] );

        $boxed = $this->hero();
        $boxed[0]['settings']['layout'] = 'boxed';
        $row   = ( new ConverterEngine() )->convert( $boxed )['divi']['elements'][0]['elements'][0];
        $this->assertArrayNotHasKey( 'width', $row['settings']['module']['decoration']['sizing']['desktop']['value'] );
    }

    public function test_column_overlay_colour_stays_on_the_column(): void {
        $hero = $this->hero();
        $hero[0]['elements'][0]['settings']['background_overlay_background'] = 'classic';
        $hero[0]['elements'][0]['settings']['background_overlay_color']      = '#000000';
        $hero[0]['elements'][0]['settings']['background_overlay_opacity']    = [ 'size' => 0.5, 'unit' => 'px' ];

        $left = ( new ConverterEngine() )->convert( $hero )['divi']['elements'][0]['elements'][0]['elements'][0];

        // StyleMapper::mapBackgroundOverlay(): an overlay colour over an image is a
        // ::before layer in the column's custom CSS (css.*.before, a key Divi reads).
        $this->assertSame( 'https://x.test/hero.jpg', $left['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] );
        $this->assertStringContainsString( 'linear-gradient(', $left['settings']['css']['desktop']['value']['before'] );
        $this->assertSame( [ 'divi/divider' ], array_column( $left['elements'], 'name' ) );
    }
}
