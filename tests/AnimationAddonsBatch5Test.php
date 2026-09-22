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

    // -------------------------------------------------------------------------
    // wcf--image-accordion (image-accordion.php) — a repeater of items always
    // shown side by side (no slidesToShow/navigation, unlike the group-carousel
    // cluster), each with its own image/title/subtitle/description/link, plus
    // one widget-wide title_tag and btn_text. Becomes a plain divi/group of
    // divi/group children, the same free-form-container shape as
    // WcfImageBoxSliderConverter's per-slide boxes but without a carousel wrapper.
    // -------------------------------------------------------------------------

    public function test_image_accordion_builds_one_group_per_item(): void {
        [ $block, $result ] = $this->convert( 'wcf--image-accordion', [
            'title_tag' => 'h3',
            'link_type' => 'button',
            'btn_text'  => 'Read More',
            'accordions' => [
                [
                    'image' => [ 'url' => 'https://x.test/a.jpg', 'alt' => 'A' ],
                    'title' => 'Item A', 'subtitle' => 'Sub A', 'description' => 'Desc A',
                    'details_link' => [ 'url' => 'https://x.test/a' ],
                ],
                [
                    'image' => [ 'url' => 'https://x.test/b.jpg' ],
                    'title' => 'Item B',
                ],
            ],
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $this->assertCount( 2, $block['elements'] );

        $item_a = $block['elements'][0];
        $this->assertSame( 'divi/group', $item_a['name'] );
        $names_a = array_map( static fn( $c ) => $c['name'], $item_a['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/text', 'divi/button' ], $names_a );
        $this->assertSame( 'Item A', $item_a['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h3', $item_a['elements'][1]['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( 'Read More', $item_a['elements'][3]['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/a', $item_a['elements'][3]['settings']['button']['innerContent']['desktop']['value']['linkUrl'] );

        // Item B has no 'details_link', but the widget-wide button label still
        // renders (Aaeaddon_Button_Trait shows the button whenever link_type is
        // 'button' regardless of whether that item's own link is set) — just
        // without a linkUrl.
        $item_b = $block['elements'][1];
        $names_b = array_map( static fn( $c ) => $c['name'], $item_b['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/button' ], $names_b );
        $this->assertArrayNotHasKey( 'linkUrl', $item_b['elements'][2]['settings']['button']['innerContent']['desktop']['value'] );

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_image_accordion_no_button_when_link_type_none(): void {
        [ $block ] = $this->convert( 'wcf--image-accordion', [
            'link_type' => 'none',
            'accordions' => [
                [ 'title' => 'Item A', 'details_link' => [ 'url' => 'https://x.test/a' ] ],
            ],
        ] );

        $names = array_map( static fn( $c ) => $c['name'], $block['elements'][0]['elements'] ?? [] );
        $this->assertNotContains( 'divi/button', $names );
    }

    // -------------------------------------------------------------------------
    // wcf--author-box (author-box.php) — 'source' => 'custom' is fully static
    // (author_avatar/author_name/author_website/author_bio/posts_url are plain
    // settings) and becomes a divi/group of image/heading/text/button, the same
    // shape as wcf--image-box. 'source' => 'current' (the widget's own default)
    // instead pulls the *current post's author* at render time via
    // get_the_author_meta()/get_author_posts_url()/count_user_posts() — none of
    // which the converter has access to (ConverterEngine has no post context),
    // so that variant has no static equivalent and falls back to
    // GenericFallbackConverter's placeholder rather than fabricating content.
    // -------------------------------------------------------------------------

    public function test_author_box_custom_source_builds_static_group(): void {
        [ $block, $result ] = $this->convert( 'wcf--author-box', [
            'source'         => 'custom',
            'author_avatar'  => [ 'url' => 'https://x.test/jd.jpg' ],
            'author_name'    => 'John Doe',
            'author_name_tag' => 'h5',
            'author_website' => [ 'url' => 'https://x.test/john' ],
            'author_bio'     => 'Writes about yoga.',
            'posts_url'      => [ 'url' => 'https://x.test/author/john' ],
            'link_text'      => 'All Posts',
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/text', 'divi/button' ], $names );

        $this->assertSame( 'https://x.test/jd.jpg', $block['elements'][0]['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( 'John Doe', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h5', $block['elements'][1]['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( 'Writes about yoga.', $block['elements'][2]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'All Posts', $block['elements'][3]['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/author/john', $block['elements'][3]['settings']['button']['innerContent']['desktop']['value']['linkUrl'] );

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_author_box_current_source_falls_back_to_placeholder(): void {
        [ $block, $result ] = $this->convert( 'wcf--author-box', [ 'source' => 'current' ] );

        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertNotEmpty( $result['report']['warnings'] );
    }

    public function test_author_box_defaults_to_current_source_when_unset(): void {
        [ $block ] = $this->convert( 'wcf--author-box', [] );

        $this->assertSame( 'divi/code', $block['name'] );
    }
}
