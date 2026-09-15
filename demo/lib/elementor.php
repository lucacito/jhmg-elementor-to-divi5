<?php
/**
 * Builds Elementor documents for the Ferncourt demo site.
 *
 * Pure PHP with no WordPress calls, so the same page files load in the offline
 * harness (demo/tests/php) and inside WordPress (demo/lib/seed.php).
 */

namespace Ferncourt\Demo;

/**
 * Everything a page needs that only exists once the site is seeded: attachment
 * IDs and URLs, the contact form, category IDs, the menu, and the build time
 * that event dates count from.
 */
final class Context {

    /**
     * @param array{
     *   images: array<string, array{id: int, url: string, alt: string}>,
     *   form_id: int,
     *   categories: array<string, int>,
     *   menu: string,
     *   home: string,
     *   now: int
     * } $data
     */
    public function __construct( private array $data ) {}

    /** An Elementor media control value for a seeded file. The converter reads alt text only from here. */
    public function image( string $file ): array {
        $media = $this->media( $file );

        return [ 'url' => $media['url'], 'id' => $media['id'], 'alt' => $media['alt'], 'source' => 'library', 'size' => '' ];
    }

    public function mediaUrl( string $file ): string {
        return $this->media( $file )['url'];
    }

    public function url( string $path = '/' ): string {
        return rtrim( $this->data['home'], '/' ) . '/' . ltrim( $path, '/' );
    }

    public function formId(): int {
        return $this->data['form_id'];
    }

    public function categoryId( string $slug ): int {
        $id = $this->data['categories'][ $slug ] ?? null;
        if ( $id === null ) {
            throw new \InvalidArgumentException( "No seeded category {$slug}." );
        }
        return $id;
    }

    public function menu(): string {
        return $this->data['menu'];
    }

    /**
     * A date relative to the build, e.g. date( '+21 days 18:00' ), so events stay in the
     * future after a rebuild. Always UTC: strtotime() would use the host's timezone.
     */
    public function date( string $modifier, string $format = 'Y-m-d H:i' ): string {
        return ( new \DateTimeImmutable( '@' . $this->data['now'] ) )
            ->setTimezone( new \DateTimeZone( 'UTC' ) )
            ->modify( $modifier )
            ->format( $format );
    }

    private function media( string $file ): array {
        $media = $this->data['images'][ $file ] ?? null;
        if ( $media === null ) {
            throw new \InvalidArgumentException( "No seeded media named {$file}." );
        }
        return $media;
    }
}

