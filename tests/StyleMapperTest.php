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
        // Button font color lives in the button-specific decoration sub-group, one per breakpoint.
        $color_desktop = $result['divi_attrs']['button']['decoration']['button']['desktop']['value']['font']['color'];
        $color_tablet  = $result['divi_attrs']['button']['decoration']['button']['tablet']['value']['font']['color'];
        $color_phone   = $result['divi_attrs']['button']['decoration']['button']['phone']['value']['font']['color'];

        $this->assertSame( '#ffffff', $color_desktop );
        $this->assertSame( '#ffffff', $color_tablet );
        $this->assertSame( '#ffffff', $color_phone );
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

    // -------------------------------------------------------------------------
    // parseSizeValue fixes
    // -------------------------------------------------------------------------

    public function test_parse_size_value_empty_size_returns_empty_string(): void {
        // Before fix, this produced "px" (empty string + unit).
        $result = $this->mapper->map( 'heading', [ 'typography_font_size' => [ 'size' => '', 'unit' => 'px' ] ] );
        $attrs  = $result['divi_attrs'];
        // Should produce no font size attr (empty value is skipped).
        $this->assertArrayNotHasKey(
            'title',
            $attrs,
            'Empty font_size should not write any attr (would produce "px" without fix)'
        );
    }

    public function test_parse_size_value_null_size_returns_empty_string(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_font_size' => [ 'size' => null, 'unit' => 'em' ] ] );
        $attrs  = $result['divi_attrs'];
        $this->assertArrayNotHasKey( 'title', $attrs );
    }

    public function test_parse_size_value_handles_elementor_3x_sizes_format(): void {
        $result = $this->mapper->map( 'heading', [
            'typography_font_size' => [
                'sizes' => [
                    'desktop' => [ 'size' => 24, 'unit' => 'px' ],
                ],
            ],
        ] );
        $size = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['size'] ?? null;
        $this->assertSame( '24px', $size );
    }

    // -------------------------------------------------------------------------
    // normalizeRadius fixes
    // -------------------------------------------------------------------------

    public function test_normalize_radius_with_pre_formatted_values_does_not_double_unit(): void {
        // Corner values that already carry a "px" suffix must not become "0pxpx".
        $settings = [
            'border_radius' => [ 'top' => '0px', 'right' => '8px', 'bottom' => '0px', 'left' => '8px', 'unit' => 'px' ],
        ];
        $result = $this->mapper->map( 'heading', $settings );
        $radius = $result['divi_attrs']['module']['decoration']['border']['desktop']['value']['radius'];

        $this->assertSame( '0px', $radius['topLeft'] );
        $this->assertSame( '8px', $radius['topRight'] );
        $this->assertSame( '0px', $radius['bottomRight'] );
        $this->assertSame( '8px', $radius['bottomLeft'] );
    }

    // -------------------------------------------------------------------------
    // Font family, style flags, responsive weight
    // -------------------------------------------------------------------------

    public function test_maps_font_family_to_heading_path(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_font_family' => 'Montserrat' ] );
        $family = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['family'] ?? null;
        $this->assertSame( 'Montserrat', $family );
    }

    public function test_maps_font_weight_responsive(): void {
        $result = $this->mapper->map( 'heading', [
            'typography_font_weight'        => '700',
            'typography_font_weight_tablet' => '400',
        ] );
        $desktop = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['weight'] ?? null;
        $tablet  = $result['divi_attrs']['title']['decoration']['font']['font']['tablet']['value']['weight'] ?? null;
        $this->assertSame( '700', $desktop );
        $this->assertSame( '400', $tablet );
    }

    public function test_maps_italic_font_style_flag(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_font_style' => 'italic' ] );
        $style  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['style'] ?? null;
        $this->assertContains( 'italic', $style );
    }

    public function test_maps_uppercase_text_transform_flag(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_text_transform' => 'uppercase' ] );
        $style  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['style'] ?? null;
        $this->assertContains( 'uppercase', $style );
    }

    public function test_maps_underline_text_decoration_flag(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_text_decoration' => 'underline' ] );
        $style  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['style'] ?? null;
        $this->assertContains( 'underline', $style );
    }

    public function test_maps_line_through_to_strikethrough_flag(): void {
        $result = $this->mapper->map( 'heading', [ 'typography_text_decoration' => 'line-through' ] );
        $style  = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['style'] ?? null;
        $this->assertContains( 'strikethrough', $style );
    }

    public function test_combines_multiple_style_flags(): void {
        $result = $this->mapper->map( 'heading', [
            'typography_font_style'      => 'italic',
            'typography_text_transform'  => 'uppercase',
            'typography_text_decoration' => 'underline',
        ] );
        $style = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['style'] ?? [];
        $this->assertContains( 'italic', $style );
        $this->assertContains( 'uppercase', $style );
        $this->assertContains( 'underline', $style );
    }

    // -------------------------------------------------------------------------
    // Background gradient
    // -------------------------------------------------------------------------

    public function test_maps_linear_gradient_background(): void {
        $result = $this->mapper->map( 'section', [
            'background_background'   => 'gradient',
            'background_gradient_type'  => 'linear',
            'background_gradient_angle' => [ 'size' => 135, 'unit' => 'deg' ],
            'background_gradient_stops' => [
                [ 'color' => '#ff0000', 'position' => [ 'size' => 0,   'unit' => '%' ] ],
                [ 'color' => '#0000ff', 'position' => [ 'size' => 100, 'unit' => '%' ] ],
            ],
        ] );
        $gradient = $result['divi_attrs']['module']['decoration']['background']['desktop']['value']['gradient'] ?? null;
        $this->assertSame( 'on', $gradient['enabled'] );
        $this->assertSame( 'linear', $gradient['type'] );
        $this->assertSame( '135deg', $gradient['direction'] );
        $this->assertSame( '#ff0000', $gradient['stops'][0]['color'] );
        $this->assertSame( '0%', $gradient['stops'][0]['position'] );
        $this->assertSame( '#0000ff', $gradient['stops'][1]['color'] );
    }

    public function test_maps_gradient_with_two_color_shorthand(): void {
        $result = $this->mapper->map( 'section', [
            'background_background' => 'gradient',
            'background_color'      => '#aabbcc',
            'background_color_b'    => '#112233',
        ] );
        $stops = $result['divi_attrs']['module']['decoration']['background']['desktop']['value']['gradient']['stops'] ?? [];
        $this->assertCount( 2, $stops );
        $this->assertSame( '#aabbcc', $stops[0]['color'] );
        $this->assertSame( '#112233', $stops[1]['color'] );
        // Solid color path must NOT be set for gradient type
        $solid = $result['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'] ?? null;
        $this->assertNull( $solid );
    }

    // -------------------------------------------------------------------------
    // Box shadow
    // -------------------------------------------------------------------------

    public function test_maps_box_shadow_to_module_decoration(): void {
        $result = $this->mapper->map( 'heading', [
            'box_shadow_box_shadow_type' => 'yes',
            'box_shadow_box_shadow'      => [
                'horizontal' => 2,
                'vertical'   => 4,
                'blur'       => 10,
                'spread'     => 0,
                'color'      => 'rgba(0,0,0,0.2)',
                'position'   => '',
            ],
        ] );
        $shadow = $result['divi_attrs']['module']['decoration']['boxShadow']['desktop']['value'] ?? null;
        $this->assertSame( '2px', $shadow['horizontal'] );
        $this->assertSame( '4px', $shadow['vertical'] );
        $this->assertSame( '10px', $shadow['blur'] );
        $this->assertSame( 'rgba(0,0,0,0.2)', $shadow['color'] );
        $this->assertSame( 'outer', $shadow['position'] );
    }

    public function test_maps_inset_box_shadow_position(): void {
        $result = $this->mapper->map( 'heading', [
            'box_shadow_box_shadow' => [
                'horizontal' => 0, 'vertical' => 2, 'blur' => 5,
                'spread' => 0, 'color' => '#000000', 'position' => 'inset',
            ],
        ] );
        $pos = $result['divi_attrs']['module']['decoration']['boxShadow']['desktop']['value']['position'] ?? null;
        $this->assertSame( 'inner', $pos );
    }

    // -------------------------------------------------------------------------
    // Text shadow
    // -------------------------------------------------------------------------

    public function test_maps_text_shadow_to_font_path(): void {
        $result = $this->mapper->map( 'heading', [
            'text_shadow_text_shadow_type' => 'yes',
            'text_shadow_text_shadow'      => [
                'horizontal' => 1, 'vertical' => 2, 'blur' => 3, 'color' => 'rgba(0,0,0,0.5)',
            ],
        ] );
        $shadow = $result['divi_attrs']['title']['decoration']['font']['font']['desktop']['value']['textShadow'] ?? null;
        $this->assertSame( 'rgba(0,0,0,0.5)', $shadow['color'] );
        $this->assertSame( '1px', $shadow['horizontal'] );
    }

    // -------------------------------------------------------------------------
    // Opacity
    // -------------------------------------------------------------------------

    public function test_maps_opacity_to_module_decoration(): void {
        $result  = $this->mapper->map( 'heading', [ '_opacity' => '0.5' ] );
        $opacity = $result['divi_attrs']['module']['decoration']['opacity']['desktop']['value'] ?? null;
        $this->assertSame( '0.5', $opacity );
    }

    public function test_opacity_of_1_is_not_written(): void {
        $result = $this->mapper->map( 'heading', [ '_opacity' => '1' ] );
        $this->assertArrayNotHasKey( 'opacity', $result['divi_attrs']['module']['decoration'] ?? [] );
    }

    // -------------------------------------------------------------------------
    // Min height
    // -------------------------------------------------------------------------

    public function test_maps_min_height_for_section(): void {
        $result = $this->mapper->map( 'section', [
            'height'        => 'min-height',
            'custom_height' => [ 'size' => 500, 'unit' => 'px' ],
        ] );
        $min_h = $result['divi_attrs']['module']['decoration']['sizing']['desktop']['value']['minHeight'] ?? null;
        $this->assertSame( '500px', $min_h );
    }

    public function test_maps_min_height_responsive_for_column(): void {
        $result = $this->mapper->map( 'column', [
            'custom_height'        => [ 'size' => 400, 'unit' => 'px' ],
            'custom_height_tablet' => [ 'size' => 300, 'unit' => 'px' ],
        ] );
        $desktop = $result['divi_attrs']['module']['decoration']['sizing']['desktop']['value']['minHeight'] ?? null;
        $tablet  = $result['divi_attrs']['module']['decoration']['sizing']['tablet']['value']['minHeight'] ?? null;
        $this->assertSame( '400px', $desktop );
        $this->assertSame( '300px', $tablet );
    }

    // -------------------------------------------------------------------------
    // Button background color
    // -------------------------------------------------------------------------

    public function test_maps_button_background_color_per_breakpoint(): void {
        $result = $this->mapper->map( 'button', [ 'button_background_color' => '#1a73e8' ] );
        $desktop = $result['divi_attrs']['button']['decoration']['button']['desktop']['value']['background']['color'] ?? null;
        $tablet  = $result['divi_attrs']['button']['decoration']['button']['tablet']['value']['background']['color'] ?? null;
        $this->assertSame( '#1a73e8', $desktop );
        $this->assertSame( '#1a73e8', $tablet );
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
