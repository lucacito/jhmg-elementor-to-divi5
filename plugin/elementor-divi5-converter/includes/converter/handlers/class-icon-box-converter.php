<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IconBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['title_text'] ?? '' ) ? ( $settings['title_text'] ?? '' ) : '';
        $description = is_string( $settings['description_text'] ?? '' ) ? ( $settings['description_text'] ?? '' ) : '';
        $icon_value  = $this->extractIconValue( $settings );
        $icon_size   = $this->sizeString( $settings['icon_size'] ?? null );

        $style_result = ( new StyleMapper() )->map( 'blurb', $settings );
        $attrs        = $style_result['divi_attrs'];

        if ( $icon_value !== '' ) {
            $icon_content = [ 'innerContent' => [ 'desktop' => [ 'value' => $icon_value ] ] ];
            if ( $icon_size !== '' ) {
                $icon_content['advanced'] = [ 'size' => [ 'desktop' => [ 'value' => $icon_size ] ] ];
            }
            $attrs['imageIcon'] = $icon_content;
        }

        if ( $title !== '' ) {
            // Divi blurb has_header_text resolver checks $value['text'], not a plain string.
            $attrs['title']['innerContent']['desktop']['value'] = [ 'text' => $title ];
        }

        if ( $description !== '' ) {
            $attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $description ] ] ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'title_text', 'description_text', 'selected_icon', 'icon',
                'icon_size', 'icon_color', 'hover_animation', 'link_to', 'link',
                'position', 'title_size', 'content_vertical_alignment',
                'title_typography_typography', 'title_typography_font_family',
                'title_typography_font_size', 'title_typography_font_weight',
                'title_typography_line_height', 'title_color',
                'description_typography_typography', 'description_typography_font_size',
                'description_typography_font_weight', 'description_color',
                'pa_condition_repeater', 'premium_tooltip_text', 'premium_tooltip_position',
            ],
            $style_result['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $attrs,
            'elements' => [],
        ];
    }

    private function extractIconValue( array $settings ): string {
        $selected = $settings['selected_icon'] ?? null;
        if ( is_array( $selected ) ) {
            return is_string( $selected['value'] ?? '' ) ? ( $selected['value'] ?? '' ) : '';
        }
        if ( is_string( $selected ) && $selected !== '' ) {
            return $selected;
        }
        $legacy = $settings['icon'] ?? '';
        return is_string( $legacy ) ? $legacy : '';
    }

    private function sizeString( mixed $raw ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) && $raw['size'] > 0 ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        return '';
    }
}
