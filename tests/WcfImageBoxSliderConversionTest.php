<?php
// tests/WcfImageBoxSliderConversionTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "wcf--image-box-slider" (Animation Addons for Elementor) converts to
 * divi/group-carousel + one divi/group per box, not divi/slider: the widget
 * can show several boxes side by side (slides_to_show), which group-carousel's
 * module.advanced.slidesToShow natively supports, unlike divi/slider's
 * one-full-slide-at-a-time model.
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

    public function test_boxes_become_divi_group_children_holding_image_heading_and_text(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => $this->slides( 3 ) ] );

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $this->assertCount( 3, $block['elements'] );

        $first = $block['elements'][0];
        $this->assertSame( 'divi/group', $first['name'] );
        $this->assertCount( 3, $first['elements'] );

        [ $image, $heading, $text ] = $first['elements'];
        $this->assertSame( 'divi/image', $image['name'] );
        $this->assertSame( [ 'src' => 'https://x.test/img-1.jpg', 'alt' => 'Gallery 1' ], $image['settings']['image']['innerContent']['desktop']['value'] );

        $this->assertSame( 'divi/heading', $heading['name'] );
        $this->assertSame( 'Slide 1', $heading['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h3', $heading['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );

        $this->assertSame( 'divi/text', $text['name'] );
        $this->assertSame(
            "<p>Subtitle 1</p>\n<p>Description 1</p>",
            $text['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_a_box_with_only_an_image_has_a_single_child(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => [
            [ '_id' => 's1', 'image' => [ 'url' => 'https://x.test/only.jpg' ] ],
        ] ] );

        $this->assertCount( 1, $block['elements'][0]['elements'] );
        $this->assertSame( 'divi/image', $block['elements'][0]['elements'][0]['name'] );
    }

    public function test_slides_to_show_maps_directly_with_no_approximation_reported(): void {
        [ $block, $result ] = $this->convert( [ 'image_box_slider' => $this->slides( 4 ), 'slides_to_show' => '4' ] );

        $this->assertSame( '4', $block['settings']['module']['advanced']['slidesToShow']['desktop']['value'] );
        $this->assertSame( [], $result['report']['not_carried_over'] );
    }

    public function test_autoplay_navigation_pagination_and_center_slide_map_to_group_carousel_settings(): void {
        [ $block ] = $this->convert( [
            'image_box_slider' => $this->slides( 1 ),
            'autoplay'         => 'yes',
            'navigation'       => 'yes',
            'pagination'       => 'yes',
            'center_slide'     => 'yes',
        ] );

        $this->assertSame( 'on', $block['settings']['module']['advanced']['auto']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['module']['advanced']['centerMode']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['arrows']['advanced']['show']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['dotNav']['advanced']['show']['desktop']['value'] );
    }

    public function test_autoplay_off_and_empty_navigation_leave_settings_unset(): void {
        [ $block ] = $this->convert( [ 'image_box_slider' => $this->slides( 1 ) ] );

        $this->assertArrayNotHasKey( 'auto', $block['settings']['module']['advanced'] );
        $this->assertArrayNotHasKey( 'centerMode', $block['settings']['module']['advanced'] );
        $this->assertArrayNotHasKey( 'arrows', $block['settings'] );
        $this->assertArrayNotHasKey( 'dotNav', $block['settings'] );
    }

    /**
     * The scroll-animation / cursor-hover / tooltip settings this suite attaches to
     * ordinary widgets (heading here) are cosmetic-only and must not flood the
     * unmapped-settings report.
     */
    public function test_aae_and_wcf_prefixed_settings_on_an_ordinary_widget_are_not_reported_as_unmapped(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'h1',
            'elType'     => 'widget',
            'widgetType' => 'heading',
            'settings'   => [
                'title'                            => 'Hey!',
                'aae_anim_s_cus'                   => 'top top',
                'aae_anim_e_cus'                   => 'bottom top',
                'aae_header_sticky_start_position' => 300,
                'wcf_a_transform_origin'           => 'top center -50',
                'wcf_pin_area_start_custom'        => 'top top',
                'wcf_enable_cursor_hover_effect_text' => 'View',
                'wcf_advanced_tooltip_content'     => 'I am a tooltip',
                'brandberry_text_3d_enable'        => 'text_3d',
            ],
            'elements'   => [],
        ] ] );

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }
}
