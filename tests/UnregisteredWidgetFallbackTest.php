<?php
// tests/UnregisteredWidgetFallbackTest.php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * An unregistered widget used to produce no block at all: convertElement()
 * returned [] and the widget vanished from the page. The report named it, but
 * the user was left to find the resulting hole in the layout themselves.
 *
 * It is now rescued by GenericFallbackConverter — the same placeholder twelve
 * EAEL widget types are explicitly registered to — while still being reported as
 * unsupported.
 */
final class UnregisteredWidgetFallbackTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convert( array $elements ): array {
        return ( new ConverterEngine() )->convert( $elements );
    }

    private function widget( string $type, array $settings = [], string $id = 'w1' ): array {
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => [],
        ];
    }

    // -------------------------------------------------------------------------
    // The rescue
    // -------------------------------------------------------------------------

    public function test_an_unregistered_widget_leaves_a_labelled_placeholder(): void {
        $result = $this->convert( [ $this->widget( 'totally-made-up-widget' ) ] );

        $this->assertCount( 1, $result['divi']['elements'] );

        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertSame( 'w1', $block['id'], 'The placeholder keeps the original element id' );
        $this->assertStringContainsString(
            'totally-made-up-widget',
            $block['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_it_is_still_reported_as_unsupported(): void {
        $result = $this->convert( [ $this->widget( 'totally-made-up-widget' ) ] );

        $this->assertCount( 1, $result['unsupported'] );
        $this->assertSame( 'totally-made-up-widget', $result['unsupported'][0]['widgetType'] );
        $this->assertSame( 'w1', $result['unsupported'][0]['id'] );
    }

    public function test_the_placeholder_does_not_count_as_a_conversion(): void {
        $result = $this->convert( [
            $this->widget( 'button', [ 'text' => 'Go', 'link' => [ 'url' => '#' ] ], 'ok' ),
            $this->widget( 'totally-made-up-widget', [], 'bad' ),
        ] );

        $this->assertSame(
            50,
            $result['report']['quality']['widget_coverage'],
            'A placeholder is not a conversion; counting it would inflate coverage as a page got worse'
        );
    }

    public function test_widget_text_is_carried_into_the_placeholder(): void {
        $result = $this->convert( [ $this->widget( 'made-up', [ 'title' => 'Quarterly results' ] ) ] );

        $value = $result['divi']['elements'][0]['settings']['content']['innerContent']['desktop']['value'];

        $this->assertStringContainsString( 'Quarterly results', $value );
        $this->assertStringContainsString( 'data-elementor-widget="made-up"', $value );
    }

    public function test_a_widget_with_no_recognised_text_emits_only_the_marker(): void {
        $result = $this->convert( [ $this->widget( 'made-up', [ 'some_opaque_key' => 'xyz' ] ) ] );

        $value = $result['divi']['elements'][0]['settings']['content']['innerContent']['desktop']['value'];

        $this->assertSame( '<!-- elementor widget: made-up (not convertible) -->', $value );
        $this->assertStringNotContainsString( 'xyz', $value, 'Unknown keys must not be pasted into the page' );
    }

    /**
     * A placeholder is content, so the column that held the widget is no longer
     * empty — which is the point: the gap stays where it happened.
     */
    public function test_the_widget_keeps_its_place_in_the_layout(): void {
        $result = $this->convert( [ [
            'id'       => 'section-1',
            'elType'   => 'section',
            'settings' => [],
            'elements' => [ [
                'id'       => 'col-1',
                'elType'   => 'column',
                'settings' => [],
                'elements' => [
                    $this->widget( 'heading', [ 'title' => 'Before' ], 'h1' ),
                    $this->widget( 'made-up', [], 'x' ),
                    $this->widget( 'heading', [ 'title' => 'After' ], 'h2' ),
                ],
            ] ],
        ] ] );

        $column = $result['divi']['elements'][0]['elements'][0]['elements'][0];
        $names  = array_column( $column['elements'], 'name' );

        $this->assertSame( [ 'divi/heading', 'divi/code', 'divi/heading' ], $names );
    }

    // -------------------------------------------------------------------------
    // Structural elements are not widgets
    // -------------------------------------------------------------------------

    public function test_an_unknown_structural_element_emits_no_placeholder(): void {
        $result = $this->convert( [ [
            'id'       => 'weird',
            'elType'   => 'some-future-structure',
            'settings' => [],
            'elements' => [],
        ] ] );

        $this->assertSame( [], $result['divi']['elements'], 'A layout box has no meaningful placeholder' );
        $this->assertCount( 1, $result['unsupported'] );
    }

    // -------------------------------------------------------------------------
    // The twelve explicitly-registered EAEL fallbacks are unchanged
    // -------------------------------------------------------------------------

    public static function eaelFallbackProvider(): array {
        return array_map(
            static fn( string $slug ): array => [ $slug ],
            [
                'eael-nft-gallery', 'eael-career-page', 'eael-svg-draw',
                'eael-betterdocs-category-box', 'eael-betterdocs-category-grid',
                'eael-betterdocs-search-form', 'eael-better-payment',
                'eael-typeform', 'eael-formstack',
                'eael-business-reviews', 'eael-facebook-feed', 'eael-twitter-feed',
            ]
        );
    }

    #[DataProvider('eaelFallbackProvider')]
    public function test_hand_wired_eael_fallbacks_behave_identically( string $slug ): void {
        $result = $this->convert( [ $this->widget( $slug ) ] );

        // Registered, so never reported unsupported.
        $this->assertSame( [], $result['unsupported'], "'{$slug}' is registered and must not be unsupported" );

        // Same placeholder markup as before.
        $this->assertSame(
            '<!-- elementor widget: ' . $slug . ' (not convertible) -->',
            $result['divi']['elements'][0]['settings']['content']['innerContent']['desktop']['value']
        );

        // Still counted as a conversion, as they always have been.
        $this->assertSame( [ 'code' => 1 ], $result['report']['converted'] );

        // Still warned about.
        $this->assertStringContainsString( $slug, implode( ' ', $result['report']['warnings'] ) );
    }
}
