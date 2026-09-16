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

        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "widget {$type}" );

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
        $this->assertSame( 42, $block['settings']['form']['advanced']['formId']['desktop']['value'] );
    }

    public function test_contact_form_7_legacy_form_id_still_converts(): void {
        [ $block ] = $this->convert( 'eael-contact-form-7', [ 'eael_contact_form_id' => '12' ] );

        $this->assertSame( 12, $block['settings']['form']['advanced']['formId']['desktop']['value'] );
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

        $this->assertSame( '<p>Gigabit fibre on every desk.</p>', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_info_box_legacy_content_still_converts(): void {
        [ $block ] = $this->convert( 'eael-info-box', [ 'eael_infobox_content' => 'Old body' ] );

        $this->assertSame( 'Old body', $block['settings']['content']['innerContent']['desktop']['value'] );
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

        $table = $block['elements'][0]['settings'];
        $this->assertSame( 'month', $table['currencyFrequency']['innerContent']['desktop']['value']['per'] );
        $this->assertSame( 'https://example.test/join', $table['button']['innerContent']['desktop']['value']['linkUrl'] );
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

        $this->assertSame( '7', $block['settings']['menu']['advanced']['menuId']['desktop']['value'] );
    }

    public function test_simple_menu_legacy_slug_still_converts(): void {
        [ $block ] = $this->convert( 'eael-simple-menu', [ 'eael_simple_menu_slug' => 'main' ] );

        $this->assertSame( 'main', $block['settings']['menu']['advanced']['menuId']['desktop']['value'] );
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

    // -------------------------------------------------------------------------
    // Essential Addons — data table, advanced data table
    // -------------------------------------------------------------------------

    private function codeValue( array $block ): string {
        return (string) $block['settings']['content']['innerContent']['desktop']['value'];
    }

    public function test_data_table_groups_flat_row_and_col_entries_into_rows(): void {
        [ $block ] = $this->convert( 'eael-data-table', [
            'eael_data_table_header_cols_data' => [
                [ '_id' => 'h1', 'eael_data_table_header_col' => 'Plan' ],
                [ '_id' => 'h2', 'eael_data_table_header_col' => 'Price' ],
            ],
            'eael_data_table_content_rows' => [
                // 'row' is the default row type, so Elementor often omits it.
                [ '_id' => 'r1' ],
                [ '_id' => 'c1', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_row_title' => 'Day pass' ],
                [ '_id' => 'c2', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_type' => 'editor', 'eael_data_table_content_row_content' => '<strong>$25</strong>' ],
                [ '_id' => 'r2', 'eael_data_table_content_row_type' => 'row' ],
                [ '_id' => 'c3', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_row_title' => 'Private office', 'eael_data_table_content_row_colspan' => 2 ],
            ],
        ] );

        $html = $this->codeValue( $block );
        $this->assertStringContainsString( '>Plan</th>', $html );
        $this->assertStringContainsString( '>Price</th>', $html );
        $this->assertStringContainsString( '>Day pass</td>', $html );
        $this->assertStringContainsString( '<strong>$25</strong>', $html );
        $this->assertStringContainsString( ' colspan="2">Private office</td>', $html );
        $this->assertSame( 3, substr_count( $html, '<tr>' ), 'One header row and two body rows.' );
    }

    public function test_data_table_template_cell_is_reported(): void {
        [ , $result ] = $this->convert( 'eael-data-table', [
            'eael_data_table_content_rows' => [
                [ '_id' => 'r1' ],
                [ '_id' => 'c1', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_type' => 'template' ],
            ],
        ] );

        $this->assertStringContainsString( 'template', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_data_table_legacy_nested_rows_still_convert(): void {
        [ $block ] = $this->convert( 'eael-data-table', [
            'eael_data_table_header_cols' => [ [ 'eael_dt_header_col' => 'H' ] ],
            'eael_data_table_body_rows'   => [ [ 'eael_dt_body_col_rows' => [ [ 'eael_dt_body_col' => 'A' ] ] ] ],
        ] );

        $html = $this->codeValue( $block );
        $this->assertStringContainsString( '>H</th>', $html );
        $this->assertStringContainsString( '>A</td>', $html );
    }

    public function test_advanced_data_table_wraps_static_html_by_default(): void {
        // 'static' is the default source, so it is absent here.
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_static_html' => '<thead><tr><th>Room</th></tr></thead><tbody><tr><td>Studio</td></tr></tbody>',
        ] );

        $html = $this->codeValue( $block );
        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertSame( 1, substr_count( $html, '<table' ) );
        $this->assertStringContainsString( '<td>Studio</td>', $html );
    }

    public function test_advanced_data_table_does_not_double_wrap_a_full_table(): void {
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_static_html' => '<table><tr><td>Studio</td></tr></table>',
        ] );

        $this->assertSame( 1, substr_count( $this->codeValue( $block ), '<table' ) );
    }

    public function test_advanced_data_table_reads_csv_html(): void {
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_source'   => 'csv',
            'ea_adv_data_table_csv_html' => '<tr><td>Meeting room</td></tr>',
        ] );

        $this->assertStringContainsString( '<td>Meeting room</td>', $this->codeValue( $block ) );
    }

    public function test_advanced_data_table_external_source_is_reported_not_invented(): void {
        [ $block, $result ] = $this->convert( 'eael-advanced-data-table', [ 'ea_adv_data_table_source' => 'database' ] );

        $this->assertSame( '', $this->codeValue( $block ) );
        $this->assertStringContainsString( 'outside the page', implode( "\n", $result['report']['warnings'] ) );
    }

    // -------------------------------------------------------------------------
    // Essential Addons — interactive circle, content ticker
    // -------------------------------------------------------------------------

    public function test_interactive_circle_items_become_tabs(): void {
        [ $block ] = $this->convert( 'eael-interactive-circle', [
            'eael_interactive_circle_item' => [
                [ '_id' => 'i1', 'eael_interactive_circle_btn_title' => 'Community', 'eael_interactive_circle_item_content' => '<p>Monthly socials</p>' ],
                [ '_id' => 'i2', 'eael_interactive_circle_btn_title' => 'Focus', 'eael_interactive_circle_item_content' => '<p>Quiet zones</p>' ],
            ],
        ] );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/tab', $block['elements'][0]['name'] );
        $this->assertSame( 'Community', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Monthly socials</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Focus', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_interactive_circle_legacy_title_still_converts(): void {
        [ $block ] = $this->convert( 'eael-interactive-circle', [
            'eael_interactive_circle_item' => [ [ 'eael_ic_title' => 'Old title' ] ],
        ] );

        $this->assertSame( 'Old title', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_content_ticker_dynamic_feed_keeps_its_label_and_is_reported(): void {
        // 'dynamic' is EAEL's default ticker type, so it is absent here.
        [ $block, $result ] = $this->convert( 'eael-content-ticker', [ 'eael_ticker_tag_text' => 'Latest news' ] );

        $this->assertSame( 'divi/icon-list', $block['name'] );
        $this->assertCarries( $block, 'Latest news' );
        $this->assertStringContainsString( 'not carried over', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_content_ticker_legacy_items_still_convert_without_a_warning(): void {
        [ $block, $result ] = $this->convert( 'eael-content-ticker', [
            'eael_ticker_heading' => 'News',
            'eael_ticker_items'   => [ [ 'eael_ct_title' => 'Open day', 'eael_ct_link' => [ 'url' => 'https://example.test/open' ] ] ],
        ] );

        $this->assertCarries( $block, 'News', 'Open day', 'https://example.test/open' );
        $this->assertStringNotContainsString( 'not carried over', implode( "\n", $result['report']['warnings'] ) );
    }

    // -------------------------------------------------------------------------
    // ElementsKit — video
    // -------------------------------------------------------------------------

    private function videoSrc( array $block ): string {
        return (string) ( $block['settings']['video']['innerContent']['desktop']['value']['src'] ?? '' );
    }

    public function test_elementskit_video_defaults_to_youtube_popup_url(): void {
        [ $block, $result ] = $this->convert( 'elementskit-video', [ 'ekit_video_popup_url' => 'https://www.youtube.com/watch?v=VhBl3dHT5SY' ] );

        $this->assertSame( 'divi/video', $block['name'] );
        $this->assertSame( 'https://www.youtube.com/watch?v=VhBl3dHT5SY', $this->videoSrc( $block ) );
        $this->assertStringNotContainsString( 'missing source URL', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_elementskit_video_reads_vimeo_popup_url(): void {
        [ $block ] = $this->convert( 'elementskit-video', [ 'ekit_video_popup_video_type' => 'vimeo', 'ekit_video_popup_url' => 'https://vimeo.com/42' ] );

        $this->assertSame( 'https://vimeo.com/42', $this->videoSrc( $block ) );
    }

    public function test_elementskit_video_reads_self_hosted_external_url(): void {
        // The external-URL switch defaults to on, so it is absent here.
        [ $block ] = $this->convert( 'elementskit-video', [
            'ekit_video_popup_video_type'  => 'self',
            'ekit_video_self_external_url' => 'https://cdn.example.test/tour.mp4',
        ] );

        $this->assertSame( 'https://cdn.example.test/tour.mp4', $this->videoSrc( $block ) );
    }

    public function test_elementskit_video_reads_self_hosted_media(): void {
        [ $block ] = $this->convert( 'elementskit-video', [
            'ekit_video_popup_video_type'   => 'self',
            'ekit_video_self_url'           => '',
            'ekit_video_player_self_hosted' => [ 'url' => 'https://example.test/tour.mp4', 'id' => 3 ],
        ] );

        $this->assertSame( 'https://example.test/tour.mp4', $this->videoSrc( $block ) );
    }

    public function test_core_video_is_unaffected(): void {
        [ $block ] = $this->convert( 'video', [ 'video_type' => 'hosted', 'hosted_url' => [ 'url' => 'https://example.test/core.mp4' ] ] );

        $this->assertSame( 'https://example.test/core.mp4', $this->videoSrc( $block ) );
    }

    // -------------------------------------------------------------------------
    // Essential Addons — blurbs and icons rendered by Divi (Task 11)
    // -------------------------------------------------------------------------

    public function test_info_box_becomes_a_blurb_divi_renders(): void {
        [ $block, $result ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'icon',
            'eael_infobox_icon_new'    => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ],
            'eael_infobox_title'       => 'Fast wifi',
            'eael_infobox_text'        => '<p>Gigabit fibre.</p>',
        ] );

        $this->assertSame( 'divi/blurb', $block['name'] );
        // BlurbModule.php: title is a headingLink ({text}), body is content.innerContent,
        // the icon is imageIcon.innerContent {useIcon, icon{type,unicode,weight}}.
        $this->assertSame( [ 'text' => 'Fast wifi' ], $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Gigabit fibre.</p>', $block['settings']['content']['innerContent']['desktop']['value'] );
        $icon = $block['settings']['imageIcon']['innerContent']['desktop']['value'];
        $this->assertSame( 'on', $icon['useIcon'] );
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf1eb;', 'weight' => '900' ], $icon['icon'] );
        $this->assertArrayNotHasKey( 'module', $block['settings'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_info_box_with_an_image_uses_src(): void {
        [ $block ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'img',
            'eael_infobox_image'       => [ 'url' => 'https://x.test/i.jpg', 'id' => 3 ],
            'eael_infobox_title'       => 'T',
        ] );
        $this->assertSame( [ 'src' => 'https://x.test/i.jpg' ], $block['settings']['imageIcon']['innerContent']['desktop']['value'] );
    }

    public function test_info_box_unknown_icon_falls_back_to_a_star_with_a_warning(): void {
        [ $block, $result ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'icon',
            'eael_infobox_icon_new'    => [ 'value' => 'fas fa-no-such-icon', 'library' => 'fa-solid' ],
            'eael_infobox_title'       => 'T',
        ] );
        $this->assertSame( '&#xf005;', $block['settings']['imageIcon']['innerContent']['desktop']['value']['icon']['unicode'] );
        $this->assertStringContainsString( 'no-such-icon', $result['report']['warnings'][0] );
    }

    public function test_flip_box_becomes_a_blurb_with_front_and_back_text(): void {
        [ $block ] = $this->convert( 'eael-flip-box', [
            'eael_flipbox_img_or_icon' => 'icon',
            'eael_flipbox_icon_new'    => [ 'value' => 'fas fa-door-open', 'library' => 'fa-solid' ],
            'eael_flipbox_front_title' => 'Private offices',
            'eael_flipbox_front_text'  => 'Two to twelve desks.',
            'eael_flipbox_back_title'  => 'Private offices',
            'eael_flipbox_back_text'   => 'From $900 a month.',
        ] );
        $this->assertSame( [ 'text' => 'Private offices' ], $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'Two to twelve desks.', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'From $900 a month.', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['imageIcon']['innerContent']['desktop']['value']['useIcon'] );
    }

    public function test_icon_widget_emits_an_icon_object(): void {
        [ $block ] = $this->convert( 'icon', [ 'selected_icon' => [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ] ] );
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf005;', 'weight' => '900' ], $block['settings']['icon']['innerContent']['desktop']['value'] );
    }

    public function test_icon_box_maps_its_icon_instead_of_always_a_star(): void {
        [ $block ] = $this->convert( 'icon-box', [ 'selected_icon' => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ], 'title_text' => 'T' ] );
        $this->assertSame( '&#xf1eb;', $block['settings']['imageIcon']['innerContent']['desktop']['value']['icon']['unicode'] );
    }

    public function test_pricing_table_writes_the_attributes_divi_reads(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_title'        => 'Day Pass',
            'eael_pricing_table_sub_title'    => 'Try us out',
            'eael_pricing_table_price'        => '29',
            'eael_pricing_table_price_cur'    => '$',
            'eael_pricing_table_price_period' => 'day',
            'eael_pricing_table_items'        => [
                [ 'eael_pricing_table_item' => 'Any open desk' ],
                [ 'eael_pricing_table_item' => 'Meeting rooms', 'eael_pricing_table_icon_mood' => 'no' ],
            ],
            'eael_pricing_table_btn'          => 'Book a day',
            'eael_pricing_table_btn_link'     => [ 'url' => 'https://x.test/contact/' ],
            'eael_pricing_table_featured'     => 'yes',
        ] );

        $this->assertSame( 'divi/pricing-tables', $block['name'] );
        $table = $block['elements'][0]['settings'];
        // PricingTablesItemModule.php: title, subtitle, currencyFrequency{currency,per},
        // price, content (one feature per line, "-" = excluded), button{text,linkUrl}, featured.
        $this->assertSame( 'Day Pass', $table['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Try us out', $table['subtitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'currency' => '$', 'per' => 'day' ], $table['currencyFrequency']['innerContent']['desktop']['value'] );
        $this->assertSame( '29', $table['price']['innerContent']['desktop']['value'] );
        $this->assertSame( "Any open desk\n-Meeting rooms", $table['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'text' => 'Book a day', 'linkUrl' => 'https://x.test/contact/' ], $table['button']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $table['module']['advanced']['featured']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'title', $table['module']['advanced'] ?? [] );
    }

    public function test_team_member_writes_position_description_image_and_social_links(): void {
        [ $block, $result ] = $this->convert( 'eael-team-member', [
            'eael_team_member_image'                  => [ 'url' => 'https://x.test/h.jpg', 'id' => 9, 'alt' => 'Hannah' ],
            'eael_team_member_name'                   => 'Hannah Moore',
            'eael_team_member_job_title'              => 'Founder',
            'eael_team_member_description'            => 'Ran a design studio.',
            'eael_team_member_enable_social_profiles' => 'yes',
            'eael_team_member_social_profile_links'   => [
                [ 'social_new' => [ 'value' => 'fab fa-linkedin', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/in/h' ] ],
                [ 'social_new' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/h' ] ],
            ],
        ] );

        $s = $block['settings'];
        // TeamMemberModule.php: name, position, content innerContent; image.innerContent.url (line 98);
        // social.innerContent {facebookUrl, twitterUrl, googleUrl, linkedinUrl} (line 775).
        $this->assertSame( 'Hannah Moore', $s['name']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Founder', $s['position']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Ran a design studio.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'url' => 'https://x.test/h.jpg', 'alt' => 'Hannah' ], $s['image']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'linkedinUrl' => 'https://www.linkedin.com/in/h' ], $s['social']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'module', $s );
        $this->assertSame( 'social_network', $result['report']['not_carried_over'][0]['kind'] );
    }

    public function test_testimonial_writes_author_job_title_and_portrait(): void {
        [ $block ] = $this->convert( 'eael-testimonial', [
            'image'                          => [ 'url' => 'https://x.test/p.jpg', 'id' => 4 ],
            'eael_testimonial_name'          => 'Priya Raman',
            'eael_testimonial_company_title' => 'Brand designer',
            'eael_testimonial_description'   => 'The quiet rooms alone paid for it.',
        ] );

        $s = $block['settings'];
        // TestimonialModule.php: content, author, jobTitle innerContent; portrait.innerContent.src (line 98).
        $this->assertSame( 'The quiet rooms alone paid for it.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Priya Raman', $s['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Brand designer', $s['jobTitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'src' => 'https://x.test/p.jpg' ], $s['portrait']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'company', $s );
        $this->assertArrayNotHasKey( 'module', $s );
    }

    public function test_core_testimonial_writes_author_job_and_portrait(): void {
        [ $block ] = $this->convert( 'testimonial', [
            'testimonial_content' => 'Great crew.',
            'testimonial_name'    => 'Sam Ortiz',
            'testimonial_job'     => 'Homeowner',
            'testimonial_image'   => [ 'url' => 'https://x.test/s.jpg', 'id' => 2 ],
        ] );

        $s = $block['settings'];
        $this->assertSame( 'Sam Ortiz', $s['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Homeowner', $s['jobTitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'src' => 'https://x.test/s.jpg' ], $s['portrait']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'company', $s );
    }

    public function test_countdown_due_date_lands_in_content_advanced_date_time(): void {
        [ $block, $result ] = $this->convert( 'eael-countdown', [ 'eael_countdown_type' => 'due_date', 'eael_countdown_due_time' => '2026-10-06 18:00' ] );

        // countdown-timer/module.json + CountdownTimerModule.php:361: content.advanced.dateTime,
        // parsed with strtotime; the VB timepicker stores "Y-m-d H:i".
        $this->assertSame( '2026-10-06 18:00', $block['settings']['content']['advanced']['dateTime']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'module', $block['settings'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_countdown_date_only_and_unparsable_dates(): void {
        [ $block ] = $this->convert( 'eael-countdown', [ 'eael_countdown_due_time' => '2026-10-06' ] );
        $this->assertSame( '2026-10-06 00:00', $block['settings']['content']['advanced']['dateTime']['desktop']['value'] );

        [ $block, $result ] = $this->convert( 'eael-countdown', [ 'eael_countdown_due_time' => 'next tuesday-ish' ] );
        $this->assertArrayNotHasKey( 'content', $block['settings'] );
        $this->assertStringContainsString( 'next tuesday-ish', $result['report']['warnings'][0] );
    }

    // -------------------------------------------------------------------------
    // Converters that dropped content (Task 15)
    // -------------------------------------------------------------------------

    public function test_progress_bar_reads_the_slider_value(): void {
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_title' => 'Freelancers', 'progress_bar_value' => [ 'unit' => '%', 'size' => 58, 'sizes' => [] ] ] );
        $this->assertSame( '58', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_progress_bar_defaults_to_50_and_keeps_legacy_scalars_and_dynamic_values(): void {
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_title' => 'x' ] );
        $this->assertSame( '50', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_value' => '72' ] );
        $this->assertSame( '72', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_value_type' => 'dynamic', 'progress_bar_value_dynamic' => '33', 'progress_bar_value' => [ 'size' => 50 ] ] );
        $this->assertSame( '33', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_cta_box_keeps_its_body_text_after_the_subtitle(): void {
        [ $block ] = $this->convert( 'eael-cta-box', [ 'eael_cta_title' => 'Try us', 'eael_cta_sub_title' => 'A day is free.', 'eael_cta_content' => '<p>Bring a laptop.</p>', 'eael_cta_btn_text' => 'Go' ] );
        $this->assertSame( "<p>A day is free.</p>\n<p>Bring a laptop.</p>", $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_elementskit_heading_subtitle_becomes_a_text_block_before_the_heading(): void {
        [ $first, $result ] = $this->convert( 'elementskit-heading', [ 'ekit_heading_title' => 'Built by freelancers', 'ekit_heading_title_tag' => 'h1', 'ekit_heading_sub_title_show' => 'yes', 'ekit_heading_sub_title' => 'Since 2019' ] );
        $blocks = $result['divi']['elements'];
        $this->assertSame( 'divi/text', $blocks[0]['name'] );
        $this->assertSame( '<p>Since 2019</p>', $blocks[0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'divi/heading', $blocks[1]['name'] );
    }

    public function test_post_grid_carries_its_category_filter_and_reports_other_filters(): void {
        [ $block, $result ] = $this->convert( 'eael-post-grid', [ 'post_type' => 'post', 'posts_per_page' => 3, 'category_ids' => [ '4', '7' ], 'post_tag_ids' => [ '9' ] ] );
        // blog/conversion-outline.json + BlogModule.php:769: post.advanced.number, .type and .categories.
        $this->assertSame( '3', $block['settings']['post']['advanced']['number']['desktop']['value'] );
        $this->assertSame( 'post', $block['settings']['post']['advanced']['type']['desktop']['value'] );
        $this->assertSame( [ '4', '7' ], $block['settings']['post']['advanced']['categories']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'innerContent', $block['settings']['post'] );
        $this->assertSame( 'query_filter', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( 'post_tag_ids', $result['report']['not_carried_over'][0]['detail'] );

        [ $block, $result ] = $this->convert( 'eael-post-grid', [ 'post_type' => 'product', 'posts_per_page' => 6 ] );
        $this->assertStringContainsString( 'product', $result['report']['not_carried_over'][0]['detail'] );
    }

    // -------------------------------------------------------------------------
    // Icon list items (Task 22): IconListItemModule.php reads content.innerContent (line 310),
    // icon.innerContent as an icon object (line 77) and module.advanced.link (line 178).
    // -------------------------------------------------------------------------

    private function assertIconListItem( array $item, string $text, ?string $unicode, ?string $url ): void {
        $this->assertSame( 'divi/icon-list-item', $item['name'] );
        $this->assertSame( $text, $item['settings']['content']['innerContent']['desktop']['value'] );
        if ( $unicode !== null ) {
            $this->assertSame( $unicode, $item['settings']['icon']['innerContent']['desktop']['value']['unicode'] );
            $this->assertSame( 'fa', $item['settings']['icon']['innerContent']['desktop']['value']['type'] );
        }
        if ( $url !== null ) {
            $this->assertSame( $url, $item['settings']['module']['advanced']['link']['desktop']['value']['url'] );
        }
        $this->assertArrayNotHasKey( 'link', $item['settings'] );
        $this->assertArrayNotHasKey( 'text', $item['settings']['module']['advanced'] ?? [] );
    }

    public function test_feature_list_items_carry_text_icon_and_link(): void {
        [ $block ] = $this->convert( 'eael-feature-list', [ 'eael_feature_list' => [
            [ 'eael_feature_list_title' => 'Fast wifi', 'eael_feature_list_content' => 'Gigabit fibre.', 'eael_feature_list_icon_new' => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ], 'eael_feature_list_link' => [ 'url' => 'https://x.test/wifi' ] ],
            [ 'eael_feature_list_title' => 'Coffee', 'eael_feature_list_icon_new' => [ 'value' => 'fas fa-mug-hot', 'library' => 'fa-solid' ] ],
        ] ] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertIconListItem( $block['elements'][0], '<strong>Fast wifi</strong> Gigabit fibre.', '&#xf1eb;', 'https://x.test/wifi' );
        $this->assertIconListItem( $block['elements'][1], 'Coffee', '&#xf7b6;', null );
    }

    public function test_price_list_items_carry_text_and_link(): void {
        [ $block ] = $this->convert( 'price-list', [ 'price_list' => [
            [ 'title' => 'Espresso', 'price' => '$3', 'link' => [ 'url' => 'https://x.test/e' ] ],
            [ 'title' => 'Latte', 'price' => '$4' ],
        ] ] );
        $this->assertIconListItem( $block['elements'][0], 'Espresso — $3', null, 'https://x.test/e' );
        $this->assertIconListItem( $block['elements'][1], 'Latte — $4', null, null );
    }

    public function test_content_ticker_items_carry_text_and_link(): void {
        [ $block ] = $this->convert( 'eael-content-ticker', [ 'eael_ticker_type' => 'custom', 'eael_ticker_tag_text' => 'News', 'eael_ticker_custom_contents' => [
            [ 'eael_ticker_custom_content' => 'Doors open at 8', 'eael_ticker_custom_content_link' => [ 'url' => 'https://x.test/doors' ] ],
        ] ] );
        $this->assertSame( '<strong>News</strong>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertIconListItem( $block['elements'][1], 'Doors open at 8', null, 'https://x.test/doors' );
    }

    // -------------------------------------------------------------------------
    // Header Footer Elementor
    // -------------------------------------------------------------------------

    private function menuId( array $block ): string {
        return (string) $block['settings']['menu']['advanced']['menuId']['desktop']['value'];
    }

    public function test_navigation_menu_sits_on_a_transparent_background(): void {
        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary', 'layout' => 'horizontal' ] );

        $this->assertSame( 'divi/menu', $block['name'] );
        // menu/module-default-render-attributes.json paints #ffffff behind the menu; the header
        // container's background is what the page shows through in Elementor.
        $this->assertSame( 'rgba(255,255,255,0)', $block['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_navigation_menu_item_background_becomes_the_menu_background(): void {
        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary', 'bg_color_menu_item' => '#2F4F3A' ] );

        $this->assertSame( '#2F4F3A', $block['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_hfe_navigation_menu_resolves_the_stored_slug_to_a_menu_id(): void {
        $GLOBALS['__test_nav_menus'] = [ 'primary' => 7 ];

        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary' ] );

        $this->assertSame( '7', $this->menuId( $block ) );
    }

    public function test_hfe_navigation_menu_keeps_a_numeric_menu_id(): void {
        $GLOBALS['__test_nav_menus'] = [];

        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => '12' ] );

        $this->assertSame( '12', $this->menuId( $block ) );
    }

    public function test_hfe_navigation_menu_legacy_nav_menu_still_converts(): void {
        $GLOBALS['__test_nav_menus'] = [];

        [ $block ] = $this->convert( 'navigation-menu', [ 'nav_menu' => '5' ] );

        $this->assertSame( '5', $this->menuId( $block ) );
    }

    public function test_hfe_copyright_reads_shortcode_and_fills_in_hfe_shortcodes(): void {
        [ $block ] = $this->convert( 'copyright', [ 'shortcode' => 'Copyright © [hfe_current_year] [hfe_site_title]' ] );

        $this->assertSame(
            'Copyright © ' . gmdate( 'Y' ) . ' Ferncourt Test',
            $block['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_hfe_copyright_legacy_text_still_converts(): void {
        [ $block ] = $this->convert( 'copyright', [ 'copyright_text' => 'Old ©' ] );

        $this->assertSame( 'Old ©', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_site_title_reads_before_after_and_heading_tag(): void {
        [ $block ] = $this->convert( 'hfe-site-title', [ 'before' => 'Welcome to', 'after' => '!', 'heading_tag' => 'h1' ] );

        $this->assertSame( 'Welcome to Ferncourt Test !', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h1', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_hfe_site_title_legacy_names_still_convert(): void {
        [ $block ] = $this->convert( 'hfe-site-title', [ 'before_title_text' => 'Hi', 'title_html_tag' => 'h3' ] );

        $this->assertSame( 'Hi Ferncourt Test', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h3', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_hfe_site_tagline_reads_before_and_after(): void {
        [ $block ] = $this->convert( 'hfe-site-tagline', [ 'before' => 'Ferncourt:', 'after' => '(est. 2019)' ] );

        $this->assertSame( 'Ferncourt: Coworking for freelancers (est. 2019)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_counter_reads_end_number(): void {
        [ $block, $result ] = $this->convert( 'hfe-counter', [ 'start_number' => 0, 'end_number' => 120, 'suffix' => '+', 'title' => 'Members' ] );

        // Divi's number counter animates the bare number and offers only a percent
        // sign; the '+' is reported as not carried over (CounterConversionTest).
        $this->assertSame( '120', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Members', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'counter_affix', $result['report']['not_carried_over'][0]['kind'] );
    }

    public function test_core_counter_is_unaffected(): void {
        [ $block ] = $this->convert( 'counter', [ 'ending_number' => 42 ] );

        $this->assertSame( '42', $block['settings']['number']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_retina_logo_reads_retina_image(): void {
        [ $block ] = $this->convert( 'retina', [ 'retina_image' => [ 'url' => 'https://example.test/logo@2x.png', 'id' => 4 ] ] );

        $this->assertSame( 'https://example.test/logo@2x.png', $block['settings']['image']['innerContent']['desktop']['value']['src'] );
    }
}
