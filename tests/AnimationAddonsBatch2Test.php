<?php
// tests/AnimationAddonsBatch2Test.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "Animation Addons for Elementor" — Batch 2: the rest of the slider cluster.
 * All three widgets below are built on the plugin's own shared
 * Aaeaddon_Slider_Trait (inc/Aaeaddon_Slider_Trait.php), so they carry the same
 * slides_to_show/autoplay/navigation/pagination control names as
 * wcf--image-box-slider — verified against the free plugin's real source,
 * version 4.2.2 (references/animation-addons-for-elementor.4.2.2.zip):
 * event-slider.php, brand-slider.php, content-slider.php.
 */
final class AnimationAddonsBatch2Test extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convert( string $type, array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => [],
        ] ] );

        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "widget {$type}" );

        return [ $result['divi']['elements'][0], $result ];
    }

    // -------------------------------------------------------------------------
    // wcf--event-slider (event-slider.php)
    // -------------------------------------------------------------------------

    public function test_events_become_groups_with_image_name_date_and_description(): void {
        [ $block ] = $this->convert( 'wcf--event-slider', [
            'events' => [
                [
                    'event_image' => [ 'url' => 'https://x.test/e1.jpg', 'alt' => 'Launch' ],
                    'event_name'  => 'Launch Night',
                    'event_date'  => 'New York, 24 Mar 2027',
                    'event_desc'  => 'Kick off the new season.',
                    'event_link'  => [ 'url' => 'https://x.test/rsvp' ],
                ],
            ],
            'slides_to_show' => '3',
            'navigation'     => 'yes',
        ] );

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $this->assertSame( '3', $block['settings']['module']['advanced']['slidesToShow']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['arrows']['advanced']['show']['desktop']['value'] );

        [ $image, $heading, $text ] = $block['elements'][0]['elements'];
        $this->assertSame( [ 'src' => 'https://x.test/e1.jpg', 'alt' => 'Launch' ], $image['settings']['image']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Launch Night', $heading['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://x.test/rsvp', $heading['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertSame( "<p>New York, 24 Mar 2027</p>\n<p>Kick off the new season.</p>", $text['settings']['content']['innerContent']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // wcf--brand-slider (brand-slider.php) — two entirely different shapes
    // depending on 'slide_content'.
    // -------------------------------------------------------------------------

    public function test_brand_slider_image_mode_reads_the_gallery_control(): void {
        [ $block ] = $this->convert( 'wcf--brand-slider', [
            'slide_content'      => 'image',
            'wcf_brand_carousel' => [
                [ 'id' => 101, 'url' => 'https://x.test/logo1.png', 'alt' => 'Acme' ],
                [ 'id' => 102, 'url' => 'https://x.test/logo2.png' ],
            ],
        ] );

        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/group', $block['elements'][0]['name'] );
        $this->assertSame( 'divi/image', $block['elements'][0]['elements'][0]['name'] );
        $this->assertSame(
            [ 'src' => 'https://x.test/logo1.png', 'alt' => 'Acme' ],
            $block['elements'][0]['elements'][0]['settings']['image']['innerContent']['desktop']['value']
        );
    }

    public function test_brand_slider_text_mode_reads_the_repeater(): void {
        [ $block ] = $this->convert( 'wcf--brand-slider', [
            'slide_content'    => 'text',
            'repeat_list_text' => [
                [ 'list_text' => 'Featured in Forbes' ],
                [ 'list_text' => 'As seen on TechCrunch' ],
            ],
        ] );

        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/text', $block['elements'][0]['elements'][0]['name'] );
        $this->assertSame( '<p>Featured in Forbes</p>', $block['elements'][0]['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // wcf--content-slider (content-slider.php) — free content vs. a saved
    // template reference this converter cannot resolve.
    // -------------------------------------------------------------------------

    public function test_content_slider_reads_wysiwyg_html_per_slide(): void {
        [ $block ] = $this->convert( 'wcf--content-slider', [
            'content_slider' => [
                [ 'content_type' => 'content', 'slide_content' => '<p>Handcrafted since 2019.</p>' ],
            ],
        ] );

        $this->assertSame(
            '<p>Handcrafted since 2019.</p>',
            $block['elements'][0]['elements'][0]['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_content_slider_template_reference_is_reported_not_invented(): void {
        [ $block, $result ] = $this->convert( 'wcf--content-slider', [
            'content_slider' => [
                [ 'content_type' => 'template', 'elementor_templates' => '4821' ],
            ],
        ] );

        $this->assertSame( [], $block['elements'][0]['elements'] );
        $this->assertSame( 'saved_template', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( '4821', $result['report']['not_carried_over'][0]['detail'] );
    }

    // -------------------------------------------------------------------------
    // Shared trait control names (slides_to_show/autoplay/navigation/pagination)
    // behave identically across all three widgets and image-box-slider.
    // -------------------------------------------------------------------------

    public function test_autoplay_off_and_no_navigation_leave_group_carousel_settings_unset(): void {
        [ $block ] = $this->convert( 'wcf--event-slider', [ 'events' => [] ] );

        $this->assertArrayNotHasKey( 'auto', $block['settings']['module']['advanced'] );
        $this->assertArrayNotHasKey( 'arrows', $block['settings'] );
        $this->assertArrayNotHasKey( 'dotNav', $block['settings'] );
        $this->assertSame( '1', $block['settings']['module']['advanced']['slidesToShow']['desktop']['value'] );
    }
}
