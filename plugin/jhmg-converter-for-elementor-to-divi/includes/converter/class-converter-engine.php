<?php

namespace ElementorDivi5Converter\Converter;

use ElementorDivi5Converter\Converter\ConverterInterface;
use ElementorDivi5Converter\Converter\Registry\ConverterRegistry;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConverterEngine {
    private ConverterRegistry $registry;
    private array $unsupportedWidgets = [];
    private array $conversionCounts   = [];
    private array $conversionWarnings = [];
    private array $skippedSettings    = [];
    private array $unresolvedGlobals  = [];
    private int   $nestingDepth       = 0;

    /** Map of Elementor global color id → hex value (e.g. 'bef7937' => '#14305A'). */
    private array $globalColors = [];

    public function __construct() {
        $this->registry = new ConverterRegistry( $this );
    }

    /**
     * Supply resolved global color values so `__globals__` references in element
     * settings can be substituted with real hex values before conversion.
     *
     * @param array<string,string> $colorMap Elementor color id → hex string.
     */
    public function setGlobalColors( array $colorMap ): void {
        $this->globalColors = $colorMap;
    }

    public function logConverted( string $type ): void {
        $this->conversionCounts[ $type ] = ( $this->conversionCounts[ $type ] ?? 0 ) + 1;
    }

    public function logWarning( string $message ): void {
        $this->conversionWarnings[] = $message;
    }

    public function logSkippedSetting( string $message ): void {
        $this->skippedSettings[] = $message;
    }

    /**
     * Records a `__globals__` reference no configured kit could resolve.
     *
     * The property is left unset rather than filled with a stand-in, so this
     * entry is the only trace the conversion leaves of it. It names the element
     * and the setting so the value can be re-applied by hand.
     */
    public function logUnresolvedGlobal( string $element_id, string $setting_key, string $ref ): void {
        $entry = [
            'element_id'  => $element_id,
            'setting_key' => $setting_key,
            'ref'         => $ref,
        ];

        // The same preset referenced by several widgets is one gap to fix, not
        // one per widget; and a repeated line reads as a bigger problem than it is.
        if ( ! in_array( $entry, $this->unresolvedGlobals, true ) ) {
            $this->unresolvedGlobals[] = $entry;
        }
    }

    /** @return array<int,array{element_id:string,setting_key:string,ref:string}> */
    public function getUnresolvedGlobals(): array {
        return $this->unresolvedGlobals;
    }

    public function getReport(): array {
        $converted_total  = array_sum( $this->conversionCounts );
        $unsupported_total = count( $this->unsupportedWidgets );
        $all_widgets       = $converted_total + $unsupported_total;
        $widget_coverage   = $all_widgets > 0 ? (int) round( $converted_total / $all_widgets * 100 ) : 100;
        $settings_issues   = count( $this->skippedSettings );

        return [
            'converted'          => $this->conversionCounts,
            'warnings'           => $this->conversionWarnings,
            'skipped_settings'   => $this->skippedSettings,
            'unresolved_globals' => $this->unresolvedGlobals,
            'quality'            => [
                'widget_coverage'  => $widget_coverage,
                'settings_issues'  => $settings_issues,
            ],
        ];
    }

    public function convert( array $elementor_data ): array {
        $elements = $this->extractRootElements( $elementor_data );

        return [
            'divi'        => [
                'elements' => $this->convertChildren( $elements ),
            ],
            'unsupported' => $this->unsupportedWidgets,
            'report'      => $this->getReport(),
        ];
    }

    public function convertChildren( array $elements ): array {
        $this->nestingDepth++;
        $converted = [];

        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $result = $this->convertElement( $element );

            if ( empty( $result ) ) {
                continue;
            }

            // Converters return either a single block (has 'name' key) or a list
            // of blocks (numeric array). Spread the list into siblings.
            if ( isset( $result['name'] ) ) {
                $converted[] = $result;
            } else {
                foreach ( $result as $block ) {
                    if ( ! empty( $block ) ) {
                        $converted[] = $block;
                    }
                }
            }
        }

        $this->nestingDepth--;
        return $converted;
    }

    public function getNestingDepth(): int {
        return $this->nestingDepth;
    }

    public function convertElement( array $element ): array {
        $element   = $this->resolveElementGlobals( $element );
        $converter = $this->registry->getConverter( $element );

        if ( $converter instanceof ConverterInterface ) {
            return $converter->convert( $element );
        }

        $this->logUnsupportedElement( $element );

        return [];
    }

    /**
     * Substitutes `__globals__` color references with actual hex values from
     * the global color map set via setGlobalColors().
     *
     * For each entry in `settings.__globals__` of the form
     * `'globals/colors?id=<colorId>'`, if `<colorId>` exists in $globalColors
     * AND the corresponding settings key has no direct value yet, the hex value
     * is injected into settings so downstream converters and StyleMapper see it
     * as a normal hex string.
     */
    private function resolveElementGlobals( array $element ): array {
        $globals = $element['settings']['__globals__'] ?? [];
        if ( empty( $globals ) || ! is_array( $globals ) ) {
            return $element;
        }

        $element_id = (string) ( $element['id'] ?? '' );

        foreach ( $globals as $setting_key => $global_ref ) {
            if ( ! is_string( $global_ref ) || $global_ref === '' ) {
                continue;
            }

            $is_color      = strpos( $global_ref, 'globals/colors' ) !== false;
            $is_typography = strpos( $global_ref, 'globals/typography' ) !== false;

            if ( ! $is_color && ! $is_typography ) {
                continue;
            }

            // A setting that already carries a direct value does not depend on
            // the global at all, so an unknown ID costs nothing there.
            $current = $element['settings'][ $setting_key ] ?? '';
            if ( $current !== '' && $current !== null && $current !== [] ) {
                continue;
            }

            $query_string = (string) wp_parse_url( $global_ref, PHP_URL_QUERY );
            parse_str( $query_string, $params );
            $global_id = (string) ( $params['id'] ?? '' );

            if ( $global_id === '' ) {
                $this->logUnresolvedGlobal( $element_id, (string) $setting_key, $global_ref );
                continue;
            }

            if ( $is_color ) {
                // The engine's injected map and the kit filter are the same two
                // sources StyleMapper consults later; checking both here keeps
                // this report in step with what actually gets written.
                $hex = $this->globalColors[ $global_id ] ?? GlobalsResolver::resolveColor( $global_id );

                if ( $hex === null || $hex === '' ) {
                    $this->logUnresolvedGlobal( $element_id, (string) $setting_key, $global_ref );
                    continue;
                }

                $element['settings'][ $setting_key ] = $hex;
                continue;
            }

            // Typography presets are applied by StyleMapper against the widget's
            // font path, so nothing is injected here — only the gap is recorded.
            if ( GlobalsResolver::resolveTypography( $global_id ) === null ) {
                $this->logUnresolvedGlobal( $element_id, (string) $setting_key, $global_ref );
            }
        }

        return $element;
    }

    private function extractRootElements( array $elementor_data ): array {
        if ( isset( $elementor_data['elements'] ) && is_array( $elementor_data['elements'] ) ) {
            return $elementor_data['elements'];
        }

        return $elementor_data;
    }

    private function logUnsupportedElement( array $element ): void {
        $this->unsupportedWidgets[] = [
            'id' => $element['id'] ?? null,
            'elType' => $element['elType'] ?? null,
            'widgetType' => $element['widgetType'] ?? null,
        ];
    }

    public function getUnsupportedElements(): array {
        return $this->unsupportedWidgets;
    }
}
