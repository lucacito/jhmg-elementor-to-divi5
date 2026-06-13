<?php

namespace ElementorDivi5Converter\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maps Elementor styling settings to Divi 5 block attribute paths.
 *
 * Elementor uses flat keys with `_tablet` / `_mobile` responsive suffixes.
 * Divi 5 uses a nested `{breakpoint}.value` structure with `desktop`, `tablet`,
 * and `phone` breakpoints. This class handles the translation.
 */
class StyleMapper {
    private const BREAKPOINT_MAP = [
        ''        => 'desktop',
        '_tablet' => 'tablet',
        '_mobile' => 'phone',
    ];

    private const SPACING_KEYS = [ 'margin', 'padding' ];

    /**
     * Elementor `_column_size` percentage values → Divi fraction strings.
     * Matches the standard Elementor column widths Divi natively understands.
     */
    private const COLUMN_SIZE_MAP = [
        100 => '4_4',
        80  => '4_5',
        75  => '3_4',
        67  => '2_3',
        66  => '2_3',
        60  => '3_5',
        50  => '1_2',
        40  => '2_5',
        34  => '1_3',
        33  => '1_3',
        25  => '1_4',
        20  => '1_5',
    ];

    /**
     * Maps Elementor widget_type to the Divi 5 attribute path prefix for that
     * element's font decoration (the dotted path up to but not including
     * `.{breakpoint}.value`).
     *
     * - Heading / blurb title use the simple `{element}.decoration.font.font` structure.
     * - Text-editor body copy uses Divi's `bodyFont` sub-group.
     * - Button uses its own `button.decoration.font.font` element.
     */
    private const WIDGET_FONT_PATH = [
        'heading'     => 'title.decoration.font.font',
        'text-editor' => 'content.decoration.bodyFont.body.font',
        'button'      => 'button.decoration.font.font',
        'blurb'       => 'title.decoration.font.font',
    ];

    /**
     * Maps Elementor widget_type to the settings key that holds the primary
     * text/font color for that widget.
     */
    private const WIDGET_COLOR_KEY = [
        'heading'     => 'title_color',
        'text-editor' => 'text_color',
        'button'      => 'button_text_color',
        'blurb'       => 'title_color',
    ];

    /**
     * Elementor `typography_*` keys that are acknowledged but not directly
     * mappable to Divi 5 properties we convert (font family, decorations, etc.).
     * Marking them as handled prevents them appearing in the unmapped log.
     */
    private const TYPOGRAPHY_SKIP_KEYS = [
        'typography_typography',
        'typography_font_family',
        'typography_text_transform',
        'typography_font_style',
        'typography_text_decoration',
    ];

    /** Elementor border control keys whose values we map (base names without breakpoint suffix). */
    private const BORDER_KEYS = [ 'border_border', 'border_width', 'border_color', 'border_radius' ];

