<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

final class ConverterEngineTest extends TestCase {
    public function test_can_convert_elementor_section_tree_to_divi_structure(): void {
        $engine = new ConverterEngine();

        $elementorData = [
            [
                'id' => 'section-1',
                'elType' => 'section',
                'elements' => [
                    [
                        'id' => 'column-1',
                        'elType' => 'column',
                        'elements' => [
                            [
                                'id' => 'heading-1',
                                'elType' => 'widget',
                                'widgetType' => 'e-heading',
                                'settings' => [
                                    'title' => [
                                        '$$type' => 'string',
                                        'value' => 'Hello World',
                                    ],
                                ],
                                'elements' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $engine->convert( $elementorData );

        $this->assertArrayHasKey( 'divi', $result );
        $this->assertCount( 1, $result['divi']['elements'] );
        $section = $result['divi']['elements'][0];
        $this->assertSame( 'divi/section', $section['name'] );
        $this->assertCount( 1, $section['elements'] );

        // SectionConverter now wraps all column children in an explicit divi/row.
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );
        $this->assertCount( 1, $row['elements'] );

        $column = $row['elements'][0];
        $this->assertSame( 'divi/column', $column['name'] );
        $this->assertCount( 1, $column['elements'] );

        $heading = $column['elements'][0];
        $this->assertSame( 'divi/heading', $heading['name'] );
        $this->assertSame( 'Hello World', $heading['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_logs_unsupported_elementor_widget_types(): void {
        $engine = new ConverterEngine();

        $elementorData = [
            [
                'id' => 'widget-unknown',
                'elType' => 'widget',
                'widgetType' => 'e-unknown',
                'settings' => [],
                'elements' => [],
            ],
        ];

        $result = $engine->convert( $elementorData );

        $this->assertEmpty( $result['divi']['elements'] );
        $this->assertCount( 1, $result['unsupported'] );
        $this->assertSame( 'e-unknown', $result['unsupported'][0]['widgetType'] );
        $this->assertSame( 'widget-unknown', $result['unsupported'][0]['id'] );
    }

    // -------------------------------------------------------------------------
    // Quality scoring
    // -------------------------------------------------------------------------

    public function test_report_includes_quality_section(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [] );
        $quality = $result['report']['quality'];

        $this->assertArrayHasKey( 'widget_coverage', $quality );
        $this->assertArrayHasKey( 'settings_issues', $quality );
        $this->assertSame( 100, $quality['widget_coverage'] );
        $this->assertSame( 0, $quality['settings_issues'] );
    }

    public function test_widget_coverage_reflects_unsupported_count(): void {
        $engine = new ConverterEngine();
        // One known widget (button), one unknown widget.
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'button',
              'settings' => [ 'text' => 'Click', 'link' => [ 'url' => '#' ] ], 'elements' => [] ],
            [ 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'e-unknown', 'settings' => [], 'elements' => [] ],
        ] );
        $coverage = $result['report']['quality']['widget_coverage'];
        // 1 converted / 2 total = 50%
        $this->assertSame( 50, $coverage );
    }

    // -------------------------------------------------------------------------
    // Global color resolution
    // -------------------------------------------------------------------------

    public function test_global_colors_injected_into_section_background(): void {
        $engine = new ConverterEngine();
        $engine->setGlobalColors( [ 'bef7937' => '#14305A' ] );

        $result = $engine->convert( [
            [
                'id'       => 'section-1',
                'elType'   => 'section',
                'settings' => [
                    'background_background' => 'classic',
                    '__globals__'           => [ 'background_color' => 'globals/colors?id=bef7937' ],
                ],
                'elements' => [],
            ],
        ] );

        $section_attrs = $result['divi']['elements'][0]['settings'];
        $bg_color      = $section_attrs['module']['decoration']['background']['desktop']['value']['color'] ?? null;
        $this->assertSame( '#14305A', $bg_color );
    }

    public function test_global_colors_not_injected_when_direct_value_present(): void {
        $engine = new ConverterEngine();
        $engine->setGlobalColors( [ 'bef7937' => '#14305A' ] );

        $result = $engine->convert( [
            [
                'id'       => 'section-1',
                'elType'   => 'section',
                'settings' => [
                    'background_background' => 'classic',
                    'background_color'      => '#FF0000',
                    '__globals__'           => [ 'background_color' => 'globals/colors?id=bef7937' ],
                ],
                'elements' => [],
            ],
        ] );

        $section_attrs = $result['divi']['elements'][0]['settings'];
        $bg_color      = $section_attrs['module']['decoration']['background']['desktop']['value']['color'] ?? null;
        $this->assertSame( '#FF0000', $bg_color );
    }

    public function test_dual_button_uses_resolved_global_background_colors(): void {
        $engine = new ConverterEngine();
        $engine->setGlobalColors( [
            '87c60a5' => '#EAB300',
            '5e6e53b' => '#14305A',
        ] );

        $result = $engine->convert( [
            [
                'id'         => 'w1',
                'elType'     => 'widget',
                'widgetType' => 'elementskit-dual-button',
                'settings'   => [
                    'ekit_button_one_text' => 'Book a Consultation',
                    'ekit_button_two_text' => 'Watch the Overview',
                    '__globals__'          => [
                        'ekit_double_button_one_background_color' => 'globals/colors?id=87c60a5',
                        'ekit_double_button_two_background_color' => 'globals/colors?id=5e6e53b',
                    ],
                ],
                'elements'   => [],
            ],
        ] );

        $btn1 = $result['divi']['elements'][0];
        $btn2 = $result['divi']['elements'][1];

        $btn1_bg = $btn1['settings']['button']['decoration']['background']['desktop']['value']['color'] ?? null;
        $btn2_bg = $btn2['settings']['button']['decoration']['background']['desktop']['value']['color'] ?? null;

        $this->assertSame( '#EAB300', $btn1_bg, 'Button 1 background should match global color' );
        $this->assertSame( '#14305A', $btn2_bg, 'Button 2 background should match global color' );
    }

    // -------------------------------------------------------------------------
    // New widget converters
    // -------------------------------------------------------------------------

    public function test_counter_converter_produces_number_counter(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'counter',
              'settings' => [ 'ending_number' => '99', 'title' => 'Clients' ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/number-counter', $block['name'] );
        $this->assertSame( '99', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Clients', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_cta_converter_produces_cta_with_button(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'call-to-action',
              'settings' => [
                  'title'       => 'Get Started',
                  'description' => 'Join us today.',
                  'button'      => 'Sign Up',
                  'link'        => [ 'url' => 'https://example.com' ],
              ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/cta', $block['name'] );
        $this->assertSame( 'Get Started', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Sign Up', $block['settings']['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://example.com', $block['settings']['button']['innerContent']['desktop']['value']['linkUrl'] );
    }

    public function test_progress_bar_converter_produces_counters_with_children(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'progress-bar',
              'settings' => [
                  'bars' => [
                      [ 'label' => 'PHP', 'percent' => [ 'size' => 85 ] ],
                      [ 'label' => 'CSS', 'percent' => [ 'size' => 70 ] ],
                  ],
              ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/counters', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/counter', $block['elements'][0]['name'] );
        $this->assertSame( 'PHP', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '85', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_social_icons_converter_produces_follow_network_children(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'social-icons',
              'settings' => [
                  'social_icon_list' => [
                      [ 'social_icon' => 'fa fa-facebook', 'link' => [ 'url' => 'https://facebook.com/test' ] ],
                      [ 'social_icon' => 'fa fa-twitter',  'link' => [ 'url' => 'https://twitter.com/test' ] ],
                  ],
              ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/social-media-follow', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $net = $block['elements'][0]['settings']['socialNetwork']['innerContent']['desktop']['value'];
        $this->assertSame( 'facebook', $net['title'] );
        $this->assertSame( 'https://facebook.com/test', $net['link'] );
    }

    public function test_gallery_converter_extracts_attachment_ids(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'gallery',
              'settings' => [
                  'gallery' => [
                      [ 'id' => 101, 'url' => 'https://example.com/img1.jpg' ],
                      [ 'id' => 102, 'url' => 'https://example.com/img2.jpg' ],
                  ],
              ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/gallery', $block['name'] );
        $this->assertSame( [ 101, 102 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
    }

    public function test_slider_converter_produces_slides(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'slides',
              'settings' => [
                  'slides' => [
                      [
                          'heading'      => 'Slide One',
                          'description'  => 'Body text',
                          'button_text'  => 'Learn More',
                          'link'         => [ 'url' => 'https://example.com' ],
                          'background_image' => [ 'url' => 'https://example.com/bg.jpg' ],
                      ],
                  ],
              ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/slider', $block['name'] );
        $slide = $block['elements'][0];
        $this->assertSame( 'divi/slide', $slide['name'] );
        $this->assertSame( 'Slide One', $slide['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'https://example.com/bg.jpg', $slide['settings']['image']['innerContent']['desktop']['value']['src'] );
    }

    public function test_star_rating_converter_produces_unicode_stars(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'star-rating',
              'settings' => [ 'rating' => 4, 'rating_scale' => 5 ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/text', $block['name'] );
        $content = $block['settings']['content']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( '★★★★☆', $content );
    }

    public function test_alert_converter_produces_styled_html(): void {
        $engine = new ConverterEngine();
        $result = $engine->convert( [
            [ 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'alert',
              'settings' => [ 'alert_type' => 'success', 'alert_title' => 'Done!', 'alert_description' => 'All good.' ], 'elements' => [] ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( 'divi/text', $block['name'] );
        $content = $block['settings']['content']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( 'Done!', $content );
        $this->assertStringContainsString( 'All good.', $content );
        $this->assertStringContainsString( 'd4edda', $content ); // success bg color
    }
}
