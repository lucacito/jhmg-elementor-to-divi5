<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Parsers\ElementorImportParser;

/**
 * Integration test against the real Elementor export used as the project reference
 * (references/elementor-88158-2026-06-13.json).
 *
 * These tests verify the full conversion pipeline end-to-end: parser → engine →
 * output structure.  They are intentionally coarse-grained (structure + key
 * content) rather than exhaustive — the goal is to catch regressions that would
 * be invisible from unit tests alone.
 */
final class ReferencePageConversionTest extends TestCase {

    private static array $result;
    private static array $report;

    public static function setUpBeforeClass(): void {
        $file   = __DIR__ . '/../references/elementor-88158-2026-06-13.json';
        $parser = new ElementorImportParser();
        $items  = $parser->parse( $file, basename( $file ) );

        $engine        = new ConverterEngine();
        self::$result  = $engine->convert( $items[0]['elements'] );
        self::$report  = self::$result['report'];
    }

    // -------------------------------------------------------------------------
    // High-level sanity
    // -------------------------------------------------------------------------

    public function test_conversion_produces_no_unsupported_elements(): void {
        $this->assertSame( [], self::$result['unsupported'] );
    }

    public function test_conversion_produces_no_skipped_settings(): void {
        $skipped = self::$report['skipped_settings'];
        $this->assertEmpty(
            $skipped,
            'Unexpected skipped settings: ' . implode( ', ', $skipped )
        );
    }

    public function test_conversion_produces_expected_section_count(): void {
        $sections = self::$result['divi']['elements'];
        $this->assertCount( 6, $sections );
        foreach ( $sections as $section ) {
            $this->assertSame( 'divi/section', $section['name'] );
        }
    }

    public function test_conversion_counts_all_element_types(): void {
        $converted = self::$report['converted'];
        $this->assertSame( 19, $converted['text'],    'text modules' );
        $this->assertSame( 10, $converted['button'],  'button modules' );
        $this->assertSame( 31, $converted['column'],  'columns' );
        $this->assertSame( 6,  $converted['section'], 'sections' );
        $this->assertSame( 8,  $converted['heading'], 'headings' );
        $this->assertSame( 6,  $converted['blurb'],   'blurbs (icon-boxes)' );
        $this->assertSame( 1,  $converted['code'],    'code (menu-anchor)' );
    }

    public function test_only_structural_warnings_emitted(): void {
        $warnings = self::$report['warnings'];
        // Expect exactly the 3 known structural warnings:
        //   - 2 genuinely empty source columns
        //   - 1 background-lift log from SectionConverter
        $this->assertCount( 3, $warnings );

        $empty_col_warnings = array_filter( $warnings, fn( $w ) => str_contains( $w, 'Empty column' ) );
        $this->assertCount( 2, $empty_col_warnings, 'Expected 2 empty-column warnings' );

        $lift_warnings = array_filter( $warnings, fn( $w ) => str_contains( $w, 'lifted background' ) );
        $this->assertCount( 1, $lift_warnings, 'Expected 1 background-lift warning' );
    }

    // -------------------------------------------------------------------------
    // Section 1 — Hero
    // -------------------------------------------------------------------------

