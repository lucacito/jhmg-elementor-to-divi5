<?php
/**
 * Extracts what the converter's tests need from Divi's generated module definitions.
 *
 *   php scripts/divi-module-schema.php          # rewrites the two generated files
 *
 * Sources (never edited):
 *   references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json
 *   references/Divi/includes/builder/feature/icon-manager/full_icons_list.json
 *
 *   references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/conversion-outline.json
 *
 * module.json's settings tree lists the attributes that have a UI field. The
 * structural ones the server also reads (a column's module.advanced.type, a
 * row's columnStructure, a social item's label) appear only in the module's
 * conversion outline — Divi's own D4-to-D5 attribute map — so both are merged.
 *
 * Outputs:
 *   fixtures/divi-schema/modules.json                                        (tests/support/DiviModuleSchema.php)
 *   plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php   (Helpers\FontAwesomeIcons)
 */
declare( strict_types=1 );

/** @return array{diviVersion: string, modules: array<string, array>} */
function edc_divi_schema_build( string $components_dir, string $divi_style_css ): array {
    $version = '';
    if ( preg_match( '/^Version:\s*(\S+)/m', (string) file_get_contents( $divi_style_css ), $m ) ) {
        $version = $m[1];
    }

    $modules = [];
    foreach ( glob( rtrim( $components_dir, '/' ) . '/*/module.json' ) as $file ) {
        $json = json_decode( (string) file_get_contents( $file ), true );
        if ( ! is_array( $json ) || empty( $json['name'] ) ) {
            continue;
        }

        $attributes = [];
        foreach ( $json['attributes'] ?? [] as $attr_name => $spec ) {
            if ( ! is_array( $spec ) ) {
                continue;
            }
            $entry = [ 'elementType' => (string) ( $spec['elementType'] ?? '' ) ];
            foreach ( $spec['settings'] ?? [] as $group => $config ) {
                if ( $group === 'innerContent' ) {
                    $entry['innerContent'] = edc_divi_schema_sub_names( is_array( $config ) ? $config : [] );
                } elseif ( is_array( $config ) ) {
                    $entry[ $group ] = array_values( array_map( 'strval', array_keys( $config ) ) );
                }
            }
            $attributes[ $attr_name ] = $entry;
        }

        // Custom CSS keys: the four every module has (CssStyleUtils.php handles
        // before, mainElement, after and freeForm generically) plus the module's own.
        $css_fields = array_values( array_map( 'strval', array_keys( $json['customCssFields'] ?? [] ) ) );
        foreach ( [ 'before', 'mainElement', 'after', 'freeForm' ] as $field ) {
            $css_fields[] = $field;
        }

        $outline = dirname( $file ) . '/conversion-outline.json';
        if ( file_exists( $outline ) ) {
            edc_divi_schema_merge_outline( json_decode( (string) file_get_contents( $outline ), true ) ?: [], $attributes, $css_fields );
        }
        foreach ( $attributes as &$entry ) {
            foreach ( $entry as $group => &$list ) {
                if ( is_array( $list ) ) {
                    $list = array_values( array_unique( $list ) );
                }
            }
            unset( $list );
        }
        unset( $entry );

        $children = $json['childrenName'] ?? [];
        $modules[ $json['name'] ] = [
            'childrenName' => is_array( $children ) ? array_values( $children ) : [],
            'cssFields'    => array_values( array_unique( $css_fields ) ),
            'attributes'   => $attributes,
        ];
    }
    ksort( $modules );

    return [ 'diviVersion' => $version, 'modules' => $modules ];
}

/**
 * The sub-keys an innerContent value may hold. Divi declares them as `subName`
 * on `group-items` / `into-multiple-groups` entries; a `group-item` with a
 * subName is a one-key object (testimonial portrait: src); a `group-item`
 * without one is a scalar. A component group with no subNames (the button link
 * group) cannot be enumerated here and is recorded as "*".
 *
 * @return string[]
 */
function edc_divi_schema_sub_names( array $config ): array {
    $names = [];
    $open  = false;

    $collect = static function ( array $item ) use ( &$names, &$open ): void {
        if ( isset( $item['subName'] ) && $item['subName'] !== '' ) {
            $names[] = (string) $item['subName'];
        } else {
            $open = true;
        }
    };

    switch ( $config['groupType'] ?? '' ) {
        case 'group-item':
            if ( isset( $config['item']['subName'] ) ) {
                $names[] = (string) $config['item']['subName'];
            }
            break;
        case 'group-items':
            foreach ( $config['items'] ?? [] as $item ) {
                $collect( is_array( $item ) ? $item : [] );
            }
            break;
        case 'into-multiple-groups':
            foreach ( $config['groups'] ?? [] as $group ) {
                $collect( is_array( $group['item'] ?? null ) ? $group['item'] : [] );
            }
            break;
        default:
            break;
    }

    $names = array_values( array_unique( $names ) );
    if ( $open ) {
        $names[] = '*';
    }
    return $names;
}

