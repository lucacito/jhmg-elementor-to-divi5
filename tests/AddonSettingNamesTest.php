<?php
// tests/AddonSettingNamesTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Add-on widgets built with the setting names their own plugin defines must
 * convert with their content intact.
 *
 * Every settings array below uses names copied from the add-on's source:
 * Essential Addons for Elementor Lite 6.6.7, Header Footer Elementor 2.8.8,
 * ElementsKit Lite 4.0.5. The converters used to read names none of those
 * versions define, so the widget converted "successfully" with its text, links
 * or table cells silently gone. Each "legacy" case pins the old names, which
 * are kept as fallbacks.
 */
final class AddonSettingNamesTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    /** @return array{0: array, 1: array} The first converted block and the full engine result. */
    private function convert( string $type, array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => [],
        ] ] );

        return [ $result['divi']['elements'][0], $result ];
    }

    /** Every string and integer leaf in a block tree, one per line. */
    private function leaves( mixed $node ): string {
        if ( is_string( $node ) || is_int( $node ) ) {
            return $node . "\n";
        }
        if ( ! is_array( $node ) ) {
            return '';
        }
        $out = '';
        foreach ( $node as $child ) {
            $out .= $this->leaves( $child );
        }
        return $out;
    }

    private function assertCarries( array $block, string ...$needles ): void {
        $haystack = $this->leaves( $block );
        foreach ( $needles as $needle ) {
            $this->assertStringContainsString( $needle, $haystack, "Converted block lost '{$needle}'." );
        }
    }

    // -------------------------------------------------------------------------
    // Essential Addons — Contact Form 7
    // -------------------------------------------------------------------------

    public function test_contact_form_7_converter_loads_and_reads_contact_form_list(): void {
        [ $block ] = $this->convert( 'eael-contact-form-7', [ 'contact_form_list' => '42' ] );

        $this->assertSame( 'divi/contact-form-7', $block['name'] );
        $this->assertSame( 42, $block['settings']['module']['advanced']['formId']['desktop']['value'] );
    }

    public function test_contact_form_7_legacy_form_id_still_converts(): void {
        [ $block ] = $this->convert( 'eael-contact-form-7', [ 'eael_contact_form_id' => '12' ] );

        $this->assertSame( 12, $block['settings']['module']['advanced']['formId']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // Essential Addons — fancy text, info box, pricing table
    // -------------------------------------------------------------------------

    public function test_fancy_text_reads_the_strings_repeater(): void {
        [ $block ] = $this->convert( 'eael-fancy-text', [
            'eael_fancy_text_prefix'  => 'Work',
            'eael_fancy_text_strings' => [
                [ '_id' => 'a1', 'eael_fancy_text_strings_text_field' => 'better' ],
                [ '_id' => 'b2', 'eael_fancy_text_strings_text_field' => 'together' ],
            ],
            'eael_fancy_text_suffix'  => 'at Ferncourt',
        ] );

        $this->assertSame( 'Work better at Ferncourt', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_fancy_text_legacy_strings_still_convert(): void {
        [ $block ] = $this->convert( 'eael-fancy-text', [
            'eael_fancy_strings' => [ [ 'eael_fancy_string_text' => 'Hello' ] ],
        ] );

        $this->assertSame( 'Hello', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_info_box_reads_its_description_text(): void {
        [ $block ] = $this->convert( 'eael-info-box', [
            'eael_infobox_title' => 'Fast Wi-Fi',
            'eael_infobox_text'  => '<p>Gigabit fibre on every desk.</p>',
        ] );

        $this->assertSame( '<p>Gigabit fibre on every desk.</p>', $block['settings']['module']['advanced']['text']['desktop']['value'] );
    }

    public function test_info_box_legacy_content_still_converts(): void {
        [ $block ] = $this->convert( 'eael-info-box', [ 'eael_infobox_content' => 'Old body' ] );

        $this->assertSame( 'Old body', $block['settings']['module']['advanced']['text']['desktop']['value'] );
    }

    public function test_pricing_table_reads_period_and_button_link(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_title'        => 'Flex Desk',
            'eael_pricing_table_price'        => '249',
            'eael_pricing_table_price_cur'    => '$',
            'eael_pricing_table_price_period' => 'month',
            'eael_pricing_table_btn'          => 'Join',
            'eael_pricing_table_btn_link'     => [ 'url' => 'https://example.test/join', 'is_external' => '' ],
        ] );

        $advanced = $block['elements'][0]['settings']['module']['advanced'];
        $this->assertSame( 'month', $advanced['perText']['desktop']['value'] );
        $this->assertSame( 'https://example.test/join', $advanced['buttonUrl']['desktop']['value'] );
    }

    public function test_pricing_table_legacy_period_and_url_still_convert(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_price'     => '10',
            'eael_pricing_table_price_per' => 'year',
            'eael_pricing_table_btn'       => 'Buy',
            'eael_pricing_table_btn_url'   => [ 'url' => 'https://example.test/old' ],
        ] );

        $this->assertCarries( $block, 'year', 'https://example.test/old' );
    }

    // -------------------------------------------------------------------------
    // Essential Addons — code snippet, image accordion, simple menu, sticky video, tooltip
    // -------------------------------------------------------------------------

    public function test_code_snippet_reads_code_content_and_language(): void {
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'code_content' => '<?php echo 1;', 'language' => 'php' ] );

        $this->assertCarries( $block, 'language-php', '&lt;?php echo 1;' );
    }

    public function test_code_snippet_without_language_uses_the_html_default(): void {
        // EAEL's language select defaults to html, and Elementor omits defaults.
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'code_content' => '<b>Hi</b>' ] );

        $this->assertSame( '<b>Hi</b>', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_code_snippet_legacy_names_still_convert(): void {
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'eael_code_snippet_code' => 'x = 1', 'eael_code_snippet_type' => 'python' ] );

        $this->assertCarries( $block, 'language-python', 'x = 1' );
    }

    public function test_image_accordion_reads_the_accordions_repeater(): void {
        [ $block ] = $this->convert( 'eael-image-accordion', [
            'eael_img_accordions' => [ [
                '_id'                    => 'a1',
                'eael_accordion_tittle'  => 'Lounge',
                'eael_accordion_content' => '<p>Sofas and coffee</p>',
                'eael_accordion_bg'      => [ 'url' => 'https://example.test/lounge.jpg', 'id' => 5 ],
            ] ],
        ] );

        $this->assertSame( 'divi/accordion-item', $block['elements'][0]['name'] );
        $this->assertSame( 'Lounge', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertCarries( $block, '<p>Sofas and coffee</p>', 'https://example.test/lounge.jpg' );
    }

    public function test_image_accordion_legacy_items_still_convert(): void {
        [ $block ] = $this->convert( 'eael-image-accordion', [
            'eael_img_accordion_items' => [ [ 'eael_img_accordion_title' => 'Old', 'eael_img_accordion_content' => 'Body' ] ],
        ] );

        $this->assertCarries( $block, 'Old', 'Body' );
    }

    public function test_simple_menu_reads_the_selected_menu_id(): void {
        [ $block ] = $this->convert( 'eael-simple-menu', [ 'eael_simple_menu_menu' => '7' ] );

        $this->assertSame( '7', $block['settings']['menu']['innerContent']['desktop']['value']['menuId'] );
    }

    public function test_simple_menu_legacy_slug_still_converts(): void {
        [ $block ] = $this->convert( 'eael-simple-menu', [ 'eael_simple_menu_slug' => 'main' ] );

        $this->assertSame( 'main', $block['settings']['menu']['innerContent']['desktop']['value']['menuId'] );
    }

    public function test_sticky_video_defaults_to_youtube_link(): void {
        // EAEL's source select defaults to youtube, and Elementor omits defaults.
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eaelsv_link_youtube' => 'https://www.youtube.com/watch?v=abc' ] );

        $this->assertSame( 'https://www.youtube.com/watch?v=abc', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_vimeo_link(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eael_video_source' => 'vimeo', 'eaelsv_link_vimeo' => 'https://vimeo.com/1' ] );

        $this->assertSame( 'https://vimeo.com/1', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_self_hosted_media(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [
            'eael_video_source' => 'self_hosted',
            'eaelsv_hosted_url' => [ 'url' => 'https://example.test/tour.mp4', 'id' => 9 ],
        ] );

        $this->assertSame( 'https://example.test/tour.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_external_url_when_switched_on(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [
            'eael_video_source'    => 'self_hosted',
            'eaelsv_link_external' => 'yes',
            'eaelsv_external_url'  => 'https://cdn.example.test/tour.mp4',
            'eaelsv_hosted_url'    => [ 'url' => 'https://example.test/ignored.mp4' ],
        ] );

        $this->assertSame( 'https://cdn.example.test/tour.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_legacy_url_still_converts(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eael_video_url' => 'https://example.test/old.mp4' ] );

        $this->assertSame( 'https://example.test/old.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_tooltip_reads_visible_text_and_hover_content(): void {
        [ $block ] = $this->convert( 'eael-tooltip', [
            'eael_tooltip_content'       => 'Day pass',
            'eael_tooltip_hover_content' => 'Valid 8am to 8pm',
        ] );

        $this->assertSame( 'Day pass (Valid 8am to 8pm)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_tooltip_legacy_trigger_text_still_converts(): void {
        [ $block ] = $this->convert( 'eael-tooltip', [
            'eael_tooltip_trigger_text' => 'Hover me',
            'eael_tooltip_content'      => 'Tip',
        ] );

        $this->assertSame( 'Hover me (Tip)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }
}
