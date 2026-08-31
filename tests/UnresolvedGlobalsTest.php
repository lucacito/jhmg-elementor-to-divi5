<?php
// tests/UnresolvedGlobalsTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

/**
 * A global reference the site cannot resolve must leave the property unset and
 * say so, rather than substituting a literal.
 *
 * The resolver previously shipped a COLOR_MAP/TYPOGRAPHY_MAP lifted from one
 * specific site. Because it was keyed partly on Elementor's universal system IDs
 * ('primary', 'secondary', 'text', 'accent'), every install that could not
 * resolve its own globals was quietly repainted in that unrelated brand — and
 * the conversion report called it clean, so nobody had a reason to look.
 */
final class UnresolvedGlobalsTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_postmeta'] = [];
    }

    /** A heading whose title colour comes only from a global reference. */
    private function headingWithGlobalColor( string $id = 'h1', string $ref = 'globals/colors?id=primary' ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => 'heading',
            'settings'   => [
                'title'       => 'Hello',
                '__globals__' => [ 'title_color' => $ref ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // No literal substitution
    // -------------------------------------------------------------------------

    public function test_unresolvable_global_writes_no_color_attribute(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ $this->headingWithGlobalColor() ] );

        $block = $result['divi']['elements'][0];
        $color = $block['settings']['title']['decoration']['font']['font']['desktop']['value']['color'] ?? null;

        $this->assertNull( $color, 'An unresolvable global must not be filled with a stand-in colour' );
    }

    public function test_unresolvable_global_records_exactly_one_report_entry(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ $this->headingWithGlobalColor() ] );

        $unresolved = $result['report']['unresolved_globals'];

        $this->assertCount( 1, $unresolved );
        $this->assertSame( 'h1', $unresolved[0]['element_id'] );
        $this->assertSame( 'title_color', $unresolved[0]['setting_key'] );
        $this->assertSame( 'globals/colors?id=primary', $unresolved[0]['ref'] );
    }

    public function test_the_same_unresolved_preset_is_reported_once_per_element(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            $this->headingWithGlobalColor( 'h1' ),
            $this->headingWithGlobalColor( 'h2' ),
        ] );

        $unresolved = $result['report']['unresolved_globals'];

        $this->assertCount( 2, $unresolved, 'Each element names its own gap' );
        $this->assertSame( [ 'h1', 'h2' ], array_column( $unresolved, 'element_id' ) );
    }

    public function test_unresolvable_typography_global_is_reported(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'h9',
            'elType'     => 'widget',
            'widgetType' => 'heading',
            'settings'   => [
                'title'       => 'Hello',
                '__globals__' => [ 'typography_typography' => 'globals/typography?id=f8733ea' ],
            ],
        ] ] );

        $unresolved = $result['report']['unresolved_globals'];

        $this->assertCount( 1, $unresolved );
        $this->assertSame( 'typography_typography', $unresolved[0]['setting_key'] );
    }

    // -------------------------------------------------------------------------
    // Resolution still works when a kit is present
    // -------------------------------------------------------------------------

    public function test_a_resolved_global_writes_the_color_and_reports_nothing(): void {
        add_filter( 'edc_kit_globals', fn( $v ) => [
            'colors'     => [ 'primary' => '#123456' ],
            'typography' => [],
        ] );

        $engine = new ConverterEngine();
        $result = $engine->convert( [ $this->headingWithGlobalColor() ] );

        $block = $result['divi']['elements'][0];
        $color = $block['settings']['title']['decoration']['font']['font']['desktop']['value']['color'] ?? null;

        $this->assertSame( '#123456', $color );
        $this->assertSame( [], $result['report']['unresolved_globals'] );
    }

    public function test_a_setting_with_a_direct_value_is_not_reported(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'h3',
            'elType'     => 'widget',
            'widgetType' => 'heading',
            'settings'   => [
                'title'       => 'Hello',
                'title_color' => '#abcdef',
                '__globals__' => [ 'title_color' => 'globals/colors?id=primary' ],
            ],
        ] ] );

        $block = $result['divi']['elements'][0];
        $color = $block['settings']['title']['decoration']['font']['font']['desktop']['value']['color'] ?? null;

        $this->assertSame( '#abcdef', $color, 'An explicit value wins over the global' );
        $this->assertSame( [], $result['report']['unresolved_globals'], 'Nothing was missing, so nothing is reported' );
    }

    // -------------------------------------------------------------------------
    // Source (b): the Elementor kit installed on this site
    // -------------------------------------------------------------------------

    public function test_installed_kit_supplies_colors_and_typography(): void {
        update_option( 'elementor_active_kit', 77 );
        update_post_meta( 77, '_elementor_page_settings', [
            'system_colors'     => [ [ '_id' => 'primary', 'color' => '#0A0A0A' ] ],
            'custom_colors'     => [ [ '_id' => 'brandy', 'color' => '#FF8800' ] ],
            'system_typography' => [ [
                '_id'                       => 'tp1',
                'typography_font_family'    => 'Inter',
                'typography_font_weight'    => '600',
                'typography_font_size'      => [ 'size' => 18, 'unit' => 'px' ],
                'typography_line_height'    => [ 'size' => 1.4, 'unit' => 'em' ],
                'typography_letter_spacing' => [ 'size' => -0.5, 'unit' => 'px' ],
            ] ],
        ] );

        $globals = ConversionPreflight::installedKitGlobals();

        $this->assertSame( '#0A0A0A', $globals['colors']['primary'] );
        $this->assertSame( '#FF8800', $globals['colors']['brandy'] );
        $this->assertSame(
            [
                'family'        => 'Inter',
                'weight'        => '600',
                'size'          => '18px',
                'lineHeight'    => '1.4em',
                'letterSpacing' => '-0.5px',
            ],
            $globals['typography']['tp1']
        );
    }

    public function test_free_plugin_registers_the_installed_kit_as_a_fallback(): void {
        update_option( 'elementor_active_kit', 88 );
        update_post_meta( 88, '_elementor_page_settings', [
            'system_colors' => [ [ '_id' => 'primary', 'color' => '#0A0A0A' ] ],
        ] );

        ( new \ElementorDivi5Converter\Plugin() )->register_hooks();

        $this->assertSame( '#0A0A0A', GlobalsResolver::resolveColor( 'primary' ) );
    }

    /**
     * Pro's uploaded kit and free's installed-kit fallback both hook the same
     * filter, and neither can rely on running first. Pro must win on a shared ID
     * in both orderings.
     */
    public function test_pro_kit_wins_over_the_installed_kit_in_either_hook_order(): void {
        update_option( 'elementor_active_kit', 99 );
        update_post_meta( 99, '_elementor_page_settings', [
            'system_colors' => [
                [ '_id' => 'primary', 'color' => '#111111' ],
                [ '_id' => 'onlyinstalled', 'color' => '#222222' ],
            ],
        ] );
        \ElementorDivi5Converter\Pro\Kit\GlobalsStore::save(
            [ 'primary' => '#999999' ],
            [],
            'uploaded-kit'
        );

        // Free first, then Pro.
        ( new \ElementorDivi5Converter\Plugin() )->register_hooks();
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();

        $this->assertSame( '#999999', GlobalsResolver::resolveColor( 'primary' ), 'Pro overrides a shared id' );
        $this->assertSame( '#222222', GlobalsResolver::resolveColor( 'onlyinstalled' ), 'Installed kit still fills gaps' );

        // Pro first, then free.
        edc_test_reset_hooks();
        update_option( 'elementor_active_kit', 99 );
        \ElementorDivi5Converter\Pro\Kit\GlobalsStore::save( [ 'primary' => '#999999' ], [], 'uploaded-kit' );
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();
        ( new \ElementorDivi5Converter\Plugin() )->register_hooks();

        $this->assertSame( '#999999', GlobalsResolver::resolveColor( 'primary' ), 'Order must not decide the winner' );
        $this->assertSame( '#222222', GlobalsResolver::resolveColor( 'onlyinstalled' ) );
    }
}
