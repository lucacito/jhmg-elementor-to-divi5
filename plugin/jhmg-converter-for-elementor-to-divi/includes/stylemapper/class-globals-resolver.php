<?php

namespace ElementorDivi5Converter\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves Elementor global color and typography IDs to concrete values.
 *
 * Elementor stores references to global presets in the `__globals__` key of each
 * widget's settings using the pattern `globals/colors?id=<ID>` or
 * `globals/typography?id=<ID>`. These IDs are site-specific.
 *
 * There are exactly two sources, both supplied through the `edc_kit_globals`
 * filter: the Pro add-on's uploaded kit, and the Elementor kit installed on this
 * site (registered by the free plugin as a gap-filling fallback). When neither
 * knows an ID, resolution returns null and the caller writes nothing.
 *
 * This class deliberately has no built-in palette. It used to carry a fallback
 * COLOR_MAP/TYPOGRAPHY_MAP lifted from one specific site — including entries for
 * Elementor's universal system IDs ('primary', 'secondary', 'text', 'accent'),
 * which meant any site whose globals could not be resolved was silently repainted
 * in an unrelated brand's colours and reported as a clean conversion. An
 * unresolved global is now reported as unresolved; see
 * ConverterEngine::logUnresolvedGlobal().
 */
class GlobalsResolver {

    /**
     * Kit globals for this site: the Pro add-on's uploaded kit merged over the
     * installed Elementor kit. Null when neither is available.
     */
    private static function kitGlobals(): ?array {
        if ( ! function_exists( 'apply_filters' ) ) {
            return null;
        }
        $kit = apply_filters( 'edc_kit_globals', null );
        return is_array( $kit ) ? $kit : null;
    }

    /**
     * Resolve `globals/colors?id=<id>` to a concrete hex/rgba string.
     * Returns null when no configured kit knows the ID — never a literal.
     */
    public static function resolveColor( string $id ): ?string {
        $kit   = self::kitGlobals();
        $color = $kit['colors'][ $id ] ?? null;

        return is_string( $color ) && $color !== '' ? $color : null;
    }

    /**
     * Resolve `globals/typography?id=<id>` to a Divi 5 font value array.
     * Returns null when no configured kit knows the ID — never a literal.
     */
    public static function resolveTypography( string $id ): ?array {
        $kit    = self::kitGlobals();
        $preset = $kit['typography'][ $id ] ?? null;

        return is_array( $preset ) && ! empty( $preset ) ? $preset : null;
    }

    /**
     * The kit's Theme Style → Buttons settings, in the shape
     * ConversionPreflight::elementorKitButtons() produces. Empty when no kit
     * provides one.
     */
    public static function resolveButtons(): array {
        $kit     = self::kitGlobals();
        $buttons = $kit['buttons'] ?? null;

        return is_array( $buttons ) ? $buttons : [];
    }

    /**
     * Extract a color ID from a globals reference string.
     * Input:  "globals/colors?id=accent"
     * Output: "accent"  (or null if the string does not match the pattern)
     */
    public static function colorIdFromRef( string $ref ): ?string {
        if ( preg_match( '/^globals\/colors\?id=(.+)$/', $ref, $m ) ) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract a typography ID from a globals reference string.
     * Input:  "globals/typography?id=84ca66e"
     * Output: "84ca66e" (or null if the string does not match the pattern)
     */
    public static function typographyIdFromRef( string $ref ): ?string {
        if ( preg_match( '/^globals\/typography\?id=(.+)$/', $ref, $m ) ) {
            return $m[1];
        }
        return null;
    }
}
