<?php
/**
 * Finds the Elementor-built posts the direct-conversion picker lists.
 *
 * The query is wrapped in an injectable runner rather than calling WP_Query
 * inline: it keeps this class unit-testable without a WordPress database, and
 * keeps the admin screen free of query construction.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementorPageRepository {

    /** Elementor's own marker for a post built with its editor. */
    const EDIT_MODE_META = '_elementor_edit_mode';

    const PER_PAGE = 20;

    /** @var callable(array):array */
    private $query_runner;

    public function __construct( ?callable $query_runner = null ) {
        $this->query_runner = $query_runner ?? [ $this, 'run_wp_query' ];
    }

    /** @return array The WP_Query arguments this repository issues. */
    public function query_args( array $args = [] ): array {
        $per_page = (int) ( $args['per_page'] ?? self::PER_PAGE );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $search   = trim( (string) ( $args['search'] ?? '' ) );

        $query = [
            // elementor-hf: Header Footer Elementor's templates, converted as Theme Builder areas.
            'post_type'      => [ 'page', 'post', 'elementor_library', 'elementor-hf' ],
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'meta_key'       => self::EDIT_MODE_META,
            'meta_value'     => 'builder',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => $per_page > 0 ? $per_page : self::PER_PAGE,
            'paged'          => $paged,
        ];

        // Explicit offset, when given, is what actually drives WP_Query's
        // SQL OFFSET — it overrides the offset WP_Query would otherwise
        // derive from posts_per_page * (paged - 1). A caller that needs to
        // probe for one extra row beyond the display count (the picker's
        // next-page check) can raise posts_per_page for that probe without
        // also inflating the offset every subsequent page is computed from.
        if ( isset( $args['offset'] ) ) {
            $query['offset'] = max( 0, (int) $args['offset'] );
        }

        if ( $search !== '' ) {
            $query['s'] = $search;
        }

        return $query;
    }

    /**
     * @return array[] Rows shaped
     *   ['id','title','post_type','status','modified','converted'].
     */
    public function find( array $args = [] ): array {
        $posts = ( $this->query_runner )( $this->query_args( $args ) );

        $rows = [];
        foreach ( $posts as $post ) {
            $id = (int) ( $post->ID ?? 0 );
            if ( $id <= 0 ) {
                continue;
            }

            $title = trim( (string) ( $post->post_title ?? '' ) );

            $rows[] = [
                'id'        => $id,
                'title'     => $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ),
                'post_type' => (string) ( $post->post_type ?? '' ),
                'status'    => (string) ( $post->post_status ?? '' ),
                'modified'  => (string) ( $post->post_modified ?? '' ),
                'converted' => $this->already_converted( $id ),
            ];
        }

        return $rows;
    }

    public function has_any(): bool {
        return ! empty( ( $this->query_runner )( $this->query_args( [ 'per_page' => 1 ] ) ) );
    }

    /**
     * True when some post already records this one as its conversion source.
     * Purely informational — it never blocks converting the page again.
     */
    private function already_converted( int $source_post_id ): bool {
        if ( ! function_exists( 'get_posts' ) ) {
            return $this->already_converted_in_memory( $source_post_id );
        }

        $found = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_key'       => '_edc_source_post_id',
            'meta_value'     => $source_post_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        return ! empty( $found );
    }

    /** Test-environment fallback: the harness has post meta but no get_posts(). */
    private function already_converted_in_memory( int $source_post_id ): bool {
        foreach ( $GLOBALS['__test_postmeta'] ?? [] as $meta ) {
            foreach ( $meta['_edc_source_post_id'] ?? [] as $value ) {
                if ( (int) $value === $source_post_id ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array Post objects. */
    private function run_wp_query( array $args ): array {
        $query = new \WP_Query( $args );

        return $query->posts ?? [];
    }
}