    public function map( string $widget_type, array $settings ): array {
        $divi_attrs   = [];
        $handled_keys = [];

        $this->mapSpacing( $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundColor( $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundImage( $settings, $divi_attrs, $handled_keys );
        $this->mapTypography( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapTextColor( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapAlignment( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapBorder( $settings, $divi_attrs, $handled_keys );

        if ( $widget_type === 'column' ) {
            $this->mapColumnSize( $settings, $divi_attrs, $handled_keys );
        }

        if ( $widget_type === 'section' ) {
            $this->markSectionKeys( $settings, $handled_keys );
        }

        return [
            'divi_attrs'   => $divi_attrs,
            'handled_keys' => $handled_keys,
        ];
    }

    /**
     * Convert an Elementor `_column_size` percentage to a Divi fraction string.
     * Returns null when size is absent or unrecognized.
     */
    public static function columnSizeToFraction( int $size ): ?string {
        return self::COLUMN_SIZE_MAP[ $size ] ?? null;
    }

    // -------------------------------------------------------------------------
    // Per-property mappers
    // -------------------------------------------------------------------------

    private function mapSpacing( array $settings, array &$attrs, array &$handled ): void {
        foreach ( self::SPACING_KEYS as $prop ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $key = $prop . $suffix;
                $handled[] = $key;

                if ( ! isset( $settings[ $key ] ) || $settings[ $key ] === '' || $settings[ $key ] === [] ) {
                    continue;
                }

                $value = $this->normalizeSpacingValue( $settings[ $key ] );
                if ( $value === null ) {
                    continue;
                }

                self::transformPath( $attrs, "module.decoration.spacing.{$breakpoint}.value.{$prop}", $value );
            }
        }
    }

    private function mapColumnSize( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = '_column_size';

        $raw = $settings['_column_size'] ?? null;
        if ( $raw === null || ! is_numeric( $raw ) ) {
            return;
        }

        $fraction = self::COLUMN_SIZE_MAP[ (int) $raw ] ?? null;
        if ( $fraction === null ) {
            return;
        }

        self::transformPath( $attrs, 'module.advanced.type.desktop.value', $fraction );
    }

    private function mapBackgroundColor( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'background_color';

        $color = $settings['background_color'] ?? '';
        if ( ! is_string( $color ) || $color === '' ) {
            return;
        }

        self::transformPath( $attrs, 'module.decoration.background.desktop.value.color', $color );
    }

    /**
     * Maps the standard Elementor `typography_*` control group to the Divi 5
     * font decoration path for the widget's primary text element.
     *
     * Responsive variants use the BREAKPOINT_MAP suffix convention:
     *   typography_font_size          → desktop
     *   typography_font_size_tablet   → tablet
     *   typography_font_size_mobile   → phone
     *
     * Font weight is desktop-only (Elementor doesn't make weight responsive by
     * default) but we mark all suffixed variants as handled so they don't appear
     * in the unmapped log if a widget does export them.
     */
    private function mapTypography( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        // Always mark skip keys as handled regardless of widget type.
        foreach ( self::TYPOGRAPHY_SKIP_KEYS as $skip ) {
            $handled[] = $skip;
        }

        $font_path = self::WIDGET_FONT_PATH[ $widget_type ] ?? null;

        // Responsive size-like props (font-size, line-height, letter-spacing).
        $size_props = [
            'typography_font_size'      => 'size',
            'typography_line_height'    => 'lineHeight',
            'typography_letter_spacing' => 'letterSpacing',
        ];

        foreach ( $size_props as $base_key => $divi_prop ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $key       = $base_key . $suffix;
                $handled[] = $key;

                if ( $font_path === null ) {
                    continue;
                }

                $raw   = $settings[ $key ] ?? null;
                $value = $this->parseSizeValue( $raw );
                if ( $value !== '' ) {
                    self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.{$divi_prop}", $value );
                }
            }
        }

        // Font weight — desktop only; mark tablet/mobile handled to suppress log noise.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $handled[] = 'typography_font_weight' . $suffix;
        }

        if ( $font_path !== null ) {
            $weight = $settings['typography_font_weight'] ?? '';
            if ( is_string( $weight ) && $weight !== '' ) {
                self::transformPath( $attrs, "{$font_path}.desktop.value.weight", $weight );
            }
        }
    }

    /**
     * Maps the widget-specific text/font color control to the Divi 5 font color
     * path for that widget type's primary element.
     */
    private function mapTextColor( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $color_key = self::WIDGET_COLOR_KEY[ $widget_type ] ?? null;
        $font_path = self::WIDGET_FONT_PATH[ $widget_type ] ?? null;

        if ( $color_key === null || $font_path === null ) {
            return;
        }

        $handled[] = $color_key;

        $color = $settings[ $color_key ] ?? '';
        if ( ! is_string( $color ) || $color === '' ) {
            return;
        }

        self::transformPath( $attrs, "{$font_path}.desktop.value.color", $color );
    }

    /**
     * Maps the Elementor `align` (or `text_align`) control.
     *
     * - Heading: writes to `title.decoration.font.font.*.value.textAlign`.
     * - All others: writes to `module.advanced.text.text.*.value.orientation`.
     *   'justify' has no Divi equivalent; falls back to 'left'.
     *
     * Responsive variants (`align_tablet`, `align_mobile`) are handled when
     * present, matching the BREAKPOINT_MAP suffix pattern.
     */
    private function mapAlignment( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $base_keys = [ 'align', 'text_align' ];

        foreach ( $base_keys as $base ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $handled[] = $base . $suffix;
            }
        }

        // Primary value: prefer 'align', fall back to 'text_align'.
        $align = $settings['align'] ?? $settings['text_align'] ?? '';
        if ( ! is_string( $align ) || $align === '' ) {
            return;
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $bp_val = $settings[ 'align' . $suffix ] ?? $settings[ 'text_align' . $suffix ] ?? null;
            $value  = is_string( $bp_val ) && $bp_val !== '' ? $bp_val : ( $suffix === '' ? $align : null );
            if ( $value === null ) {
                continue;
            }

            if ( $widget_type === 'heading' ) {
                self::transformPath( $attrs, "title.decoration.font.font.{$breakpoint}.value.textAlign", $value );
            } else {
                $orientation = ( $value === 'justify' ) ? 'left' : $value;
                self::transformPath( $attrs, "module.advanced.text.text.{$breakpoint}.value.orientation", $orientation );
            }
        }
    }

    /**
     * Maps Elementor `background_image` (and related position/size/repeat controls)
     * to `module.decoration.background.desktop.value.image.*`.
     *
     * Responsive image variants (_tablet, _mobile) are marked handled but not
     * mapped since they are uncommon and rarely set in real Elementor exports.
     */
    private function mapBackgroundImage( array $settings, array &$attrs, array &$handled ): void {
        // Mark all related keys as handled regardless.
        foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
            $handled[] = 'background_image' . $suffix;
            $handled[] = 'background_position' . $suffix;
            $handled[] = 'background_size' . $suffix;
            $handled[] = 'background_repeat' . $suffix;
        }
        $handled[] = 'background_background';

        $image = $settings['background_image'] ?? null;
        $url   = '';
        if ( is_array( $image ) ) {
            $url = is_string( $image['url'] ?? '' ) ? ( $image['url'] ?? '' ) : '';
        } elseif ( is_string( $image ) ) {
            $url = $image;
        }

        if ( $url === '' ) {
            return;
        }

        self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.url', $url );

        // Position: Elementor "center center" → Divi "center|center".
        $pos = $settings['background_position'] ?? '';
        if ( is_string( $pos ) && $pos !== '' ) {
            self::transformPath(
                $attrs,
                'module.decoration.background.desktop.value.image.position',
                str_replace( ' ', '|', $pos )
            );
        }
    }

