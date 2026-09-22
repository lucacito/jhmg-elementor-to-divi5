<?php
// tests/AnimationAddonsBatch5Test.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "Animation Addons for Elementor" — Batch 5: wcf--image-box. Verified against
 * the free plugin's real source, version 4.2.2
 * (references/animation-addons-for-elementor.4.2.2.zip): widgets/image-box.php,
 * inc/Aaeaddon_Button_Trait.php (shared 'btn_text'/'btn_link' controls, same
 * as the standalone wcf--button widget already handled by ButtonConverter).
 *
 * Unlike wcf--image-box-slider (a repeater of several boxes shown in a
 * carousel), image-box.php is a single standalone box: one image, one
 * title/subtitle/description, an optional icon, and an optional button.
 * There's no single Divi module for that shape, so — following the same
 * "free-form container" approach WcfImageBoxSliderConverter uses per-slide —
 * it becomes a divi/group with divi/image, divi/icon, divi/heading, divi/text
 * and divi/button children built directly, rather than a new fixed-field
 * module.
 */
final class AnimationAddonsBatch5Test extends TestCase {

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

    public function test_image_box_builds_group_with_image_title_subtitle_description(): void {
        [ $block, $result ] = $this->convert( 'wcf--image-box', [
            'image'       => [ 'url' => 'https://x.test/glory.jpg', 'alt' => 'Siyantika Glory' ],
            'title'       => 'Siyantika Glory',
            'title_tag'   => 'h4',
            'subtitle'    => 'Modelling - 2012',
            'description' => 'Hatha yoga built on balance.',
            'link_type'   => 'none',
        ] );

        $this->assertSame( 'divi/group', $block['name'] );

        $names = array_map( static fn( $child ) => $child['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/text' ], $names );

        $image = $block['elements'][0];
        $this->assertSame( 'https://x.test/glory.jpg', $image['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( 'Siyantika Glory', $image['settings']['image']['innerContent']['desktop']['value']['alt'] );

        $heading = $block['elements'][1];
        $this->assertSame( 'Siyantika Glory', $heading['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h4', $heading['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );

        $text = $block['elements'][2];
        $this->assertSame(
            "<p>Modelling - 2012</p>\n<p>Hatha yoga built on balance.</p>",
            $text['settings']['content']['innerContent']['desktop']['value']
        );

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_image_box_adds_button_child_when_link_type_button(): void {
        [ $block ] = $this->convert( 'wcf--image-box', [
            'title'     => 'Read more',
            'link_type' => 'button',
            'btn_text'  => 'View Profile',
            'btn_link'  => [ 'url' => 'https://x.test/profile' ],
        ] );

        $names = array_map( static fn( $child ) => $child['name'], $block['elements'] );
        $this->assertContains( 'divi/button', $names );

        $button = $block['elements'][ array_search( 'divi/button', $names, true ) ];
        $this->assertSame( 'View Profile', $button['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/profile', $button['settings']['button']['innerContent']['desktop']['value']['linkUrl'] );
    }

    public function test_image_box_adds_icon_child_when_icon_set(): void {
        [ $block ] = $this->convert( 'wcf--image-box', [
            'title'          => 'Yoga',
            'image_box_icon' => [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ],
            'link_type'      => 'none',
        ] );

        $names = array_map( static fn( $child ) => $child['name'], $block['elements'] );
        $this->assertSame( [ 'divi/icon', 'divi/heading' ], $names );
    }

    public function test_image_box_no_button_when_link_type_none(): void {
        [ $block, $result ] = $this->convert( 'wcf--image-box', [
            'title'     => 'Just a title',
            'link_type' => 'none',
        ] );

        $names = array_map( static fn( $child ) => $child['name'], $block['elements'] );
        $this->assertNotContains( 'divi/button', $names );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--toggle-switch (toggle-switcher.php; get_name() is 'wcf--toggle-switch',
    // not 'wcf--toggle-switcher') — a two-item repeater, same title/content
    // shape as wcf--tabs (TabsConverter), just with its own 'switch_title'/
    // 'switch_content' keys, so it targets divi/tabs the same way.
    // -------------------------------------------------------------------------

    public function test_toggle_switch_builds_two_tabs_from_its_own_keys(): void {
        [ $block, $result ] = $this->convert( 'wcf--toggle-switch', [
            'toggle_switcher' => [
                [ 'switch_title' => 'Monthly', 'content_type' => 'content', 'switch_content' => '<p>Billed monthly.</p>' ],
                [ 'switch_title' => 'Yearly', 'content_type' => 'content', 'switch_content' => '<p>Billed yearly, 2 months free.</p>' ],
            ],
        ] );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/tab', $block['elements'][0]['name'] );
        $this->assertSame( 'Monthly', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Billed monthly.</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Yearly', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_toggle_switch_template_item_has_no_content_but_no_noise(): void {
        [ $block, $result ] = $this->convert( 'wcf--toggle-switch', [
            'toggle_switcher' => [
                [ 'switch_title' => 'Monthly', 'content_type' => 'template', 'elementor_templates' => '42' ],
            ],
        ] );

        $this->assertArrayNotHasKey( 'content', $block['elements'][0]['settings'] ?? [] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }
}
