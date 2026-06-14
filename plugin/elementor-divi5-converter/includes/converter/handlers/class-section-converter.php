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

        // When a section's single 100%-wide column owns the background image (a
        // common Elementor "hero" pattern), move that background up to the section
        // so it spans the full viewport in Divi.  Must run before the other lifters
        // so the section URL is already set when they check for an existing background.
        $this->liftColumnBackgroundToSection( $element, $settings );

        // Elementor allows image widgets to be placed with _position:absolute so they
        // float behind section content as decorative shapes.  Divi 5 has no equivalent
        // block attribute, so we strip these widgets and promote the first one's URL to
        // the section background image (when no real background is already present).
        $this->liftAbsolutePositionedImages( $element, $settings );

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
     * Detects the "hero column" pattern: a section whose single child is a 100%-wide
     * column that carries the background image while the section itself has none.
     *
     * This is common in Elementor when a full-width section contains a 100% column
     * with a background image + a spacer widget to define height.  In Elementor the
     * column is visually indistinguishable from the section because it fills the whole
     * row.  In Divi 5, column backgrounds are confined to the column box, so the image
     * must be placed on the section to span the full viewport width.
     *
     * The background-related settings are copied to $settings (the section's settings
     * array that StyleMapper will read) and removed from the column element so the
     * column is rendered with no background.
     */
    private function liftColumnBackgroundToSection( array &$element, array &$settings ): void {
        // Skip if the section already has a background image.
        $existing_bg  = $settings['background_image'] ?? [];
        $existing_url = is_array( $existing_bg )
            ? ( $existing_bg['url'] ?? '' )
            : ( is_string( $existing_bg ) ? $existing_bg : '' );
        if ( $existing_url !== '' ) {
            return;
        }

        // Must be exactly one column at 100% width.
        $columns = $element['elements'] ?? [];
        if ( count( $columns ) !== 1 ) {
            return;
        }
        $col = $columns[0];
        if ( ( $col['elType'] ?? '' ) !== 'column' ) {
            return;
        }
        if ( (int) ( $col['settings']['_column_size'] ?? 0 ) !== 100 ) {
            return;
        }

        // The column must have a background image URL.
        $col_bg_image = $col['settings']['background_image'] ?? null;
        $col_bg_url   = is_array( $col_bg_image )
            ? ( $col_bg_image['url'] ?? '' )
            : ( is_string( $col_bg_image ) ? $col_bg_image : '' );
        if ( $col_bg_url === '' ) {
            return;
        }

        // Keys to promote from column → section.
        $lift_keys = [
            'background_background',
            'background_image',
            'background_color',
            'background_position',
            'background_size',
            'background_repeat',
            'background_attachment',
            'background_overlay_background',
            'background_overlay_image',
            'background_overlay_color',
            'background_overlay_opacity',
        ];

        foreach ( $lift_keys as $key ) {
            if ( isset( $col['settings'][ $key ] ) ) {
                $settings[ $key ]                         = $col['settings'][ $key ];
                unset( $element['elements'][0]['settings'][ $key ] );
            }
        }

        $this->engine->logWarning(
            "Section {$element['id']}: lifted background from 100%-column {$col['id']} to section level."
        );
    }

    /**
     * Finds image widgets inside section columns that carry `_position: absolute`,
     * strips them from the column (they cannot be reproduced as Divi 5 block elements),
     * and promotes the first one's URL to the section's background image when no real
     * background image is already present.
     *
     * This pattern is used in Elementor to layer decorative shapes (e.g. a grey geometric
     * graphic) behind section content by placing an image widget with absolute positioning
     * and a negative z-index.
     */
    private function liftAbsolutePositionedImages( array &$element, array &$settings ): void {
        // Determine whether a background image already exists (set by liftColumnBackgroundToSection
        // or by the original export data).
        $existing_url = '';
        $existing_bg  = $settings['background_image'] ?? [];
        if ( is_array( $existing_bg ) ) {
            $existing_url = is_string( $existing_bg['url'] ?? '' ) ? ( $existing_bg['url'] ?? '' ) : '';
        } elseif ( is_string( $existing_bg ) ) {
            $existing_url = $existing_bg;
        }

        $first_url = null;

        foreach ( $element['elements'] as $col_idx => $column ) {
            // Collect indices in reverse so array_splice doesn't shift remaining items.
            $to_remove = [];

            foreach ( $column['elements'] as $widget_idx => $widget ) {
                if ( ( $widget['widgetType'] ?? '' ) !== 'image' ) {
                    continue;
                }
                if ( ( $widget['settings']['_position'] ?? '' ) !== 'absolute' ) {
                    continue;
                }

                $img = $widget['settings']['image'] ?? [];
                $url = is_array( $img )
                    ? ( is_string( $img['url'] ?? '' ) ? ( $img['url'] ?? '' ) : '' )
                    : ( is_string( $img ) ? $img : '' );

                if ( $url !== '' && $first_url === null ) {
                    $first_url = $url;
                }

                $to_remove[] = $widget_idx;
            }

            // Remove in reverse order to keep earlier indices stable.
            foreach ( array_reverse( $to_remove ) as $idx ) {
                array_splice( $element['elements'][ $col_idx ]['elements'], $idx, 1 );
            }
        }

        if ( $first_url === null ) {
            return;
        }

        // Only promote to background image when the section has no existing background
        // image AND no background color — to avoid overriding a solid fill (which may
        // have been resolved from a __globals__ reference) with a decorative shape.
        $existing_color = $settings['background_color'] ?? '';
        if ( $existing_url === '' && ( $existing_color === '' || $existing_color === null ) ) {
            $settings['background_image'] = [ 'url' => $first_url ];
        }
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
                if ( isset( $settings['background_image'] ) && is_array( $settings['background_image'] ) ) {
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
