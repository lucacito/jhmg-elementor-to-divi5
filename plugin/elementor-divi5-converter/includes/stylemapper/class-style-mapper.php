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
     * Elementor `typography_*` keys that are acknowledged but deliberately not
     * mapped (preset selector names that have no per-block Divi 5 equivalent).
     */
    private const TYPOGRAPHY_SKIP_KEYS = [
        'typography_typography',
    ];

    /**
     * Maps Elementor `typography_text_decoration` values to the flag name used
     * in Divi 5's `font.{bp}.value.style` array.
     */
    private const DECORATION_TO_DIVI_FLAG = [
        'underline'    => 'underline',
        'line-through' => 'strikethrough',
        'overline'     => 'underline', // best approximation
    ];

    /** Elementor border control keys whose values we map (base names without breakpoint suffix). */
    private const BORDER_KEYS = [ 'border_border', 'border_width', 'border_color', 'border_radius' ];

    public function map( string $widget_type, array $settings ): array {
        $divi_attrs   = [];
        $handled_keys = [];

        $this->mapSpacing( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundColor( $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundImage( $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundGradient( $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundOverlay( $settings, $divi_attrs, $handled_keys );
        $this->mapTypography( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapTextColor( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapAlignment( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapBorder( $settings, $divi_attrs, $handled_keys );
        $this->mapBoxShadow( $settings, $divi_attrs, $handled_keys );
        $this->mapTextShadow( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapOpacity( $settings, $divi_attrs, $handled_keys );

        if ( in_array( $widget_type, [ 'section', 'column' ], true ) ) {
            $this->mapMinHeight( $settings, $divi_attrs, $handled_keys );
        }

        if ( $widget_type === 'column' ) {
            $this->mapColumnSize( $settings, $divi_attrs, $handled_keys );
            $this->markColumnKeys( $settings, $handled_keys );
        }

        if ( $widget_type === 'section' ) {
            $this->markSectionKeys( $settings, $handled_keys );
        }

        if ( $widget_type === 'image' ) {
            $this->mapImageBorderRadius( $settings, $divi_attrs, $handled_keys );
        }

        if ( $widget_type === 'button' ) {
            $this->mapButtonPadding( $settings, $divi_attrs, $handled_keys );
            $this->mapButtonBackground( $settings, $divi_attrs, $handled_keys );
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

    private function mapSpacing( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        // Divi 5 rows use flex layout and manage inter-column spacing via their own
        // gutter system. Column margin-right/left applied with !important breaks that
        // layout — so skip margin entirely for columns (padding is safe to keep).
        $props = $widget_type === 'column'
            ? [ 'padding' ]
            : self::SPACING_KEYS;

        // Mark both standard keys and their Elementor Advanced-tab underscore variants
        // (_padding, _margin) as handled for every widget type.
        foreach ( self::SPACING_KEYS as $prop ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
                $handled[] = $prop . $suffix;
                $handled[] = '_' . $prop . $suffix;
            }
        }

        foreach ( $props as $prop ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                // Prefer the widget-specific key; fall back to the Advanced-tab
                // prefixed variant (_padding/_margin) when the plain key is absent.
                $value = $settings[ $prop . $suffix ] ?? $settings[ '_' . $prop . $suffix ] ?? null;

                if ( $value === '' || $value === [] || $value === null ) {
                    continue;
                }

                $normalized = $this->normalizeSpacingValue( $value );
                if ( $normalized === null ) {
                    continue;
                }

                self::transformPath( $attrs, "module.decoration.spacing.{$breakpoint}.value.{$prop}", $normalized );
            }
        }
    }

    private function mapColumnSize( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = '_column_size';
        // _inline_size is Elementor's flex-basis override (arbitrary percentage).
        // Divi 5 only supports standard fraction strings, so we acknowledge these
        // keys without mapping them to avoid false "skipped" reports.
        $handled[] = '_inline_size';
        $handled[] = '_inline_size_tablet';
        $handled[] = '_inline_size_mobile';

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

        // When background type is gradient, background_color is the first gradient stop —
        // it should not ALSO be written as a solid color (mapBackgroundGradient handles it).
        if ( ( $settings['background_background'] ?? '' ) === 'gradient' ) {
            return;
        }

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

        // Font weight — responsive; Elementor rarely exports tablet/mobile but we mark all as handled.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = 'typography_font_weight' . $suffix;
            $handled[] = $key;

            if ( $font_path !== null ) {
                $weight = $settings[ $key ] ?? '';
                if ( is_string( $weight ) && $weight !== '' ) {
                    self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.weight", $weight );
                }
            }
        }

        // Font family — mark all breakpoints handled; map when present.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = 'typography_font_family' . $suffix;
            $handled[] = $key;

            if ( $font_path !== null ) {
                $family = $settings[ $key ] ?? '';
                if ( is_string( $family ) && $family !== '' ) {
                    self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.family", $family );
                }
            }
        }

        // Style flags array: italic, text-transform, text-decoration — per breakpoint.
        // Divi 5 stores these as an array of flag strings on font.{bp}.value.style.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $style_key      = 'typography_font_style' . $suffix;
            $transform_key  = 'typography_text_transform' . $suffix;
            $decoration_key = 'typography_text_decoration' . $suffix;

            $handled[] = $style_key;
            $handled[] = $transform_key;
            $handled[] = $decoration_key;

            if ( $font_path === null ) {
                continue;
            }

            $flags = [];

            $font_style = is_string( $settings[ $style_key ] ?? '' ) ? ( $settings[ $style_key ] ?? '' ) : '';
            if ( $font_style === 'italic' ) {
                $flags[] = 'italic';
            }

            $transform = is_string( $settings[ $transform_key ] ?? '' ) ? ( $settings[ $transform_key ] ?? '' ) : '';
            if ( in_array( $transform, [ 'uppercase', 'lowercase', 'capitalize' ], true ) ) {
                $flags[] = $transform;
            }

            $decoration = is_string( $settings[ $decoration_key ] ?? '' ) ? ( $settings[ $decoration_key ] ?? '' ) : '';
            $divi_flag  = self::DECORATION_TO_DIVI_FLAG[ $decoration ] ?? '';
            if ( $divi_flag !== '' ) {
                $flags[] = $divi_flag;
            }

            if ( ! empty( $flags ) ) {
                self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.style", $flags );
            }
        }
    }

    /**
     * Maps the widget-specific text/font color control to the Divi 5 font color path.
     *
     * Button font color goes to `button.decoration.button.{breakpoint}.value.font.color`
     * (the button-specific decoration sub-group), not the generic font.font path.
     * All other widgets use `{font_path}.desktop.value.color` as before.
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

        if ( $widget_type === 'button' ) {
            // Button font color lives inside the button's own decoration sub-group.
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                self::transformPath( $attrs, "button.decoration.button.{$breakpoint}.value.font.color", $color );
            }
        } else {
            self::transformPath( $attrs, "{$font_path}.desktop.value.color", $color );
        }
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
            } elseif ( $widget_type === 'button' ) {
                // Button alignment controls button position in its container, not text orientation.
                self::transformPath( $attrs, "module.advanced.alignment.{$breakpoint}.value", $value );
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
        // Mark all image and gradient background keys as handled regardless of value.
        foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
            $handled[] = 'background_image' . $suffix;
            $handled[] = 'background_position' . $suffix;
            $handled[] = 'background_size' . $suffix;
            $handled[] = 'background_repeat' . $suffix;
        }
        $handled[] = 'background_background';
        // Gradient-specific keys (handled here globally so mapBackgroundGradient doesn't double-mark).
        foreach ( [
            'background_color_b',
            'background_gradient_type', 'background_gradient_angle',
            'background_gradient_position', 'background_gradient_stops',
            'background_gradient_color', 'background_gradient_color_b',
        ] as $k ) {
            $handled[] = $k;
        }

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
     * Maps Elementor background overlay (color + optional image) to Divi 5 gradient.
     *
     * Two Elementor patterns:
     * - Pattern A: image in `background_overlay_image`, color in `background_overlay_color`
     * - Pattern B: image already in `background_image`, color overlay on top
     * - No image: overlay color converted to rgba applied as plain background color
     *
     * Divi 5 renders the gradient on top of the background image when
     * `gradient.overlaysImage` is "on".
     */
    private function mapBackgroundOverlay( array $settings, array &$attrs, array &$handled ): void {
        foreach ( [
            'background_overlay_background',
            'background_overlay_color',
            'background_overlay_opacity',
            'background_overlay_image',
            'background_overlay_position',
            'background_overlay_size',
            'background_overlay_repeat',
            'background_overlay_blend_mode',
            // Extended offset / sizing controls — acknowledged without mapping.
            'background_overlay_xpos',
            'background_overlay_ypos',
            'background_overlay_bg_width',
            'background_overlay_xpos_mobile',
            'background_overlay_ypos_mobile',
            'background_overlay_bg_width_mobile',
            'background_overlay_xpos_tablet',
            'background_overlay_ypos_tablet',
            'background_overlay_bg_width_tablet',
        ] as $k ) {
            $handled[] = $k;
        }

        $overlay_color = $settings['background_overlay_color'] ?? '';
        if ( ! is_string( $overlay_color ) || $overlay_color === '' ) {
            return;
        }

        $opacity_raw = $settings['background_overlay_opacity'] ?? null;
        $opacity     = 1.0;
        if ( is_array( $opacity_raw ) && isset( $opacity_raw['size'] ) ) {
            $opacity = (float) $opacity_raw['size'];
        } elseif ( is_numeric( $opacity_raw ) ) {
            $opacity = (float) $opacity_raw;
        }

        // Pattern A: image lives in the overlay_image field.
        $overlay_image     = $settings['background_overlay_image'] ?? null;
        $overlay_image_url = '';
        if ( is_array( $overlay_image ) && ! empty( $overlay_image['url'] ) ) {
            $overlay_image_url = (string) $overlay_image['url'];
        }

        // Pattern B: image lives in the standard background_image field.
        $bg_image     = $settings['background_image'] ?? null;
        $bg_image_url = '';
        if ( is_array( $bg_image ) && ! empty( $bg_image['url'] ) ) {
            $bg_image_url = (string) $bg_image['url'];
        }

        $rgba = $this->hexToRgba( $overlay_color, $opacity );

        if ( $overlay_image_url !== '' ) {
            // Pattern A: write the overlay image as the background image.
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.url', $overlay_image_url );
        }

        if ( $overlay_image_url !== '' || $bg_image_url !== '' ) {
            // Gradient overlay on top of background image.
            $stops = [
                [ 'color' => $rgba, 'position' => '0%' ],
                [ 'color' => $rgba, 'position' => '100%' ],
            ];
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.gradient.enabled', 'on' );
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.gradient.overlaysImage', 'on' );
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.gradient.type', 'linear' );
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.gradient.stops', $stops );
        } else {
            // No image — apply rgba color as semi-transparent background.
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.color', $rgba );
        }
    }

    private function hexToRgba( string $hex, float $opacity ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . (string) round( $opacity, 4 ) . ')';
    }

    /**
     * Silently absorbs section-level Elementor keys that have no Divi equivalent,
     * preventing them from appearing in the unmapped-settings log.
     */
    private function markSectionKeys( array $settings, array &$handled ): void {
        $ignore = [
            'gap', 'structure', 'reverse_order_mobile',
            'layout', 'stretch_section', 'content_width',
            // Custom column gap — Divi manages gutter differently.
            'gap_columns_custom',
            // Section height mode selector ('default', 'min-height', 'full', 'fit-to-content').
            // The actual min-height pixel value is mapped by mapMinHeight().
            'height', 'height_tablet', 'height_mobile',
            // Inner section height — no direct Divi 5 equivalent.
            'height_inner', 'custom_height_inner', 'custom_height_inner_tablet',
            // Vertical alignment of section content — no direct Divi 5 section attr.
            'content_position',
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
     * Silently absorbs column-level Elementor keys that have no direct Divi 5
     * equivalent, preventing them from appearing in the unmapped-settings log.
     */
    private function markColumnKeys( array $settings, array &$handled ): void {
        foreach ( [
            // Elementor widget-wrap gutter — Divi manages this via row settings.
            'space_between_widgets',
            // Vertical alignment of column content — no Divi 5 column attr.
            'content_position', 'content_position_tablet',
            // HTML tag override for the column wrapper.
            'html_tag',
            // Column height mode selector — actual pixel value mapped by mapMinHeight().
            'height', 'height_tablet', 'height_mobile',
            // Column-level default colour overrides (heading, body, link).
            'heading_color', 'color_text', 'color_link',
            // Granular background position offsets beyond the main position string.
            'background_ypos', 'background_xpos', 'background_bg_width',
        ] as $key ) {
            $handled[] = $key;
        }
    }

    /**
     * Maps the Elementor `image_border_radius` control (image widget) to the
     * Divi 5 border radius path `module.decoration.border.desktop.value.radius`.
     *
     * Also marks `image_size` as handled (preset names like "large" / "full" have
     * no direct Divi 5 block attribute equivalent).
     */
    private function mapImageBorderRadius( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'image_border_radius';
        $handled[] = 'image_size';

        $raw = $settings['image_border_radius'] ?? null;
        if ( $raw === null ) {
            return;
        }

        if ( is_array( $raw ) ) {
            $radius = $this->normalizeRadius( $raw );
            if ( ! empty( $radius ) ) {
                self::transformPath( $attrs, 'module.decoration.border.desktop.value.radius', $radius );
            }
        } elseif ( is_string( $raw ) && $raw !== '' ) {
            self::transformPath( $attrs, 'module.decoration.border.desktop.value.radius', [
                'topLeft'     => $raw,
                'topRight'    => $raw,
                'bottomRight' => $raw,
                'bottomLeft'  => $raw,
            ] );
        }
    }

    /**
     * Maps the Elementor `text_padding` control (button widget inner padding) to
     * `button.decoration.spacing.{breakpoint}.value.padding` in Divi 5.
     *
     * Responsive variants (`text_padding_tablet`, `text_padding_mobile`) are
     * handled using the standard BREAKPOINT_MAP suffix convention.
     */
    private function mapButtonPadding( array $settings, array &$attrs, array &$handled ): void {
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = 'text_padding' . $suffix;
            $handled[] = $key;

            $raw = $settings[ $key ] ?? null;
            if ( $raw === null || $raw === '' || $raw === [] ) {
                continue;
            }

            $value = $this->normalizeSpacingValue( $raw );
            if ( $value === null ) {
                continue;
            }

            self::transformPath( $attrs, "button.decoration.spacing.{$breakpoint}.value.padding", $value );
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
                // Advanced-tab border keys (underscore prefix) map to the same Divi path.
                $handled[] = '_' . $base . $suffix;
            }
        }
        // Also absorb 'border_style' / '_border_style' as aliases for 'border_border'.
        foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
            $handled[] = 'border_style' . $suffix;
            $handled[] = '_border_style' . $suffix;
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $base_path = "module.decoration.border.{$breakpoint}.value";

            // Style (border type). Widget-level key takes precedence; fall back to Advanced-tab
            // underscore variant so decorative borders set via the Advanced tab are preserved.
            $style = $settings[ 'border_border' . $suffix ]
                ?? $settings[ '_border_border' . $suffix ]
                ?? $settings[ 'border_style' . $suffix ]
                ?? $settings[ '_border_style' . $suffix ]
                ?? '';
            if ( is_string( $style ) && $style !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.style", $style );
            }

            // Color.
            $color = $settings[ 'border_color' . $suffix ] ?? $settings[ '_border_color' . $suffix ] ?? '';
            if ( is_string( $color ) && $color !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.color", $color );
            }

            // Width.
            $width_raw = $settings[ 'border_width' . $suffix ] ?? $settings[ '_border_width' . $suffix ] ?? null;
            if ( is_array( $width_raw ) ) {
                $this->applyBorderWidth( $width_raw, $attrs, "{$base_path}.styles" );
            } elseif ( is_string( $width_raw ) && $width_raw !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.width", $width_raw );
            }

            // Radius — Divi 5 expects per-corner object {topLeft, topRight, bottomRight, bottomLeft}.
            $radius_raw = $settings[ 'border_radius' . $suffix ] ?? $settings[ '_border_radius' . $suffix ] ?? null;
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

    /**
     * Maps Elementor `background_background: gradient` settings to Divi 5
     * `module.decoration.background.desktop.value.gradient.*`.
     *
     * Handles two Elementor formats:
     * - stops array: `background_gradient_stops` (Elementor 3.x)
     * - two-color shorthand: `background_color` + `background_color_b`
     */
    private function mapBackgroundGradient( array $settings, array &$attrs, array &$handled ): void {
        if ( ( $settings['background_background'] ?? '' ) !== 'gradient' ) {
            return;
        }

        $grad_type = is_string( $settings['background_gradient_type'] ?? '' )
            ? ( $settings['background_gradient_type'] ?? 'linear' )
            : 'linear';
        if ( $grad_type === '' ) {
            $grad_type = 'linear';
        }

        // Direction / angle.
        $direction = '180deg';
        if ( $grad_type === 'linear' ) {
            $angle_raw = $settings['background_gradient_angle'] ?? null;
            if ( is_array( $angle_raw ) && isset( $angle_raw['size'] ) ) {
                $direction = (string) $angle_raw['size'] . ( $angle_raw['unit'] ?? 'deg' );
            } elseif ( is_string( $angle_raw ) && $angle_raw !== '' ) {
                $direction = $angle_raw;
            }
        } elseif ( $grad_type === 'radial' ) {
            $pos       = is_string( $settings['background_gradient_position'] ?? '' )
                ? ( $settings['background_gradient_position'] ?? 'center center' )
                : 'center center';
            $direction = $pos !== '' ? $pos : 'center center';
        }

        // Build stops array.
        $stops     = [];
        $raw_stops = $settings['background_gradient_stops'] ?? [];
        if ( is_array( $raw_stops ) && ! empty( $raw_stops ) ) {
            foreach ( $raw_stops as $stop ) {
                if ( ! is_array( $stop ) ) {
                    continue;
                }
                $color = is_string( $stop['color'] ?? '' ) ? ( $stop['color'] ?? '' ) : '';
                if ( $color === '' ) {
                    continue;
                }
                $pos_raw = $stop['position'] ?? null;
                $pos_str = '0%';
                if ( is_array( $pos_raw ) && isset( $pos_raw['size'] ) ) {
                    $pos_str = (string) $pos_raw['size'] . ( $pos_raw['unit'] ?? '%' );
                } elseif ( is_string( $pos_raw ) && $pos_raw !== '' ) {
                    $pos_str = $pos_raw;
                }
                $stops[] = [ 'color' => $color, 'position' => $pos_str ];
            }
        }

        // Fallback: two-color shorthand.
        if ( empty( $stops ) ) {
            $c1_key = 'background_gradient_color';
            $c2_key = 'background_gradient_color_b';
            $c1 = is_string( $settings[ $c1_key ] ?? $settings['background_color'] ?? '' )
                ? ( $settings[ $c1_key ] ?? $settings['background_color'] ?? '' )
                : '';
            $c2 = is_string( $settings[ $c2_key ] ?? $settings['background_color_b'] ?? '' )
                ? ( $settings[ $c2_key ] ?? $settings['background_color_b'] ?? '' )
                : '';
            if ( $c1 !== '' ) {
                $stops[] = [ 'color' => $c1, 'position' => '0%' ];
            }
            if ( $c2 !== '' ) {
                $stops[] = [ 'color' => $c2, 'position' => '100%' ];
            }
        }

        if ( empty( $stops ) ) {
            return;
        }

        $base = 'module.decoration.background.desktop.value.gradient';
        self::transformPath( $attrs, "{$base}.enabled", 'on' );
        self::transformPath( $attrs, "{$base}.type", $grad_type );
        if ( $grad_type === 'radial' ) {
            self::transformPath( $attrs, "{$base}.directionRadial", $direction );
        } else {
            self::transformPath( $attrs, "{$base}.direction", $direction );
        }
        self::transformPath( $attrs, "{$base}.stops", $stops );
    }

    /**
     * Maps the Elementor box-shadow control to Divi 5
     * `module.decoration.boxShadow.{breakpoint}.value.*`.
     *
     * Elementor exports `box_shadow_box_shadow` as an object with numeric
     * horizontal/vertical/blur/spread values (in px) and a string color.
     * `box_shadow_box_shadow_type` = 'yes' enables the shadow.
     */
    private function mapBoxShadow( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'box_shadow_box_shadow_type';

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = 'box_shadow_box_shadow' . $suffix;
            $handled[] = $key;

            $raw = $settings[ $key ] ?? null;
            if ( ! is_array( $raw ) ) {
                continue;
            }

            $color = is_string( $raw['color'] ?? '' ) ? ( $raw['color'] ?? '' ) : '';
            if ( $color === '' && empty( $raw['horizontal'] ) && empty( $raw['vertical'] ) ) {
                continue;
            }

            $to_px = static fn( mixed $v ): string => ( $v !== '' && $v !== null )
                ? ( is_numeric( $v ) ? $v . 'px' : (string) $v )
                : '0px';

            $position_raw = is_string( $raw['position'] ?? '' ) ? ( $raw['position'] ?? '' ) : '';

            $shadow = [
                'style'      => 'preset1',
                'position'   => $position_raw === 'inset' ? 'inner' : 'outer',
                'color'      => $color,
                'horizontal' => $to_px( $raw['horizontal'] ?? '' ),
                'vertical'   => $to_px( $raw['vertical'] ?? '' ),
                'blur'       => $to_px( $raw['blur'] ?? '' ),
                'spread'     => $to_px( $raw['spread'] ?? '' ),
            ];

            self::transformPath( $attrs, "module.decoration.boxShadow.{$breakpoint}.value", $shadow );
        }
    }

    /**
     * Maps the Elementor text-shadow control to Divi 5's font value `textShadow` sub-object.
     *
     * The shadow is written into the widget-specific font path so it applies to the
     * element's primary text rather than to the module container.
     */
    private function mapTextShadow( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'text_shadow_text_shadow_type';

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = 'text_shadow_text_shadow' . $suffix;
            $handled[] = $key;

            $raw = $settings[ $key ] ?? null;
            if ( ! is_array( $raw ) ) {
                continue;
            }

            $color = is_string( $raw['color'] ?? '' ) ? ( $raw['color'] ?? '' ) : '';
            if ( $color === '' ) {
                continue;
            }

            $font_path = self::WIDGET_FONT_PATH[ $widget_type ] ?? null;
            if ( $font_path === null ) {
                continue;
            }

            $to_px = static fn( mixed $v ): string => ( $v !== '' && $v !== null )
                ? ( is_numeric( $v ) ? $v . 'px' : (string) $v )
                : '0px';

            $shadow_value = [
                'style'      => 'preset1',
                'color'      => $color,
                'horizontal' => $to_px( $raw['horizontal'] ?? '' ),
                'vertical'   => $to_px( $raw['vertical'] ?? '' ),
                'blur'       => $to_px( $raw['blur'] ?? '' ),
            ];

            self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.textShadow", $shadow_value );
        }
    }

    /**
     * Maps Elementor element opacity (`_opacity`) to
     * `module.decoration.opacity.{breakpoint}.value`.
     */
    private function mapOpacity( array $settings, array &$attrs, array &$handled ): void {
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = '_opacity' . $suffix;
            $handled[] = $key;

            $raw = $settings[ $key ] ?? null;
            if ( $raw === null || $raw === '' ) {
                continue;
            }
            $val = (float) $raw;
            // Elementor default is 1.0; skip if exactly 1.
            if ( $val === 1.0 ) {
                continue;
            }
            self::transformPath( $attrs, "module.decoration.opacity.{$breakpoint}.value", (string) round( $val, 4 ) );
        }
    }

    /**
     * Maps Elementor section / column min-height (`custom_height`) to
     * `module.decoration.sizing.{breakpoint}.value.minHeight`.
     *
     * Elementor's `height` selector ('min-height', 'full', etc.) is acknowledged
     * in `markSectionKeys()` / `markColumnKeys()`; here we only act when the
     * pixel value is non-empty.
     */
    private function mapMinHeight( array $settings, array &$attrs, array &$handled ): void {
        $suffixes = [
            ''        => 'desktop',
            '_tablet' => 'tablet',
            '_mobile' => 'phone',
        ];

        foreach ( $suffixes as $suffix => $breakpoint ) {
            $key       = 'custom_height' . $suffix;
            $handled[] = $key;

            $raw   = $settings[ $key ] ?? null;
            $value = $this->parseSizeValue( $raw );
            if ( $value !== '' ) {
                self::transformPath( $attrs, "module.decoration.sizing.{$breakpoint}.value.minHeight", $value );
            }
        }
    }

    /**
     * Maps the Elementor button background colour (`button_background_color`) to
     * `button.decoration.button.{breakpoint}.value.background.color` in Divi 5.
     *
     * This is the actual button-face background — distinct from
     * `module.decoration.background.*.value.color` which is the module wrapper.
     */
    private function mapButtonBackground( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'button_background_color';

        $color = $settings['button_background_color'] ?? '';
        if ( ! is_string( $color ) || $color === '' ) {
            return;
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            self::transformPath( $attrs, "button.decoration.button.{$breakpoint}.value.background.color", $color );
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
     *
     * Handles two Elementor formats:
     * - Flat:          {size: 16, unit: 'px'}
     * - Elementor 3.x: {sizes: {desktop: {size: 16, unit: 'px'}, tablet: {...}, ...}}
     */
    private function parseSizeValue( mixed $raw ): string {
        if ( is_array( $raw ) ) {
            // Elementor 3.x responsive shape: {sizes: {desktop: {size, unit}, ...}}.
            if ( isset( $raw['sizes'] ) && is_array( $raw['sizes'] ) ) {
                foreach ( [ 'desktop', 'tablet', 'mobile' ] as $bp ) {
                    $entry = $raw['sizes'][ $bp ] ?? null;
                    if ( is_array( $entry ) && isset( $entry['size'] ) && $entry['size'] !== '' && $entry['size'] !== null ) {
                        $unit = is_string( $entry['unit'] ?? '' ) ? ( $entry['unit'] ?? 'px' ) : 'px';
                        return (string) $entry['size'] . $unit;
                    }
                }
                return '';
            }

            if ( isset( $raw['size'] ) ) {
                $size = $raw['size'];
                if ( $size === '' || $size === null ) {
                    return '';
                }
                $unit = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
                return (string) $size . $unit;
            }
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
            // Only append the unit when the value is purely numeric; values that
            // already carry a unit suffix (e.g. "0px") must not be doubled.
            fn( string $v ) => $v !== '' ? ( is_numeric( $v ) ? $v . $unit : $v ) : '0px',
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
