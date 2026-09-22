<?php
// tests/AnimationAddonsBatch3Test.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * "Animation Addons for Elementor" — Batch 3: wcf--button and wcf--image
 * (reused converters, verified against button.php/image.php + their shared
 * Aaeaddon_Button_Trait.php), and the testimonial-carousel cluster
 * (testimonial.php, testimonial2.php, testimonial3.php, advanced-testimonial.php),
 * verified against the free plugin's real source, version 4.2.2
 * (references/animation-addons-for-elementor.4.2.2.zip).
 */
final class AnimationAddonsBatch3Test extends TestCase {

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
    // wcf--button (button.php + Aaeaddon_Button_Trait.php) — its own
    // btn_text/btn_link keys, not the native widget's text/link.
    // -------------------------------------------------------------------------

    public function test_button_reads_its_own_btn_text_and_btn_link(): void {
        [ $block, $result ] = $this->convert( 'wcf--button', [
            'btn_text' => 'Book a tour',
            'btn_link' => [ 'url' => 'https://x.test/tour', 'is_external' => 'on' ],
        ] );

        $this->assertSame( 'divi/button', $block['name'] );
        $this->assertSame(
            [ 'text' => 'Book a tour', 'linkUrl' => 'https://x.test/tour', 'linkTarget' => '_blank' ],
            $block['settings']['button']['innerContent']['desktop']['value']
        );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_core_button_is_unaffected_by_the_btn_fallback(): void {
        [ $block ] = $this->convert( 'button', [ 'text' => 'Contact', 'link' => [ 'url' => 'https://x.test/c' ] ] );

        $this->assertSame(
            [ 'text' => 'Contact', 'linkUrl' => 'https://x.test/c' ],
            $block['settings']['button']['innerContent']['desktop']['value']
        );
    }

    // -------------------------------------------------------------------------
    // wcf--image (image.php) — identical 'image'/'link' keys to the native
    // widget; also exercises ImageConverter's new link support directly.
    // -------------------------------------------------------------------------

    public function test_image_reads_its_link_into_the_image_innercontent(): void {
        [ $block, $result ] = $this->convert( 'wcf--image', [
            'image' => [ 'url' => 'https://x.test/hero.jpg', 'alt' => 'Hero' ],
            'link'  => [ 'url' => 'https://x.test/gallery', 'is_external' => 'on' ],
        ] );

        $this->assertSame( 'divi/image', $block['name'] );
        $value = $block['settings']['image']['innerContent']['desktop']['value'];
        $this->assertSame( 'https://x.test/hero.jpg', $value['src'] );
        $this->assertSame( 'https://x.test/gallery', $value['linkUrl'] );
        $this->assertSame( '_blank', $value['linkTarget'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_image_without_a_link_has_no_link_fields(): void {
        [ $block ] = $this->convert( 'wcf--image', [ 'image' => [ 'url' => 'https://x.test/hero.jpg' ] ] );

        $value = $block['settings']['image']['innerContent']['desktop']['value'];
        $this->assertArrayNotHasKey( 'linkUrl', $value );
        $this->assertArrayNotHasKey( 'linkTarget', $value );
    }

    // -------------------------------------------------------------------------
    // wcf--testimonial / wcf--testimonial2 / wcf--testimonial3
    // -------------------------------------------------------------------------

    private function testimonials(): array {
        return [
            'testimonials' => [
                [
                    'testimonial_content' => 'The quiet rooms alone paid for it.',
                    'testimonial_name'    => 'Priya Raman',
                    'testimonial_job'     => 'Brand designer',
                    'testimonial_image'   => [ 'url' => 'https://x.test/p.jpg' ],
                ],
                [
                    'testimonial_content' => 'Great crew, flexible desks.',
                    'testimonial_name'    => 'Sam Ortiz',
                    'testimonial_job'     => 'Founder',
                ],
            ],
        ];
    }

    public function test_testimonial_carousel_wraps_each_item_in_a_group_with_a_divi_testimonial(): void {
        [ $block, $result ] = $this->convert( 'wcf--testimonial', $this->testimonials() );

        $this->assertSame( 'divi/group-carousel', $block['name'] );
        $this->assertCount( 2, $block['elements'] );

        $first = $block['elements'][0];
        $this->assertSame( 'divi/group', $first['name'] );
        $this->assertSame( 'divi/testimonial', $first['elements'][0]['name'] );
        $s = $first['elements'][0]['settings'];
        $this->assertSame( 'The quiet rooms alone paid for it.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Priya Raman', $s['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Brand designer', $s['jobTitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'src' => 'https://x.test/p.jpg' ], $s['portrait']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_testimonial2_and_testimonial3_share_the_same_field_names(): void {
        [ $b2 ] = $this->convert( 'wcf--testimonial2', $this->testimonials() );
        [ $b3 ] = $this->convert( 'wcf--testimonial3', $this->testimonials() );

        $this->assertSame( 'Priya Raman', $b2['elements'][0]['elements'][0]['settings']['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Priya Raman', $b3['elements'][0]['elements'][0]['settings']['author']['innerContent']['desktop']['value'] );
    }

    public function test_testimonial_without_an_image_has_no_portrait_field(): void {
        [ $block ] = $this->convert( 'wcf--testimonial', $this->testimonials() );

        $this->assertArrayNotHasKey( 'portrait', $block['elements'][1]['elements'][0]['settings'] );
    }

    // -------------------------------------------------------------------------
    // wcf--a-testimonial (advanced-testimonial.php) — different tsm_* field
    // names; tsm_reason folds ahead of tsm_content; tsm_rating has no
    // divi/testimonial field and is reported.
    // -------------------------------------------------------------------------

    public function test_advanced_testimonial_combines_reason_and_content_when_shown(): void {
        // reason_show/client_img_show/rating_show gate whether the widget shows
        // these at all (advanced-testimonial.php:1118,1128) — a real page found
        // through the Docker probe hides all three by default.
        [ $block, $result ] = $this->convert( 'wcf--a-testimonial', [
            'reason_show'      => 'yes',
            'client_img_show'  => 'yes',
            'rating_show'      => 'yes',
            'testimonials'     => [ [
                'tsm_reason' => 'Best decision this year.',
                'tsm_content' => 'The team was responsive throughout.',
                'tsm_name'    => 'Jordan Lee',
                'tsm_role'    => 'Ops manager',
                'tsm_image'   => [ 'url' => 'https://x.test/j.jpg' ],
                'tsm_rating'  => 5,
            ] ],
        ] );

        $s = $block['elements'][0]['elements'][0]['settings'];
        $this->assertSame( 'Best decision this year. The team was responsive throughout.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Jordan Lee', $s['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Ops manager', $s['jobTitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'src' => 'https://x.test/j.jpg' ], $s['portrait']['innerContent']['desktop']['value'] );
        $this->assertSame( 'testimonial_rating', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( '5', $result['report']['not_carried_over'][0]['detail'] );
    }

    public function test_advanced_testimonial_hides_reason_image_and_rating_by_default(): void {
        [ $block, $result ] = $this->convert( 'wcf--a-testimonial', [
            'testimonials' => [ [
                'tsm_reason'  => 'Best decision this year.',
                'tsm_content' => 'The team was responsive throughout.',
                'tsm_name'    => 'Jordan Lee',
                'tsm_image'   => [ 'url' => 'https://x.test/j.jpg' ],
                'tsm_rating'  => 5,
            ] ],
        ] );

        $s = $block['elements'][0]['elements'][0]['settings'];
        $this->assertSame( 'The team was responsive throughout.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'portrait', $s );
        $this->assertSame( [], $result['report']['not_carried_over'] );
    }
}
