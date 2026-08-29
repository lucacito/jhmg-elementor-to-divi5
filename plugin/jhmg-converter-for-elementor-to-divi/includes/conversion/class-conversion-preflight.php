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
        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }

        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        if ( $kit_id <= 0 ) {
            return [];
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
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
}
