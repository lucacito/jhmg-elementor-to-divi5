<?php
/**
 * Widget coverage sweep.
 *
 * Answers the two questions the pricing page and the readme both depend on:
 * how many Elementor widget types convert to a real Divi module, and how many
 * only leave a labelled placeholder — and, given an Elementor source tree, which
 * of that release's widgets are not handled at all.
 *
 * Usage:
 *   php scripts/widget-coverage.php
 *   php scripts/widget-coverage.php /path/to/elementor /path/to/elementor-pro
 *
 * Elementor Pro is a licensed download and is not in this repo. Drop an
 * extracted copy anywhere and pass its path as the second argument; without it
 * the Pro column reads UNKNOWN rather than guessing, which is the whole point.
 */

$root = dirname( __DIR__ );

require $root . '/tests/bootstrap.php';

/** Every widget slug the registry answers to, and what it produces. */
function registry_slugs( string $root ): array {
    $file = $root . '/plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php';
    $src  = (string) file_get_contents( $file );

    preg_match_all( "/registerWidget\(\s*'([a-z0-9_-]+)'/", $src, $m );
    $explicit = $m[1] ?? [];

    // The Tier-4 loop registers its slugs from an array literal, so they carry
    // no registerWidget( 'literal' ) of their own.
    $placeholder_only = [];
    // Anchored on the foreach, not on the first slug — starting at the slug
    // itself consumes its opening quote and silently loses it from the count.
    if ( preg_match( "/foreach \(\s*\[[^\]]*eael-nft-gallery.*?\]\s*as\s*\\\$slug/s", $src, $block ) ) {
        preg_match_all( "/'([a-z0-9-]+)'/", $block[0], $pm );
        $placeholder_only = $pm[1] ?? [];
    }

    $all = array_values( array_unique( array_merge( $explicit, $placeholder_only ) ) );

    // `e-*` are legacy test-fixture aliases Elementor has never emitted.
    // Counting them overstates coverage, which is how "140+" happened.
    $aliases = array_values( array_filter( $all, static fn( $s ) => str_starts_with( $s, 'e-' ) ) );
    $real    = array_values( array_diff( $all, $aliases ) );

    return [
        'real'             => $real,
        'aliases'          => $aliases,
        'placeholder_only' => array_values( array_intersect( $real, $placeholder_only ) ),
    ];
}

/** Widget slugs an Elementor tree declares, read from get_name(). */
function elementor_slugs( string $path ): array {
    if ( ! is_dir( $path ) ) {
        return [];
    }

    $slugs = [];
    $it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );

    foreach ( $it as $file ) {
        if ( $file->getExtension() !== 'php' ) {
            continue;
        }
        $src = (string) file_get_contents( $file->getPathname() );
        if ( strpos( $src, 'function get_name' ) === false ) {
            continue;
        }

        // Only widget classes. Every module, manager and component in Elementor
        // also declares get_name(), and counting those produced nonsense like
        // 'ajax' and 'admin-top-bar' sitting in a widget coverage table.
        $is_widget = (bool) preg_match(
            '/class\s+\w+\s+extends\s+[\\\\\w]*(Widget_Base|Widget_Heading|Widget_Image|Widget_Text_Editor|Base_Widget|Widget_Nested_Base|Widget_Nested_Tabs|Widget_Link_In_Bio_Base|Widget_Contact_Button_Base|Widget_Floating_Bars_Base)\b/',
            $src
        );

        if ( ! $is_widget ) {
            continue;
        }
        // get_name() { return 'slug'; } — the declaration Elementor registers by.
        if ( preg_match_all( "/function get_name\(\)\s*(?::\s*\w+\s*)?\{\s*return\s*'([a-z0-9_-]+)'/", $src, $m ) ) {
            foreach ( $m[1] as $slug ) {
                $slugs[] = $slug;
            }
        }
    }

    // Base classes, not user-placeable widgets.
    $internal = [ 'common', 'common-base', 'common-optimized' ];

    // Two more that are not really gaps:
    //   inner-section  — serialises as elType 'section' with isInner, which
    //                    SectionConverter already handles; the widget class
    //                    exists only for the editor panel.
    //   wp-widget-     — a prefix, not a slug: get_name() returns
    //                    'wp-widget-' . $id_base, one per registered WP widget.
    $not_a_gap = [ 'inner-section', 'wp-widget-' ];

    return array_values( array_diff( array_unique( $slugs ), $internal, $not_a_gap ) );
}

$registry = registry_slugs( $root );
$mapped   = count( $registry['real'] ) - count( $registry['placeholder_only'] );

echo "Converter registry\n";
echo str_repeat( '-', 60 ), "\n";
printf( "  %-44s %d\n", 'Widget types mapped to a Divi module', $mapped );
printf( "  %-44s %d\n", 'Widget types that emit a placeholder only', count( $registry['placeholder_only'] ) );
printf( "  %-44s %d\n", 'Total recognised', count( $registry['real'] ) );
printf( "  %-44s %d\n", 'Legacy e-* fixture aliases (not counted)', count( $registry['aliases'] ) );
echo "\n";

foreach ( [ 'free' => $argv[1] ?? '', 'pro' => $argv[2] ?? '' ] as $label => $path ) {
    $title = $label === 'free' ? 'Elementor (free)' : 'Elementor Pro';

    if ( $path === '' || ! is_dir( $path ) ) {
        echo "{$title}\n", str_repeat( '-', 60 ), "\n";
        echo "  UNKNOWN — no source tree given.\n";
        if ( $label === 'pro' ) {
            echo "  Elementor Pro is a licensed download. Extract a copy and pass its\n";
            echo "  path as the second argument to get this number.\n";
        }
        echo "\n";
        continue;
    }

    $slugs   = elementor_slugs( $path );
    $handled = array_values( array_intersect( $slugs, $registry['real'] ) );
    $missing = array_values( array_diff( $slugs, $registry['real'] ) );
    sort( $missing );

    echo "{$title}  ({$path})\n", str_repeat( '-', 60 ), "\n";
    printf( "  %-44s %d\n", 'Widget types declared', count( $slugs ) );
    printf( "  %-44s %d\n", 'Handled by the converter', count( $handled ) );
    printf( "  %-44s %d\n", 'NOT handled', count( $missing ) );

    if ( $missing ) {
        echo "\n  Not handled:\n";
        foreach ( $missing as $slug ) {
            echo "    - {$slug}\n";
        }
    }
    echo "\n";
}