function widget( string $type, array $settings ): array {
    return [ 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
}

function container( array $children, array $settings = [] ): array {
    return [ 'elType' => 'container', 'settings' => $settings, 'elements' => array_values( $children ) ];
}

/** A full-width page band: boxed content stacked in a column, generous vertical padding, optional global background color. */
function band( array $children, string $background = '', array $settings = [] ): array {
    $base = [
        'content_width'  => 'boxed',
        'flex_direction' => 'column',
        'flex_gap'       => gap( 32 ),
        'padding'        => box( 96, 24 ),
        'padding_mobile' => box( 56, 16 ),
    ];
    if ( $background !== '' ) {
        $base['background_background'] = 'classic';
        $base['__globals__']           = [ 'background_color' => color( $background ) ];
    }

    return container( $children, array_replace_recursive( $base, $settings ) );
}

/** Children side by side, stacking on mobile. */
function row( array $children, int $gap = 24, array $settings = [] ): array {
    return container( $children, array_replace( [
        'content_width'         => 'full',
        'flex_direction'        => 'row',
        'flex_direction_mobile' => 'column',
        'flex_wrap'             => 'wrap',
        'flex_gap'              => gap( $gap ),
    ], $settings ) );
}

/** A column inside a row, taking $width percent of it (100% on mobile). */
function col( array $children, int $width, array $settings = [] ): array {
    return container( $children, array_replace( [
        'content_width'  => 'full',
        'flex_direction' => 'column',
        'flex_gap'       => gap( 16 ),
        'width'          => slider( $width, '%' ),
        'width_mobile'   => slider( 100, '%' ),
    ], $settings ) );
}

function heading( string $text, string $tag = 'h2', string $color = 'primary', string $align = '' ): array {
    $settings = [
        'title'       => $text,
        'header_size' => $tag,
        '__globals__' => [ 'title_color' => color( $color ), 'typography_typography' => font( 'primary' ) ],
    ];
    if ( $align !== '' ) {
        $settings['align'] = $align;
    }

    return widget( 'heading', $settings );
}

function text( string $html, string $color = 'text' ): array {
    return widget( 'text-editor', [
        'editor'      => $html,
        '__globals__' => [ 'text_color' => color( $color ), 'typography_typography' => font( 'text' ) ],
    ] );
}

function button( string $label, string $url ): array {
    return widget( 'button', [
        'text'        => $label,
        'link'        => link( $url ),
        '__globals__' => [ 'background_color' => color( 'accent' ), 'typography_typography' => font( 'accent' ) ],
    ] );
}

function gap( int $px ): array {
    return [ 'column' => (string) $px, 'row' => (string) $px, 'isLinked' => true, 'unit' => 'px', 'size' => $px ];
}

function box( int $vertical, int $horizontal ): array {
    return [ 'unit' => 'px', 'top' => (string) $vertical, 'right' => (string) $horizontal, 'bottom' => (string) $vertical, 'left' => (string) $horizontal, 'isLinked' => false ];
}

function slider( int|float $size, string $unit = 'px' ): array {
    return [ 'unit' => $unit, 'size' => $size, 'sizes' => [] ];
}

function link( string $url, bool $external = false ): array {
    return [ 'url' => $url, 'is_external' => $external ? 'on' : '', 'nofollow' => '', 'custom_attributes' => '' ];
}

function icon( string $class, string $library = 'fa-solid' ): array {
    return [ 'value' => $class, 'library' => $library ];
}

function color( string $id ): string {
    return 'globals/colors?id=' . $id;
}

function font( string $id ): string {
    return 'globals/typography?id=' . $id;
}

/** One repeater row. Elementor gives every repeater row an `_id`. */
function item( string $id, array $fields ): array {
    return [ '_id' => $id ] + $fields;
}

/**
 * Assigns stable 7-character hex ids and isInner flags, as Elementor does when it saves.
 * Ids derive from the slug and position, so a rebuild produces identical documents.
 */
function finalize( string $slug, array $elements ): array {
    return assign_ids( $slug, $elements, false );
}

function assign_ids( string $path, array $elements, bool $inner ): array {
    $out = [];
    foreach ( array_values( $elements ) as $index => $element ) {
        $here          = $path . '/' . $index;
        $element['id'] = substr( md5( $here ), 0, 7 );
        if ( $element['elType'] === 'container' ) {
            $element['isInner']  = $inner;
            $element['elements'] = assign_ids( $here, $element['elements'], true );
        }
        $out[] = $element;
    }
    return $out;
}

/** @return array{title: string, slug: string, template: string, front_page: bool, elements: array, survive: string[], survive_exact: string[]} */
function load_document( string $file, Context $ctx ): array {
    $factory = require $file;
    $doc     = $factory( $ctx );

    foreach ( [ 'title', 'slug', 'elements' ] as $key ) {
        if ( ! isset( $doc[ $key ] ) ) {
            throw new \InvalidArgumentException( basename( $file ) . " does not return '{$key}'." );
        }
    }

    return [
        'title'         => $doc['title'],
        'slug'          => $doc['slug'],
        'template'      => $doc['template'] ?? '',
        'front_page'    => $doc['front_page'] ?? false,
        'elements'      => finalize( $doc['slug'], $doc['elements'] ),
        'survive'       => $doc['survive'] ?? [],
        'survive_exact' => $doc['survive_exact'] ?? [],
    ];
}

/** @return array<string, array{path: string, alt: string}> Every file the seed imports into the media library. */
function media_files( string $content_dir ): array {
    $shots = json_decode( (string) file_get_contents( $content_dir . '/images/shots.json' ), true );

    $files = [];
    foreach ( $shots as $shot ) {
        $files[ $shot['file'] ] = [ 'path' => $content_dir . '/images/' . $shot['file'], 'alt' => $shot['alt'] ];
    }
    $files['logo.png']        = [ 'path' => $content_dir . '/logo.png', 'alt' => 'Ferncourt Coworking' ];
    $files['tour-poster.jpg'] = [ 'path' => $content_dir . '/video/tour-poster.jpg', 'alt' => 'The Ferncourt lounge' ];
    $files['tour.webm']       = [ 'path' => $content_dir . '/video/tour.webm', 'alt' => '' ];

    return $files;
}
