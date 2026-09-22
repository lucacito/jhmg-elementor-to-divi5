<?php
// tests/WcfImageBoxSliderConversionTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "wcf--image-box-slider" (Animation Add-ons / "wcf" widget suite) converts to
 * divi/slider + divi/slide, the same target SliderConverter uses for
 * Elementor's own Slides widget: each item carries a title, subtitle,
 * description and image, not just a photo.
 */
final class WcfImageBoxSliderConversionTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convert( array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => 'wcf--image-box-slider',
            'settings'   => $settings,
            'elements'   => [],
        ] ] );

        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], 'wcf image box slider' );

        return [ $result['divi']['elements'][0], $result ];
    }

    private function slides( int $n ): array {
        $out = [];
        for ( $i = 1; $i <= $n; $i++ ) {
            $out[] = [
                '_id'         => "s{$i}",
                'image'       => [ 'id' => 100 + $i, 'url' => "https://x.test/img-{$i}.jpg", 'alt' => "Gallery {$i}" ],
                'title'       => "Slide {$i}",
                'subtitle'    => "Subtitle {$i}",
                'description' => "Description {$i}",
            ];
        }
        return $out;
    }

    public function test_slides_become_divi_slide_children_with_title_image_and_combined_content(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => $this->slides( 3 ) ] );

        $this->assertSame( 'divi/slider', $block['name'] );
        $this->assertCount( 3, $block['elements'] );

        $first = $block['elements'][0];
        $this->assertSame( 'divi/slide', $first['name'] );
        $this->assertSame( 'Slide 1', $first['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame(
            "<p>Subtitle 1</p>\n<p>Description 1</p>",
            $first['settings']['content']['innerContent']['desktop']['value']
        );
        $this->assertSame(
            [ 'src' => 'https://x.test/img-1.jpg', 'alt' => 'Gallery 1' ],
            $first['settings']['image']['innerContent']['desktop']['value']
        );
    }

    public function test_slide_with_no_subtitle_or_description_has_no_content_field(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => [
            [ '_id' => 's1', 'title' => 'Only a title' ],
        ] ] );

        $this->assertArrayNotHasKey( 'content', $block['elements'][0]['settings'] );
    }

    public function test_autoplay_navigation_and_pagination_map_to_divi_slider_settings(): void {
        [ $block ] = $this->convert( [
            'image_box_slider' => $this->slides( 1 ),
            'autoplay'         => 'yes',
            'navigation'       => 'yes',
            'pagination'       => 'yes',
        ] );

        $this->assertSame( 'on', $block['settings']['module']['advanced']['auto']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['arrows']['advanced']['show']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['pagination']['advanced']['show']['desktop']['value'] );
    }

    public function test_autoplay_off_and_empty_navigation_leave_settings_unset(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => $this->slides( 1 ) ] );

        $this->assertArrayNotHasKey( 'module', $block['settings'] );
        $this->assertArrayNotHasKey( 'arrows', $block['settings'] );
        $this->assertArrayNotHasKey( 'pagination', $block['settings'] );
    }

    public function test_more_than_one_slide_shown_at_once_is_reported_as_approximated(): void {
        [ , $result ] = $this->convert( [ 'image_box_slider' => $this->slides( 4 ), 'slides_to_show' => '4' ] );

        $this->assertSame( 'slider_layout', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( '4 slides side by side', $result['report']['not_carried_over'][0]['detail'] );
    }

    public function test_a_single_slide_shown_is_not_reported(): void {
        [ , $result ] = $this->convert( [ 'image_box_slider' => $this->slides( 1 ), 'slides_to_show' => '1' ] );

        $this->assertSame( [], $result['report']['not_carried_over'] );
    }

    /**
     * The scroll-animation / cursor-hover / tooltip settings this suite attaches to
     * ordinary widgets (heading here) are cosmetic-only and must not flood the
     * unmapped-settings report.
     */
    public function test_aae_and_wcf_prefixed_settings_on_an_ordinary_widget_are_not_reported_as_unmapped(): void {
        [ , $result ] = ( function () {
            $engine = new ConverterEngine();
            $out    = $engine->convert( [ [
                'id'         => 'h1',
                'elType'     => 'widget',
                'widgetType' => 'heading',
                'settings'   => [
                    'title'                          => 'Hey!',
                    'aae_anim_s_cus'                 => 'top top',
                    'aae_anim_e_cus'                 => 'bottom top',
                    'aae_header_sticky_start_position' => 300,
                    'wcf_a_transform_origin'         => 'top center -50',
                    'wcf_pin_area_start_custom'      => 'top top',
                    'wcf_enable_cursor_hover_effect_text' => 'View',
                    'wcf_advanced_tooltip_content'   => 'I am a tooltip',
                    'brandberry_text_3d_enable'      => 'text_3d',
                ],
                'elements'   => [],
            ] ] );
            return [ $out['divi']['elements'][0], $out ];
        } )();

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }
}
