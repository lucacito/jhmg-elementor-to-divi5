<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Converter\TextHeading;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeadingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        // Animation Addons' wcf--animated-heading (animated-heading.php) uses its
        // own 'heading'/'heading_tag'/'heading_link' keys for the same
        // title/tag/link controls; wcf--title (animated-title.php) happens to
        // already match title/header_size/link exactly.
        $title = $this->getSettingValue( $settings, 'title' );
        if ( $title === '' || $title === null ) {
            $title = $this->getSettingValue( $settings, 'heading', '' );
        }
        // Real Elementor exports use 'header_size'; our fixtures use 'tag'.
        $tag_raw  = $this->getSettingValue( $settings, 'tag', '' );
        $tag      = $tag_raw !== '' ? $tag_raw : $this->getSettingValue( $settings, 'header_size', '' );
        $tag      = $tag !== '' ? $tag : $this->getSettingValue( $settings, 'heading_tag', 'h2' );

        // Elementor's heading falls back to the kit's Primary typography (heading.php).
        $style = ( new StyleMapper() )->map( 'heading', $settings, [ 'elementor_defaults' => true ] );

        // Build title attrs with a stable key order: innerContent → decoration.
        $text        = html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $title_attrs = [
            'innerContent' => [
                'desktop' => [ 'value' => $text ],
            ],
        ];

        // Carry over any decoration keys produced by StyleMapper (e.g. textAlign).
        if ( ! empty( $style['divi_attrs']['title']['decoration'] ) ) {
            $title_attrs['decoration'] = $style['divi_attrs']['title']['decoration'];
        }

        // Heading level lives alongside other font values in the decoration.
        if ( $tag !== '' ) {
            $title_attrs['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        $attrs          = $style['divi_attrs'];
        $attrs['title'] = $title_attrs;

        // Map Elementor heading link → Divi 5 module.advanced.link.
        $link     = is_array( $settings['link'] ?? null ) ? $settings['link'] : ( is_array( $settings['heading_link'] ?? null ) ? $settings['heading_link'] : [] );
        $link_url = is_string( $link['url'] ?? '' ) ? trim( (string) ( $link['url'] ?? '' ) ) : '';
        if ( $link_url !== '' ) {
            $link_value  = [ 'url' => $link_url ];
            $is_external = is_string( $link['is_external'] ?? '' ) ? ( $link['is_external'] ?? '' ) : '';
            if ( $is_external === 'on' || $is_external === 'true' || $is_external === '1' ) {
                $link_value['target'] = '_blank';
            }
            $nofollow = is_string( $link['nofollow'] ?? '' ) ? ( $link['nofollow'] ?? '' ) : '';
            if ( $nofollow === 'on' || $nofollow === 'true' || $nofollow === '1' ) {
                $link_value['rel'] = [ 'nofollow' ];
            }
            $attrs['module']['advanced']['link']['desktop']['value'] = $link_value;
        }

        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'title', 'tag', 'header_size', 'title_tag', 'size', 'link',
                // wcf--animated-heading's own keys, plus its and wcf--title's
                // GSAP-only trigger/colour controls with no Divi equivalent.
                'heading', 'heading_tag', 'heading_link',
                'trigger_type', 'trigger_selector', 'heading_color_mode', 'heading_color',
                'heading_colors', 'heading_color_end', 'blend_mode', 'highlight',
                'highlight_color', 'highlight_blend_mode', 'title_color',
                'show_title_prefix', 'title_prefix_use', 'title_prefix_color',
                'title_prefix_v_alignment', 'title_h_color', 'highlight_h_color', 'prefix_h_color',
            ],
            $style['handled_keys']
        ) );

        // span, p and div: Divi's heading module styles h1-h6 only, so the
        // typography would be lost. TextHeading keeps the tag on a text module.
        if ( ! TextHeading::isHeadingTag( (string) $tag ) ) {
            $this->engine->logConverted( 'text' );
            return TextHeading::block( $id, (string) $tag, esc_html( $text ), $attrs );
        }

        $this->engine->logConverted( 'heading' );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