    /**
     * Silently absorbs section-level Elementor keys that have no Divi equivalent,
     * preventing them from appearing in the unmapped-settings log.
     */
    private function markSectionKeys( array $settings, array &$handled ): void {
        // Keys that describe layout/structure hints with no direct Divi mapping.
        $ignore = [
            'gap', 'structure', 'reverse_order_mobile',
            'background_overlay_background', 'background_overlay_color',
            'background_overlay_opacity',
        ];
        foreach ( $ignore as $key ) {
            $handled[] = $key;
        }

        // Parallax / motion FX: prefix match.
        foreach ( array_keys( $settings ) as $key ) {
            if ( str_starts_with( $key, 'background_motion_fx_' ) ) {
                $handled[] = $key;
            }
        }
    }

    /**
     * Maps Elementor border controls to `module.decoration.border`.
     *
     * Elementor groups border style, width, color, and radius under keys with
     * the standard `_tablet` / `_mobile` responsive suffixes:
     *
     *   border_border        → Divi styles.all.style   (e.g. 'solid')
     *   border_width         → Divi styles.{side}.width (uniform → .all, per-side → individual)
     *   border_color         → Divi styles.all.color
     *   border_radius        → Divi radius              ({topLeft, topRight, bottomRight, bottomLeft})
     */
    private function mapBorder( array $settings, array &$attrs, array &$handled ): void {
        foreach ( self::BORDER_KEYS as $base ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $handled[] = $base . $suffix;
            }
        }
        // Also absorb 'border_style' as an alias for 'border_border'.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $handled[] = 'border_style' . $suffix;
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $base_path = "module.decoration.border.{$breakpoint}.value";

            // Style (border type).
            $style = $settings[ 'border_border' . $suffix ] ?? $settings[ 'border_style' . $suffix ] ?? '';
            if ( is_string( $style ) && $style !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.style", $style );
            }

            // Color.
            $color = $settings[ 'border_color' . $suffix ] ?? '';
            if ( is_string( $color ) && $color !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.color", $color );
            }

            // Width.
            $width_raw = $settings[ 'border_width' . $suffix ] ?? null;
            if ( is_array( $width_raw ) ) {
                $this->applyBorderWidth( $width_raw, $attrs, "{$base_path}.styles" );
            } elseif ( is_string( $width_raw ) && $width_raw !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.width", $width_raw );
            }

