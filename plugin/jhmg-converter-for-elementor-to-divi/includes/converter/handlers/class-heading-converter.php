<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeadingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        $title    = $this->getSettingValue( $settings, 'title' );
        // Real Elementor exports use 'header_size'; our fixtures use 'tag'.
        $tag_raw  = $this->getSettingValue( $settings, 'tag', '' );
        $tag      = $tag_raw !== '' ? $tag_raw : $this->getSettingValue( $settings, 'header_size', 'h2' );

        $style = ( new StyleMapper() )->map( 'heading', $settings );

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
        $link     = is_array( $settings['link'] ?? null ) ? $settings['link'] : [];
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

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'title', 'tag', 'header_size', 'title_tag', 'size', 'link' ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
