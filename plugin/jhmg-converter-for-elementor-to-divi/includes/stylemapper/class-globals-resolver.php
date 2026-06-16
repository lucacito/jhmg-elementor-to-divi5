<?php

namespace ElementorDivi5Converter\StyleMapper;

use ElementorDivi5Converter\Premium\GlobalsStore;

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
 * When a Global Kit is loaded (via the Global Kit tab), its colors and typography
 * take priority over the fallback maps below. The fallback maps provide example
 * defaults; upload a kit ZIP to apply your own site's global styles.
 */
class GlobalsResolver {

    /**
     * Maps Elementor global color IDs to their resolved hex / rgba values.
     * Key = the ID segment after `globals/colors?id=`.
     */
    private const COLOR_MAP = [
        'accent'    => '#F9A620',
        'primary'   => '#070707',
        'secondary' => '#110A72',
        'text'      => '#6E7271',
        '308e809'   => '#FFFFFF',
        '651faef'   => '#F0F5F8',
        '2d69694'   => '#083B5C',
        '2db06a9'   => '#58595B',
        'a954db2'   => '#C6D9E5',
        'bd8f609'   => '#DBE4C9',
        '9eaa092'   => 'rgba(255,255,255,0)',
    ];

    /**
     * Maps Elementor global typography IDs to Divi 5 font attribute values.
     *
     * Keys within each entry match Divi 5 font.{bp}.value property names:
     *   family, size, weight, letterSpacing, lineHeight
     * The optional `style` key holds an array of flag strings ('capitalize',
     * 'uppercase', 'lowercase', 'italic', 'underline', 'strikethrough').
     */
    private const TYPOGRAPHY_MAP = [
        // h1 label style
        '84ca66e' => [
            'family'        => 'Rethink Sans',
            'size'          => 'clamp(1.125rem, 1.0761rem + 0.2174vw, 1.25rem)',
            'weight'        => '700',
            'letterSpacing' => '0.5px',
            'lineHeight'    => '1em',
        ],
        // Large headline
        '3715edf' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(2.3125rem, 1.7255rem + 2.6087vw, 3.8125rem)',
            'weight'        => '600',
            'letterSpacing' => '-1.5px',
            'lineHeight'    => '1.12em',
            'style'         => [ 'capitalize' ],
        ],
        // Body / paragraph text
        'b2ce6af' => [
            'family'        => 'Rethink Sans',
            'size'          => 'clamp(1rem, 0.9511rem + 0.2174vw, 1.125rem)',
            'weight'        => '500',
            'letterSpacing' => '0px',
            'lineHeight'    => '1.5em',
        ],
        // Small label / caption
        '69c152f' => [
            'family'        => 'Rethink Sans',
            'size'          => 'clamp(0.75rem, 0.7011rem + 0.2174vw, 0.875rem)',
            'weight'        => '400',
            'letterSpacing' => '0px',
            'lineHeight'    => '1.6em',
        ],
        // Stat / counter label
        '7044a64' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(1rem, 0.9022rem + 0.4348vw, 1.25rem)',
            'weight'        => '500',
            'letterSpacing' => '-0.2px',
            'lineHeight'    => '1.3em',
        ],
        // Tiny helper text
        '112b6c4' => [
            'family'        => 'Rethink Sans',
            'size'          => 'clamp(0.6875rem, 0.6386rem + 0.2174vw, 0.8125rem)',
            'weight'        => '400',
            'letterSpacing' => '0px',
            'lineHeight'    => '1.5em',
        ],
        // Button / CTA text
        '520c191' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(0.875rem, 0.8261rem + 0.2174vw, 1rem)',
            'weight'        => '600',
            'letterSpacing' => '0px',
            'lineHeight'    => '1.3em',
        ],
        // H2 section heading
        '583e54c' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(1.9375rem, 1.4973rem + 1.9565vw, 3.0625rem)',
            'weight'        => '600',
            'letterSpacing' => '-1px',
            'lineHeight'    => '1.25em',
            'style'         => [ 'capitalize' ],
        ],
        // H3 / sub-heading
        '5d167aa' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(1.625rem, 1.3071rem + 1.413vw, 2.4375rem)',
            'weight'        => '600',
            'letterSpacing' => '-1px',
            'lineHeight'    => '1.25em',
        ],
        // H4
        '83682a1' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(1.1875rem, 1.0408rem + 0.6522vw, 1.5625rem)',
            'weight'        => '500',
            'letterSpacing' => '-0.5px',
            'lineHeight'    => '1.27em',
        ],
        // H5
        'd3dae9a' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(1.3125rem, 1.0679rem + 1.087vw, 1.9375rem)',
            'weight'        => '600',
            'letterSpacing' => '-0.8px',
            'lineHeight'    => '1.27em',
        ],
        // Hero / display heading
        'f8733ea' => [
            'family'        => 'Roboto',
            'size'          => 'clamp(3.25rem, 2.1984rem + 4.6739vw, 5.9375rem)',
            'weight'        => '700',
            'letterSpacing' => '-2px',
            'lineHeight'    => '1em',
            'style'         => [ 'capitalize' ],
        ],
    ];

    /**
     * Resolve `globals/colors?id=<id>` to a concrete hex/rgba string.
     * Returns null when the ID is not in the map.
     */
    public static function resolveColor( string $id ): ?string {
        if ( function_exists( 'get_option' ) ) {
            $kit = GlobalsStore::load();
            if ( $kit !== null && isset( $kit['colors'][ $id ] ) ) {
                return $kit['colors'][ $id ];
            }
        }
        return self::COLOR_MAP[ $id ] ?? null;
    }

    /**
     * Resolve `globals/typography?id=<id>` to a Divi 5 font value array.
     * Returns null when the ID is not in the map.
     */
    public static function resolveTypography( string $id ): ?array {
        if ( function_exists( 'get_option' ) ) {
            $kit = GlobalsStore::load();
            if ( $kit !== null && isset( $kit['typography'][ $id ] ) ) {
                return $kit['typography'][ $id ];
            }
        }
        return self::TYPOGRAPHY_MAP[ $id ] ?? null;
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