    public function test_hero_section_has_background_image(): void {
        $section = self::$result['divi']['elements'][0];
        $bg_url  = $section['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] ?? '';
        $this->assertStringContainsString( 'website-banner', $bg_url );
    }

    public function test_hero_section_has_button_with_url(): void {
        $btn = $this->findFirst( self::$result['divi']['elements'][0], 'divi/button' );
        $this->assertNotNull( $btn, 'Hero button not found' );
        $val = $btn['settings']['button']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( 'complimentary call', strtolower( $val['text'] ) );
        $this->assertNotEmpty( $val['linkUrl'] );
    }

    public function test_hero_button_has_background_color(): void {
        $btn = $this->findFirst( self::$result['divi']['elements'][0], 'divi/button' );
        $color = $btn['settings']['module']['decoration']['background']['desktop']['value']['color'] ?? '';
        $this->assertNotEmpty( $color, 'Hero button background color should be mapped to module background' );
    }

    public function test_hero_button_has_text_padding(): void {
        $btn = $this->findFirst( self::$result['divi']['elements'][0], 'divi/button' );
        $padding = $btn['settings']['button']['decoration']['spacing']['desktop']['value']['padding'] ?? null;
        $this->assertNotNull( $padding, 'Button text_padding should be mapped to Divi button spacing' );
        $this->assertSame( '20px', $padding['top'] );
        $this->assertSame( '25px', $padding['right'] );
    }

    // -------------------------------------------------------------------------
    // Section 2 — Why Firms Partner
    // -------------------------------------------------------------------------

    public function test_why_firms_heading_is_correct(): void {
        $heading = $this->findFirst( self::$result['divi']['elements'][1], 'divi/heading' );
        $this->assertNotNull( $heading );
        $text = $heading['settings']['title']['innerContent']['desktop']['value'];
        $this->assertSame( 'Why Firms Partner with EMI', $text );
    }

    public function test_why_firms_has_three_blurbs(): void {
        $section = self::$result['divi']['elements'][1];
        $blurbs  = $this->findAll( $section, 'divi/blurb' );
        $this->assertCount( 3, $blurbs );
    }

    public function test_blurb_titles_correct(): void {
        $section = self::$result['divi']['elements'][1];
        $blurbs  = $this->findAll( $section, 'divi/blurb' );

        $expected = [ 'Industry-Specific Expertise', 'Behavior-Based Learning', 'Real Results' ];
        foreach ( $expected as $i => $title ) {
            $val = $blurbs[ $i ]['settings']['title']['innerContent']['desktop']['value'] ?? null;
            $this->assertSame( [ 'text' => $title ], $val, "Blurb $i title mismatch" );
        }
    }

    public function test_blurb_descriptions_not_empty(): void {
        $section = self::$result['divi']['elements'][1];
        $blurbs  = $this->findAll( $section, 'divi/blurb' );
        foreach ( $blurbs as $i => $blurb ) {
            $desc = $blurb['settings']['content']['innerContent']['desktop']['value'] ?? '';
            $this->assertNotEmpty( $desc, "Blurb $i description should not be empty" );
        }
    }

    // -------------------------------------------------------------------------
    // Section 3 — What EMI Offers (complex nested)
    // -------------------------------------------------------------------------

    public function test_offers_section_has_blue_background(): void {
        $section = self::$result['divi']['elements'][2];
        $color   = $section['settings']['module']['decoration']['background']['desktop']['value']['color'] ?? '';
        $this->assertSame( '#E9F6FF', $color );
    }

    public function test_offers_section_has_icon_boxes_with_overlay(): void {
        $section = self::$result['divi']['elements'][2];
        $groups  = $this->findAll( $section, 'divi/group' );
        $this->assertNotEmpty( $groups, 'Icon-box columns should be wrapped in divi/group with overlay bg' );

        // Overlay is an rgba color mapped from background_overlay_color.
        $color = $groups[0]['settings']['module']['decoration']['background']['desktop']['value']['color'] ?? '';
        $this->assertStringStartsWith( 'rgba(', $color, 'Group should carry the overlay rgba background color' );
    }

    public function test_offers_section_explore_button_has_correct_text(): void {
        $section = self::$result['divi']['elements'][2];
        $btns    = $this->findAll( $section, 'divi/button' );
        $texts   = array_map(
            fn( $b ) => strtolower( $b['settings']['button']['innerContent']['desktop']['value']['text'] ?? '' ),
            $btns
        );
        $this->assertContains( 'explore corporate trainings', $texts );
        $this->assertContains( 'get certified', $texts );
        $this->assertContains( 'see upcoming events', $texts );
    }

    // -------------------------------------------------------------------------
    // Section 4 — Dark quote / speaker image
    // -------------------------------------------------------------------------

    public function test_quote_section_has_background_image(): void {
        $section = self::$result['divi']['elements'][3];
        $bg_url  = $section['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] ?? '';
        $this->assertStringContainsString( 'DSC05809', $bg_url );
    }

    // -------------------------------------------------------------------------
    // Section 5 — Ready to Take Next Step
    // -------------------------------------------------------------------------

    public function test_next_step_section_has_background_image(): void {
        $section = self::$result['divi']['elements'][4];
        $bg_url  = $section['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] ?? '';
        $this->assertStringContainsString( 'website-banner', $bg_url );
    }

    public function test_next_step_has_two_cta_buttons(): void {
        $section = self::$result['divi']['elements'][4];
        $btns    = $this->findAll( $section, 'divi/button' );
        $this->assertCount( 2, $btns );

        $texts = array_map(
            fn( $b ) => strtolower( trim( $b['settings']['button']['innerContent']['desktop']['value']['text'] ?? '' ) ),
            $btns
        );
        $this->assertContains( 'book a discovery call', $texts );
        $this->assertContains( 'get certified', $texts );
    }

    // -------------------------------------------------------------------------
    // Section 6 — Insights / Newsletter
    // -------------------------------------------------------------------------

    public function test_insights_section_has_menu_anchor(): void {
        $section = self::$result['divi']['elements'][5];
        $code    = $this->findFirst( $section, 'divi/code' );
        $this->assertNotNull( $code );
        $this->assertStringContainsString( 'id="insights"', $code['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_newsletter_groups_have_background_images(): void {
        $section = self::$result['divi']['elements'][5];
        $groups  = $this->findAll( $section, 'divi/group' );
        $this->assertNotEmpty( $groups );

        // At least half of the groups should carry a background image.
        $with_bg = array_filter( $groups, function ( $g ) {
            $url = $g['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] ?? '';
            return $url !== '';
        } );
        $this->assertGreaterThanOrEqual( 2, count( $with_bg ), 'Newsletter groups should have background images' );
    }

    // -------------------------------------------------------------------------
    // Cross-cutting: image quality
    // -------------------------------------------------------------------------

    public function test_all_images_have_src(): void {
        $all_images = $this->findAll( self::$result['divi'], 'divi/image' );
        foreach ( $all_images as $img ) {
            $src = $img['settings']['image']['innerContent']['desktop']['value']['src'] ?? '';
            $this->assertNotEmpty( $src, 'Every divi/image should have a src' );
        }
    }

    public function test_images_have_border_radius_applied(): void {
        $all_images = $this->findAll( self::$result['divi'], 'divi/image' );
        foreach ( $all_images as $img ) {
            $radius = $img['settings']['module']['decoration']['border']['desktop']['value']['radius'] ?? null;
            $this->assertNotNull( $radius, 'image_border_radius should be mapped to Divi border radius' );
            $this->assertSame( '15px', $radius['topLeft'] );
        }
    }

    // -------------------------------------------------------------------------
    // Cross-cutting: spacing
    // -------------------------------------------------------------------------

    public function test_advanced_tab_padding_is_applied_to_text_modules(): void {
        $section  = self::$result['divi']['elements'][0];
        $texts    = $this->findAll( $section, 'divi/text' );
        $this->assertNotEmpty( $texts );

        $has_padding = false;
        foreach ( $texts as $t ) {
            $spacing = $t['settings']['module']['decoration']['spacing'] ?? [];
            if ( ! empty( $spacing ) ) {
                $has_padding = true;
                break;
            }
        }
        $this->assertTrue( $has_padding, 'Advanced-tab _padding/_margin should be mapped to Divi module spacing' );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findFirst( array $el, string $name ): ?array {
        if ( ( $el['name'] ?? '' ) === $name ) {
            return $el;
        }
        foreach ( $el['elements'] ?? [] as $child ) {
            $found = $this->findFirst( $child, $name );
            if ( $found !== null ) {
                return $found;
            }
        }
        return null;
    }

    private function findAll( array $el, string $name, array &$results = [] ): array {
        if ( ( $el['name'] ?? '' ) === $name ) {
            $results[] = $el;
        }
        foreach ( $el['elements'] ?? [] as $child ) {
            $this->findAll( $child, $name, $results );
        }
        return $results;
    }
}
