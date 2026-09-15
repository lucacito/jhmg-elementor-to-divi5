<?php
/**
 * Add-on setting-name sweep.
 *
 * For every widget the converter registry maps, lists the setting names its
 * converter reads that the add-on providing that widget never defines. Each
 * one is content a conversion silently drops: the widget converts without
 * error and without an unsupported flag, just emptier.
 *
 * A read like `$settings['new'] ?? $settings['old']` counts once and passes
 * when any name in the chain is defined, so fallbacks kept for older add-on
 * versions are not reported. Write fallbacks as `??` chains for this reason.
 *
 * Usage:
 *   php scripts/addon-setting-names.php [addon.zip ...]
 *
 * With no arguments it sweeps the Essential Addons and Header Footer Elementor
 * zips in references/. Pass other add-on zips (ElementsKit Lite, Premium
 * Addons, …) to sweep those instead. Exits 1 when anything is found.
 */

/** Keys a converter reads that are not add-on controls: nested value keys and Elementor internals. */
const ASN_GENERIC = [
    'url', 'id', 'value', 'library', 'size', 'unit', 'sizes', 'is_external', 'nofollow',
    'alt', 'src', 'source', 'custom_attributes', '__globals__', '__dynamic__',
];

/** Names the free add-on source cannot confirm, with the reason. */
const ASN_UNVERIFIABLE = [
    'eael-embedpress'     => [
        'eael_embedpress_url' => "EAEL's element is only an 'install EmbedPress' prompt; embeds live in EmbedPress's own widgets.",
    ],
    'eael-content-ticker' => [
        'eael_ticker_custom_contents' => 'Custom ticker items are an EAEL Pro control.',
        'eael_ct_title'               => 'Per-item name of the EAEL Pro custom ticker; Pro source unavailable.',
        'eael_ct_link'                => 'Per-item name of the EAEL Pro custom ticker; Pro source unavailable.',
    ],
];

/**
 * Names read on purpose for older exports, outside a `??` chain because the
 * old shape needs different handling from the current one.
 */
const ASN_LEGACY = [
    'eael-tooltip'    => [
        'eael_tooltip_trigger_text' => 'Pre-6.x trigger text; its presence selects the legacy branch.',
    ],
    'eael-data-table' => [
        'eael_data_table_body_rows' => 'Pre-6.x nested body rows.',
        'eael_dt_body_col_rows'     => 'Pre-6.x nested body rows.',
        'eael_dt_body_row_value'    => 'Pre-6.x nested body rows.',
        'eael_dt_body_col'          => 'Pre-6.x nested body rows.',
        'eael_dt_body_col_span'     => 'Pre-6.x nested body rows.',
        'eael_dt_body_row_span'     => 'Pre-6.x nested body rows.',
    ],
];

/** @return array<string,string> widget slug => converter source file */
function asn_registry( string $root ): array {
    $converter = $root . '/plugin/jhmg-converter-for-elementor-to-divi/includes/converter/';
    $registry  = (string) file_get_contents( $converter . 'registry/class-converter-registry.php' );

    $class_files = [];
    foreach ( glob( $converter . 'handlers/*.php' ) ?: [] as $file ) {
        if ( preg_match( '/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', (string) file_get_contents( $file ), $m ) ) {
            $class_files[ $m[1] ] = $file;
        }
    }

    preg_match_all( "/registerWidget\(\s*'([a-z0-9_-]+)'\s*,\s*'([^']+)'/", $registry, $matches, PREG_SET_ORDER );

    $map = [];
    foreach ( $matches as [ , $slug, $class ] ) {
        $short = substr( (string) strrchr( '\\' . $class, '\\' ), 1 );
        if ( isset( $class_files[ $short ] ) ) {
            $map[ $slug ] = $class_files[ $short ];
        }
    }

    return $map;
}

