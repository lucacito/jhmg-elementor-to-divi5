<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--t-h-image" widget (Animation Addons for Elementor,
 * text-hover-image.php — get_name() returns 'wcf--t-h-image', not a
 * guessable slug) to divi/heading.
 *
 * The widget's heading text is built from three fragments
 * ('before_hover_text' + 'hover_text' + 'after_hover_text'); the middle
 * fragment reveals a decorative image on hover
 * (.hover_text .hover_img { background-image }). The concatenated text and
 * 'link' carry over cleanly to divi/heading (module.advanced.link, the same
 * pattern HeadingConverter uses); the hover-reveal image itself has no Divi
 * heading equivalent, so it's logged as not carried over rather than dropped
 * silently or fabricated as a separate image module the real layout never had.
 */
class WcfTextHoverImageConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_heading_' );
        $settings = $element['settings'] ?? [];

        $before = is_string( $settings['before_hover_text'] ?? null ) ? ( $settings['before_hover_text'] ?? '' ) : '';
        $hover  = is_string( $settings['hover_text'] ?? null ) ? ( $settings['hover_text'] ?? '' ) : '';
        $after  = is_string( $settings['after_hover_text'] ?? null ) ? ( $settings['after_hover_text'] ?? '' ) : '';
        $text   = $before . $hover . $after;

        $tag = is_string( $settings['html_tag'] ?? null ) ? ( $settings['html_tag'] ?? 'h2' ) : 'h2';

        $title_attrs = [
            'innerContent' => [ 'desktop' => [ 'value' => $text ] ],
            'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => $tag ] ] ] ] ],
        ];

        $attrs = [ 'title' => $title_attrs ];

        $link     = is_array( $settings['link'] ?? null ) ? $settings['link'] : [];
        $link_url = is_string( $link['url'] ?? '' ) ? trim( (string) ( $link['url'] ?? '' ) ) : '';
        if ( $link_url !== '' ) {
            $attrs['module']['advanced']['link']['desktop']['value'] = [ 'url' => $link_url ];
        }

        $image = is_array( $settings['image'] ?? null ) ? $settings['image'] : [];
        $image_url = is_string( $image['url'] ?? '' ) ? ( $image['url'] ?? '' ) : '';
        if ( $image_url !== '' ) {
            $this->engine->logNotCarriedOver( 'hover_reveal_image', (string) $id, "the hover-reveal image on '{$hover}' has no divi/heading equivalent" );
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, [
            'before_hover_text', 'hover_text', 'after_hover_text', 'html_tag', 'link', 'image',
            // wcf--t-h-image's own layout and colour controls (text-hover-image.php).
            'align', 'title_color', 'title_hover_color', 'title_typography_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
