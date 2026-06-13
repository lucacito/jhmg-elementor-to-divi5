<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

final class StyleMapperTest extends TestCase {
    private StyleMapper $mapper;

    protected function setUp(): void {
        $this->mapper = new StyleMapper();
    }

    public function test_returns_empty_attrs_for_empty_settings(): void {
        $result = $this->mapper->map( 'heading', [] );

        $this->assertSame( [], $result['divi_attrs'] );
        $this->assertNotEmpty( $result['handled_keys'] );
    }

    public function test_maps_margin_to_divi_spacing_path(): void {
        $settings = [
            'margin' => [ 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $margin = $result['divi_attrs']['module']['decoration']['spacing']['desktop']['value']['margin'];

        $this->assertSame( '10px', $margin['top'] );
        $this->assertSame( '0px', $margin['right'] );
        $this->assertSame( '10px', $margin['bottom'] );
        $this->assertSame( '0px', $margin['left'] );
        $this->assertSame( 'off', $margin['syncVertical'] );
        $this->assertSame( 'off', $margin['syncHorizontal'] );
    }

    public function test_maps_padding_to_divi_spacing_path(): void {
        $settings = [
            'padding' => [ 'top' => '20', 'right' => '15', 'bottom' => '20', 'left' => '15', 'unit' => 'em' ],
        ];

        $result  = $this->mapper->map( 'button', $settings );
        $padding = $result['divi_attrs']['module']['decoration']['spacing']['desktop']['value']['padding'];

        $this->assertSame( '20em', $padding['top'] );
        $this->assertSame( '15em', $padding['right'] );
    }

    public function test_maps_responsive_tablet_margin(): void {
        $settings = [
            'margin_tablet' => [ 'top' => '5', 'right' => '', 'bottom' => '5', 'left' => '', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $this->assertArrayHasKey( 'tablet', $result['divi_attrs']['module']['decoration']['spacing'] );
        $margin = $result['divi_attrs']['module']['decoration']['spacing']['tablet']['value']['margin'];
        $this->assertSame( '5px', $margin['top'] );
        $this->assertSame( '', $margin['right'] );
    }

    public function test_maps_responsive_mobile_to_phone_breakpoint(): void {
        $settings = [
            'margin_mobile' => [ 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $this->assertArrayHasKey( 'phone', $result['divi_attrs']['module']['decoration']['spacing'] );
    }

    public function test_maps_background_color(): void {
        $settings = [ 'background_color' => '#ff0000' ];

        $result = $this->mapper->map( 'heading', $settings );
        $color  = $result['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'];

        $this->assertSame( '#ff0000', $color );
    }

    public function test_ignores_empty_background_color(): void {
        $result = $this->mapper->map( 'heading', [ 'background_color' => '' ] );

        $this->assertArrayNotHasKey( 'module', $result['divi_attrs'] );
    }

    public function test_handled_keys_includes_all_spacing_and_background_keys(): void {
        $result = $this->mapper->map( 'heading', [] );
        $keys   = $result['handled_keys'];

        $this->assertContains( 'margin', $keys );
        $this->assertContains( 'margin_tablet', $keys );
        $this->assertContains( 'margin_mobile', $keys );
        $this->assertContains( 'padding', $keys );
        $this->assertContains( 'padding_tablet', $keys );
        $this->assertContains( 'padding_mobile', $keys );
        $this->assertContains( 'background_color', $keys );
    }

    // -------------------------------------------------------------------------
    // Typography
    // -------------------------------------------------------------------------

    public function test_maps_typography_font_size_to_heading_title_font(): void {
        $settings = [ 'typography_font_size' => [ 'size' => 48, 'unit' => 'px' ] ];

        $result = $this->mapper->map( 'heading', $settings );
        $size   = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['size'];

        $this->assertSame( '48px', $size );
        $this->assertContains( 'typography_font_size', $result['handled_keys'] );
    }

    public function test_maps_typography_font_size_responsive_tablet_and_phone(): void {
        $settings = [
            'typography_font_size'        => [ 'size' => 48, 'unit' => 'px' ],
            'typography_font_size_tablet' => [ 'size' => 32, 'unit' => 'px' ],
            'typography_font_size_mobile' => [ 'size' => 24, 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $font   = $result['divi_attrs']['title']['decoration']['font']['font'];

        $this->assertSame( '48px', $font['desktop']['value']['size'] );
        $this->assertSame( '32px', $font['tablet']['value']['size'] );
        $this->assertSame( '24px', $font['phone']['value']['size'] );
    }

    public function test_maps_typography_line_height_and_letter_spacing(): void {
        $settings = [
            'typography_line_height'    => [ 'size' => 1.6, 'unit' => 'em' ],
            'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $val    = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value'];

        $this->assertSame( '1.6em', $val['lineHeight'] );
        $this->assertSame( '2px', $val['letterSpacing'] );
    }

    public function test_maps_typography_font_weight(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_font_weight' => '700' ] );
        $weight = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['weight'];

        $this->assertSame( '700', $weight );
    }

    public function test_typography_maps_to_bodyFont_path_for_text_editor(): void {
        $settings = [ 'typography_font_size' => [ 'size' => 16, 'unit' => 'px' ] ];

        $result = $this->mapper->map( 'text-editor', $settings );
        $size   = $result['divi_attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['size'];

        $this->assertSame( '16px', $size );
    }

    public function test_typography_produces_no_attrs_for_unregistered_widget_type(): void {
        $settings = [ 'typography_font_size' => [ 'size' => 20, 'unit' => 'px' ] ];

        $result = $this->mapper->map( 'divider', $settings );

        $this->assertArrayNotHasKey( 'title', $result['divi_attrs'] );
        $this->assertArrayNotHasKey( 'content', $result['divi_attrs'] );
        // Key must still be marked handled so it doesn't pollute the unmapped log.
        $this->assertContains( 'typography_font_size', $result['handled_keys'] );
    }

    // -------------------------------------------------------------------------
    // Text color
    // -------------------------------------------------------------------------

    public function test_maps_heading_title_color(): void {
        $result = $this->mapper->map( 'heading', [ 'title_color' => '#222222' ] );
        $color  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['color'];

        $this->assertSame( '#222222', $color );
        $this->assertContains( 'title_color', $result['handled_keys'] );
    }

    public function test_maps_text_editor_text_color_to_bodyFont_path(): void {
        $result = $this->mapper->map( 'text-editor', [ 'text_color' => '#555555' ] );
        $color  = $result['divi_attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'];

        $this->assertSame( '#555555', $color );
    }

    public function test_maps_button_text_color(): void {
        $result = $this->mapper->map( 'button', [ 'button_text_color' => '#ffffff' ] );
        $color  = $result['divi_attrs']['button']['decoration']['font']['font']['desktop']['value']['color'];

        $this->assertSame( '#ffffff', $color );
    }

    // -------------------------------------------------------------------------
    // Alignment
    // -------------------------------------------------------------------------

    public function test_maps_heading_align_to_title_font_textAlign(): void {
        $result = $this->mapper->map( 'heading', [ 'align' => 'center' ] );
        $align  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['textAlign'];

        $this->assertSame( 'center', $align );
        $this->assertContains( 'align', $result['handled_keys'] );
    }

    public function test_maps_non_heading_align_to_module_text_orientation(): void {
        $result      = $this->mapper->map( 'text-editor', [ 'align' => 'right' ] );
        $orientation = $result['divi_attrs']['module']['advanced']['text']['text']['desktop']['value']['orientation'];

        $this->assertSame( 'right', $orientation );
    }

    public function test_justify_alignment_falls_back_to_left_for_non_heading(): void {
        $result      = $this->mapper->map( 'text-editor', [ 'align' => 'justify' ] );
        $orientation = $result['divi_attrs']['module']['advanced']['text']['text']['desktop']['value']['orientation'];

        $this->assertSame( 'left', $orientation );
    }

    // -------------------------------------------------------------------------
    // Border
    // -------------------------------------------------------------------------

    public function test_maps_border_style_and_color(): void {
        $settings = [
            'border_border' => 'solid',
            'border_color'  => '#000000',
        ];

        $result  = $this->mapper->map( 'heading', $settings );
        $styles  = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['styles']['all'];

        $this->assertSame( 'solid', $styles['style'] );
        $this->assertSame( '#000000', $styles['color'] );
        $this->assertContains( 'border_border', $result['handled_keys'] );
        $this->assertContains( 'border_color', $result['handled_keys'] );
    }

    public function test_maps_uniform_border_width_to_all_side(): void {
        $settings = [
            'border_width' => [ 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $width  = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['styles']['all']['width'];

        $this->assertSame( '2px', $width );
    }

    public function test_maps_mixed_border_width_to_individual_sides(): void {
        $settings = [
            'border_width' => [ 'top' => '1', 'right' => '0', 'bottom' => '1', 'left' => '0', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $styles = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['styles'];

        $this->assertSame( '1px', $styles['top']['width'] );
        $this->assertSame( '0px', $styles['right']['width'] );
        $this->assertSame( '1px', $styles['bottom']['width'] );
        $this->assertSame( '0px', $styles['left']['width'] );
        $this->assertArrayNotHasKey( 'all', $styles );
    }

    public function test_maps_uniform_border_radius(): void {
        $settings = [
            'border_radius' => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $radius = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['radius'];

        $this->assertSame( [ 'topLeft' => '8px', 'topRight' => '8px', 'bottomRight' => '8px', 'bottomLeft' => '8px' ], $radius );
    }

    public function test_maps_mixed_border_radius_per_corner(): void {
        $settings = [
            'border_radius' => [ 'top' => '8', 'right' => '0', 'bottom' => '8', 'left' => '0', 'unit' => 'px' ],
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $radius = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['radius'];

        $this->assertSame( '8px', $radius['topLeft'] );
        $this->assertSame( '0px', $radius['topRight'] );
        $this->assertSame( '8px', $radius['bottomRight'] );
        $this->assertSame( '0px', $radius['bottomLeft'] );
    }

    public function test_maps_border_responsive_tablet_variant(): void {
        $settings = [
            'border_border_tablet' => 'dashed',
            'border_color_tablet'  => '#aaaaaa',
        ];

        $result = $this->mapper->map( 'heading', $settings );
        $styles = $result['divi_attrs']['module']['decoration']['border']['tablet']['value']['styles']['all'];

        $this->assertSame( 'dashed', $styles['style'] );
        $this->assertSame( '#aaaaaa', $styles['color'] );
    }

    public function test_handled_keys_includes_all_typography_and_border_keys(): void {
        $result = $this->mapper->map( 'heading', [] );
        $keys   = $result['handled_keys'];

        // Typography
        $this->assertContains( 'typography_font_size', $keys );
        $this->assertContains( 'typography_font_size_tablet', $keys );
        $this->assertContains( 'typography_font_size_mobile', $keys );
        $this->assertContains( 'typography_font_weight', $keys );
        $this->assertContains( 'typography_line_height', $keys );
        $this->assertContains( 'typography_letter_spacing', $keys );
        $this->assertContains( 'typography_typography', $keys );
        $this->assertContains( 'typography_font_family', $keys );
        // Color + alignment
        $this->assertContains( 'title_color', $keys );
        $this->assertContains( 'align', $keys );
        // Border
        $this->assertContains( 'border_border', $keys );
        $this->assertContains( 'border_width', $keys );
        $this->assertContains( 'border_color', $keys );
        $this->assertContains( 'border_radius', $keys );
        $this->assertContains( 'border_border_tablet', $keys );
        $this->assertContains( 'border_color_mobile', $keys );
    }

    // -------------------------------------------------------------------------
    // Column size
    // -------------------------------------------------------------------------

    public function test_column_size_50_maps_to_1_2(): void {
        $result   = $this->mapper->map( 'column', [ '_column_size' => 50 ] );
        $fraction = $result['divi_attrs']['module']['advanced']['type']['desktop']['value'];

        $this->assertSame( '1_2', $fraction );
        $this->assertContains( '_column_size', $result['handled_keys'] );
    }

    public function test_column_size_25_maps_to_1_4(): void {
        $result   = $this->mapper->map( 'column', [ '_column_size' => 25 ] );
        $fraction = $result['divi_attrs']['module']['advanced']['type']['desktop']['value'];

        $this->assertSame( '1_4', $fraction );
    }

    public function test_column_size_missing_sets_no_type_attr(): void {
        $result = $this->mapper->map( 'column', [] );

        $this->assertArrayNotHasKey( 'module', $result['divi_attrs'] );
    }

    public function test_column_size_to_fraction_static_helper(): void {
        $this->assertSame( '3_4', StyleMapper::columnSizeToFraction( 75 ) );
        $this->assertNull( StyleMapper::columnSizeToFraction( 99 ) );
    }

    public function test_heading_converter_uses_style_mapper_for_background_color(): void {
        $engine = new ElementorDivi5Converter\Converter\ConverterEngine();
        $result = $engine->convert( [
            [
                'id'         => 'w1',
                'elType'     => 'widget',
                'widgetType' => 'heading',
                'settings'   => [
                    'title'            => 'Styled Heading',
                    'header_size'      => 'h3',
                    'background_color' => '#26476c',
                ],
                'elements' => [],
            ],
        ] );

        $element = $result['divi']['elements'][0];
        $this->assertSame( 'divi/heading', $element['name'] );
        $this->assertSame( 'Styled Heading', $element['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '#26476c', $element['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }
}