/** @return string[][] Every settings read in the source, as the chain of names it tries in order. */
function asn_reads( string $php ): array {
    $access = '\$(?:settings|s|item|tab|row|col|cell|entry|slide|feature|member|link|link_raw|hosted|media|logo|custom)\[\s*\'[a-z0-9_]+\'\s*\]';

    preg_match_all( '/' . $access . '(?:\s*\?\?\s*' . $access . ')*/', $php, $chains );

    $reads = [];
    foreach ( $chains[0] as $chain ) {
        preg_match_all( '/\[\s*\'([a-z0-9_]+)\'\s*\]/', $chain, $keys );
        $reads[] = $keys[1];
    }

    return $reads;
}

/** @return array{widgets: array<string,true>, defined: array<string,true>} */
function asn_addon_index( string $zip_path ): array {
    $zip = new ZipArchive();
    if ( $zip->open( $zip_path ) !== true ) {
        throw new RuntimeException( "Cannot open add-on zip: {$zip_path}" );
    }

    $widgets = [];
    $defined = [];

    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        if ( ! str_ends_with( (string) $zip->getNameIndex( $i ), '.php' ) ) {
            continue;
        }
        $src = (string) $zip->getFromIndex( $i );

        preg_match_all( '/add_(?:responsive_|group_)?control\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $controls );
        preg_match_all( '/[\'"]name[\'"]\s*=>\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $repeater_fields );
        foreach ( array_merge( $controls[1], $repeater_fields[1] ) as $name ) {
            $defined[ $name ] = true;
        }

        if ( preg_match_all( '/function get_name\(\)\s*(?::\s*string\s*)?\{\s*return\s*[\'"]([a-z0-9_-]+)[\'"]/', $src, $slugs ) ) {
            foreach ( $slugs[1] as $slug ) {
                $widgets[ $slug ] = true;
            }
        }
    }

    $zip->close();

    return [ 'widgets' => $widgets, 'defined' => $defined ];
}

/** @return array<string,string[]> widget slug => reads (written "a ?? b") the add-on never defines */
function addon_setting_name_gaps( string $root, array $zip_paths ): array {
    $registry = asn_registry( $root );
    $gaps     = [];

    foreach ( $zip_paths as $zip_path ) {
        $addon = asn_addon_index( $zip_path );

        foreach ( $registry as $slug => $file ) {
            if ( ! isset( $addon['widgets'][ $slug ] ) ) {
                continue;
            }
            $unverifiable = ( ASN_UNVERIFIABLE[ $slug ] ?? [] ) + ( ASN_LEGACY[ $slug ] ?? [] );

            foreach ( asn_reads( (string) file_get_contents( $file ) ) as $chain ) {
                $satisfied = false;
                foreach ( $chain as $name ) {
                    // Responsive variants (title_tablet) are generated from the base control.
                    $base = (string) preg_replace( '/_(?:tablet_extra|mobile_extra|tablet|mobile|laptop|widescreen)$/', '', $name );
                    if ( in_array( $name, ASN_GENERIC, true ) || str_starts_with( $name, '_' )
                        || isset( $addon['defined'][ $name ] ) || isset( $addon['defined'][ $base ] )
                        || isset( $unverifiable[ $name ] ) ) {
                        $satisfied = true;
                        break;
                    }
                }
                if ( ! $satisfied ) {
                    $gaps[ $slug ][] = implode( ' ?? ', $chain );
                }
            }
        }
    }

    foreach ( $gaps as $slug => $reads ) {
        $gaps[ $slug ] = array_values( array_unique( $reads ) );
    }
    ksort( $gaps );

    return $gaps;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
    $root = dirname( __DIR__ );
    $zips = array_slice( $argv, 1 ) ?: [
        $root . '/references/essential-addons-for-elementor-lite.6.6.7.zip',
        $root . '/references/header-footer-elementor.2.8.8.zip',
    ];

    foreach ( $zips as $zip ) {
        echo 'Swept ', basename( $zip ), "\n";
    }

    $gaps = addon_setting_name_gaps( $root, $zips );
    if ( ! $gaps ) {
        echo "No converter reads a setting name its add-on does not define.\n";
        exit( 0 );
    }

    foreach ( $gaps as $slug => $reads ) {
        echo "\n{$slug}\n";
        foreach ( $reads as $read ) {
            echo "  - {$read}\n";
        }
    }
    exit( 1 );
}
