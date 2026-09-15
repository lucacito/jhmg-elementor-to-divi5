<?php
/**
 * Checks converter output against Divi 5.7.4's module definitions
 * (fixtures/divi-schema/modules.json, extracted by scripts/divi-module-schema.php).
 *
 * Value-key allow-lists are taken from the server-side style declarations under
 * references/Divi/includes/builder-5/server/Packages/Module/Options/<Group>/ and
 * from the renderers named next to each entry.
 */
final class DiviModuleSchema {
    private const BREAKPOINTS = [ 'desktop', 'tablet', 'phone' ];
    private const STATES      = [ 'value', 'hover', 'sticky' ];

    /** Options/Font/Style/StyleDeclarations.php + Font/FontStyle.php */
    private const FONT_KEYS = [ 'family', 'size', 'weight', 'style', 'color', 'lineHeight', 'letterSpacing', 'textAlign', 'headingLevel' ];
    /** Options/TextShadow/Style/StyleDeclarations.php */
    private const TEXT_SHADOW_KEYS = [ 'style', 'horizontal', 'vertical', 'blur', 'color' ];

    /**
     * Groups whose value sits directly under {breakpoint}.{state}: allowed value keys,
     * or null when the value is a scalar or the keys are not checked.
     */
    private const RESPONSIVE_GROUPS = [
        // Options/Background/Style/StyleDeclarations.php
        'background'   => [ 'color', 'image', 'gradient', 'mask', 'pattern', 'video' ],
        // Options/Spacing/Style/StyleDeclarations.php
        'spacing'      => [ 'margin', 'padding' ],
        // Options/Sizing/Style/StyleDeclarations.php
        'sizing'       => [ 'width', 'maxWidth', 'minWidth', 'height', 'minHeight', 'maxHeight', 'alignment', 'flex', 'flexBasis', 'flexGrow', 'flexShrink', 'alignSelf' ],
        // Options/Border/Style/StyleDeclarations.php
        'border'       => [ 'radius', 'styles' ],
        // Options/BoxShadow/Style/StyleDeclarations.php
        'boxShadow'    => [ 'style', 'horizontal', 'vertical', 'blur', 'spread', 'color', 'position' ],
        // Options/Layout/Style/StyleDeclarations.php
        'layout'       => [ 'display', 'flexDirection', 'flexWrap', 'justifyContent', 'alignItems', 'alignContent', 'columnGap', 'rowGap', 'gridColumnCount', 'gridColumnWidths', 'gridTemplateColumns', 'gridRowCount', 'gridRowHeights', 'gridTemplateRows', 'gridAutoFlow', 'gridJustifyItems', 'gridAlignItems', 'gridOffsetRules', 'collapseEmptyColumns', 'gap' ],
        // Options/Link/LinkUtils.php
        'link'         => [ 'url', 'target', 'rel' ],
        // Options/Button/Style/StyleDeclarations.php
        'button'       => [ 'enable', 'icon', 'alignment' ],
        // Options/Overflow/Style/StyleDeclarations.php
        'overflow'     => [ 'x', 'y' ],
        // Options/Position/Style/StyleDeclarations.php
        'position'     => [ 'mode', 'origin', 'offset' ],
        // Options/Filters/Style/StyleDeclarations.php
        'filters'      => [ 'hueRotate', 'saturate', 'brightness', 'contrast', 'invert', 'sepia', 'opacity', 'blur', 'blendMode' ],
        'zIndex'       => null,
        'disabledOn'   => null,
        'attributes'   => null,
        'animation'    => null,
        'transform'    => null,
        'transition'   => null,
        'conditions'   => null,
        'interactions' => null,
        'order'        => null,
        'scroll'       => null,
        'sticky'       => null,
        'fit'          => null,
        'image'        => null,
        'icon'         => null,
    ];

    /** Groups with sub-groups before the breakpoint. */
    private const SUB_GROUPED = [
        'font'        => [ 'font' => 'FONT', 'textShadow' => 'TEXT_SHADOW', 'textEffects' => null ],
        // Options/Text/Style/StyleDeclarations.php
        'text'        => [ 'text' => [ 'orientation', 'color' ], 'textShadow' => 'TEXT_SHADOW' ],
        'bodyFont'    => [ 'body' => 'FONT_GROUP', 'link' => 'FONT_GROUP', 'ul' => 'FONT_GROUP', 'ol' => 'FONT_GROUP', 'quote' => 'FONT_GROUP' ],
        'headingFont' => [ 'h1' => 'FONT_GROUP', 'h2' => 'FONT_GROUP', 'h3' => 'FONT_GROUP', 'h4' => 'FONT_GROUP', 'h5' => 'FONT_GROUP', 'h6' => 'FONT_GROUP' ],
    ];

