<?php
/**
 * Runs a conversion without committing it.
 *
 * This is the one engine behind both of 3.0.0's features: the preview screen
 * shows a plan, and a commit writes one. Building it once is the reason those
 * two features shipped together — built separately, each would have grown its
 * own half of it.
 *
 * The invariant that matters: run() performs no database writes. ConversionPreflightTest
 * asserts it against the in-memory post and meta stores rather than trusting it.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviBlockSerializer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionPreflight {

    /**
     * Free converts one page per run. Pro raises this. It is a quantity
     * boundary, not a feature flag: the whole loop ships in the free plugin
     * and nothing here is disabled code waiting to be unlocked.
     */
    const LIMIT_FILTER  = 'edc_direct_conversion_limit';
    const DEFAULT_LIMIT = 1;

    private ?ConverterEngine $engine;
    private DiviBlockSerializer $serializer;

    /**
     * @param ConverterEngine|null $engine Injected only by tests that need to
     *   observe the engine. Left null in production so each item gets a fresh
     *   one — the engine accumulates report state across convert() calls.
     */
    public function __construct( ?ConverterEngine $engine = null, ?DiviBlockSerializer $serializer = null ) {
        $this->engine     = $engine;
        $this->serializer = $serializer ?? new DiviBlockSerializer();
    }

    public static function limit(): int {
        if ( ! function_exists( 'apply_filters' ) ) {
            return self::DEFAULT_LIMIT;
        }

        return max( 1, (int) apply_filters( self::LIMIT_FILTER, self::DEFAULT_LIMIT ) );
    }

    public function run( ConversionSource $source ): ConversionPlan {
        $limit     = self::limit();
        $all       = $source->items();
        $truncated = count( $all ) > $limit;
        $items     = array_slice( $all, 0, $limit );

        $planned = [];
        foreach ( $items as $item ) {
            $planned[] = $this->planItem( $item );
        }

        return new ConversionPlan( $planned, $limit, $truncated );
    }

    /**
     * Plan every item, ignoring the direct-conversion cap. Used by the upload
     * path: a kit ZIP's page count is the user's file, not a tier boundary.
     */
    public function runUnlimited( ConversionSource $source ): ConversionPlan {
        $planned = [];
        foreach ( $source->items() as $item ) {
            $planned[] = $this->planItem( $item );
        }

        return new ConversionPlan( $planned, PHP_INT_MAX, false );
    }

    private function planItem( array $item ): array {
        $base = [
            'title'         => $item['title']         ?? '',
            'post_type'     => $item['post_type']     ?? 'page',
            'post_name'     => $item['post_name']     ?? '',
            'template_type' => $item['template_type'] ?? '',
            'source_ref'    => $item['source_ref']    ?? [],
        ];

        $incoming_error = (string) ( $item['error'] ?? '' );
        if ( $incoming_error !== '' ) {
            return ConversionPlan::item( $base + [ 'error' => $incoming_error ] );
        }

        try {
            $engine    = $this->engineForItem();
            $converted = $engine->convert( $item['elements'] ?? [] );

            return ConversionPlan::item( $base + [
                'blocks'      => $converted['divi'] ?? [],
                'content'     => $this->serializer->serialize( $converted ),
                'report'      => $converted['report'] ?? [],
                'unsupported' => $converted['unsupported'] ?? [],
                'outline'     => ConversionOutline::build(
                    $converted['divi']['elements'] ?? [],
                    $converted['unsupported'] ?? []
                ),
            ] );
        } catch ( \Throwable $e ) {
            return ConversionPlan::item( $base + [ 'error' => $e->getMessage() ] );
        }
    }

    /**
     * A fresh engine per item unless one was injected: ConverterEngine
     * accumulates counts and warnings across convert() calls, so a shared
     * instance would report item A's problems on item B.
     */
    private function engineForItem(): ConverterEngine {
        if ( $this->engine !== null ) {
            return $this->engine;
        }

        $engine = new ConverterEngine();

        $colors = self::elementorGlobalColors();
        if ( ! empty( $colors ) ) {
            $engine->setGlobalColors( $colors );
        }

        return $engine;
    }

    /**
     * Elementor stores the active Kit post ID in `elementor_active_kit`; the
     * Kit's `_elementor_page_settings` meta holds system and custom color
     * arrays of `{_id, color}` objects.
     *
     * @return array<string,string> color id => hex
     */
    public static function elementorGlobalColors(): array {
        $kit_settings = self::activeKitSettings();
        if ( $kit_settings === null ) {
            return [];
        }

        $colors = [];
        foreach ( [ 'system_colors', 'custom_colors' ] as $group_key ) {
            $group = $kit_settings[ $group_key ] ?? [];
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $color_item ) {
                $id  = $color_item['_id']   ?? '';
                $hex = $color_item['color'] ?? '';
                if ( $id !== '' && $hex !== '' ) {
                    $colors[ $id ] = $hex;
                }
            }
        }

        return $colors;
    }

    /**
     * The installed Kit's global typography presets, in the same shape
     * GlobalsResolver::resolveTypography() returns and StyleMapper's
     * applyGlobalTypography() consumes (family, size, weight, lineHeight,
     * letterSpacing).
     *
     * Mirrors Pro\Kit\KitGlobalsParser::parse()'s typography reader, which
     * extracts the identical fields from a kit ZIP's site-settings.json. The two
     * must stay in step: a preset shape only one of them produces would render
     * differently depending on where the kit came from.
     *
     * @return array<string,array<string,string>> typography id => preset
     */
    public static function elementorGlobalTypography(): array {
        $kit_settings = self::activeKitSettings();
        if ( $kit_settings === null ) {
            return [];
        }

        $typography = [];
        foreach ( [ 'system_typography', 'custom_typography' ] as $group_key ) {
            $group = $kit_settings[ $group_key ] ?? [];
            if ( ! is_array( $group ) ) {
                continue;
            }

            foreach ( $group as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }
                $id = $entry['_id'] ?? '';
                if ( $id === '' ) {
                    continue;
                }

                $preset = [];
                if ( ! empty( $entry['typography_font_family'] ) ) {
                    $preset['family'] = (string) $entry['typography_font_family'];
                }
                if ( ! empty( $entry['typography_font_weight'] ) ) {
                    $preset['weight'] = (string) $entry['typography_font_weight'];
                }
                $size = self::sizeWithUnit( $entry['typography_font_size'] ?? null, 'px' );
                if ( $size !== '' ) {
                    $preset['size'] = $size;
                }
                $line_height = self::sizeWithUnit( $entry['typography_line_height'] ?? null, 'em' );
                if ( $line_height !== '' ) {
                    $preset['lineHeight'] = $line_height;
                }
                // Letter spacing is a {size,unit} group in current Elementor and a
                // bare number in older kits; accept both.
                $raw_spacing = $entry['typography_letter_spacing'] ?? null;
                if ( is_array( $raw_spacing ) ) {
                    $spacing = self::sizeWithUnit( $raw_spacing, 'px' );
                } elseif ( is_scalar( $raw_spacing ) && (string) $raw_spacing !== '' ) {
                    $spacing = (string) $raw_spacing . 'px';
                } else {
                    $spacing = '';
                }
                if ( $spacing !== '' ) {
                    $preset['letterSpacing'] = $spacing;
                }

                if ( ! empty( $preset ) ) {
                    $typography[ $id ] = $preset;
                }
            }
        }

        return $typography;
    }

    /**
     * Both global groups on this site, in the `edc_kit_globals` shape. Registered
     * by the free plugin as the gap-filling half of that filter — see
     * Plugin::register_hooks().
     *
     * @return array{colors: array<string,string>, typography: array<string,array>}
     */
    public static function installedKitGlobals(): array {
        return [
            'colors'     => self::elementorGlobalColors(),
            'typography' => self::elementorGlobalTypography(),
        ];
    }

    /** The active Kit's `_elementor_page_settings`, or null when there is none. */
    private static function activeKitSettings(): ?array {
        if ( ! function_exists( 'get_option' ) ) {
            return null;
        }

        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        if ( $kit_id <= 0 ) {
            return null;
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

        return is_array( $kit_settings ) ? $kit_settings : null;
    }

    /**
     * Elementor size controls serialise as `{size, unit}`. Returns '' when the
     * value is absent or empty so callers can skip the property entirely rather
     * than emitting a unit with no number.
     */
    private static function sizeWithUnit( mixed $raw, string $default_unit ): string {
        if ( ! is_array( $raw ) ) {
            return '';
        }
        $size = $raw['size'] ?? null;
        if ( $size === null || $size === '' ) {
            return '';
        }
        $unit = is_string( $raw['unit'] ?? '' ) && $raw['unit'] !== '' ? $raw['unit'] : $default_unit;

        return (string) $size . $unit;
    }
}
