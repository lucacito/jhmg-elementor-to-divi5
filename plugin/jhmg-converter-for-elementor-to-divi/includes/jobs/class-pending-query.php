<?php
/**
 * The Elementor posts that have not been converted yet.
 *
 * Written as SQL rather than WP_Query because the point of this screen is to
 * work on sites with tens of thousands of posts: one statement, no post objects
 * loaded, a keyset cursor (ID > last seen) instead of OFFSET for the runner,
 * and only indexed lookups — `(post_id, meta_key)` on postmeta and
 * `post_id` on the job items table.
 *
 * "Pending" means all of:
 *  - built with Elementor (_elementor_edit_mode = builder);
 *  - _elementor_data looks like a list of elements — it starts "[{". That
 *    drops empty strings, "[]", "null" and non-JSON text in SQL; data that
 *    starts well but does not decode is caught when the page is run, marked
 *    unconvertible, and leaves this list then;
 *  - not flagged converted (_edc_converted_at) or unconvertible;
 *  - has not failed in a job (those are in the Failed bucket, to be retried);
 *  - a live post: not trashed, not an auto-draft or revision.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PendingQuery implements PendingSource {

    const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

    /**
     * Every public post type with Elementor data, except Elementor's own
     * template library (headers, footers and popups have their own flow).
     *
     * @return string[]
     */
    public static function allowed_types(): array {
        $types = array_keys( get_post_types( [ 'public' => true ] ) );
        $types = array_diff( $types, [ 'elementor_library', 'attachment' ] );

        return array_values( apply_filters( 'edc_jobs_post_types', $types ) );
    }

    public function count( array $post_types ): int {
        global $wpdb;

        $types = $this->clean_types( $post_types );
        if ( ! $types ) {
            return 0;
        }

        [ $from, $args ] = $this->from_where( $types );

        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) ' . $from, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function page( array $post_types, int $after_id, int $limit ): array {
        global $wpdb;

        $types = $this->clean_types( $post_types );
        if ( ! $types ) {
            return [];
        }

        [ $from, $args ] = $this->from_where( $types );
        $sql             = 'SELECT p.ID, p.post_type, p.post_title ' . $from . ' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d';
        $args[]          = max( 0, $after_id );
        $args[]          = max( 1, $limit );

        return $this->rows( $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /** Offset pagination for the screen, where a page number is what the user picks. */
    public function list( array $post_types, int $limit, int $offset, string $search = '' ): array {
        global $wpdb;

        $types = $this->clean_types( $post_types );
        if ( ! $types ) {
            return [];
        }

        [ $from, $args ] = $this->from_where( $types );
        $sql             = 'SELECT p.ID, p.post_type, p.post_title, p.post_status, p.post_modified ' . $from;
        if ( $search !== '' ) {
            $sql   .= ' AND p.post_title LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        $sql   .= ' ORDER BY p.ID ASC LIMIT %d OFFSET %d';
        $args[] = max( 1, $limit );
        $args[] = max( 0, $offset );

        return $this->rows( $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /** Pending posts matching a title search, for pagination controls. */
    public function count_matching( array $post_types, string $search ): int {
        global $wpdb;

        $types = $this->clean_types( $post_types );
        if ( ! $types ) {
            return 0;
        }

        [ $from, $args ] = $this->from_where( $types );
        $sql             = 'SELECT COUNT(*) ' . $from;
        if ( $search !== '' ) {
            $sql   .= ' AND p.post_title LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /** Pending posts per post type, for the filter chips. @return array<string,int> */
    public function counts_by_type( array $post_types ): array {
        global $wpdb;

        $types = $this->clean_types( $post_types );
        if ( ! $types ) {
            return [];
        }

        [ $from, $args ] = $this->from_where( $types );
        $rows            = $wpdb->get_results( $wpdb->prepare( 'SELECT p.post_type, COUNT(*) AS n ' . $from . ' GROUP BY p.post_type', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $out = [];
        foreach ( $rows ?: [] as $row ) {
            $out[ $row['post_type'] ] = (int) $row['n'];
        }

        return $out;
    }

    /** Posts carrying the converted flag (by the converter, by backfill, or by hand). */
    public function converted_count(): int {
        global $wpdb;

        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            "SELECT COUNT(*) FROM {$wpdb->postmeta} cv
             INNER JOIN {$wpdb->posts} p ON p.ID = cv.post_id
             WHERE cv.meta_key = '" . ConversionFlag::AT . "' AND p.post_status <> 'trash'"
        );
    }

    /** @return array{0:string,1:array} FROM … WHERE (with placeholders) and its arguments. */
    private function from_where( array $types ): array {
        global $wpdb;

        $in_types    = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
        $in_statuses = implode( ', ', array_fill( 0, count( self::STATUSES ), '%s' ) );

        $sql = "FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_elementor_edit_mode' AND em.meta_value = 'builder'
            INNER JOIN {$wpdb->postmeta} ed ON ed.post_id = p.ID AND ed.meta_key = '_elementor_data' AND ed.meta_value LIKE '[{%'
            WHERE p.post_type IN ({$in_types}) AND p.post_status IN ({$in_statuses})
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} cv
                WHERE cv.post_id = p.ID AND cv.meta_key IN ('" . ConversionFlag::AT . "', '" . ConversionFlag::UNCONVERTIBLE . "')
            )
            AND NOT EXISTS (
                SELECT 1 FROM " . Schema::items_table() . " fi WHERE fi.post_id = p.ID AND fi.state = 'failed'
            )";

        return [ $sql, array_merge( $types, self::STATUSES ) ];
    }

    /** Only types this screen is allowed to touch, whatever the caller asked for. */
    private function clean_types( array $post_types ): array {
        return array_values( array_intersect( array_map( 'strval', $post_types ), self::allowed_types() ) );
    }

    private function rows( $rows ): array {
        return array_map( static function ( $r ) {
            $r['ID'] = (int) $r['ID'];

            return $r;
        }, $rows ?: [] );
    }
}