    /**
     * innerContent value shapes the renderers read, where module.json alone is not
     * enough. Cited per entry.
     */
    private const INNER_CONTENT_OVERRIDES = [
        // TeamMemberModule.php:98 reads ['url']; the subName in module.json is also url.
        'divi/team-member image'    => [ 'url', 'alt', 'animation', 'title' ],
        // TestimonialModule.php:98 reads ['src'] ?? ['url'].
        'divi/testimonial portrait' => [ 'src', 'url', 'alt', 'animation' ],
        // BlurbModule.php:171,607 read useIcon, src; icon is an icon object.
        'divi/blurb imageIcon'      => [ 'useIcon', 'icon', 'src', 'alt', 'title', 'animation' ],
        // IconModule.php:197 reads the icon object.
        'divi/icon icon'            => [ 'unicode', 'type', 'weight' ],
    ];

    /**
     * Paths the server renderer reads that neither module.json's settings nor the
     * conversion outline declare. Cited per entry.
     */
    private const SERVER_READS = [
        // GroupCarouselModule.php:141,162 read showArrows / showDots (module.json names them show).
        'divi/group-carousel' => [ 'arrows.advanced.showArrows', 'dotNav.advanced.showDots' ],
    ];

    private static ?array $schema = null;
    private static array $gaps    = [];

    public static function assertBlocksValid( array $blocks, string $context ): void {
        $problems = self::problems( $blocks );
        $known    = self::knownGaps();
        $report   = [];
        $matched  = [];

        foreach ( $problems as $problem ) {
            $gap = self::matchGap( $problem, $known );
            if ( $gap === null ) {
                $report[] = "{$context}: {$problem}";
            } else {
                $matched[ $gap ] = true;
            }
        }

        PHPUnit\Framework\Assert::assertSame( [], $report, "Converter output that Divi 5.7.4 will not render:\n" . implode( "\n", $report ) );
        self::$gaps = array_merge( self::$gaps, array_keys( $matched ) );
    }

    /** Gaps in the known list that no fixture, probe or page has hit this run. */
    public static function unusedKnownGaps(): array {
        return array_values( array_diff( array_keys( self::knownGaps() ), array_unique( self::$gaps ) ) );
    }

