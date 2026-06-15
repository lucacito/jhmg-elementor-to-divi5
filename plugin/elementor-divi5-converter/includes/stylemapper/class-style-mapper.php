<?php

namespace ElementorDivi5Converter\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-globals-resolver.php';

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
        70  => '3_4',   // approximate — closer to 3/4 than 2/3
        67  => '2_3',
        66  => '2_3',
        65  => '2_3',
        63  => '2_3',
        60  => '3_5',
        55  => '1_2',   // between 1/2 and 3/5; round to 1/2
        52  => '1_2',
        50  => '1_2',
        48  => '1_2',
        45  => '2_5',
        40  => '2_5',
        38  => '2_5',
        35  => '1_3',
        34  => '1_3',
        33  => '1_3',
        30  => '1_3',
        25  => '1_4',
        20  => '1_5',
    ];

    /**
     * Maps Elementor widget_type to the Divi 5 attribute path prefix for that
     * element's font decoration (the dotted path up to but not including
     * `.{breakpoint}.value`).
     */
    private const WIDGET_FONT_PATH = [
        'heading'        => 'title.decoration.font.font',
        'text-editor'    => 'content.decoration.bodyFont.body.font',
        'button'         => 'button.decoration.font.font',
        'blurb'          => 'title.decoration.font.font',
        'counter'        => 'number.decoration.font.font',
        'cta'            => 'title.decoration.font.font',
        'alert'          => 'title.decoration.font.font',
    ];

    /**
     * Secondary font path for widgets with two text elements (e.g. blurb has
     * both a title and a description). The secondary path maps the description
     * / body text typography controls.
     */
    private const WIDGET_SECONDARY_FONT_PATH = [
        'blurb' => 'content.decoration.bodyFont.body.font',
    ];

    /** Elementor control-group prefix for secondary typography (description). */
    private const WIDGET_SECONDARY_TYPOGRAPHY_PREFIX = [
        'blurb' => 'description_typography_',
    ];

    /** Elementor settings key for the secondary text/font color. */
    private const WIDGET_SECONDARY_COLOR_KEY = [
        'blurb' => 'description_color',
    ];

    /**
     * Maps Elementor widget_type to the Elementor key prefix used for its
     * typography group controls.
     *
     * Most widgets use the standard `typography_` prefix.  Widgets whose
     * controls are scoped to a specific element (e.g. button, blurb title)
     * use a different prefix that must be listed here.
     *
     * The `mapTypography()` method consults this map to resolve the prefix
     * before constructing key names like `{prefix}font_size`, `{prefix}font_family`, etc.
     */
    private const WIDGET_TYPOGRAPHY_PREFIX = [
        // Note: 'button' (standalone widget) uses the standard 'typography_' prefix —
        // it is intentionally absent from this map so the default fallback applies.
        'blurb'   => 'title_typography_',
        'counter' => 'number_typography_',
        'cta'     => 'title_typography_',
        'alert'   => 'title_typography_',
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
        'counter'     => 'number_color',
        'cta'         => 'title_color',
        'alert'       => 'title_color',
    ];

    /**
     * Elementor `typography_*` keys that are acknowledged but deliberately not
     * mapped (preset selector names that have no per-block Divi 5 equivalent).
     * Word-spacing is intentionally absent — it is handled by mapWordSpacing().
     */
    private const TYPOGRAPHY_SKIP_KEYS = [
        'typography_typography',
    ];

    /**
     * All responsive typography property suffixes used when a widget has a
     * non-standard typography prefix and the standard `typography_*` variants
     * still need to be marked handled.
     */
    private const STANDARD_TYPOGRAPHY_PROPS = [
        'font_size', 'font_size_tablet', 'font_size_mobile',
        'font_weight', 'font_weight_tablet', 'font_weight_mobile',
        'font_family', 'font_family_tablet', 'font_family_mobile',
        'font_style', 'font_style_tablet', 'font_style_mobile',
        'text_transform', 'text_transform_tablet', 'text_transform_mobile',
        'text_decoration', 'text_decoration_tablet', 'text_decoration_mobile',
        'line_height', 'line_height_tablet', 'line_height_mobile',
        'letter_spacing', 'letter_spacing_tablet', 'letter_spacing_mobile',
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

        $this->suppressUnimplementable( $settings, $handled_keys );
        $this->mapSpacing( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapBackgroundColor( $widget_type, $settings, $divi_attrs, $handled_keys );
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

        if ( in_array( $widget_type, [ 'section', 'column', 'container', 'row' ], true ) ) {
            $this->mapMinHeight( $settings, $divi_attrs, $handled_keys );
            $this->mapContentPosition( $widget_type, $settings, $divi_attrs, $handled_keys );
        }

        if ( $widget_type === 'column' ) {
            $this->mapColumnSize( $settings, $divi_attrs, $handled_keys );
            $this->markColumnKeys( $settings, $handled_keys );
        }

        // Containers and their derivatives use the same structural keys as sections.
        if ( in_array( $widget_type, [ 'section', 'container', 'row' ], true ) ) {
            $this->markSectionKeys( $settings, $handled_keys );
        }

        if ( $widget_type === 'image' ) {
            $this->mapImageBorderRadius( $settings, $divi_attrs, $handled_keys );
        }

        if ( $widget_type === 'button' ) {
            $this->mapButtonPadding( $settings, $divi_attrs, $handled_keys );
            $this->mapButtonBackground( $settings, $divi_attrs, $handled_keys );
            $this->mapButtonBorder( $settings, $divi_attrs, $handled_keys );
        }

        $this->mapCustomCssClass( $settings, $divi_attrs, $handled_keys );
        $this->mapZIndex( $settings, $divi_attrs, $handled_keys );
        $this->mapOverflow( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapFilters( $settings, $divi_attrs, $handled_keys );
        $this->mapBlendMode( $settings, $divi_attrs, $handled_keys );
        $this->mapSecondaryTypography( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapSecondaryTextColor( $widget_type, $settings, $divi_attrs, $handled_keys );

        if ( $widget_type === 'image' ) {
            $this->mapImageWidth( $settings, $divi_attrs, $handled_keys );
        }

        $this->mapWordSpacing( $widget_type, $settings, $divi_attrs, $handled_keys );
        $this->mapCssMain( $widget_type, $settings, $divi_attrs, $handled_keys );

        // css_main reflects the full css.desktop.value.main content, which may include
        // contributions from mapBlendMode(), mapImageWidth(), and mapCssMain().
        $css_main = $divi_attrs['css']['desktop']['value']['main'] ?? '';

        return [
            'divi_attrs'   => $divi_attrs,
            'handled_keys' => $handled_keys,
            'css_main'     => $css_main,
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

    private function mapBackgroundColor( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
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
     */
    private function mapTypography( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $pfx = self::WIDGET_TYPOGRAPHY_PREFIX[ $widget_type ] ?? 'typography_';

        // Always mark the standard typography skip keys as handled.
        foreach ( self::TYPOGRAPHY_SKIP_KEYS as $skip ) {
            $handled[] = $skip;
        }
        // Mark the widget-specific preset selector name as handled.
        $handled[] = $pfx . 'typography';
        // word_spacing variants are owned by mapWordSpacing() — not listed here.

        // When a widget uses a non-standard prefix, Elementor still emits the standard
        // typography_* keys — mark them all handled so they don't appear in the log.
        if ( $pfx !== 'typography_' ) {
            foreach ( self::STANDARD_TYPOGRAPHY_PROPS as $sp ) {
                $handled[] = 'typography_' . $sp;
            }
        }

        $font_path = self::WIDGET_FONT_PATH[ $widget_type ] ?? null;
        $this->applyFontGroup( $pfx, $font_path, $settings, $attrs, $handled );

        // Global typography preset fallback.
        if ( $font_path !== null ) {
            $ref = $settings['__globals__'][ $pfx . 'typography' ] ?? '';
            if ( is_string( $ref ) && $ref !== '' ) {
                $id = GlobalsResolver::typographyIdFromRef( $ref );
                if ( $id !== null ) {
                    $preset = GlobalsResolver::resolveTypography( $id );
                    if ( $preset !== null ) {
                        $this->applyGlobalTypography( $font_path, $preset, $attrs );
                    }
                }
            }
        }
    }

    /**
     * Maps the secondary typography controls (e.g. description text on blurb)
     * to the widget's secondary font decoration path.
     *
     * Only fires for widget types listed in WIDGET_SECONDARY_FONT_PATH.
     */
    private function mapSecondaryTypography( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $font_path = self::WIDGET_SECONDARY_FONT_PATH[ $widget_type ] ?? null;
        if ( $font_path === null ) {
            return;
        }

        $pfx = self::WIDGET_SECONDARY_TYPOGRAPHY_PREFIX[ $widget_type ];

        // Mark the secondary preset selector name as handled.
        // word_spacing variants for the secondary group are also marked handled here
        // (not mapped — targeting just the description element via css_main is not reliable).
        $handled[] = $pfx . 'typography';
        foreach ( [ 'word_spacing', 'word_spacing_tablet', 'word_spacing_mobile' ] as $sk ) {
            $handled[] = $pfx . $sk;
        }

        $this->applyFontGroup( $pfx, $font_path, $settings, $attrs, $handled );
    }

    /**
     * Core font-group mapper used by both mapTypography and mapSecondaryTypography.
     *
     * Given a control prefix (e.g. `typography_` or `description_typography_`) and
     * a Divi font path (e.g. `title.decoration.font.font`), reads all responsive
     * size/weight/family/style properties from $settings and writes them to $attrs.
     *
     * @param string      $pfx       Elementor control-group prefix (trailing underscore included).
     * @param string|null $font_path Divi 5 dot-path up to (not including) `.{bp}.value.*`.
     *                               When null keys are still marked handled but nothing is written.
     */
    private function applyFontGroup( string $pfx, ?string $font_path, array $settings, array &$attrs, array &$handled ): void {
        // Responsive size properties.
        $size_props = [
            'font_size'      => 'size',
            'line_height'    => 'lineHeight',
            'letter_spacing' => 'letterSpacing',
        ];

        foreach ( $size_props as $base_name => $divi_prop ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $key       = $pfx . $base_name . $suffix;
                $handled[] = $key;

                if ( $font_path !== null ) {
                    $value = $this->parseSizeValue( $settings[ $key ] ?? null );
                    if ( $value !== '' ) {
                        self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.{$divi_prop}", $value );
                    }
                }
            }
        }

        // Font weight.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = $pfx . 'font_weight' . $suffix;
            $handled[] = $key;

            if ( $font_path !== null ) {
                $weight = $settings[ $key ] ?? '';
                if ( is_string( $weight ) && $weight !== '' ) {
                    self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.weight", $weight );
                }
            }
        }

        // Font family.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = $pfx . 'font_family' . $suffix;
            $handled[] = $key;

            if ( $font_path !== null ) {
                $family = $settings[ $key ] ?? '';
                if ( is_string( $family ) && $family !== '' ) {
                    self::transformPath( $attrs, "{$font_path}.{$breakpoint}.value.family", $family );
                }
            }
        }

        // Style flags (italic, text-transform, text-decoration) — stored as a string array.
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $style_key      = $pfx . 'font_style' . $suffix;
            $transform_key  = $pfx . 'text_transform' . $suffix;
            $decoration_key = $pfx . 'text_decoration' . $suffix;

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
     * Maps the secondary text/font color (e.g. `description_color` on blurb) to
     * the widget's secondary font color path.
     */
    private function mapSecondaryTextColor( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $color_key = self::WIDGET_SECONDARY_COLOR_KEY[ $widget_type ] ?? null;
        $font_path = self::WIDGET_SECONDARY_FONT_PATH[ $widget_type ] ?? null;

        if ( $color_key === null || $font_path === null ) {
            return;
        }

        $handled[] = $color_key;

        $color = $this->resolveColorSetting( $settings, $color_key );
        if ( $color !== '' ) {
            self::transformPath( $attrs, "{$font_path}.desktop.value.color", $color );
        }
    }

    /**
     * Applies a global typography preset to the font path, but only for properties
     * that are not already set by explicit per-widget typography_* controls.
     * This ensures explicit overrides always take precedence.
     *
     * @param string $font_path  Divi 5 dot-path up to (not including) `.{bp}.value.*`.
     * @param array  $preset     From GlobalsResolver::resolveTypography().
     * @param array  $attrs      The attrs array being built (passed by reference).
     */
    private function applyGlobalTypography( string $font_path, array $preset, array &$attrs ): void {
        $scalar_props = [ 'family', 'size', 'weight', 'letterSpacing', 'lineHeight' ];

        foreach ( $scalar_props as $prop ) {
            if ( ! isset( $preset[ $prop ] ) ) {
                continue;
            }

            $existing_path = explode( '.', "{$font_path}.desktop.value.{$prop}" );
            $existing      = $attrs;
            foreach ( $existing_path as $segment ) {
                if ( ! is_array( $existing ) || ! isset( $existing[ $segment ] ) ) {
                    $existing = null;
                    break;
                }
                $existing = $existing[ $segment ];
            }

            // Only write when no explicit value already exists.
            if ( $existing === null ) {
                self::transformPath( $attrs, "{$font_path}.desktop.value.{$prop}", (string) $preset[ $prop ] );
            }
        }

        // Style flags (e.g. 'capitalize') — only add when no flags already set.
        if ( ! empty( $preset['style'] ) ) {
            $existing_path = explode( '.', "{$font_path}.desktop.value.style" );
            $existing      = $attrs;
            foreach ( $existing_path as $segment ) {
                if ( ! is_array( $existing ) || ! isset( $existing[ $segment ] ) ) {
                    $existing = null;
                    break;
                }
                $existing = $existing[ $segment ];
            }
            if ( $existing === null ) {
                self::transformPath( $attrs, "{$font_path}.desktop.value.style", $preset['style'] );
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

        $color = $this->resolveColorSetting( $settings, $color_key );
        if ( $color === '' ) {
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
     * Resolves a color setting by first checking the direct key in $settings,
     * then falling back to the `__globals__` reference for that key and resolving
     * it through GlobalsResolver.
     */
    private function resolveColorSetting( array $settings, string $key ): string {
        $direct = $settings[ $key ] ?? '';
        if ( is_string( $direct ) && $direct !== '' ) {
            return $direct;
        }

        $ref = $settings['__globals__'][ $key ] ?? '';
        if ( ! is_string( $ref ) || $ref === '' ) {
            return '';
        }

        $id = GlobalsResolver::colorIdFromRef( $ref );
        if ( $id === null ) {
            return '';
        }

        return GlobalsResolver::resolveColor( $id ) ?? '';
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

        // Desktop base value — may be empty when only responsive overrides are set.
        $desktop_align = is_string( $settings['align'] ?? '' ) ? ( $settings['align'] ?? '' ) : '';
        if ( $desktop_align === '' ) {
            $desktop_align = is_string( $settings['text_align'] ?? '' ) ? ( $settings['text_align'] ?? '' ) : '';
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $bp_val = $settings[ 'align' . $suffix ] ?? $settings[ 'text_align' . $suffix ] ?? null;
            // For desktop, fall back to the plain desktop value when no responsive override is set.
            $value  = ( is_string( $bp_val ) && $bp_val !== '' ) ? $bp_val : ( $suffix === '' && $desktop_align !== '' ? $desktop_align : null );
            if ( $value === null ) {
                continue;
            }

            if ( $widget_type === 'heading' ) {
                self::transformPath( $attrs, "title.decoration.font.font.{$breakpoint}.value.textAlign", $value );
            } elseif ( $widget_type === 'button' ) {
                // Button alignment controls button position in its container, not text orientation.
                self::transformPath( $attrs, "module.advanced.alignment.{$breakpoint}.value", $value );
            } elseif ( $widget_type === 'image' ) {
                // Image alignment controls the image's horizontal position in its column.
                self::transformPath( $attrs, "module.advanced.align.{$breakpoint}.value", $value );
            } elseif ( $widget_type === 'icon' ) {
                // Icon alignment lives on the icon sub-attr, not the module text path.
                self::transformPath( $attrs, "icon.advanced.align.{$breakpoint}.value", $value );
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
        // Slideshow-specific keys — always mark handled; first gallery image used as fallback below.
        foreach ( [
            'background_slideshow_gallery', 'background_slideshow_loop',
            'background_slideshow_slide_duration', 'background_slideshow_slide_transition',
            'background_slideshow_transition_speed',
        ] as $k ) {
            $handled[] = $k;
        }

        // Slideshow background: use the first gallery image as a static fallback.
        if ( ( $settings['background_background'] ?? '' ) === 'slideshow' ) {
            $gallery = $settings['background_slideshow_gallery'] ?? [];
            if ( is_array( $gallery ) && ! empty( $gallery ) ) {
                $first     = reset( $gallery );
                $slide_url = is_array( $first ) ? ( $first['url'] ?? '' ) : '';
                if ( is_string( $slide_url ) && $slide_url !== '' ) {
                    self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.url', $slide_url );
                }
            }
            return;
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

        // Size: map cover/contain and any other explicit value.
        $size = $settings['background_size'] ?? '';
        if ( is_string( $size ) && $size !== '' && $size !== 'initial' && $size !== 'auto' ) {
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.size', $size );
        }

        // Repeat.
        $repeat = $settings['background_repeat'] ?? '';
        if ( is_string( $repeat ) && $repeat !== '' ) {
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.repeat', $repeat );
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
            'background_overlay_color_b',
            'background_overlay_color_stop',
            'background_overlay_color_b_stop',
            'background_overlay_gradient_angle',
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
        $has_color     = is_string( $overlay_color ) && $overlay_color !== '';

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

        // Nothing actionable — no color, no overlay image, no background image.
        if ( ! $has_color && $overlay_image_url === '' && $bg_image_url === '' ) {
            return;
        }

        if ( $overlay_image_url !== '' ) {
            // Pattern A: write the overlay image as the background image, but only
            // when there is no main background image and no solid background color.
            // When a color already exists the overlay is a decorative pattern on top
            // of the color — mixing it into the Divi background image slot breaks CSS
            // generation for the color (Divi emits no CSS at all when both are set).
            $existing_bg_color = is_string( $settings['background_color'] ?? '' )
                ? ( $settings['background_color'] ?? '' )
                : '';
            if ( $bg_image_url === '' && $existing_bg_color === '' ) {
                self::transformPath( $attrs, 'module.decoration.background.desktop.value.image.url', $overlay_image_url );
            }
        }

        if ( ! $has_color ) {
            // No color to build a gradient with — image already written above.
            return;
        }

        $opacity_raw = $settings['background_overlay_opacity'] ?? null;
        $opacity     = 1.0;
        if ( is_array( $opacity_raw ) && isset( $opacity_raw['size'] ) ) {
            $opacity = (float) $opacity_raw['size'];
        } elseif ( is_numeric( $opacity_raw ) ) {
            $opacity = (float) $opacity_raw;
        }

        // Second gradient stop color and positions.
        $color_b     = $settings['background_overlay_color_b'] ?? '';
        $has_color_b = is_string( $color_b ) && $color_b !== '';

        $stop_a_raw = $settings['background_overlay_color_stop'] ?? null;
        $stop_b_raw = $settings['background_overlay_color_b_stop'] ?? null;
        $pos_a      = ( is_array( $stop_a_raw ) && isset( $stop_a_raw['size'] ) )
            ? ( (int) $stop_a_raw['size'] . '%' )
            : '0%';
        $pos_b      = ( is_array( $stop_b_raw ) && isset( $stop_b_raw['size'] ) )
            ? ( (int) $stop_b_raw['size'] . '%' )
            : '100%';

        // For 8-digit hex colors the alpha is embedded; use it directly and apply
        // the overall overlay opacity only to plain 6-digit hex stop colors.
        $rgba_a = $this->hexToRgba( $overlay_color, $opacity );
        $rgba_b = $has_color_b ? $this->hexToRgba( $color_b, $opacity ) : $rgba_a;

        // Gradient direction from Elementor angle control (default 180deg = top→bottom).
        $angle_raw = $settings['background_overlay_gradient_angle'] ?? null;
        $direction = '180deg';
        if ( is_array( $angle_raw ) && isset( $angle_raw['size'] ) && $angle_raw['size'] !== '' ) {
            $direction = (string) (int) $angle_raw['size'] . ( $angle_raw['unit'] ?? 'deg' );
        } elseif ( is_string( $angle_raw ) && $angle_raw !== '' ) {
            $direction = $angle_raw;
        }

        if ( $overlay_image_url !== '' || $bg_image_url !== '' ) {
            // Gradient overlay on top of background image — emit as ::before CSS.
            // Divi 5's native gradient.overlaysImage block attr renders malformed CSS;
            // custom ::before CSS matches what the Divi builder produces for this pattern.
            if ( $has_color_b ) {
                $css_stops = "{$rgba_a} {$pos_a}, {$rgba_b} {$pos_b}";
            } else {
                $css_stops = "{$rgba_a} 0%, {$rgba_a} 100%";
            }
            $before_css = "background-image: linear-gradient({$direction}, {$css_stops}); content: \"\"; position: absolute; top: 0; left: 0; right: 0; bottom: 0;";
            self::transformPath( $attrs, 'css.desktop.value.before', $before_css );
        } else {
            // No image — apply rgba color as semi-transparent background.
            self::transformPath( $attrs, 'module.decoration.background.desktop.value.color', $rgba_a );
        }
    }

    private function hexToRgba( string $hex, float $opacity ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        // 8-digit hex: last two chars encode the alpha channel (00–FF).
        // Use the embedded alpha directly; override the external $opacity param.
        if ( strlen( $hex ) === 8 ) {
            $opacity = round( hexdec( substr( $hex, 6, 2 ) ) / 255, 4 );
            $hex     = substr( $hex, 0, 6 );
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . (string) round( $opacity, 4 ) . ')';
    }

    /**
     * Globally suppresses Elementor settings that have no Divi 5 equivalent
     * regardless of widget type. Called first in map() so per-property mappers
     * do not need to re-list them.
     *
     * Covered categories:
     * - Advanced-tab background / mask / transform / element-width keys (underscore prefix)
     * - Motion FX (parallax, mouse-track, tilt, blur, etc.) — Divi 5 has no equivalent
     * - Sticky behaviour — handled separately via Divi section settings; raw keys ignored
     * - Hover-state backgrounds — Divi 5 uses a separate design system; not mappable here
     * - Video fallback images — only used when background_background = 'video', not mapped
     */
    private function suppressUnimplementable( array $settings, array &$handled ): void {
        // Key prefixes whose entire family can be silently ignored.
        $prefix_suppressions = [
            'motion_fx_',              // Motion Effects (parallax, scroll, tilt, mouse-track, etc.)
            'sticky_',                 // Sticky scroll behaviour
            '_background_',            // Advanced-tab background overrides (not the element's own bg)
            '_mask_',                  // Advanced-tab CSS mask
            '_element_',               // Advanced-tab element-width / visibility overrides
            '_flex_',                  // Advanced-tab flex-size override
            '_transform_',             // Advanced-tab CSS transform
            '_offset_',                // Advanced-tab position offset
            '_box_shadow_',            // Advanced-tab box-shadow (distinct from widget-level box_shadow_*)
            '_border_radius',          // Advanced-tab border-radius (handled in mapBorder for own key)
            'button_background_hover_',// Hover-state button background — no static Divi mapping
        ];

        // Exact keys that are hover-state, video-fallback, or responsive sub-controls
        // with no Divi 5 equivalent.
        $exact_suppressions = [
            // Hover-state backgrounds — Divi uses separate hover design tokens.
            'background_video_fallback',
            'background_hover_image',
            'background_hover_video_fallback',
            // Overlay hover state.
            'background_overlay_video_fallback',
            'background_overlay_hover_image',
            'background_overlay_hover_video_fallback',
            'background_overlay_position_tablet',
            'background_overlay_position_mobile',
            // Responsive gradient stop positions (desktop gradient is mapped; tablet/mobile are not).
            'background_color_stop_tablet',
            'background_color_stop_mobile',
            'background_color_b_stop_tablet',
            'background_color_b_stop_mobile',
            'background_gradient_angle_tablet',
            'background_gradient_angle_mobile',
            'background_gradient_position_tablet',
            'background_gradient_position_mobile',
            'background_color_b_stop',
            // Granular background X/Y position offsets (superseded by background_position string).
            'background_xpos',
            'background_xpos_tablet',
            'background_xpos_mobile',
            'background_ypos',
            'background_ypos_tablet',
            'background_ypos_mobile',
            'background_bg_width',
            'background_bg_width_tablet',
            'background_bg_width_mobile',
            // Misc widget utility settings with no Divi mapping.
            'hide_desktop',
            'lazyload',
        ];

        foreach ( array_keys( $settings ) as $key ) {
            foreach ( $prefix_suppressions as $prefix ) {
                if ( str_starts_with( $key, $prefix ) ) {
                    $handled[] = $key;
                    break;
                }
            }
        }

        foreach ( $exact_suppressions as $key ) {
            $handled[] = $key;
        }
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
            // HTML tag override for the column wrapper.
            'html_tag',
            // Column height mode selector — actual pixel value mapped by mapMinHeight().
            'height', 'height_tablet', 'height_mobile',
            // Column-level default colour overrides (heading, body, link).
            'heading_color', 'color_text', 'color_link',
            // Flex layout — mapped via columnFlexSettingsFromContainer() in converters.
            'flex_direction', 'flex_justify_content', 'flex_align_items',
            'flex_wrap', 'flex_align_content', 'flex_gap',
        ] as $key ) {
            $handled[] = $key;
        }
    }

    /**
     * Maps Elementor `content_position` (vertical alignment of content within a
     * section or column) to the Divi 5 layout alignment property.
     *
     * Sections (and container/row types): writes `alignItems` on the desktop
     * layout since old-style sections only expose a single non-responsive value.
     *
     * Columns: writes `justifyContent` across all three breakpoints.  In Divi 5
     * columns use flex-direction:column, so justifyContent controls the vertical
     * position of child modules.
     *
     * Elementor values → CSS flex values:
     *   'top'    → 'flex-start'
     *   'middle' → 'center'
     *   'bottom' → 'flex-end'
     *   others   → passed through (e.g. 'space-evenly', 'stretch')
     */
    private function mapContentPosition( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        if ( $widget_type === 'column' ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
                $key       = 'content_position' . $suffix;
                $handled[] = $key;

                $pos = $settings[ $key ] ?? '';
                if ( is_string( $pos ) && $pos !== '' ) {
                    self::transformPath(
                        $attrs,
                        "module.decoration.layout.{$breakpoint}.value.justifyContent",
                        $this->normalizeFlexAlignment( $pos )
                    );
                }
            }
            return;
        }

        // section / container / row — desktop-only (responsive variants rarely set).
        $handled[] = 'content_position';
        $pos = $settings['content_position'] ?? '';
        if ( is_string( $pos ) && $pos !== '' ) {
            self::transformPath(
                $attrs,
                'module.decoration.layout.desktop.value.alignItems',
                $this->normalizeFlexAlignment( $pos )
            );
        }
    }

    private function normalizeFlexAlignment( string $pos ): string {
        return match ( $pos ) {
            'top'    => 'flex-start',
            'middle' => 'center',
            'bottom' => 'flex-end',
            default  => $pos,
        };
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
            // Elementor section key.
            $key1      = 'custom_height' . $suffix;
            $handled[] = $key1;
            $val1      = $this->parseSizeValue( $settings[ $key1 ] ?? null );

            // Elementor container key (distinct from section).
            $key2      = 'min_height' . $suffix;
            $handled[] = $key2;
            $val2      = $this->parseSizeValue( $settings[ $key2 ] ?? null );

            $value = $val1 !== '' ? $val1 : $val2;
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

    /**
     * Maps Elementor button border controls to the Divi 5 button decoration path.
     *
     * Elementor groups button border under `button_border_border`, `button_border_width`,
     * `button_border_color`, and `button_border_radius`.  In Divi 5 these live at
     * `button.decoration.button.{bp}.value.border.*` rather than the standard
     * `module.decoration.border` path used by non-button elements.
     */
    private function mapButtonBorder( array $settings, array &$attrs, array &$handled ): void {
        $btn_border_keys = [
            'button_border_border', 'button_border_width', 'button_border_color', 'button_border_radius',
        ];
        foreach ( $btn_border_keys as $base ) {
            foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
                $handled[] = $base . $suffix;
            }
        }

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $base_path = "button.decoration.button.{$breakpoint}.value.border";

            // Border style.
            $style = $settings[ 'button_border_border' . $suffix ] ?? '';
            if ( is_string( $style ) && $style !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.style", $style );
            }

            // Border color.
            $color = $settings[ 'button_border_color' . $suffix ] ?? '';
            if ( is_string( $color ) && $color !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.color", $color );
            }

            // Border width.
            $width_raw = $settings[ 'button_border_width' . $suffix ] ?? null;
            if ( is_array( $width_raw ) ) {
                $this->applyBorderWidth( $width_raw, $attrs, "{$base_path}.styles" );
            } elseif ( is_string( $width_raw ) && $width_raw !== '' ) {
                self::transformPath( $attrs, "{$base_path}.styles.all.width", $width_raw );
            }

            // Border radius: prefer button_border_radius (composite-widget prefix);
            // fall back to the standard border_radius control (standalone button widget).
            $radius_raw = $settings[ 'button_border_radius' . $suffix ]
                ?? ( $suffix === '' ? ( $settings['border_radius'] ?? null ) : null );
            if ( is_array( $radius_raw ) ) {
                $radius_obj = $this->normalizeRadius( $radius_raw );
                if ( ! empty( $radius_obj ) ) {
                    self::transformPath( $attrs, "{$base_path}.radius", $radius_obj );
                }
            } elseif ( is_string( $radius_raw ) && $radius_raw !== '' ) {
                self::transformPath( $attrs, "{$base_path}.radius", [
                    'topLeft'     => $radius_raw,
                    'topRight'    => $radius_raw,
                    'bottomRight' => $radius_raw,
                    'bottomLeft'  => $radius_raw,
                ] );
            }
        }
    }

    /**
     * Maps Elementor element z-index (`_z_index`) to
     * `module.decoration.zIndex.{breakpoint}.value` (native Divi 5 attr).
     *
     * Marks both the Advanced-tab `_z_index*` variants and the plain `z_index`
     * widget key as handled for all breakpoints.
     */
    private function mapZIndex( array $settings, array &$attrs, array &$handled ): void {
        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $adv_key   = '_z_index' . $suffix;
            $handled[] = $adv_key;

            $raw = $settings[ $adv_key ] ?? null;
            if ( ( is_string( $raw ) || is_numeric( $raw ) ) && (string) $raw !== '' ) {
                self::transformPath( $attrs, "module.decoration.zIndex.{$breakpoint}.value", (string) $raw );
            }
        }

        // Plain `z_index` key (some widget-level controls use this form).
        $handled[] = 'z_index';
        $plain = $settings['z_index'] ?? null;
        if ( ( is_string( $plain ) || is_numeric( $plain ) ) && (string) $plain !== '' ) {
            self::transformPath( $attrs, 'module.decoration.zIndex.desktop.value', (string) $plain );
        }
    }

    /**
     * Maps Elementor overflow to `module.decoration.overflow.desktop.value`
     * (native Divi 5 attr, value format: `{x: 'hidden', y: 'hidden'}`).
     *
     * Also auto-applies `overflow: hidden` on container types that carry a
     * border-radius, so child images are clipped by the rounded corners.
     */
    private function mapOverflow( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        foreach ( [ 'overflow', '_overflow', 'content_overflow' ] as $key ) {
            $handled[] = $key;
        }

        // Prefer underscore Advanced-tab key; fall back to plain 'overflow'.
        $explicit = '';
        foreach ( [ '_overflow', 'overflow', 'content_overflow' ] as $key ) {
            $v = $settings[ $key ] ?? '';
            if ( is_string( $v ) && $v !== '' && $v !== 'default' ) {
                $explicit = $v;
                break;
            }
        }

        // For container types: auto-clip children through border-radius.
        $effective = $explicit;
        if (
            $effective === '' &&
            in_array( $widget_type, [ 'section', 'column', 'container', 'row' ], true ) &&
            $this->hasBorderRadius( $settings )
        ) {
            $effective = 'hidden';
        }

        if ( $effective !== '' ) {
            self::transformPath( $attrs, 'module.decoration.overflow.desktop.value', [ 'x' => $effective, 'y' => $effective ] );
        }
    }

    /**
     * Maps Elementor CSS filter controls (`css_filters_*`) to the native Divi 5
     * `module.decoration.filters.{breakpoint}.value` attr.
     *
     * Divi accepts an object with keys: brightness, contrast, saturate, hueRotate,
     * blur, invert, sepia, opacity.  Values that equal their CSS defaults are
     * omitted to avoid generating unnecessary filter CSS.
     */
    private function mapFilters( array $settings, array &$attrs, array &$handled ): void {
        // Map of Elementor key → [divi_key, default_size].
        $filter_map = [
            'css_filters_brightness' => [ 'brightness', 100 ],
            'css_filters_contrast'   => [ 'contrast',   100 ],
            'css_filters_saturate'   => [ 'saturate',   100 ],
            'css_filters_hue'        => [ 'hueRotate',    0 ],
            'css_filters_blur'       => [ 'blur',          0 ],
            'css_filters_invert'     => [ 'invert',        0 ],
            'css_filters_sepia'      => [ 'sepia',         0 ],
            'css_filters_opacity'    => [ 'opacity',     100 ],
        ];

        // Mode selector — no value to map.
        $handled[] = 'css_filters_css_filter';

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $filter_values = [];

            foreach ( $filter_map as $el_key => [ $divi_key, $default ] ) {
                $full_key  = $el_key . $suffix;
                $handled[] = $full_key;

                $raw = $settings[ $full_key ] ?? null;
                if ( ! is_array( $raw ) || ! isset( $raw['size'] ) ) {
                    continue;
                }

                $size = (float) $raw['size'];
                if ( $size == $default ) {
                    continue; // At default — no CSS effect; skip.
                }

                $unit              = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? '' ) : '';
                $filter_values[ $divi_key ] = (string) $raw['size'] . $unit;
            }

            if ( ! empty( $filter_values ) ) {
                self::transformPath( $attrs, "module.decoration.filters.{$breakpoint}.value", $filter_values );
            }
        }
    }

    /**
     * Maps Elementor `blend_mode` to `mix-blend-mode` via `css.desktop.value.main`.
     * Divi 5 has no native block attr for blend mode; custom CSS is the only option.
     */
    private function mapBlendMode( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'blend_mode';
        $mode      = is_string( $settings['blend_mode'] ?? '' ) ? ( $settings['blend_mode'] ?? '' ) : '';
        if ( $mode === '' || $mode === 'normal' ) {
            return;
        }

        $existing = $attrs['css']['desktop']['value']['main'] ?? '';
        $rule     = "mix-blend-mode: {$mode}";
        $merged   = ( is_string( $existing ) && $existing !== '' )
            ? rtrim( $existing, '; ' ) . '; ' . $rule . ';'
            : $rule . ';';
        self::transformPath( $attrs, 'css.desktop.value.main', $merged );
    }

    /**
     * Maps the Elementor image widget `width` control to `width` in custom CSS
     * on the module's main selector.  Divi's image module has no native block-attr
     * slot for arbitrary image widths, so this falls back to `css.desktop.value.main`.
     */
    private function mapImageWidth( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'width';

        $width_str = $this->parseSizeValue( $settings['width'] ?? null );
        if ( $width_str === '' ) {
            return;
        }

        $existing = $attrs['css']['desktop']['value']['main'] ?? '';
        $rule     = "width: {$width_str}; max-width: 100%";
        $merged   = ( is_string( $existing ) && $existing !== '' )
            ? rtrim( $existing, '; ' ) . '; ' . $rule . ';'
            : $rule . ';';
        self::transformPath( $attrs, 'css.desktop.value.main', $merged );
    }

    /**
     * Maps Elementor `typography_word_spacing` (and responsive _tablet/_mobile variants)
     * to `word-spacing` in the module's custom CSS block (`css.*.value.main`).
     *
     * Divi 5 has no native block attribute for word-spacing, so the value is emitted
     * as raw CSS. Responsive overrides go to `css.tablet.value.main` / `css.phone.value.main`
     * so that Divi renders them inside the correct media queries.
     *
     * For widgets with a non-standard typography prefix (e.g. blurb → `title_typography_`)
     * the standard `typography_word_spacing*` keys are also marked handled (but not mapped)
     * because Elementor emits both sets when a widget group control has a custom prefix.
     */
    private function mapWordSpacing( string $widget_type, array $settings, array &$attrs, array &$handled ): void {
        $pfx = self::WIDGET_TYPOGRAPHY_PREFIX[ $widget_type ] ?? 'typography_';

        foreach ( self::BREAKPOINT_MAP as $suffix => $breakpoint ) {
            $key       = $pfx . 'word_spacing' . $suffix;
            $handled[] = $key;

            // When the widget uses a non-standard prefix, the standard typography_word_spacing*
            // key is also emitted by Elementor — mark it handled without mapping.
            if ( $pfx !== 'typography_' ) {
                $handled[] = 'typography_word_spacing' . $suffix;
            }

            $value = $this->parseSizeValue( $settings[ $key ] ?? null );
            if ( $value === '' ) {
                continue;
            }

            $rule     = "word-spacing: {$value}";
            $existing = $attrs['css'][ $breakpoint ]['value']['main'] ?? '';
            $merged   = ( is_string( $existing ) && $existing !== '' )
                ? rtrim( $existing, '; ' ) . '; ' . $rule . ';'
                : $rule . ';';
            self::transformPath( $attrs, "css.{$breakpoint}.value.main", $merged );
        }
    }

    /**
     * Collects CSS properties that have no native Divi 5 block attribute slot and
     * emits them on `css.desktop.value.main` (the module's primary selector).
     *
     * Properties handled here:
     *  - _element_custom_width → max-width (when _element_width = 'initial')
     *  - custom_width          → max-width
     *
     * z-index, overflow, filters, and blend-mode each have their own dedicated
     * mapper and are NOT handled here.
     *
     * Returns the CSS string written (empty string when nothing was emitted).
     */
    private function mapCssMain( string $widget_type, array $settings, array &$attrs, array &$handled ): string {
        $rules = [];

        // --- max-width / custom element width --------------------------------
        // _element_* keys are prefix-suppressed in suppressUnimplementable(); no need to add to handled.
        $handled[] = 'custom_width';
        $el_width  = is_string( $settings['_element_width'] ?? '' ) ? ( $settings['_element_width'] ?? '' ) : '';
        if ( $el_width === 'initial' ) {
            $cw_str = $this->parseSizeValue( $settings['_element_custom_width'] ?? null );
            if ( $cw_str !== '' ) {
                $rules[] = "max-width: {$cw_str}";
            }
        }
        $custom_width_str = $this->parseSizeValue( $settings['custom_width'] ?? null );
        if ( $custom_width_str !== '' ) {
            $rules[] = "max-width: {$custom_width_str}";
        }

        if ( empty( $rules ) ) {
            return '';
        }

        $css = implode( '; ', $rules ) . ';';

        // Merge with any CSS already written to this path (e.g. from mapBlendMode).
        $existing = $attrs['css']['desktop']['value']['main'] ?? '';
        $merged   = ( is_string( $existing ) && $existing !== '' )
            ? rtrim( $existing, '; ' ) . '; ' . $css
            : $css;

        self::transformPath( $attrs, 'css.desktop.value.main', $merged );

        return $css;
    }

    /**
     * Returns true when any breakpoint of the element has a non-zero border-radius.
     * Checks both the standard widget key (`border_radius`) and the Advanced-tab
     * variant (`_border_radius`) used by some Elementor containers.
     */
    private function hasBorderRadius( array $settings ): bool {
        foreach ( self::BREAKPOINT_MAP as $suffix => $_ ) {
            foreach ( [ 'border_radius', '_border_radius' ] as $base ) {
                $raw = $settings[ $base . $suffix ] ?? null;
                if ( is_array( $raw ) ) {
                    $non_zero = array_filter(
                        [ $raw['top'] ?? '', $raw['right'] ?? '', $raw['bottom'] ?? '', $raw['left'] ?? '' ],
                        static fn( $v ) => is_numeric( $v ) ? ( (float) $v > 0 ) : ( $v !== '' && $v !== '0' && $v !== '0px' )
                    );
                    if ( ! empty( $non_zero ) ) {
                        return true;
                    }
                } elseif ( is_string( $raw ) && $raw !== '' && $raw !== '0' && $raw !== '0px' ) {
                    return true;
                }
            }
        }
        return false;
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
            // Only enter this branch when sizes is a non-empty keyed array; Elementor
            // always serialises `sizes: []` alongside the flat `size` key, so an empty
            // array must fall through to the `size` key below.
            if ( isset( $raw['sizes'] ) && is_array( $raw['sizes'] ) && ! empty( $raw['sizes'] ) ) {
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

    /**
     * Maps Elementor CSS class names (`css_classes` / `_css_classes`) to the
     * Divi 5 custom CSS class attribute.
     *
     * Both keys are also listed in logUnmappedSettings()'s $always_ignore so they
     * are never reported as skipped when not handled; this method ensures they are
     * actively written to the Divi attrs when present.
     */
    private function mapCustomCssClass( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'css_classes';
        $handled[] = '_css_classes';

        $classes = trim( (string) ( $settings['css_classes'] ?? $settings['_css_classes'] ?? '' ) );
        if ( $classes === '' ) {
            return;
        }

        self::transformPath( $attrs, 'module.advanced.customCssClassNames.desktop.value', $classes );
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
