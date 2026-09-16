<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

/**
 * Elementor's button widget defaults to the global accent background
 * (includes/widgets/traits/button-trait.php: Group_Control_Background default
 * Global_Colors::COLOR_ACCENT), white 15px text with 12px/24px padding and a 3px
 * radius (assets/css/frontend.css .elementor-button), and the kit's Theme Style
 * → Buttons (core/kits/documents/tabs/theme-style-buttons.php) can override each.
 * Divi's default button is a transparent outline in the theme accent blue, so a
 * converted button that set nothing explicitly looked nothing like the original.
 */
final class ButtonDefaultsTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function kit( array $extra = [] ): void {
        add_filter( 'edc_kit_globals', static fn() => array_merge( [
            'colors'     => [ 'accent' => '#C8643B' ],
            'typography' => [ 'accent' => [ 'family' => 'Inter', 'weight' => '600' ] ],
        ], $extra ) );
    }

    private function button( array $settings ): array {
        return ( new StyleMapper() )->map( 'button', $settings, [ 'elementor_defaults' => true ] )['divi_attrs']['button']['decoration'];
    }

    public function test_a_bare_button_gets_elementors_defaults_with_the_kit_accent(): void {
        $this->kit();
        $d = $this->button( [] );

        $this->assertSame( '#C8643B', $d['background']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '15px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( 'Inter', $d['font']['font']['desktop']['value']['family'] );
        $this->assertSame( '600', $d['font']['font']['desktop']['value']['weight'] );
        $this->assertSame( [ 'top' => '12px', 'right' => '24px', 'bottom' => '12px', 'left' => '24px' ], $d['spacing']['desktop']['value']['padding'] );
        $this->assertSame( '3px', $d['border']['desktop']['value']['radius']['topLeft'] );
    }

    public function test_without_any_kit_the_background_is_elementors_grey(): void {
        $this->assertSame( '#69727d', $this->button( [] )['background']['desktop']['value']['color'] );
    }

    public function test_kit_button_theme_style_overrides_the_built_in_defaults(): void {
        $this->kit( [ 'buttons' => [
            'background_color' => '#111111', 'text_color' => '#eeeeee',
            'typography' => [ 'size' => '18px', 'weight' => '500' ],
            'border_radius' => [ 'topLeft' => '30px', 'topRight' => '30px', 'bottomRight' => '30px', 'bottomLeft' => '30px' ],
            'padding' => [ 'top' => '20px', 'right' => '40px', 'bottom' => '20px', 'left' => '40px' ],
        ] ] );
        $d = $this->button( [] );

        $this->assertSame( '#111111', $d['background']['desktop']['value']['color'] );
        $this->assertSame( '#eeeeee', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '18px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '500', $d['font']['font']['desktop']['value']['weight'] );
        $this->assertSame( '30px', $d['border']['desktop']['value']['radius']['topLeft'] );
        $this->assertSame( '40px', $d['spacing']['desktop']['value']['padding']['right'] );
    }

    public function test_widget_values_win_over_kit_and_defaults(): void {
        $this->kit( [ 'buttons' => [ 'background_color' => '#111111', 'text_color' => '#eeeeee' ] ] );
        $d = $this->button( [
            'button_text_color' => '#F4F0EE',
            'border_radius' => [ 'unit' => 'px', 'top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'isLinked' => '1' ],
            'text_padding' => [ 'unit' => 'px', 'top' => '20', 'right' => '35', 'bottom' => '20', 'left' => '35', 'isLinked' => '' ],
            'typography_typography' => 'custom', 'typography_font_size' => [ 'unit' => 'px', 'size' => 13 ],
        ] );

        $this->assertSame( '#F4F0EE', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '13px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '50px', $d['border']['desktop']['value']['radius']['topLeft'] );
        $this->assertSame( '35px', $d['spacing']['desktop']['value']['padding']['right'] );
        $this->assertSame( '#111111', $d['background']['desktop']['value']['color'], 'kit background still fills the gap' );
    }

    public function test_defaults_are_not_applied_without_the_option(): void {
        $this->kit();
        $this->assertArrayNotHasKey( 'background', ( new StyleMapper() )->map( 'button', [] )['divi_attrs']['button']['decoration'] ?? [] );
    }

    public function test_button_converter_applies_the_cascade(): void {
        $this->kit();
        $block = ( new ConverterEngine() )->convert( [ [ 'id' => 'b', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => [ 'text' => 'Go' ], 'elements' => [] ] ] )['divi']['elements'][0];
        $this->assertSame( '#C8643B', $block['settings']['button']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $block['settings']['button']['decoration']['font']['font']['desktop']['value']['color'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'button' );
    }

    public function test_installed_kit_button_theme_style_is_read(): void {
        update_option( 'elementor_active_kit', 900 );
        update_post_meta( 900, '_elementor_page_settings', [
            'button_background_color' => '#222222',
            'button_text_color'       => '#fafafa',
            'button_typography_font_size' => [ 'unit' => 'px', 'size' => 17 ],
            'button_typography_font_weight' => '700',
            'button_border_radius' => [ 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => '1' ],
            'button_padding' => [ 'unit' => 'px', 'top' => '10', 'right' => '30', 'bottom' => '10', 'left' => '30', 'isLinked' => '' ],
            'button_border_border' => 'solid', 'button_border_width' => [ 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => '1' ], 'button_border_color' => '#000000',
        ] );

        $this->assertSame( [
            'background_color' => '#222222',
            'text_color'       => '#fafafa',
            'typography'       => [ 'weight' => '700', 'size' => '17px' ],
            'border_radius'    => [ 'topLeft' => '4px', 'topRight' => '4px', 'bottomRight' => '4px', 'bottomLeft' => '4px' ],
            'padding'          => [ 'top' => '10px', 'right' => '30px', 'bottom' => '10px', 'left' => '30px' ],
            'border'           => [ 'style' => 'solid', 'width' => '2px', 'color' => '#000000' ],
        ], ConversionPreflight::elementorKitButtons() );
        $this->assertArrayHasKey( 'buttons', ConversionPreflight::installedKitGlobals() );
    }
}