            // Radius — Divi 5 expects per-corner object {topLeft, topRight, bottomRight, bottomLeft}.
            $radius_raw = $settings[ 'border_radius' . $suffix ] ?? null;
            if ( is_array( $radius_raw ) ) {
                $radius_obj = $this->normalizeRadius( $radius_raw );
                if ( ! empty( $radius_obj ) ) {
                    self::transformPath( $attrs, "{$base_path}.radius", $radius_obj );
                }
            } elseif ( is_string( $radius_raw ) && $radius_raw !== '' ) {
                // Plain string shorthand — apply uniformly.
                $radius_obj = [
                    'topLeft'     => $radius_raw,
                    'topRight'    => $radius_raw,
                    'bottomRight' => $radius_raw,
                    'bottomLeft'  => $radius_raw,
                ];
                self::transformPath( $attrs, "{$base_path}.radius", $radius_obj );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Value normalizers
    // -------------------------------------------------------------------------

    /**
     * Convert Elementor spacing value `{top, right, bottom, left, unit}` to
     * Divi 5 format `{top, right, bottom, left, syncVertical, syncHorizontal}`.
     */
    private function normalizeSpacingValue( mixed $raw ): ?array {
        if ( ! is_array( $raw ) ) {
            return null;
        }

        $unit   = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
        $result = [];

        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $val           = $raw[ $side ] ?? '';
            $result[$side] = ( $val !== '' && $val !== null ) ? ( (string) $val . $unit ) : '';
        }

        $result['syncVertical']   = 'off';
        $result['syncHorizontal'] = 'off';

        return $result;
    }

    /**
     * Convert an Elementor responsive size object `{size, unit}` to a CSS string.
     * Returns an empty string when the value cannot be parsed.
     */
    private function parseSizeValue( mixed $raw ): string {
        if ( is_array( $raw ) && isset( $raw['size'] ) ) {
            $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            return (string) $raw['size'] . $unit;
        }
        if ( is_string( $raw ) && $raw !== '' ) {
            return $raw;
        }
        return '';
    }

    /**
     * Apply an Elementor border-width object `{top, right, bottom, left, unit}`
     * to the Divi `styles` nested array.
     *
     * When all four sides share the same value, writes to `styles.all.width`.
     * When sides differ, writes individual per-side entries so that border style
     * and color can still be set on `.all` separately.
     */
    private function applyBorderWidth( array $raw, array &$attrs, string $styles_path ): void {
        $unit   = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
        $sides  = [
            'top'    => (string) ( $raw['top'] ?? '' ),
            'right'  => (string) ( $raw['right'] ?? '' ),
            'bottom' => (string) ( $raw['bottom'] ?? '' ),
            'left'   => (string) ( $raw['left'] ?? '' ),
        ];
        $filled = array_filter( $sides, fn( string $v ) => $v !== '' );

        if ( empty( $filled ) ) {
            return;
        }

        $unique = array_unique( array_values( $filled ) );

        if ( count( $unique ) === 1 ) {
            self::transformPath( $attrs, "{$styles_path}.all.width", reset( $unique ) . $unit );
        } else {
            foreach ( $sides as $side => $val ) {
                if ( $val !== '' ) {
                    self::transformPath( $attrs, "{$styles_path}.{$side}.width", $val . $unit );
                }
            }
        }
    }

    /**
     * Convert an Elementor border-radius object `{top, right, bottom, left, unit}`
     * to the Divi 5 per-corner format `{topLeft, topRight, bottomRight, bottomLeft}`.
     *
     * Elementor's top/right/bottom/left match CSS border-radius shorthand corners:
     * top-left, top-right, bottom-right, bottom-left.
     *
     * Returns an empty array when no values are present.
     */
    private function normalizeRadius( array $raw ): array {
        $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';

        $corners = [
            'topLeft'     => (string) ( $raw['top']    ?? '' ),
            'topRight'    => (string) ( $raw['right']  ?? '' ),
            'bottomRight' => (string) ( $raw['bottom'] ?? '' ),
            'bottomLeft'  => (string) ( $raw['left']   ?? '' ),
        ];

        $has_value = array_filter( $corners, fn( string $v ) => $v !== '' );
        if ( empty( $has_value ) ) {
            return [];
        }

        return array_map(
            fn( string $v ) => $v !== '' ? $v . $unit : '0px',
            $corners
        );
    }

    // -------------------------------------------------------------------------
    // Path writer
    // -------------------------------------------------------------------------

    /**
     * Write a value into a nested array using a dot-separated path string.
     *
     * Example: transformPath($arr, 'a.b.c', 'x') sets $arr['a']['b']['c'] = 'x'.
     */
    private static function transformPath( array &$target, string $dot_path, mixed $value ): void {
        $keys    = explode( '.', $dot_path );
        $current = &$target;

        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $current ) || ! is_array( $current[ $key ] ) ) {
                $current[ $key ] = [];
            }
            $current = &$current[ $key ];
        }

        $current = $value;
    }
}