/**
 * Adds every attribute path a conversion outline names to the module's
 * attributes: "attr.group.sub…" (a `*` stands for the breakpoint/state
 * envelope) and, under innerContent, the sub-key after the `*`.
 *
 * @param array    $outline    Decoded conversion-outline.json.
 * @param array    $attributes The module's attributes, by reference.
 * @param string[] $css_fields The module's custom CSS keys, by reference.
 */
function edc_divi_schema_merge_outline( array $outline, array &$attributes, array &$css_fields ): void {
    $paths = [];
    $walk  = static function ( $node ) use ( &$walk, &$paths ): void {
        if ( is_string( $node ) ) {
            $paths[] = $node;
        } elseif ( is_array( $node ) ) {
            foreach ( $node as $child ) {
                $walk( $child );
            }
        }
    };
    $walk( $outline['module'] ?? [] );
    $walk( $outline['advanced'] ?? [] );

    foreach ( $paths as $path ) {
        $segments = explode( '.', $path );
        $star     = array_search( '*', $segments, true );
        $before   = $star === false ? $segments : array_slice( $segments, 0, $star );
        $after    = $star === false ? [] : array_slice( $segments, $star + 1 );

        if ( count( $before ) < 2 ) {
            continue; // A top-level attr with no group (row's backgroundHorizontalOffset1.*): nothing to declare.
        }
        [ $attr, $group ] = $before;
        if ( ! isset( $attributes[ $attr ] ) ) {
            $attributes[ $attr ] = [ 'elementType' => '' ];
        }
        if ( $group === 'innerContent' ) {
            $attributes[ $attr ]['innerContent'] = $attributes[ $attr ]['innerContent'] ?? [];
            if ( $after !== [] ) {
                $attributes[ $attr ]['innerContent'][] = $after[0];
            }
            continue;
        }
        if ( ! in_array( $group, [ 'advanced', 'decoration', 'meta' ], true ) || ! isset( $before[2] ) ) {
            continue;
        }
        $attributes[ $attr ][ $group ]   = $attributes[ $attr ][ $group ] ?? [];
        $attributes[ $attr ][ $group ][] = $before[2];
    }

    foreach ( $outline['css'] ?? [] as $target ) {
        if ( is_string( $target ) && preg_match( '/^css\.\*\.([A-Za-z0-9_]+)$/', $target, $m ) ) {
            $css_fields[] = $m[1];
        }
    }
}

/** @return array<string, array{unicode: string, solid?: string, line?: string}> */
function edc_fa_icons_build( string $icons_json ): array {
    $list  = json_decode( (string) file_get_contents( $icons_json ), true );
    $icons = [];
    foreach ( is_array( $list ) ? $list : [] as $entry ) {
        $styles = $entry['styles'] ?? [];
        if ( ! is_array( $styles ) || ! in_array( 'fa', $styles, true ) ) {
            continue;
        }
        $name   = strtolower( (string) ( $entry['name'] ?? '' ) );
        $style  = in_array( 'line', $styles, true ) ? 'line' : 'solid';
        $weight = (string) ( $entry['font_weight'] ?? '400' );
        if ( $name === '' ) {
            continue;
        }
        $icons[ $name ]['unicode'] = (string) $entry['unicode'];
        $icons[ $name ][ $style ]  = $weight;
    }
    ksort( $icons );
    return $icons;
}

if ( PHP_SAPI === 'cli' && realpath( $argv[0] ?? '' ) === __FILE__ ) {
    $root   = dirname( __DIR__ );
    $schema = edc_divi_schema_build(
        $root . '/references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components',
        $root . '/references/Divi/style.css'
    );
    if ( ! is_dir( $root . '/fixtures/divi-schema' ) ) {
        mkdir( $root . '/fixtures/divi-schema', 0755, true );
    }
    file_put_contents(
        $root . '/fixtures/divi-schema/modules.json',
        json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
    );

    $icons = edc_fa_icons_build( $root . '/references/Divi/includes/builder/feature/icon-manager/full_icons_list.json' );
    $php   = "<?php\n// Generated by scripts/divi-module-schema.php from Divi {$schema['diviVersion']}'s\n"
        . "// includes/builder/feature/icon-manager/full_icons_list.json. Do not edit.\n"
        . "// FontAwesome name => unicode entity and the font weight per style.\n"
        . 'return ' . var_export( $icons, true ) . ";\n";
    $data_dir = $root . '/plugin/jhmg-converter-for-elementor-to-divi/includes/data';
    if ( ! is_dir( $data_dir ) ) {
        mkdir( $data_dir, 0755, true );
    }
    file_put_contents( $data_dir . '/fa-icons.php', $php );

    printf( "Divi %s: %d modules, %d FontAwesome icons.\n", $schema['diviVersion'], count( $schema['modules'] ), count( $icons ) );
}
