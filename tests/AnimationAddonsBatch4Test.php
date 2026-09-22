<?php
// tests/AnimationAddonsBatch4Test.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "Animation Addons for Elementor" — Batch 4: site chrome, breadcrumbs,
 * single-field heading/text widgets and the single (non-carousel) team member
 * widget. Verified against the free plugin's real source, version 4.2.2
 * (references/animation-addons-for-elementor.4.2.2.zip): site-logo.php,
 * nav-menu/nav-menu.php, breadcrumbs.php, animated-heading.php,
 * animated-text.php, animated-title.php, team.php.
 */
final class AnimationAddonsBatch4Test extends TestCase {

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
    // wcf--site-logo (site-logo.php) — its own MEDIA control is 'image', not
    // HfeSiteLogoConverter's 'custom_image'/'retina_image'/'logo'.
    // -------------------------------------------------------------------------

    public function test_site_logo_reads_its_own_image_control(): void {
        [ $block, $result ] = $this->convert( 'wcf--site-logo', [
            'image' => [ 'url' => 'https://x.test/logo.png' ],
        ] );

        $this->assertSame( 'divi/image', $block['name'] );
        $this->assertSame( 'https://x.test/logo.png', $block['settings']['image']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--nav-menu (nav-menu/nav-menu.php) — its 'nav_menu' key was already a
    // fallback HfeNavigationMenuConverter read for HFE's own older exports.
    // -------------------------------------------------------------------------

    public function test_nav_menu_reads_its_nav_menu_key(): void {
        $GLOBALS['__test_nav_menus'] = [];

        [ $block, $result ] = $this->convert( 'wcf--nav-menu', [ 'nav_menu' => '9' ] );

        $this->assertSame( 'divi/menu', $block['name'] );
        $this->assertSame( '9', $block['settings']['menu']['advanced']['menuId']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--breadcrumbs (breadcrumbs.php) — no real content settings at all;
    // 'yoast_seo' is a real toggle, 'warning_text' a static notice.
    // -------------------------------------------------------------------------

    public function test_breadcrumbs_has_no_unmapped_noise(): void {
        [ $block, $result ] = $this->convert( 'wcf--breadcrumbs', [
            'yoast_seo' => 'yes', 'html_tag' => 'div', 'br_separator' => '/',
            'text_color' => '#000', 'link_color' => '#111', 'link_hover_color' => '#222',
        ] );

        $this->assertSame( 'divi/breadcrumbs', $block['name'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--animated-heading (animated-heading.php) — own heading/heading_tag/
    // heading_link keys; wcf--title (animated-title.php) already matches
    // HeadingConverter's native title/header_size/link exactly.
    // -------------------------------------------------------------------------

    public function test_animated_heading_reads_its_own_keys(): void {
        [ $block, $result ] = $this->convert( 'wcf--animated-heading', [
            'heading'      => 'Hey! It’s Rob',
            'heading_tag'  => 'h1',
            'heading_link' => [ 'url' => 'https://x.test/about', 'is_external' => 'on' ],
        ] );

        $this->assertSame( 'divi/heading', $block['name'] );
        $this->assertSame( "Hey! It’s Rob", $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h1', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
        $this->assertSame( 'https://x.test/about', $block['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_title_widget_converts_with_no_new_converter_needed(): void {
        [ $block ] = $this->convert( 'wcf--title', [
            'title'       => 'Ceramic Studio',
            'header_size' => 'h2',
            'link'        => [ 'url' => 'https://x.test/studio' ],
        ] );

        $this->assertSame( 'Ceramic Studio', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://x.test/studio', $block['settings']['module']['advanced']['link']['desktop']['value']['url'] );
    }

    // -------------------------------------------------------------------------
    // wcf--text (animated-text.php) — WYSIWYG under 'text'.
    // -------------------------------------------------------------------------

    public function test_text_widget_reads_its_own_text_key(): void {
        [ $block, $result ] = $this->convert( 'wcf--text', [ 'text' => '<p>Working globally since 2009.</p>' ] );

        $this->assertSame( 'divi/text', $block['name'] );
        $this->assertSame( '<p>Working globally since 2009.</p>', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--team (team.php) — a single member, not a carousel.
    // -------------------------------------------------------------------------

    public function test_team_member_reads_name_role_image_and_social(): void {
        [ $block, $result ] = $this->convert( 'wcf--team', [
            'member_name'        => 'Hannah Moore',
            'member_designation' => 'Founder',
            'member_description' => 'Ran a design studio.',
            'member_image'       => [ 'url' => 'https://x.test/h.jpg', 'alt' => 'Hannah' ],
            'show_social_icons'  => 'yes',
            'team_social_icons'  => [
                [ 'social_icon' => [ 'value' => 'fab fa-linkedin', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/in/h' ] ],
            ],
        ] );

        $this->assertSame( 'divi/team-member', $block['name'] );
        $s = $block['settings'];
        $this->assertSame( 'Hannah Moore', $s['name']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Founder', $s['position']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Ran a design studio.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'url' => 'https://x.test/h.jpg', 'alt' => 'Hannah' ], $s['image']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'linkedinUrl' => 'https://www.linkedin.com/in/h' ], $s['social']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_team_member_social_hidden_by_default(): void {
        [ $block ] = $this->convert( 'wcf--team', [
            'member_name'       => 'Sam Ortiz',
            'team_social_icons' => [
                [ 'social_icon' => [ 'value' => 'fab fa-linkedin' ], 'link' => [ 'url' => 'https://x.test/s' ] ],
            ],
        ] );

        $this->assertArrayNotHasKey( 'social', $block['settings'] );
    }
}
