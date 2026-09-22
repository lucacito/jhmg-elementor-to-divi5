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
    private array $notCarriedOver     = [];
    private array $approximateCounts  = [];
    private array $approximateMatches = [];
    private int   $nestingDepth       = 0;

    /**
     * Set only for the duration of a shape-matched converter's convert() call, so
     * its logConverted() lands in the approximate bucket instead of the real one.
     */
    private bool $countingApproximate = false;

    /**
     * Elementor settings that are dropped by design and, until now, without a
     * trace: they sit in StyleMapper::suppressUnimplementable() or in
     * logUnmappedSettings()'s $always_ignore, so they never reached the report at
     * all. A page could lose every animation and every dynamic binding on it and
     * still convert "clean".
     *
     * Keys are matched by prefix. Values are the report category.
     */
    private const NOT_CARRIED_PREFIXES = [
        'motion_fx_' => 'motion',
        // Sections carry their parallax under this prefix rather than the plain
        // one, and StyleMapper::markSectionKeys() silently absorbs it.
        'background_motion_fx_' => 'motion',
        'sticky_'    => 'motion',
        '_animation' => 'animation',
        'animation'  => 'animation',
    ];

    /**
     * Exact keys, matched alongside the prefixes above.
     *
     * Sticky is an Elementor Pro feature, so its control schema could not be read
     * from source here — only Pro ships the module. The rest of this codebase
     * assumes the `sticky_` prefix; the bare key is matched too because missing a
     * real sticky element costs the user a silent loss, while a widget that
     * happens to have an unrelated setting named exactly `sticky` costs one
     * accurate-looking line in a report.
     */
    private const NOT_CARRIED_EXACT = [
        'sticky' => 'motion',
    ];

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
        if ( $this->countingApproximate ) {
            $this->approximateCounts[ $type ] = ( $this->approximateCounts[ $type ] ?? 0 ) + 1;
            return;
        }

        $this->conversionCounts[ $type ] = ( $this->conversionCounts[ $type ] ?? 0 ) + 1;
    }

    /**
     * Records that a widget reached its converter by settings-shape guesswork
     * rather than by being registered — see ConverterRegistry::detectByShape().
     *
     * Such a match is a guess that happened to fit, so it is reported as
     * approximate and kept out of the converted counts. Counting it as a clean
     * conversion told the user a widget nobody had mapped came through intact.
     */
    public function flagApproximate( string $element_id, string $widget_type, string $matched_to ): void {
        $this->approximateMatches[] = [
            'element_id'  => $element_id,
            'widget_type' => $widget_type,
            'matched_to'  => $matched_to,
        ];
    }

    /** @return array<int,array{element_id:string,widget_type:string,matched_to:string}> */
    public function getApproximateMatches(): array {
        return $this->approximateMatches;
    }

    /**
     * Records something the conversion could not carry over at all.
     *
     * @param string $kind One of 'dynamic', 'animation', 'motion', 'form_fields',
     *                     'counter_affix', 'gallery_extras', 'social_network', 'query_filter',
     *                     'saved_template', 'testimonial_rating'.
     */
    public function logNotCarriedOver( string $kind, string $element_id, string $detail ): void {
        $entry = [
            'kind'       => $kind,
            'element_id' => $element_id,
            'detail'     => $detail,
        ];

        if ( ! in_array( $entry, $this->notCarriedOver, true ) ) {
            $this->notCarriedOver[] = $entry;
        }
    }

    /** @return array<int,array{kind:string,element_id:string,detail:string}> */
    public function getNotCarriedOver(): array {
        return $this->notCarriedOver;
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
        $converted_total   = array_sum( $this->conversionCounts );
        $approximate_total = array_sum( $this->approximateCounts );
        $unsupported_total = count( $this->unsupportedWidgets );

        // Approximate matches sit in the denominator but not the numerator: they
        // are widgets that arrived somewhere plausible by guesswork, and counting
        // them as clean conversions overstated how much of the page came through.
        $all_widgets     = $converted_total + $approximate_total + $unsupported_total;
        $widget_coverage = $all_widgets > 0 ? (int) round( $converted_total / $all_widgets * 100 ) : 100;
        $settings_issues = count( $this->skippedSettings );

        return [
            'converted'          => $this->conversionCounts,
            'approximate'        => $this->approximateCounts,
            'approximate_matches' => $this->approximateMatches,
            'warnings'           => $this->conversionWarnings,
            'skipped_settings'   => $this->skippedSettings,
            'unresolved_globals' => $this->unresolvedGlobals,
            'not_carried_over'   => $this->notCarriedOver,
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

    /**
     * Runs the per-element bookkeeping that convertElement() would have done,
     * for structural elements that never reach it.
     *
     * Nested sections and containers are routed straight to convertInnerAsRow()
     * and friends by BaseElementorConverter::convertStructureChildren(), which
     * bypasses convertElement() entirely. Without this they resolved no globals
     * and reported no losses: a nested container with a global background colour
     * dropped it in silence, and one with an entrance animation said nothing at
     * all — on the containers most pages are actually built from.
     *
     * @return array The element with any resolvable globals substituted in.
     */
    public function prepareNestedElement( array $element ): array {
        $element = $this->resolveElementGlobals( $element );
        $this->recordNotCarriedOver( $element );

        return $element;
    }

    public function convertElement( array $element ): array {
        $element   = $this->resolveElementGlobals( $element );
        $this->recordNotCarriedOver( $element );

        $matches_before = count( $this->approximateMatches );
        $converter      = $this->registry->getConverter( $element );

        if ( $converter instanceof ConverterInterface ) {
            // getConverter() flags a shape match as it makes one, so a new entry
            // means this converter was guessed at rather than registered.
            $is_approximate = count( $this->approximateMatches ) > $matches_before;

            if ( ! $is_approximate ) {
                return $converter->convert( $element );
            }

            $this->countingApproximate = true;
            try {
                return $converter->convert( $element );
            } finally {
                $this->countingApproximate = false;
            }
        }

        $this->logUnsupportedElement( $element );

        // Structural element types have no meaningful placeholder — an orphan
        // column or section is a container, not content, and emitting a code
        // block for one would put a stray marker where a layout box used to be.
        if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
            return [];
        }

        // Still unsupported, and still reported as such — but the widget keeps
        // its place in the layout and its text instead of vanishing.
        return $this->registry->defaultConverter( $element )->convert( $element );
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
    /**
     * Notes the things this element carries that the conversion cannot express
     * at all, before any converter runs.
     *
     * These are all suppressed further down the pipeline — dynamic tags and
     * animation keys never reach logSkippedSetting(), and form_fields is listed
     * as "mapped" by FormConverter while nothing reads it — so this is the only
     * place they are visible. Detection lives here rather than in the handlers
     * because the element id and the raw settings are both in hand exactly once.
     */
    public function recordNotCarriedOver( array $element ): void {
        // Deliberately not limited to widgets. Sticky is almost always set on a
        // section or container rather than a widget, and entrance animations are
        // routinely set on sections and columns — restricting this to widgets
        // meant the most common places these are used reported nothing at all.
        $settings   = $element['settings'] ?? [];
        $element_id = (string) ( $element['id'] ?? '' );

        if ( ! is_array( $settings ) ) {
            return;
        }

        // Dynamic tags: the widget renders a live value (a post field, an ACF
        // field, a site setting). Conversion keeps only whatever static fallback
        // happened to be stored alongside it, which is often nothing.
        $dynamic = $settings['__dynamic__'] ?? [];
        if ( is_array( $dynamic ) ) {
            foreach ( array_keys( $dynamic ) as $setting_key ) {
                $this->logNotCarriedOver( 'dynamic', $element_id, (string) $setting_key );
            }
        }

        // Elementor Pro form field definitions. FormConverter maps the submit
        // button, recipient and success message; every field is discarded and
        // Divi renders its own default name/email/message trio instead.
        $fields = $settings['form_fields'] ?? null;
        if ( is_array( $fields ) && ! empty( $fields ) ) {
            $this->logNotCarriedOver(
                'form_fields',
                $element_id,
                /* translators: %d: number of form fields that were discarded */
                sprintf( _n( '%d field', '%d fields', count( $fields ), 'jhmg-converter-for-elementor-to-divi' ), count( $fields ) )
            );
        }

        $this->recordDroppedMotion( $settings, $element_id );
    }

    /** Entrance animations and motion effects, neither of which Divi 5 can express. */
    private function recordDroppedMotion( array $settings, string $element_id ): void {
        $found = [];

        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || $this->isEmptyValue( $value ) ) {
                continue;
            }

            if ( isset( self::NOT_CARRIED_EXACT[ $key ] ) && ! ( is_string( $value ) && $value === 'none' ) ) {
                $kind           = self::NOT_CARRIED_EXACT[ $key ];
                $found[ $kind ] = $found[ $kind ] ?? ( is_string( $value ) ? $value : $key );
                continue;
            }

            foreach ( self::NOT_CARRIED_PREFIXES as $prefix => $kind ) {
                if ( ! str_starts_with( $key, $prefix ) ) {
                    continue;
                }

                // 'none' is Elementor's way of saying the control is switched off.
                if ( is_string( $value ) && $value === 'none' ) {
                    continue 2;
                }

                // One line per element per category: a single entrance animation
                // sets half a dozen keys, and listing each would read as six
                // separate losses.
                $found[ $kind ] = $found[ $kind ] ?? ( is_string( $value ) ? $value : $key );
                continue 2;
            }
        }

        foreach ( $found as $kind => $detail ) {
            $this->logNotCarriedOver( $kind, $element_id, $detail );
        }
    }

    private function isEmptyValue( mixed $value ): bool {
        return $value === '' || $value === null || $value === [] || $value === false;
    }

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