    /** @return string[] "<block name> <dotted path> — <reason>" */
    public static function problems( array $blocks, ?string $parent = null ): array {
        $problems = [];
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }
            $name   = (string) ( $block['name'] ?? '' );
            $module = self::schema()['modules'][ $name ] ?? null;
            if ( $module === null ) {
                $problems[] = "{$name} — not a Divi 5.7.4 module";
                continue;
            }
            if ( $parent !== null ) {
                $allowed = self::schema()['modules'][ $parent ]['childrenName'] ?? [];
                if ( ! empty( $allowed ) && ! in_array( $name, $allowed, true ) ) {
                    $problems[] = "{$name} — not an allowed child of {$parent} (" . implode( ', ', $allowed ) . ')';
                }
            }
            foreach ( $block['settings'] ?? [] as $attr => $groups ) {
                foreach ( self::attrProblems( $name, (string) $attr, $groups, $module ) as $p ) {
                    $problems[] = "{$name} {$p}";
                }
            }
            foreach ( self::problems( $block['elements'] ?? [], $name ) as $p ) {
                $problems[] = $p;
            }
        }
        return $problems;
    }

    private static function attrProblems( string $name, string $attr, mixed $groups, array $module ): array {
        if ( $attr === 'css' ) {
            $allowed = $module['cssFields'];
            $out     = self::envelopeProblems( 'css', $groups );
            foreach ( self::values( $groups, 'css' ) as $path => $value ) {
                foreach ( is_array( $value ) ? array_keys( $value ) : [] as $key ) {
                    if ( ! in_array( (string) $key, $allowed, true ) ) {
                        $out[] = "{$path}.{$key} — not a custom CSS field of this module";
                    }
                }
            }
            return $out;
        }

        $spec = $module['attributes'][ $attr ] ?? null;
        if ( $spec === null ) {
            return [ "{$attr} — not an attribute of this module (" . implode( ', ', array_keys( $module['attributes'] ) ) . ')' ];
        }
        if ( ! is_array( $groups ) ) {
            return [ "{$attr} — must be an object of groups" ];
        }

        $out = [];
        foreach ( $groups as $group => $subs ) {
            $group = (string) $group;
            if ( ! array_key_exists( $group, $spec ) || $group === 'elementType' ) {
                $out[] = "{$attr}.{$group} — attribute '{$attr}' declares no '{$group}' group";
                continue;
            }
            if ( $group === 'innerContent' ) {
                foreach ( self::innerContentProblems( $name, $attr, $spec, $subs ) as $p ) {
                    $out[] = $p;
                }
                continue;
            }
            if ( ! is_array( $subs ) ) {
                $out[] = "{$attr}.{$group} — must be an object";
                continue;
            }
            foreach ( $subs as $sub => $value ) {
                $sub = (string) $sub;
                if ( ! in_array( $sub, $spec[ $group ], true ) && ! in_array( "{$attr}.{$group}.{$sub}", self::SERVER_READS[ $name ] ?? [], true ) ) {
                    $out[] = "{$attr}.{$group}.{$sub} — not declared (" . implode( ', ', $spec[ $group ] ) . ')';
                    continue;
                }
                foreach ( self::groupValueProblems( "{$attr}.{$group}.{$sub}", $sub, $value ) as $p ) {
                    $out[] = $p;
                }
            }
        }
        return $out;
    }

    private static function innerContentProblems( string $name, string $attr, array $spec, mixed $data ): array {
        $out = self::envelopeProblems( "{$attr}.innerContent", $data );
        if ( $out !== [] ) {
            return $out;
        }
        $sub_names = self::INNER_CONTENT_OVERRIDES[ "{$name} {$attr}" ] ?? $spec['innerContent'];
        $open      = in_array( '*', $sub_names, true );
        $type      = $spec['elementType'];

        foreach ( self::values( $data, "{$attr}.innerContent" ) as $path => $value ) {
            if ( $type === 'heading' || $type === 'content' ) {
                if ( ! is_string( $value ) ) {
                    $out[] = "{$path} — a '{$type}' element value is a string";
                }
                continue;
            }
            if ( $type === 'headingLink' || $type === 'button' ) {
                // ModuleElements.php:1012-1024: the text lives under subName 'text';
                // ButtonModule.php:140 falls back to the link when text is empty.
                if ( ! is_array( $value ) ) {
                    $out[] = "{$path} — a '{$type}' element value is an object ({text, …}) (ModuleElements.php:1012-1024)";
                }
                continue;
            }
            if ( $sub_names === [] ) {
                if ( is_array( $value ) ) {
                    $out[] = "{$path} — this innerContent is a scalar in module.json";
                }
                continue;
            }
            if ( ! is_array( $value ) ) {
                $out[] = "{$path} — this innerContent is an object with keys " . implode( ', ', $sub_names );
                continue;
            }
            if ( ! $open ) {
                foreach ( array_keys( $value ) as $key ) {
                    if ( ! in_array( (string) $key, $sub_names, true ) ) {
                        $out[] = "{$path}.{$key} — not one of " . implode( ', ', $sub_names );
                    }
                }
            }
            if ( isset( $value['icon'] ) && ! ( is_array( $value['icon'] ) && isset( $value['icon']['unicode'], $value['icon']['type'] ) ) ) {
                $out[] = "{$path}.icon — an icon is {unicode, type, weight} (IconModule.php:344-350)";
            }
        }
        return $out;
    }

    private static function groupValueProblems( string $path, string $group, mixed $value ): array {
        if ( isset( self::SUB_GROUPED[ $group ] ) ) {
            if ( ! is_array( $value ) ) {
                return [ "{$path} — must be an object of sub-groups" ];
            }
            $out = [];
            foreach ( $value as $sub => $inner ) {
                $shape = self::SUB_GROUPED[ $group ][ $sub ] ?? false;
                if ( $shape === false ) {
                    $out[] = "{$path}.{$sub} — not a sub-group of '{$group}' (" . implode( ', ', array_keys( self::SUB_GROUPED[ $group ] ) ) . ')';
                    continue;
                }
                if ( $shape === 'FONT_GROUP' ) {
                    foreach ( self::groupValueProblems( "{$path}.{$sub}", 'font', $inner ) as $p ) {
                        $out[] = $p;
                    }
                    continue;
                }
                $keys = $shape === 'FONT' ? self::FONT_KEYS : ( $shape === 'TEXT_SHADOW' ? self::TEXT_SHADOW_KEYS : $shape );
                foreach ( self::responsiveProblems( "{$path}.{$sub}", $inner, $keys ) as $p ) {
                    $out[] = $p;
                }
                if ( $shape === 'FONT' ) {
                    foreach ( self::values( $inner, "{$path}.{$sub}" ) as $vpath => $font ) {
                        $level = is_array( $font ) ? ( $font['headingLevel'] ?? null ) : null;
                        if ( $level !== null && ! preg_match( '/^h[1-6]$/', (string) $level ) ) {
                            $out[] = "{$vpath}.headingLevel — '{$level}' is not styled by Divi; the heading selector covers h1-h6 only (heading/module.json title.selector)";
                        }
                    }
                }
            }
            return $out;
        }

        if ( array_key_exists( $group, self::RESPONSIVE_GROUPS ) ) {
            return self::responsiveProblems( $path, $value, self::RESPONSIVE_GROUPS[ $group ] );
        }

        // An `advanced.*` field or a group this helper has no key list for: envelope only.
        return self::envelopeProblems( $path, $value );
    }

    private static function responsiveProblems( string $path, mixed $value, ?array $keys ): array {
        $out = self::envelopeProblems( $path, $value );
        if ( $out !== [] || $keys === null ) {
            return $out;
        }
        foreach ( self::values( $value, $path ) as $vpath => $v ) {
            if ( ! is_array( $v ) ) {
                $out[] = "{$vpath} — expected an object with keys " . implode( ', ', $keys );
                continue;
            }
            foreach ( array_keys( $v ) as $key ) {
                if ( ! in_array( (string) $key, $keys, true ) ) {
                    $out[] = "{$vpath}.{$key} — not a key Divi reads here (" . implode( ', ', $keys ) . ')';
                }
            }
        }
        return $out;
    }

    /** {desktop|tablet|phone}.{value|hover|sticky} */
    private static function envelopeProblems( string $path, mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [ "{$path} — expected {breakpoint: {state: …}}" ];
        }
        $out = [];
        foreach ( $value as $bp => $states ) {
            if ( ! in_array( (string) $bp, self::BREAKPOINTS, true ) ) {
                $out[] = "{$path}.{$bp} — not a breakpoint (desktop, tablet, phone)";
                continue;
            }
            if ( ! is_array( $states ) ) {
                $out[] = "{$path}.{$bp} — expected {value: …}";
                continue;
            }
            foreach ( array_keys( $states ) as $state ) {
                if ( ! in_array( (string) $state, self::STATES, true ) ) {
                    $out[] = "{$path}.{$bp}.{$state} — not a state (value, hover, sticky)";
                }
            }
        }
        return $out;
    }

    /** @return array<string, mixed> "path.desktop.value" => value, for a valid envelope */
    private static function values( mixed $envelope, string $path = '' ): array {
        $out = [];
        foreach ( is_array( $envelope ) ? $envelope : [] as $bp => $states ) {
            foreach ( is_array( $states ) ? $states : [] as $state => $value ) {
                $out[ ltrim( "{$path}.{$bp}.{$state}", '.' ) ] = $value;
            }
        }
        return $out;
    }

    private static function schema(): array {
        if ( self::$schema === null ) {
            self::$schema = json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/divi-schema/modules.json' ), true );
        }
        return self::$schema;
    }

    private static function knownGaps(): array {
        $file = __DIR__ . '/divi-schema-known-gaps.php';
        return file_exists( $file ) ? require $file : [];
    }

    /** A gap matches when the problem starts with its key ("divi/x attr.path"). */
    private static function matchGap( string $problem, array $known ): ?string {
        foreach ( array_keys( $known ) as $prefix ) {
            if ( str_starts_with( $problem, $prefix ) ) {
                return $prefix;
            }
        }
        return null;
    }
}
