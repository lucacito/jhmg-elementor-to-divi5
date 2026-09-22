<?php
// tests/AnimationAddonsBatch1Test.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "Animation Addons for Elementor" (wordpress.org: animation-addons-for-elementor,
 * widget-slug prefix "wcf--"/"aae--") — Batch 1: widgets whose settings match, or
 * were given a small fallback to match, an already-supported widget's converter.
 *
 * Every settings array below uses control names copied from the free plugin's own
 * source, version 2026-09-20 (references/animation-addons-for-elementor/widgets/):
 * counter.php, progressbar.php, countdown.php, icon-box.php, social-icons.php,
 * tabs.php, advance-accordion.php, image-gallery.php.
 */
final class AnimationAddonsBatch1Test extends TestCase {

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
    // wcf--counter (counter.php) — identical keys to Elementor's own Counter.
    // -------------------------------------------------------------------------

    public function test_counter_converts_with_no_new_converter_needed(): void {
        [ $block, $result ] = $this->convert( 'wcf--counter', [
            'starting_number' => 0,
            'ending_number'   => 250,
            'prefix'          => '',
            'suffix'          => '',
            'title'           => 'Happy Clients',
            'thousand_separator'      => 'yes',
            'thousand_separator_char' => '.',
        ] );

        $this->assertSame( 'divi/number-counter', $block['name'] );
        $this->assertSame( '250', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Happy Clients', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--progressbar (progressbar.php) — no title field, value under
    // 'percentage' => ['size' => n] rather than the native widget's 'percent'.
    // -------------------------------------------------------------------------

    public function test_progressbar_reads_the_slider_percentage_with_no_title(): void {
        [ $block, $result ] = $this->convert( 'wcf--progressbar', [
            'element_list'        => '1',
            'percentage'          => [ 'unit' => '%', 'size' => 72 ],
            'display_percentage'  => 'show',
            'color'               => '#7DDED8',
        ] );

        $this->assertSame( 'divi/counters', $block['name'] );
        $bar = $block['elements'][0];
        $this->assertSame( 'divi/counter', $bar['name'] );
        $this->assertSame( '72', $bar['settings']['barProgress']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'title', $bar['settings'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--countdown (countdown.php) — same DATE_TIME picker, different key name.
    // -------------------------------------------------------------------------

    public function test_countdown_reads_its_own_due_date_key(): void {
        [ $block, $result ] = $this->convert( 'wcf--countdown', [
            'countdown_timer_due_date'      => '2026-12-31 23:59',
            'countdown_style'               => 'style-1',
            'countdown_timer_days_label'    => 'Days',
            'show_separator'                => 'yes',
        ] );

        $this->assertSame( '2026-12-31 23:59', $block['settings']['content']['advanced']['dateTime']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--icon-box (icon-box.php) — identical keys to Elementor's own Icon Box.
    // -------------------------------------------------------------------------

    public function test_icon_box_converts_with_no_new_converter_needed(): void {
        [ $block, $result ] = $this->convert( 'wcf--icon-box', [
            'element_list'     => '1',
            'selected_icon'    => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ],
            'title_text'       => 'Fast Wi-Fi',
            'description_text' => 'Gigabit fibre on every desk.',
            'icon_color'       => '#123456',
            'title_color'      => '#000000',
        ] );

        $this->assertSame( 'divi/blurb', $block['name'] );
        $this->assertSame( [ 'text' => 'Fast Wi-Fi' ], $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Gigabit fibre on every desk.', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( '&#xf1eb;', $block['settings']['imageIcon']['innerContent']['desktop']['value']['icon']['unicode'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--social-icons (social-icons.php) — same {social_icon,link} item shape,
    // repeater key 'wcf_social_icons' instead of 'social_icon_list'.
    // -------------------------------------------------------------------------

    public function test_social_icons_reads_its_own_repeater_key(): void {
        [ $block, $result ] = $this->convert( 'wcf--social-icons', [
            'wcf_social_icons' => [
                [ 'social_icon' => [ 'value' => 'fab fa-linkedin', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/in/h' ] ],
            ],
        ] );

        $this->assertSame( 'divi/social-media-follow', $block['name'] );
        $this->assertCount( 1, $block['elements'] );
        $network = $block['elements'][0]['settings']['socialNetwork']['innerContent']['desktop']['value'];
        $this->assertSame( 'linkedin', $network['title'] );
        $this->assertSame( 'https://www.linkedin.com/in/h', $network['link'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--tabs (tabs.php) and wcf--a-accordion (advance-accordion.php) — both
    // repeat under an outer 'tabs' key with 'tab_title'/'tab_content' items,
    // identical to what TabsConverter/AccordionConverter already read.
    // -------------------------------------------------------------------------

    public function test_tabs_converts_with_no_new_converter_needed(): void {
        [ $block, $result ] = $this->convert( 'wcf--tabs', [
            'tabs' => [
                [ 'tab_title' => 'Rooms', 'tab_content' => '<p>Private and shared.</p>', 'tabs_content_type' => 'content' ],
                [ 'tab_title' => 'Pricing', 'tab_content' => '<p>From $29/day.</p>', 'tabs_content_type' => 'content' ],
            ],
            'tabs_direction' => 'horizontal',
        ] );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'Rooms', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Private and shared.</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_accordion_converts_with_no_new_converter_needed(): void {
        [ $block, $result ] = $this->convert( 'wcf--a-accordion', [
            'tabs' => [
                [ 'tab_count' => '01', 'tab_title' => 'What is included?', 'tab_content' => '<p>Desk, wifi, coffee.</p>' ],
            ],
            'accordion_style' => 'style-1',
            'first_item_open' => 'yes',
        ] );

        $this->assertSame( 'divi/accordion', $block['name'] );
        $this->assertSame( 'divi/accordion-item', $block['elements'][0]['name'] );
        $this->assertSame( 'What is included?', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--image-gallery (image-gallery.php) — repeater key 'wcf_image_gallery',
    // each item wraps its attachment under an 'image' field rather than being
    // the attachment object itself.
    // -------------------------------------------------------------------------

    public function test_image_gallery_unwraps_its_own_repeater_items(): void {
        [ $block, $result ] = $this->convert( 'wcf--image-gallery', [
            'wcf_image_gallery' => [
                [ 'image' => [ 'id' => 501, 'url' => 'https://x.test/1.jpg' ], 'link' => [ 'url' => '' ] ],
                [ 'image' => [ 'id' => 502, 'url' => 'https://x.test/2.jpg' ], 'link' => [ 'url' => '' ] ],
            ],
            'layout_style'    => 'grid',
            'enable_lightbox' => 'yes',
        ] );

        $this->assertSame( 'divi/gallery', $block['name'] );
        $this->assertSame( [ 501, 502 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }
}
