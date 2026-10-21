<?php
/**
 * JobStore over $wpdb. Every query is prepared; table names come from
 * Schema (built from $wpdb->prefix), never from input.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WpdbJobStore implements JobStore {

    private const JOB_FIELDS = [
        'updated_at', 'finished_at', 'status', 'mode', 'scope', 'total', 'converted',
        'skipped', 'failed', 'unsupported', 'heartbeat', 'locked_until', 'created_by',
    ];

    private const ITEM_FIELDS = [ 'state', 'result_post_id', 'message', 'modules', 'warnings' ];

    private static function now(): string {
        return gmdate( 'Y-m-d H:i:s' );
    }

    public function create_job( array $job ): int {
        global $wpdb;

        $now = self::now();
        $wpdb->insert( Schema::jobs_table(), [
            'created_at' => $now,
            'updated_at' => $now,
            'status'     => 'pending',
            'mode'       => (string) ( $job['mode'] ?? 'copy' ),
            'scope'      => wp_json_encode( $job['scope'] ?? [] ),
            'created_by' => (int) ( $job['created_by'] ?? 0 ),
        ] );

        return (int) $wpdb->insert_id;
    }

    public function get_job( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::jobs_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return $row ? $this->hydrate_job( $row ) : null;
    }

    public function update_job( int $id, array $fields ): void {
        global $wpdb;

        $set = [];
        foreach ( $fields as $key => $value ) {
            if ( ! in_array( $key, self::JOB_FIELDS, true ) ) {
                continue;
            }
            $set[ $key ] = in_array( $key, [ 'scope', 'unsupported' ], true ) ? wp_json_encode( $value ) : $value;
        }
        $set['updated_at'] = self::now();

        $wpdb->update( Schema::jobs_table(), $set, [ 'id' => $id ] );
    }

    public function list_jobs( array $filters, int $limit, int $offset ): array {
        global $wpdb;

        [ $where, $args ] = $this->job_where( $filters );
        $sql              = 'SELECT j.* FROM ' . Schema::jobs_table() . " j {$where} ORDER BY j.id DESC LIMIT %d OFFSET %d";
        $args[]           = max( 1, $limit );
        $args[]           = max( 0, $offset );

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array_map( [ $this, 'hydrate_job' ], $rows ?: [] );
    }

    public function count_jobs( array $filters ): int {
        global $wpdb;

        [ $where, $args ] = $this->job_where( $filters );
        $sql              = 'SELECT COUNT(*) FROM ' . Schema::jobs_table() . " j {$where}";

        return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /** @return array{0:string,1:array} WHERE clause (with %s/%d placeholders) and its arguments. */
    private function job_where( array $f ): array {
        $clauses = [];
        $args    = [];

        if ( ! empty( $f['status'] ) ) {
            $clauses[] = 'j.status = %s';
            $args[]    = (string) $f['status'];
        }
        if ( ! empty( $f['post_type'] ) ) {
            $clauses[] = 'EXISTS (SELECT 1 FROM ' . Schema::items_table() . ' i WHERE i.job_id = j.id AND i.post_type = %s)';
            $args[]    = (string) $f['post_type'];
        }
        if ( ! empty( $f['date_from'] ) ) {
            $clauses[] = 'j.created_at >= %s';
            $args[]    = $f['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $f['date_to'] ) ) {
            $clauses[] = 'j.created_at <= %s';
            $args[]    = $f['date_to'] . ' 23:59:59';
        }
        if ( ! empty( $f['heartbeat_older_than'] ) ) {
            $clauses[] = '(j.heartbeat IS NULL OR j.heartbeat < %s)';
            $args[]    = (string) $f['heartbeat_older_than'];
        }
        if ( ! empty( $f['heartbeat_newer_than'] ) ) {
            $clauses[] = 'j.heartbeat >= %s';
            $args[]    = (string) $f['heartbeat_newer_than'];
        }

        return [ $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '', $args ];
    }

    public function add_items( int $job_id, array $items ): int {
        global $wpdb;

        $added = 0;
        foreach ( array_chunk( $items, 500 ) as $chunk ) {
            $values = [];
            $args   = [];
            foreach ( $chunk as $item ) {
                $values[] = '(%d, %d, %s, %s)';
                $args[]   = $job_id;
                $args[]   = (int) $item['post_id'];
                $args[]   = substr( (string) ( $item['post_type'] ?? '' ), 0, 40 );
                $args[]   = mb_substr( (string) ( $item['title'] ?? '' ), 0, 255 );
            }
            // INSERT IGNORE: the unique (job_id, post_id) key silently drops repeats.
            $sql    = 'INSERT IGNORE INTO ' . Schema::items_table() . ' (job_id, post_id, post_type, title) VALUES ' . implode( ', ', $values );
            $added += (int) $wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'UPDATE ' . Schema::jobs_table() . ' SET total = (SELECT COUNT(*) FROM ' . Schema::items_table() . ' WHERE job_id = %d), updated_at = %s WHERE id = %d',
            $job_id, self::now(), $job_id
        ) );

        return $added;
    }

    public function next_pending_items( int $job_id, int $limit ): array {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'SELECT * FROM ' . Schema::items_table() . " WHERE job_id = %d AND state = 'pending' ORDER BY id ASC LIMIT %d",
            $job_id, max( 1, $limit )
        ), ARRAY_A );

        return array_map( [ $this, 'hydrate_item' ], $rows ?: [] );
    }

    public function set_item_result( int $job_id, int $post_id, array $result ): void {
        global $wpdb;

        $set = [ 'updated_at' => self::now() ];
        foreach ( self::ITEM_FIELDS as $field ) {
            if ( array_key_exists( $field, $result ) ) {
                $set[ $field ] = $result[ $field ];
            }
        }

        $wpdb->update( Schema::items_table(), $set, [ 'job_id' => $job_id, 'post_id' => $post_id ] );
    }

    public function list_items( int $job_id, ?string $state, int $limit, int $offset ): array {
        global $wpdb;

        $sql  = 'SELECT * FROM ' . Schema::items_table() . ' WHERE job_id = %d';
        $args = [ $job_id ];
        if ( $state !== null ) {
            $sql   .= ' AND state = %s';
            $args[] = $state;
        }
        $sql   .= ' ORDER BY id ASC LIMIT %d OFFSET %d';
        $args[] = max( 1, $limit );
        $args[] = max( 0, $offset );

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array_map( [ $this, 'hydrate_item' ], $rows ?: [] );
    }

    public function count_items( int $job_id, ?string $state ): int {
        global $wpdb;

        $sql  = 'SELECT COUNT(*) FROM ' . Schema::items_table() . ' WHERE job_id = %d';
        $args = [ $job_id ];
        if ( $state !== null ) {
            $sql   .= ' AND state = %s';
            $args[] = $state;
        }

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function counts_by_state( int $job_id ): array {
        global $wpdb;

        $counts = array_fill_keys( self::STATES, 0 );
        $rows   = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'SELECT state, COUNT(*) AS n FROM ' . Schema::items_table() . ' WHERE job_id = %d GROUP BY state',
            $job_id
        ), ARRAY_A );

        foreach ( $rows ?: [] as $row ) {
            if ( isset( $counts[ $row['state'] ] ) ) {
                $counts[ $row['state'] ] = (int) $row['n'];
            }
        }

        return $counts;
    }

    public function sync_counts( int $job_id ): void {
        $c = $this->counts_by_state( $job_id );

        $this->update_job( $job_id, [ 'converted' => $c['converted'], 'skipped' => $c['skipped'], 'failed' => $c['failed'] ] );
    }

    public function reset_items( int $job_id, array $states, ?array $post_ids ): int {
        global $wpdb;

        $states = array_values( array_intersect( $states, self::STATES ) );
        if ( ! $states ) {
            return 0;
        }

        $sql  = 'UPDATE ' . Schema::items_table() . " SET state = 'pending', message = NULL, updated_at = %s WHERE job_id = %d AND state IN (" . implode( ', ', array_fill( 0, count( $states ), '%s' ) ) . ')';
        $args = array_merge( [ self::now(), $job_id ], $states );

        if ( $post_ids !== null ) {
            $post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
            if ( ! $post_ids ) {
                return 0;
            }
            $sql .= ' AND post_id IN (' . implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) ) . ')';
            $args = array_merge( $args, $post_ids );
        }

        return (int) $wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function resolve_posts( array $post_ids, array $from_states, array $result, ?int $only_result_post_id = null ): array {
        global $wpdb;

        $post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
        $states   = array_values( array_intersect( $from_states, self::STATES ) );
        if ( ! $post_ids || ! $states ) {
            return [];
        }

        $set  = [ 'updated_at = %s' ];
        $args = [ self::now() ];
        foreach ( self::ITEM_FIELDS as $field ) {
            if ( array_key_exists( $field, $result ) ) {
                $set[]  = "{$field} = " . ( is_int( $result[ $field ] ) ? '%d' : '%s' );
                $args[] = $result[ $field ];
            }
        }

        $in_posts  = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
        $in_states = implode( ', ', array_fill( 0, count( $states ), '%s' ) );
        $where     = " WHERE post_id IN ({$in_posts}) AND state IN ({$in_states})";
        $where_arg = array_merge( $post_ids, $states );
        if ( $only_result_post_id !== null ) {
            $where      .= ' AND result_post_id = %d';
            $where_arg[] = $only_result_post_id;
        }

        // Which jobs are about to change, read before the UPDATE removes the evidence.
        $job_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT job_id FROM ' . Schema::items_table() . $where, $where_arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( ! $job_ids ) {
            return [];
        }

        $wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::items_table() . ' SET ' . implode( ', ', $set ) . $where, array_merge( $args, $where_arg ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array_map( 'intval', $job_ids );
    }

    public function claim_lock( int $job_id, int $seconds ): bool {
        global $wpdb;

        $now   = self::now();
        $until = gmdate( 'Y-m-d H:i:s', time() + max( 1, $seconds ) );

        // One UPDATE both tests and takes the lock, so two tabs cannot both win.
        $taken = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'UPDATE ' . Schema::jobs_table() . ' SET locked_until = %s WHERE id = %d AND (locked_until IS NULL OR locked_until < %s)',
            $until, $job_id, $now
        ) );

        return (int) $taken === 1;
    }

    public function release_lock( int $job_id ): void {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::jobs_table() . ' SET locked_until = NULL WHERE id = %d', $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function count_failed_posts(): int {
        global $wpdb;

        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'SELECT COUNT(DISTINCT i.post_id) FROM ' . Schema::items_table() . " i
             LEFT JOIN {$wpdb->postmeta} cv ON cv.post_id = i.post_id AND cv.meta_key = '_edc_converted_at'
             WHERE i.state = 'failed' AND cv.meta_id IS NULL"
        );
    }

    private function hydrate_job( array $row ): array {
        $scope       = json_decode( (string) $row['scope'], true );
        $unsupported = json_decode( (string) ( $row['unsupported'] ?? '' ), true );

        return [
            'id'           => (int) $row['id'],
            'created_at'   => (string) $row['created_at'],
            'updated_at'   => (string) $row['updated_at'],
            'finished_at'  => $row['finished_at'],
            'status'       => (string) $row['status'],
            'mode'         => (string) $row['mode'],
            'scope'        => is_array( $scope ) ? $scope : [],
            'total'        => (int) $row['total'],
            'converted'    => (int) $row['converted'],
            'skipped'      => (int) $row['skipped'],
            'failed'       => (int) $row['failed'],
            'unsupported'  => is_array( $unsupported ) ? $unsupported : [],
            'heartbeat'    => $row['heartbeat'],
            'locked_until' => $row['locked_until'],
            'created_by'   => (int) $row['created_by'],
        ];
    }

    private function hydrate_item( array $row ): array {
        return [
            'job_id'         => (int) $row['job_id'],
            'post_id'        => (int) $row['post_id'],
            'post_type'      => (string) $row['post_type'],
            'title'          => (string) $row['title'],
            'state'          => (string) $row['state'],
            'result_post_id' => (int) $row['result_post_id'],
            'message'        => (string) ( $row['message'] ?? '' ),
            'modules'        => (int) $row['modules'],
            'warnings'       => (int) $row['warnings'],
        ];
    }
}
