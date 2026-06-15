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
        $has_icon    = $this->hasIcon( $settings );
        $icon_size   = $this->sizeString( $settings['icon_size'] ?? null );
        $icon_color  = is_string( $settings['icon_color'] ?? '' ) ? ( $settings['icon_color'] ?? '' ) : '';

        $style_result = ( new StyleMapper() )->map( 'blurb', $settings );
        $attrs        = $style_result['divi_attrs'];

        if ( $has_icon ) {
            // Elementor uses FontAwesome icons; Divi 5 has a different icon library.
            // Always fall back to a default Divi star icon rather than trying to map FA classes.
            $icon_content = [
                'innerContent' => [
                    'desktop' => [
                        'value' => [
                            'useIcon' => 'on',
                            'icon'    => [
                                'type'    => 'fa',
                                'unicode' => '&#xf005;',
                                'weight'  => '900',
                            ],
                        ],
                    ],
                ],
            ];
            $advanced = [];
            if ( $icon_color !== '' ) {
                $advanced['color'] = [ 'desktop' => [ 'value' => $icon_color ] ];
            }
            if ( $icon_size !== '' ) {
                $advanced['size'] = [ 'desktop' => [ 'value' => $icon_size ] ];
            }
            if ( ! empty( $advanced ) ) {
                $icon_content['advanced'] = $advanced;
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
                // Elementor group-control type selectors — not CSS properties.
                'title_typography_typography', 'description_typography_typography',
                'pa_condition_repeater', 'icon_space', 'title_bottom_space',
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

    private function hasIcon( array $settings ): bool {
        $selected = $settings['selected_icon'] ?? null;
        if ( is_array( $selected ) ) {
            $val = $selected['value'] ?? '';
            return is_string( $val ) && $val !== '';
        }
        if ( is_string( $selected ) && $selected !== '' ) {
            return true;
        }
        $legacy = $settings['icon'] ?? '';
        return is_string( $legacy ) && $legacy !== '';
    }

    private function sizeString( mixed $raw ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) && $raw['size'] > 0 ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        return '';
    }
}
