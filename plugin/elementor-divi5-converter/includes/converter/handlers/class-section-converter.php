<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_section_' );
        $settings = $element['settings'] ?? [];
        $is_inner = ! empty( $element['isInner'] );

        // When a section declares "classic" background but the exported URL is empty
        // (media-library reference lost on export), the banner image is often placed as
        // the first image widget inside a column.  Lift it into background_image so
        // StyleMapper can apply the position/size/repeat settings, and remove the widget
        // so it doesn't also render as a standalone image module.
        $this->liftBannerImageToBackground( $element, $settings );

        $children     = $this->convertChildren( $element );
        $style        = ( new StyleMapper() )->map( 'section', $settings );
        $row_settings = $this->rowSettingsFromColumns( $children );

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings, $style['handled_keys'] );

        // Inner sections live inside a column — emit divi/row directly.
        if ( $is_inner ) {
            return [
                'id'       => $id . '-row',
                'name'     => 'divi/row',
                'settings' => $row_settings,
                'elements' => $children,
            ];
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty section after conversion: {$id}" );
        }

        return [
            'id'       => $id,
            'name'     => 'divi/section',
            'settings' => $style['divi_attrs'],
            'elements' => [
                [
                    'id'       => $id . '-row',
                    'name'     => 'divi/row',
                    'settings' => $row_settings,
                    'elements' => $children,
                ],
            ],
        ];
    }

    /**
     * Detects the pattern where Elementor exported a section with background_background=classic
     * but an empty background_image URL (the media-library image ID wasn't resolved), while the
     * actual banner image was placed as an image widget inside a column.
     *
     * When detected, injects the widget's URL into $settings['background_image'] so StyleMapper
     * applies it as the section background (along with existing position/size/repeat settings),
     * and removes that widget from $element so it won't also render as an image module.
     */
    private function liftBannerImageToBackground( array &$element, array &$settings ): void {
        $bg_type  = $settings['background_background'] ?? '';
        $bg_image = $settings['background_image'] ?? [];
        $bg_url   = is_array( $bg_image )
            ? ( is_string( $bg_image['url'] ?? '' ) ? ( $bg_image['url'] ?? '' ) : '' )
            : ( is_string( $bg_image ) ? $bg_image : '' );

        // Only act when section is "classic" background but has no exported URL.
        if ( $bg_type !== 'classic' || $bg_url !== '' ) {
            return;
        }

        foreach ( $element['elements'] as $col_idx => $column ) {
            foreach ( $column['elements'] as $widget_idx => $widget ) {
                if ( ( $widget['widgetType'] ?? '' ) !== 'image' ) {
                    continue;
                }
                $img = $widget['settings']['image'] ?? [];
                $url = is_array( $img )
                    ? ( is_string( $img['url'] ?? '' ) ? ( $img['url'] ?? '' ) : '' )
                    : ( is_string( $img ) ? $img : '' );
                if ( $url === '' ) {
                    continue;
                }

                // Inject the URL so StyleMapper picks it up with the section's other
                // background settings (position, size, repeat).
                if ( is_array( $settings['background_image'] ) ) {
                    $settings['background_image']['url'] = $url;
                } else {
                    $settings['background_image'] = [ 'url' => $url ];
                }

                // Remove the widget so it is not also emitted as an image module.
                array_splice( $element['elements'][ $col_idx ]['elements'], $widget_idx, 1 );
                return;
            }
        }
    }
}
