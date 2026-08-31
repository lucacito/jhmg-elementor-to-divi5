<?php
// tests/NativeSlugRegistrationTest.php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Three widgets were registered under names Elementor never emits, so a real
 * export always fell through to "unsupported" and the widget was dropped from
 * the page entirely.
 *
 * Slugs verified against Elementor 3.28.2 get_name():
 *   includes/widgets/google-maps.php   → 'google_maps'
 *   includes/widgets/image-gallery.php → 'image-gallery'
 *   includes/widgets/progress.php      → 'progress'
 */
final class NativeSlugRegistrationTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convertWidget( string $widget_type, array $settings ): array {
        $engine = new ConverterEngine();

        return $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => $widget_type,
            'settings'   => $settings,
            'elements'   => [],
        ] ] );
    }

    public static function nativeSlugProvider(): array {
        return [
            'google maps' => [
                'google_maps',
                [ 'address' => 'Trafalgar Square', 'zoom' => [ 'size' => 12, 'unit' => 'px' ] ],
                'divi/code',
            ],
            'image gallery' => [
                'image-gallery',
                [ 'wp_gallery' => [ [ 'id' => 5, 'url' => 'https://example.test/a.jpg' ] ] ],
                'divi/gallery',
            ],
            'progress' => [
                'progress',
                [ 'title' => 'Design', 'percent' => [ 'size' => 80, 'unit' => '%' ] ],
                'divi/counters',
            ],
        ];
    }

    #[DataProvider('nativeSlugProvider')]
    public function test_native_slug_is_registered_and_not_dropped(
        string $widget_type,
        array $settings,
        string $expected_block
    ): void {
        $result = $this->convertWidget( $widget_type, $settings );

        $this->assertSame(
            [],
            $result['unsupported'],
            "'{$widget_type}' is a real Elementor slug and must not be reported unsupported"
        );

        $block = $result['divi']['elements'][0];
        $this->assertSame( $expected_block, $block['name'] );
    }

    // -------------------------------------------------------------------------
    // Payload shape: registering the slug is only half the fix
    // -------------------------------------------------------------------------

    public function test_google_maps_carries_the_address_and_zoom(): void {
        $result = $this->convertWidget( 'google_maps', [
            'address' => 'Trafalgar Square',
            'zoom'    => [ 'size' => 12, 'unit' => 'px' ],
        ] );

        $html = $result['divi']['elements'][0]['settings']['content']['innerContent']['desktop']['value'];

        $this->assertStringContainsString( 'Trafalgar%20Square', $html );
        $this->assertStringContainsString( 'z=12', $html );
    }

    public function test_image_gallery_reads_the_wp_gallery_key(): void {
        $result = $this->convertWidget( 'image-gallery', [
            'wp_gallery' => [
                [ 'id' => 5, 'url' => 'https://example.test/a.jpg' ],
                [ 'id' => 6, 'url' => 'https://example.test/b.jpg' ],
            ],
        ] );

        $ids = $result['divi']['elements'][0]['settings']['image']['advanced']['galleryIds']['desktop']['value'];

        $this->assertSame( [ 5, 6 ], $ids );
    }

    /**
     * Elementor's `progress` widget is a single bar with flat `title`/`percent`
     * keys — it has no `bars` repeater. Reading only `bars` produced a counters
     * module with no children: the content vanished while the report still
     * counted the widget as converted.
     */
    public function test_native_progress_produces_one_populated_bar(): void {
        $result = $this->convertWidget( 'progress', [
            'title'   => 'Design',
            'percent' => [ 'size' => 80, 'unit' => '%' ],
        ] );

        $bars = $result['divi']['elements'][0]['elements'];

        $this->assertCount( 1, $bars, 'A native progress widget is one bar, not zero' );
        $this->assertSame( 'Design', $bars[0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '80', $bars[0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_bars_repeater_shape_still_wins_when_present(): void {
        $result = $this->convertWidget( 'progress-bar', [
            'bars' => [
                [ 'label' => 'One', 'percent' => [ 'size' => 10 ] ],
                [ 'label' => 'Two', 'percent' => [ 'size' => 20 ] ],
            ],
        ] );

        $bars = $result['divi']['elements'][0]['elements'];

        $this->assertCount( 2, $bars );
        $this->assertSame( 'One', $bars[0]['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_legacy_aliases_remain_registered(): void {
        foreach ( [ 'google-maps', 'gallery', 'progress-bar' ] as $alias ) {
            $result = $this->convertWidget( $alias, [] );
            $this->assertSame( [], $result['unsupported'], "Alias '{$alias}' must keep working" );
        }
    }
}
