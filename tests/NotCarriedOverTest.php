<?php
// tests/NotCarriedOverTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\NotCarriedOverRenderer;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Three kinds of loss were invisible in every report.
 *
 * Dynamic tags (__dynamic__) and animation keys (motion_fx_*, sticky_*,
 * _animation*) are suppressed before they can reach logSkippedSetting(), and
 * form_fields is listed as handled by FormConverter while nothing reads it. A
 * page could therefore lose every animation, every dynamic binding and every
 * form field on it and still be reported as converting clean.
 */
final class NotCarriedOverTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convertWidget( string $type, array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => [],
        ] ] );
    }

    private function entriesOfKind( array $report, string $kind ): array {
        return array_values( array_filter(
            $report['not_carried_over'],
            static fn( array $e ): bool => $e['kind'] === $kind
        ) );
    }

    // -------------------------------------------------------------------------
    // Dynamic tags
    // -------------------------------------------------------------------------

    public function test_dynamic_bindings_are_reported_with_the_element_id(): void {
        $result = $this->convertWidget( 'heading', [
            'title'       => '',
            '__dynamic__' => [ 'title' => 'tag_id=post_title' ],
        ] );

        $dynamic = $this->entriesOfKind( $result['report'], 'dynamic' );

        $this->assertCount( 1, $dynamic );
        $this->assertSame( 'w1', $dynamic[0]['element_id'] );
        $this->assertSame( 'title', $dynamic[0]['detail'] );
    }

    public function test_each_dynamic_binding_on_an_element_is_listed(): void {
        $result = $this->convertWidget( 'heading', [
            '__dynamic__' => [ 'title' => 'tag_id=post_title', 'link' => 'tag_id=post_url' ],
        ] );

        $this->assertCount( 2, $this->entriesOfKind( $result['report'], 'dynamic' ) );
    }

    // -------------------------------------------------------------------------
    // Animations and motion effects
    // -------------------------------------------------------------------------

    public function test_entrance_animation_is_reported(): void {
        $result = $this->convertWidget( 'heading', [ 'title' => 'Hi', '_animation' => 'fadeInUp' ] );

        $animation = $this->entriesOfKind( $result['report'], 'animation' );

        $this->assertCount( 1, $animation );
        $this->assertSame( 'w1', $animation[0]['element_id'] );
        $this->assertSame( 'fadeInUp', $animation[0]['detail'] );
    }

    public function test_motion_effects_are_reported(): void {
        $result = $this->convertWidget( 'heading', [
            'title'                     => 'Hi',
            'motion_fx_motion_fx_scrolling' => 'yes',
        ] );

        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'motion' ) );
    }

    public function test_sticky_effects_are_reported_as_motion(): void {
        $result = $this->convertWidget( 'heading', [ 'title' => 'Hi', 'sticky' => 'top' ] );

        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'motion' ) );
    }

    /**
     * A single entrance animation sets several keys. Listing each one would read
     * as several separate losses.
     */
    public function test_one_animation_is_reported_once_not_once_per_key(): void {
        $result = $this->convertWidget( 'heading', [
            'title'              => 'Hi',
            '_animation'         => 'fadeInUp',
            '_animation_delay'   => 300,
            'animation_duration' => 'slow',
        ] );

        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'animation' ) );
    }

    public function test_a_switched_off_animation_is_not_reported(): void {
        $result = $this->convertWidget( 'heading', [ 'title' => 'Hi', '_animation' => 'none' ] );

        $this->assertSame( [], $this->entriesOfKind( $result['report'], 'animation' ) );
    }

    public function test_an_empty_animation_key_is_not_reported(): void {
        $result = $this->convertWidget( 'heading', [ 'title' => 'Hi', '_animation' => '' ] );

        $this->assertSame( [], $this->entriesOfKind( $result['report'], 'animation' ) );
    }

    // -------------------------------------------------------------------------
    // Form fields
    // -------------------------------------------------------------------------

    public function test_discarded_form_fields_are_reported_with_a_count(): void {
        $result = $this->convertWidget( 'form', [
            'button_text' => 'Send',
            'form_fields' => [
                [ 'field_type' => 'text',  'field_label' => 'Name' ],
                [ 'field_type' => 'email', 'field_label' => 'Email' ],
                [ 'field_type' => 'tel',   'field_label' => 'Phone' ],
            ],
        ] );

        $fields = $this->entriesOfKind( $result['report'], 'form_fields' );

        $this->assertCount( 1, $fields );
        $this->assertSame( 'w1', $fields[0]['element_id'] );
        $this->assertSame( '3 fields', $fields[0]['detail'] );
    }

    public function test_a_clean_widget_reports_nothing(): void {
        $result = $this->convertWidget( 'heading', [ 'title' => 'Hi' ] );

        $this->assertSame( [], $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // Approximate (shape-matched) widgets
    // -------------------------------------------------------------------------

    public function test_a_shape_matched_widget_is_reported_as_approximate(): void {
        $result = $this->convertWidget( 'some-unmapped-box', [
            'title_text'       => 'Fast',
            'description_text' => 'Very fast',
            'selected_icon'    => [ 'value' => 'fas fa-bolt' ],
        ] );

        $matches = $result['report']['approximate_matches'];

        $this->assertCount( 1, $matches );
        $this->assertSame( 'w1', $matches[0]['element_id'] );
        $this->assertSame( 'some-unmapped-box', $matches[0]['widget_type'] );
        $this->assertSame( 'IconBoxConverter', $matches[0]['matched_to'] );
    }

    public function test_an_approximate_match_is_not_counted_as_converted(): void {
        $result = $this->convertWidget( 'some-unmapped-box', [
            'title_text'       => 'Fast',
            'description_text' => 'Very fast',
            'selected_icon'    => [ 'value' => 'fas fa-bolt' ],
        ] );

        $this->assertSame( [], $result['report']['converted'], 'A guess is not a conversion' );
        $this->assertSame( [ 'blurb' => 1 ], $result['report']['approximate'] );
    }

    public function test_approximate_matches_lower_the_coverage_figure(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [
                'id' => 'ok', 'elType' => 'widget', 'widgetType' => 'heading',
                'settings' => [ 'title' => 'Real' ], 'elements' => [],
            ],
            [
                'id' => 'guess', 'elType' => 'widget', 'widgetType' => 'some-unmapped-box',
                'settings' => [
                    'title_text'       => 'Fast',
                    'description_text' => 'Very fast',
                    'selected_icon'    => [ 'value' => 'fas fa-bolt' ],
                ],
                'elements' => [],
            ],
        ] );

        $this->assertSame(
            50,
            $result['report']['quality']['widget_coverage'],
            'A guessed match must not read as full coverage'
        );
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function test_renderer_returns_nothing_when_there_is_nothing_to_report(): void {
        $this->assertSame( '', NotCarriedOverRenderer::render( [], [] ) );
    }

    public function test_renderer_lists_every_category_with_its_element_id(): void {
        $html = NotCarriedOverRenderer::render(
            [
                [ 'kind' => 'dynamic',     'element_id' => 'a1', 'detail' => 'title' ],
                [ 'kind' => 'animation',   'element_id' => 'b2', 'detail' => 'fadeInUp' ],
                [ 'kind' => 'motion',      'element_id' => 'c3', 'detail' => 'motion_fx_scrolling' ],
                [ 'kind' => 'form_fields', 'element_id' => 'd4', 'detail' => '3 fields' ],
            ],
            [ [ 'element_id' => 'e5', 'widget_type' => 'odd-box', 'matched_to' => 'IconBoxConverter' ] ]
        );

        $this->assertStringContainsString( 'Not carried over', $html );

        foreach ( [ 'a1', 'b2', 'c3', 'd4', 'e5' ] as $id ) {
            $this->assertStringContainsString( $id, $html, "Element {$id} must be named" );
        }

        $this->assertStringContainsString( 'fadeInUp', $html );
        $this->assertStringContainsString( '3 fields', $html );
        $this->assertStringContainsString( 'odd-box', $html );
    }

    public function test_renderer_escapes_element_ids(): void {
        $html = NotCarriedOverRenderer::render(
            [ [ 'kind' => 'dynamic', 'element_id' => '<script>x</script>', 'detail' => 'title' ] ],
            []
        );

        $this->assertStringNotContainsString( '<script>', $html );
    }

    public function test_renderer_omits_categories_with_no_entries(): void {
        $html = NotCarriedOverRenderer::render(
            [ [ 'kind' => 'dynamic', 'element_id' => 'a1', 'detail' => 'title' ] ],
            []
        );

        $this->assertStringContainsString( 'Dynamic content', $html );
        $this->assertStringNotContainsString( 'Form fields', $html );
    }

    // -------------------------------------------------------------------------
    // Structural elements, and the nested paths that bypass convertElement()
    // -------------------------------------------------------------------------

    private function convertTree( array $elements ): array {
        return ( new ConverterEngine() )->convert( $elements );
    }

    /**
     * Sticky is almost always set on a section or container, never a widget, and
     * entrance animations are routinely set on sections and columns. Limiting
     * this to widgets meant the most common places these are used reported
     * nothing at all.
     */
    public function test_a_section_reports_its_animation_and_sticky(): void {
        $result = $this->convertTree( [ [
            'id'       => 'sec1',
            'elType'   => 'section',
            'settings' => [ '_animation' => 'fadeInUp', 'sticky' => 'top' ],
            'elements' => [],
        ] ] );

        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'animation' ) );
        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'motion' ) );
    }

    public function test_a_column_reports_its_animation(): void {
        $result = $this->convertTree( [ [
            'id' => 'sec', 'elType' => 'section', 'settings' => [], 'elements' => [ [
                'id'       => 'col1',
                'elType'   => 'column',
                'settings' => [ '_animation' => 'fadeInUp' ],
                'elements' => [],
            ] ],
        ] ] );

        $animation = $this->entriesOfKind( $result['report'], 'animation' );

        $this->assertCount( 1, $animation );
        $this->assertSame( 'col1', $animation[0]['element_id'] );
    }

    public function test_section_parallax_is_reported_under_its_own_prefix(): void {
        $result = $this->convertTree( [ [
            'id'       => 'sec1',
            'elType'   => 'section',
            'settings' => [ 'background_motion_fx_motion_fx_scrolling' => 'yes' ],
            'elements' => [],
        ] ] );

        $this->assertCount( 1, $this->entriesOfKind( $result['report'], 'motion' ) );
    }

    /**
     * A nested section or container is routed straight to convertInnerAsRow() by
     * convertStructureChildren(), never reaching ConverterEngine::convertElement().
     * Until prepareNestedElement() existed, such an element resolved no globals
     * and reported no losses — on the containers most pages are built from.
     */
    public function test_a_nested_container_reports_losses_and_resolves_globals(): void {
        $result = $this->convertTree( [ [
            'id' => 'outer', 'elType' => 'container', 'settings' => [], 'elements' => [ [
                'id'       => 'inner',
                'elType'   => 'container',
                'settings' => [
                    '_animation'  => 'fadeInUp',
                    '__globals__' => [ 'background_color' => 'globals/colors?id=primary' ],
                ],
                'elements' => [],
            ] ],
        ] ] );

        $animation = $this->entriesOfKind( $result['report'], 'animation' );
        $this->assertCount( 1, $animation, 'A nested container must report its animation' );
        $this->assertSame( 'inner', $animation[0]['element_id'] );

        $this->assertCount(
            1,
            $result['report']['unresolved_globals'],
            'A nested container must report a global it could not resolve'
        );
    }

    public function test_a_nested_container_global_colour_resolves_when_a_kit_knows_it(): void {
        add_filter( 'edc_kit_globals', fn( $v ) => [
            'colors'     => [ 'primary' => '#123456' ],
            'typography' => [],
        ] );

        $result = $this->convertTree( [ [
            'id' => 'outer', 'elType' => 'container', 'settings' => [], 'elements' => [ [
                'id'       => 'inner',
                'elType'   => 'container',
                'settings' => [ '__globals__' => [ 'background_color' => 'globals/colors?id=primary' ] ],
                'elements' => [],
            ] ],
        ] ] );

        $this->assertSame( [], $result['report']['unresolved_globals'] );

        $json = json_encode( $result['divi'] );
        $this->assertStringContainsString( '#123456', $json, 'The resolved colour must reach the output' );
    }
}
