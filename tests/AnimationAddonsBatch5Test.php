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

    // -------------------------------------------------------------------------
    // wcf--services-tab (services-tab.php) — its 'tabs' repeater already uses
    // TabsConverter's own 'tab_title'/'tab_content' keys exactly, so it's a
    // straight reuse with zero new converter code. Its own extra per-item
    // fields ('tab_number', 'tab_image', 'link') and widget-level style/button
    // controls aren't read by TabsConverter, so they need adding to its
    // skipped-settings allowlist.
    // -------------------------------------------------------------------------

    public function test_services_tab_reuses_tabs_converter(): void {
        [ $block, $result ] = $this->convert( 'wcf--services-tab', [
            'tabs' => [
                [ 'tab_number' => '01', 'tab_title' => 'Consulting', 'tab_content' => '<p>We advise.</p>', 'tab_image' => [ 'url' => 'https://x.test/a.jpg' ], 'link' => [ 'url' => 'https://x.test/a' ] ],
                [ 'tab_number' => '02', 'tab_title' => 'Design', 'tab_content' => '<p>We design.</p>' ],
            ],
            'view'         => 'traditional',
            'element_list' => '1',
            'btn_text'     => 'Get ticket',
            'image_size'   => 'full',
            'image_size_size' => [ 'width' => 100 ],
            'tabs_direction' => 'row',
            'tabs_align'     => 'left',
        ] );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'Consulting', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>We advise.</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--t-h-image (text-hover-image.php; get_name() returns 'wcf--t-h-image',
    // not a guessable slug) — a heading built from three text fragments
    // (before_hover_text/hover_text/after_hover_text) plus a decorative image
    // that only appears on hover over the middle fragment. The text and link
    // convert cleanly to divi/heading (same module.advanced.link pattern
    // HeadingConverter already uses); the hover-reveal image has no Divi
    // heading equivalent and is logged as not carried over rather than dropped
    // silently.
    // -------------------------------------------------------------------------

    public function test_text_hover_image_builds_heading_from_three_fragments(): void {
        [ $block, $result ] = $this->convert( 'wcf--t-h-image', [
            'before_hover_text' => "I'm ",
            'hover_text'        => 'Mariya',
            'after_hover_text'  => ' the awarded dancer',
            'html_tag'          => 'h2',
            'image'             => [ 'url' => 'https://x.test/mariya.jpg' ],
            'link'              => [ 'url' => 'https://x.test/about' ],
        ] );

        $this->assertSame( 'divi/heading', $block['name'] );
        $this->assertSame( "I'm Mariya the awarded dancer", $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h2', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( 'https://x.test/about', $block['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // wcf--one-page-nav (one-page-nav.php) — a fixed scroll-spy side nav whose
    // items (icon + text + section_id anchor) each become a divi/icon-list-item
    // (PriceListConverter's proven icon-list shape: text in content.innerContent,
    // link in module.advanced.link — here '#' + section_id instead of a URL
    // control, since this widget links to in-page anchors, not external URLs).
    // The fixed/scroll-spy positioning itself has no Divi equivalent and is
    // logged as not carried over; the nav items and their anchors do carry over.
    // -------------------------------------------------------------------------

    public function test_one_page_nav_builds_icon_list_with_anchor_links(): void {
        [ $block, $result ] = $this->convert( 'wcf--one-page-nav', [
            'wcf_one_page_nav' => [
                [ 'nav_text' => 'Home', 'section_id' => 'home', 'selected_icon' => [ 'value' => 'fas fa-home', 'library' => 'fa-solid' ] ],
                [ 'nav_text' => 'About', 'section_id' => 'about' ],
            ],
        ] );

        $this->assertSame( 'divi/icon-list', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/icon-list-item', $block['elements'][0]['name'] );
        $this->assertSame( 'Home', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( '#home', $block['elements'][0]['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertArrayHasKey( 'icon', $block['elements'][0]['settings'] );
        $this->assertSame( '#about', $block['elements'][1]['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // wfc--team-slider (team-slider.php — get_name() returns 'wfc--team-slider',
    // a typo'd prefix, not 'wcf--team-slider'; confirmed from source rather than
    // guessed) — a carousel version of wcf--team's single member. Each item
    // becomes a divi/team-member (WcfTeamConverter's target) wrapped in a
    // divi/group, inside divi/group-carousel — the same "typed module wrapped
    // in a group" shape WcfTestimonialConverter already uses for its carousel.
    // -------------------------------------------------------------------------

    public function test_team_slider_builds_group_carousel_of_team_members(): void {
        [ $block, $result ] = $this->convert( 'wfc--team-slider', [
            'title_tag'  => 'h3',
            'slides_to_show' => 3,
            'team_slides' => [
                [
                    'title' => 'Jeanel Christina', 'desc' => 'Senior Developer',
                    'image' => [ 'url' => 'https://x.test/jc.jpg' ],
                    'helo_show_social' => 'yes',
                    'social_icon_01' => [ 'value' => 'fab fa-facebook' ], 'link_one' => [ 'url' => 'https://x.test/fb' ],
                ],
            ],
        ] );

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $this->assertCount( 1, $block['elements'] );
        $item_group = $block['elements'][0];
        $this->assertSame( 'divi/group', $item_group['name'] );
        $this->assertCount( 1, $item_group['elements'] );
        $member = $item_group['elements'][0];
        $this->assertSame( 'divi/team-member', $member['name'] );
        $this->assertSame( 'Jeanel Christina', $member['settings']['name']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Senior Developer', $member['settings']['position']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://x.test/jc.jpg', $member['settings']['image']['innerContent']['desktop']['value']['url'] );
        $this->assertSame( [ 'facebookUrl' => 'https://x.test/fb' ], $member['settings']['social']['innerContent']['desktop']['value'] );

        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // The real widget's own bug (team-slider.php render_team_slider_one()):
    // every social <a href> reads $item['link_one']['url'] regardless of which
    // icon it's for — social_icon_02/03/04 all point at link_one's URL, not
    // their own link_two/three/four. Matched here for visual parity with what
    // the real plugin (v4.2.2) actually renders.
    public function test_team_slider_reproduces_widgets_own_link_one_bug(): void {
        [ $block ] = $this->convert( 'wfc--team-slider', [
            'team_slides' => [
                [
                    'title' => 'A', 'helo_show_social' => 'yes',
                    'social_icon_01' => [ 'value' => 'fab fa-facebook' ], 'link_one' => [ 'url' => 'https://x.test/fb' ],
                    'social_icon_02' => [ 'value' => 'fab fa-twitter' ], 'link_two' => [ 'url' => 'https://x.test/tw' ],
                ],
            ],
        ] );

        $social = $block['elements'][0]['elements'][0]['settings']['social']['innerContent']['desktop']['value'];
        $this->assertSame( 'https://x.test/fb', $social['facebookUrl'] );
        $this->assertSame( 'https://x.test/fb', $social['twitterUrl'] );
    }

    // -------------------------------------------------------------------------
    // wcf--a-pricing-table (widgets/advance-pricing-table/advance-pricing-table.php,
    // its content controls shared across all skins — skin-pricing-table-*.php
    // only vary the style controls/markup) — same divi/pricing-tables +
    // divi/pricing-table target PriceTableConverter already uses for
    // Elementor's native Price Table widget, with this widget's own
    // 'currency_symbol' enum (mapped to the same HTML-entity table
    // skin-pricing-table-base.php's get_currency_symbol() uses) and
    // Aaeaddon_Button_Trait's 'btn_text'/'btn_link' instead of
    // 'button_text'/'button_url'.
    // -------------------------------------------------------------------------

    public function test_advance_pricing_table_builds_pricing_table(): void {
        [ $block, $result ] = $this->convert( 'wcf--a-pricing-table', [
            'title' => 'Pro Plan', 'title_tag' => 'h3', 'sub_title' => 'For growing teams',
            'currency_symbol' => 'dollar', 'price' => '9.99', 'period' => 'Monthly',
            'features_list' => [
                [ 'item_text' => 'Starter Pack Included', 'selected_item_icon' => [ 'value' => 'fas fa-check' ] ],
                [ 'item_text' => 'Venue Booking' ],
            ],
            'btn_text' => 'Choose Plan', 'btn_link' => [ 'url' => 'https://x.test/choose' ],
        ] );

        $this->assertSame( 'divi/pricing-tables', $block['name'] );
        $table = $block['elements'][0];
        $this->assertSame( 'divi/pricing-table', $table['name'] );
        $s = $table['settings'];
        $this->assertSame( 'Pro Plan', $s['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'For growing teams', $s['subtitle']['innerContent']['desktop']['value'] );
        $this->assertSame( '9.99', $s['price']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'currency' => '$ ', 'per' => 'Monthly' ], $s['currencyFrequency']['innerContent']['desktop']['value'] );
        $this->assertSame( "Starter Pack Included\nVenue Booking", $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Choose Plan', $s['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/choose', $s['button']['innerContent']['desktop']['value']['linkUrl'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_advance_pricing_table_custom_currency_symbol(): void {
        [ $block ] = $this->convert( 'wcf--a-pricing-table', [
            'title' => 'Custom', 'currency_symbol' => 'custom', 'currency_symbol_custom' => 'CHF ',
            'price' => '49',
        ] );

        $this->assertSame( 'CHF ', $block['elements'][0]['settings']['currencyFrequency']['innerContent']['desktop']['value']['currency'] );
    }

    // -------------------------------------------------------------------------
    // wcf--contact-form-7 (contact-form-7.php) — its only real content control
    // is 'contact_form_id' (a wpcf7_contact_form post ID), the same thing
    // EaelContactForm7Converter already reads as 'contact_form_list' (or the
    // older 'eael_contact_form_id') to build divi/contact-form-7's
    // form.advanced.formId. Reused directly with 'contact_form_id' added as a
    // third fallback key.
    // -------------------------------------------------------------------------

    public function test_contact_form_7_reads_its_own_form_id_key(): void {
        [ $block, $result ] = $this->convert( 'wcf--contact-form-7', [ 'contact_form_id' => '42' ] );

        $this->assertSame( 'divi/contact-form-7', $block['name'] );
        $this->assertSame( 42, $block['settings']['form']['advanced']['formId']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--filterable-slider (filterable-slider.php) — a project carousel with
    // JS category filtering. Its 'project_items' repeater (image/title/
    // subtitle/description/link) is the same shape WcfImageBoxSliderConverter
    // already builds per-slide, so it targets divi/group-carousel the same
    // way. Each item's own 'project_item_filter_name' and the widget's
    // separate 'filter_items' repeater only drive the JS filter buttons —
    // logged as not carried over, since Divi's carousel has no filtering.
    // -------------------------------------------------------------------------

    public function test_filterable_slider_builds_group_carousel(): void {
        [ $block, $result ] = $this->convert( 'wcf--filterable-slider', [
            'title_tag' => 'h3',
            'project_items' => [
                [
                    'project_item_filter_name' => 'Construction',
                    'project_image' => [ 'url' => 'https://x.test/a.jpg', 'alt' => 'A' ],
                    'title' => 'Alexa Complex', 'subtitle' => 'Construction',
                    'description' => '<p>A big project.</p>', 'link' => [ 'url' => 'https://x.test/a' ],
                ],
            ],
        ] );

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $item_group = $block['elements'][0];
        $this->assertSame( 'divi/group', $item_group['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $item_group['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/text' ], $names );
        $this->assertSame( 'Alexa Complex', $item_group['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // wcf--nested-slider (nested-slider.php) — different from every other
    // carousel widget here: 'carousel_items' is a Control_Nested_Repeater
    // (frontend_available), so each slide's body is a full container of real
    // Elementor elements in the widget's own top-level 'elements' array,
    // index-aligned with the repeater — not settings fields. Each slide's own
    // 'slide_title' is confirmed from source (nested-slider.php's render/
    // data-binding) to be purely an editor-panel label, never printed on the
    // frontend, so it's dropped rather than fabricated as visible content.
    // Real child elements are recursively converted (convertChildren()) and
    // nested inside a divi/group per slide, inside divi/group-carousel.
    // -------------------------------------------------------------------------

    public function test_nested_slider_converts_real_nested_elements(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'ns1',
            'elType'     => 'widget',
            'widgetType' => 'wcf--nested-slider',
            'settings'   => [
                'slides_to_show' => 1,
                'carousel_items' => [
                    [ 'slide_title' => 'Slide #1' ],
                    [ 'slide_title' => 'Slide #2' ],
                ],
            ],
            'elements'   => [
                [
                    'id' => 'body1', 'elType' => 'container', 'settings' => [], 'elements' => [
                        [ 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'wcf--text', 'settings' => [ 'text' => '<p>First slide body.</p>' ], 'elements' => [] ],
                    ],
                ],
                [
                    'id' => 'body2', 'elType' => 'container', 'settings' => [], 'elements' => [
                        [ 'id' => 'h2', 'elType' => 'widget', 'widgetType' => 'wcf--text', 'settings' => [ 'text' => '<p>Second slide body.</p>' ], 'elements' => [] ],
                    ],
                ],
            ],
        ] ] );

        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], 'widget wcf--nested-slider' );
        $block = $result['divi']['elements'][0];

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $slide1 = $block['elements'][0];
        $this->assertSame( 'divi/group', $slide1['name'] );
        $this->assertSame( 'divi/text', $slide1['elements'][0]['name'] );
        $this->assertSame( '<p>First slide body.</p>', $slide1['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Second slide body.</p>', $block['elements'][1]['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--image-compare (image-compare.php) — a JS drag-to-reveal before/after
    // image slider. Divi has no interactive compare-slider module, so the
    // drag behaviour itself is logged as not carried over; the two images
    // (and captions, if shown) still carry over as a divi/group of two
    // divi/image (+ divi/text caption) pairs, rather than an empty
    // placeholder that would drop both images from the page entirely.
    // -------------------------------------------------------------------------

    public function test_image_compare_builds_group_with_both_images(): void {
        [ $block, $result ] = $this->convert( 'wcf--image-compare', [
            'before_image' => [ 'url' => 'https://x.test/before.jpg', 'alt' => 'Before' ],
            'after_image'  => [ 'url' => 'https://x.test/after.jpg', 'alt' => 'After' ],
            'show_caption' => 'yes',
            'before_caption' => 'Old kitchen', 'after_caption' => 'New kitchen',
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/text', 'divi/image', 'divi/text' ], $names );
        $this->assertSame( 'https://x.test/before.jpg', $block['elements'][0]['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( 'Old kitchen', $block['elements'][1]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://x.test/after.jpg', $block['elements'][2]['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( 'New kitchen', $block['elements'][3]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    public function test_image_compare_no_captions_when_hidden(): void {
        [ $block ] = $this->convert( 'wcf--image-compare', [
            'before_image' => [ 'url' => 'https://x.test/before.jpg' ],
            'after_image'  => [ 'url' => 'https://x.test/after.jpg' ],
        ] );

        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/image' ], $names );
    }

    // -------------------------------------------------------------------------
    // aae--image-hotspot (image-hotspot.php; get_name() returns
    // 'aae--image-hotspot', not 'wcf--image-hotspot') — a base image with
    // absolutely-positioned hover-tooltip pins ('hsp_list' repeater). Divi has
    // no absolute-hotspot-with-tooltip module, so the pin positions/tooltip
    // interactivity are logged as not carried over; the base image and each
    // hotspot's own visible text (its 'hsp_text' label, and 'tlp_content' for
    // tooltip-type hotspots) still carry over as a divi/group of the image
    // plus one divi/text per hotspot with something to show.
    // -------------------------------------------------------------------------

    public function test_image_hotspot_builds_group_with_image_and_hotspot_text(): void {
        [ $block, $result ] = $this->convert( 'aae--image-hotspot', [
            'hsp_image' => [ 'url' => 'https://x.test/floorplan.jpg', 'alt' => 'Floor plan' ],
            'hsp_list'  => [
                [ 'hsp_layout' => 'text', 'hsp_text' => 'Kitchen', 'tooltip_type' => 'tooltip', 'tlp_content' => '<p>Modern kitchen.</p>' ],
                [ 'hsp_layout' => 'dot', 'tooltip_type' => 'link', 'tlp_link' => [ 'url' => 'https://x.test/bath' ] ],
            ],
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/text' ], $names );
        $this->assertSame( 'https://x.test/floorplan.jpg', $block['elements'][0]['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertStringContainsString( 'Kitchen', $block['elements'][1]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'Modern kitchen.', $block['elements'][1]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // wcf--mailchimp (widgets/mailchimp/mailchimp.php) — a live email-signup
    // form wired to a Mailchimp API key + audience/list ID via the plugin's
    // own AJAX subscribe endpoint. There's no static settings-to-settings
    // mapping possible: Divi's own Email Optin module requires its own
    // separately-connected Mailchimp account (Divi's Bloom/Email-Optin account
    // system), not an arbitrary API key pasted into widget settings, so
    // there's no way to carry the connection over even approximately.
    // Explicitly registered to GenericFallbackConverter (counted as a
    // deliberate placeholder, not a hole nobody mapped) rather than left to
    // the registry's silent unregistered-widget fallback.
    // -------------------------------------------------------------------------

    public function test_mailchimp_has_no_static_equivalent(): void {
        [ $block, $result ] = $this->convert( 'wcf--mailchimp', [
            'mailchimp_api' => 'fake-key-us1', 'mailchimp_lists' => 'abc123',
        ] );

        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertNotEmpty( $result['report']['warnings'] );
        $this->assertSame( 1, $result['report']['converted']['code'] ?? 0 );
    }

    // -------------------------------------------------------------------------
    // aae--advanced-button (button-pro.php; get_name() returns
    // 'aae--advanced-button', not the guessable 'wcf--button-pro') — its
    // 'btn_text'/'btn_link' are the exact same fallback keys ButtonConverter
    // already reads for the standalone wcf--button widget. A real, full reuse
    // (not a "no clean equivalent" widget, despite the fancy hover-animation
    // styles it also carries).
    // -------------------------------------------------------------------------

    public function test_advanced_button_reuses_button_converter(): void {
        [ $block, $result ] = $this->convert( 'aae--advanced-button', [
            'btn_text' => 'Get Started', 'btn_link' => [ 'url' => 'https://x.test/start' ],
            'btn_style' => '3', 'btn_icon' => [ 'value' => 'fas fa-arrow-right' ], 'btn_icon_position' => 'row',
        ] );

        $this->assertSame( 'divi/button', $block['name'] );
        $this->assertSame( 'Get Started', $block['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/start', $block['settings']['button']['innerContent']['desktop']['value']['linkUrl'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--typewriter (typewriter.php) — a static prefix ('typewriter_normal_text')
    // followed by a JS-cycling list of words ('typewriter_animated_text').
    // Divi's heading module can't animate through a word list, so the cycling
    // itself is logged as not carried over; the static prefix plus the first
    // cycling word still convert to a real divi/heading, matching what's
    // visible on page load rather than an empty placeholder.
    // -------------------------------------------------------------------------

    public function test_typewriter_builds_heading_from_prefix_and_first_word(): void {
        [ $block, $result ] = $this->convert( 'wcf--typewriter', [
            'typewriter_normal_text' => 'A Web',
            'typewriter_animated_text' => [
                [ 'list_text' => 'Designer' ], [ 'list_text' => 'Developer' ],
            ],
            'html_tag' => 'h1',
        ] );

        $this->assertSame( 'divi/heading', $block['name'] );
        $this->assertSame( 'A Web Designer', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h1', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // wcf--floating-elements (floating-elements.php) — a repeater of images
    // ('wcf_floating_elements') each absolutely positioned with a parallax/
    // float animation. Divi has no free-floating absolute-position image
    // module, so the positioning and animation are logged as not carried
    // over; each image itself still converts as a divi/image in a divi/group,
    // rather than vanishing along with the positioning.
    // -------------------------------------------------------------------------

    public function test_floating_elements_builds_group_of_images(): void {
        [ $block, $result ] = $this->convert( 'wcf--floating-elements', [
            'wcf_floating_elements' => [
                [ 'floating_image' => [ 'url' => 'https://x.test/star.png', 'alt' => 'Star' ] ],
                [ 'floating_image' => [ 'url' => 'https://x.test/cloud.png' ] ],
            ],
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/image' ], $names );
        $this->assertSame( 'https://x.test/star.png', $block['elements'][0]['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // aae--clickdrop (clickdrop.php) — a login/account-state dropdown menu
    // (different content shown logged-in vs logged-out, via is_user_logged_in()
    // at render time). That state switch has no static equivalent and is
    // logged as not carried over; the menu's own repeater items
    // (menu_title/menu_link/menu_icon) still convert to divi/icon-list-item
    // children, the same shape wcf--one-page-nav already uses.
    // -------------------------------------------------------------------------

    public function test_clickdrop_builds_icon_list_from_menu_items(): void {
        [ $block, $result ] = $this->convert( 'aae--clickdrop', [
            'login_label' => 'Login', 'logged_label' => 'Your Account',
            'menus_url' => [
                [ 'menu_title' => 'Saved', 'menu_link' => [ 'url' => 'https://x.test/saved' ], 'menu_icon' => [ 'value' => 'fas fa-heart' ] ],
            ],
        ] );

        $this->assertSame( 'divi/icon-list', $block['name'] );
        $this->assertSame( 'divi/icon-list-item', $block['elements'][0]['name'] );
        $this->assertSame( 'Saved', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://x.test/saved', $block['elements'][0]['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // aae--notification (notification.php; get_name() returns
    // 'aae--notification', not 'wcf--notification') — a dismissible top-bar
    // notice: fully static text + button. The localStorage-based dismiss
    // behaviour has no Divi equivalent (logged as not carried over); the
    // notice text and button convert to a real divi/group.
    // -------------------------------------------------------------------------

    public function test_notification_builds_group_with_text_and_button(): void {
        [ $block, $result ] = $this->convert( 'aae--notification', [
            'notify_text' => 'All plans have 30% OFF this week.',
            'btn_text' => 'Claim', 'btn_link' => [ 'url' => 'https://x.test/claim' ],
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $block['elements'] );
        $this->assertSame( [ 'divi/text', 'divi/button' ], $names );
        $this->assertSame( 'All plans have 30% OFF this week.', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Claim', $block['elements'][1]['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
        $this->assertNotEmpty( $result['report']['not_carried_over'] );
    }

    // -------------------------------------------------------------------------
    // aae--weather (weather.php; get_name() returns 'aae--weather', not
    // 'wcf--weather') — pulls live weather data from a third-party API at
    // render time based on a configured location; there is no static content
    // in the widget's settings at all to carry over.
    // -------------------------------------------------------------------------

    public function test_weather_has_no_static_equivalent(): void {
        [ $block, $result ] = $this->convert( 'aae--weather', [ 'location' => 'New York' ] );

        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertSame( 1, $result['report']['converted']['code'] ?? 0 );
    }

    // -------------------------------------------------------------------------
    // Dynamic post-context widgets (widgets/post-*.php, search-form.php) —
    // these read the current post/page at render time rather than storing
    // static content in settings, so they map to Divi's own dynamic
    // post-context modules with little more than a tag/style translation.
    // -------------------------------------------------------------------------

    // wcf--blog--post--title (post-title.php) targets the same divi/post-title
    // as HFE's HfePageTitleConverter, but as its own class reading its own
    // 'header_size' key — see WcfBlogPostTitleConverter's docblock for why
    // it isn't just a fallback added to HfePageTitleConverter.
    public function test_blog_post_title_reuses_page_title_converter(): void {
        [ $block, $result ] = $this->convert( 'wcf--blog--post--title', [ 'header_size' => 'h1' ] );

        $this->assertSame( 'divi/post-title', $block['name'] );
        $this->assertSame( 'h1', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // wcf--theme-post-content (post-content.php) has no content settings at
    // all — it always prints the current post's the_content().
    public function test_theme_post_content_has_no_content_settings(): void {
        [ $block, $result ] = $this->convert( 'wcf--theme-post-content', [] );

        $this->assertSame( 'divi/post-content', $block['name'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // wcf--blog--post--comment (post-comment.php) likewise has no content
    // settings — it always prints the current post's comment_form()/list.
    public function test_blog_post_comment_has_no_content_settings(): void {
        [ $block, $result ] = $this->convert( 'wcf--blog--post--comment', [ 'theme_comment_style' => 'yes' ] );

        $this->assertSame( 'divi/comments', $block['name'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // wcf--blog--search--form (search-form.php) reuses SearchConverter's
    // divi/search target — its 'placeholder'/'button_text' keys already
    // match exactly; its extra AJAX/date/category filter presets have no
    // Divi equivalent (divi/search is a plain search box).
    public function test_blog_search_form_reuses_search_converter(): void {
        [ $block, $result ] = $this->convert( 'wcf--blog--search--form', [
            'placeholder' => 'Search articles…', 'button_text' => 'Go',
            'preset' => 'style-classic', 'show_search_filter' => 'yes',
        ] );

        $this->assertSame( 'divi/search', $block['name'] );
        $this->assertSame( 'Search articles…', $block['settings']['searchPlaceholder']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // wcf--blog--post--paginate (post-paginate.php) — prev/next post nav.
    // Its 'prev_title'/'next_title' map to divi/post-nav's
    // links.advanced.prevText/nextText, and 'enable_prev'/'enable_next' to
    // showPrev/showNext.
    public function test_blog_post_paginate_builds_post_nav(): void {
        [ $block, $result ] = $this->convert( 'wcf--blog--post--paginate', [
            'enable_prev' => 'yes', 'prev_title' => 'Previous Post',
            'enable_next' => 'yes', 'next_title' => 'Next Post',
        ] );

        $this->assertSame( 'divi/post-nav', $block['name'] );
        $links = $block['settings']['links']['advanced'];
        $this->assertSame( 'Previous Post', $links['prevText']['desktop']['value'] );
        $this->assertSame( 'Next Post', $links['nextText']['desktop']['value'] );
        $this->assertSame( 'on', $links['showPrev']['desktop']['value'] );
        $this->assertSame( 'on', $links['showNext']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_blog_post_paginate_hides_prev_when_disabled(): void {
        [ $block ] = $this->convert( 'wcf--blog--post--paginate', [ 'enable_prev' => '' ] );

        $this->assertSame( 'off', $block['settings']['links']['advanced']['showPrev']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // Remaining widgets with no safe static or dynamic-context equivalent —
    // each explicitly registered to GenericFallbackConverter (a deliberate
    // placeholder decision, not a hole nobody looked at), real get_name()
    // confirmed from source for each:
    //
    // - wcf--blog--post--excerpt, wcf--blog--post--meta-info,
    //   wcf--theme-post-image: post-context fields with no single matching
    //   Divi module (post-excerpt.php/post-meta-info.php/post-feature-image.php
    //   → 'wcf--theme-post-image' — get_name() doesn't match the file name).
    // - aae--post-rating, aae--post-rating-form, aaeaddon-post-reactions,
    //   wcf--blog--post--social-share: interactive widgets needing their own
    //   stored per-post meta (ratings/reactions/share counts) — no static or
    //   dynamic-context conversion is meaningful without that data store.
    // - wcf--posts-timeline, aae--video-posts-tab: bespoke query-driven
    //   layouts with no Divi module shape.
    // - wcf--current-date, wcf--blog--archive--title,
    //   wcf--blog--search--result-message, wcf--blog--search--query: small
    //   dynamic text fragments (today's date, the current archive/search
    //   term) with no matching Divi dynamic-content tag.
    // - wcf--posts, aae--loop-grid, grid-hover-posts, category-showcase,
    //   wcf--banner-posts, wcf--feature-posts: each a large (1,000+ line)
    //   WP_Query-driven grid/carousel widget. divi/blog is a plausible target
    //   for some of these, but confirming each widget's real
    //   category/post-type/order query-control names (not just guessing) is
    //   its own multi-widget pass, flagged as a bigger lift in the original
    //   session plan — left as placeholders here rather than shipping an
    //   unverified query mapping that could silently show the wrong posts.
    // - aae--category-slider: pulls live WordPress category terms via
    //   get_categories() (found early this session, see class docblock notes
    //   from that pass) — dynamic WP-data, not a settings-driven carousel.
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // wcf--timeline (timeline.php) — found while cross-checking every widget
    // file's real get_name() against the registry: not in the original
    // session plan's 69-widget list at all. Fully static (a repeater of
    // image/date/title/subtitle/description items, no live data), so it
    // converts for real rather than joining the placeholder batch above —
    // same free-form-container shape as wcf--image-accordion.
    // -------------------------------------------------------------------------

    public function test_timeline_builds_group_of_step_groups(): void {
        [ $block, $result ] = $this->convert( 'wcf--timeline', [
            'timelines' => [
                [
                    'timeline_image' => [ 'url' => 'https://x.test/ny.jpg', 'alt' => 'NY' ],
                    'timeline_date' => 'Jan 01, 2021', 'timeline_title' => 'Journey Started at New York',
                    'timeline_sub' => 'Designer', 'timeline_desc' => 'It all began here.',
                ],
            ],
        ] );

        $this->assertSame( 'divi/group', $block['name'] );
        $step = $block['elements'][0];
        $this->assertSame( 'divi/group', $step['name'] );
        $names = array_map( static fn( $c ) => $c['name'], $step['elements'] );
        $this->assertSame( [ 'divi/image', 'divi/heading', 'divi/text' ], $names );
        $this->assertSame( 'Journey Started at New York', $step['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
        $text_value = $step['elements'][2]['settings']['content']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( 'Jan 01, 2021', $text_value );
        $this->assertStringContainsString( 'Designer', $text_value );
        $this->assertStringContainsString( 'It all began here.', $text_value );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_remaining_dynamic_and_bigger_lift_widgets_are_explicit_placeholders(): void {
        $widget_types = [
            'wcf--blog--post--excerpt', 'wcf--blog--post--meta-info', 'wcf--theme-post-image',
            'aae--post-rating', 'aae--post-rating-form', 'aaeaddon-post-reactions',
            'wcf--blog--post--social-share', 'wcf--posts-timeline', 'aae--video-posts-tab',
            'wcf--current-date', 'wcf--blog--archive--title',
            'wcf--blog--search--result-message', 'wcf--blog--search--query',
            'wcf--posts', 'aae--loop-grid', 'grid-hover-posts', 'category-showcase',
            'wcf--banner-posts', 'wcf--feature-posts', 'aae--category-slider',
        ];

        foreach ( $widget_types as $widget_type ) {
            [ $block, $result ] = $this->convert( $widget_type, [] );

            $this->assertSame( 'divi/code', $block['name'], "{$widget_type} should be an explicit placeholder" );
            $this->assertSame( 1, $result['report']['converted']['code'] ?? 0, "{$widget_type} should count as a deliberate placeholder, not an unregistered widget" );
        }
    }
}
