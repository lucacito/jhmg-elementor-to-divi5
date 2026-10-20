<?php
// tests/support/InMemoryJobStore.php
//
// A JobStore over arrays, for unit-testing the job logic without a database.
// Its SQL twin (WpdbJobStore) is proven against real MySQL in
// tests/integration/jobs-store.php; keep the two semantically identical.
use ElementorDivi5Converter\Jobs\JobStore;

final class InMemoryJobStore implements JobStore {

    public array $jobs  = [];
    /** @var array<int,array<int,array>> job_id => post_id => item */
    public array $items = [];
    private int $next   = 1;

    public function create_job( array $job ): int {
        $id   = $this->next++;
        $now  = gmdate( 'Y-m-d H:i:s' );
        $this->jobs[ $id ] = [
            'id' => $id, 'created_at' => $now, 'updated_at' => $now, 'finished_at' => null,
            'status' => 'pending', 'mode' => (string) ( $job['mode'] ?? 'copy' ),
            'scope' => $job['scope'] ?? [], 'total' => 0, 'converted' => 0, 'skipped' => 0, 'failed' => 0,
            'unsupported' => [], 'heartbeat' => null, 'locked_until' => null,
            'created_by' => (int) ( $job['created_by'] ?? 0 ),
        ];
        $this->items[ $id ] = [];

        return $id;
    }

    public function get_job( int $id ): ?array {
        return $this->jobs[ $id ] ?? null;
    }

    public function update_job( int $id, array $fields ): void {
        unset( $fields['id'] );
        $this->jobs[ $id ] = array_merge( $this->jobs[ $id ], $fields, [ 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ] );
    }

    public function list_jobs( array $filters, int $limit, int $offset ): array {
        $rows = array_values( array_filter( $this->jobs, fn( $j ) => $this->matches( $j, $filters ) ) );
        usort( $rows, fn( $a, $b ) => $b['id'] <=> $a['id'] );

        return array_slice( $rows, $offset, $limit );
    }

    public function count_jobs( array $filters ): int {
        return count( array_filter( $this->jobs, fn( $j ) => $this->matches( $j, $filters ) ) );
    }

    private function matches( array $j, array $f ): bool {
        if ( ! empty( $f['status'] ) && $j['status'] !== $f['status'] ) { return false; }
        if ( ! empty( $f['post_type'] ) && ! in_array( $f['post_type'], array_column( $this->items[ $j['id'] ], 'post_type' ), true ) ) { return false; }
        if ( ! empty( $f['date_from'] ) && $j['created_at'] < $f['date_from'] . ' 00:00:00' ) { return false; }
        if ( ! empty( $f['date_to'] ) && $j['created_at'] > $f['date_to'] . ' 23:59:59' ) { return false; }
        if ( ! empty( $f['heartbeat_older_than'] ) && ! ( $j['heartbeat'] === null || $j['heartbeat'] < $f['heartbeat_older_than'] ) ) { return false; }
        if ( ! empty( $f['heartbeat_newer_than'] ) && ! ( $j['heartbeat'] !== null && $j['heartbeat'] >= $f['heartbeat_newer_than'] ) ) { return false; }

        return true;
    }

    public function add_items( int $job_id, array $items ): int {
        $added = 0;
        foreach ( $items as $item ) {
            $pid = (int) $item['post_id'];
            if ( isset( $this->items[ $job_id ][ $pid ] ) ) { continue; }
            $this->items[ $job_id ][ $pid ] = [
                'job_id' => $job_id, 'post_id' => $pid, 'post_type' => (string) ( $item['post_type'] ?? '' ),
                'title' => (string) ( $item['title'] ?? '' ), 'state' => 'pending', 'result_post_id' => 0,
                'message' => '', 'modules' => 0, 'warnings' => 0,
            ];
            $added++;
        }
        $this->jobs[ $job_id ]['total'] = count( $this->items[ $job_id ] );

        return $added;
    }

    public function next_pending_items( int $job_id, int $limit ): array {
        return array_slice( array_values( array_filter( $this->items[ $job_id ], fn( $i ) => $i['state'] === 'pending' ) ), 0, $limit );
    }

    public function set_item_result( int $job_id, int $post_id, array $result ): void {
        foreach ( [ 'state', 'result_post_id', 'message', 'modules', 'warnings' ] as $f ) {
            if ( array_key_exists( $f, $result ) ) {
                $this->items[ $job_id ][ $post_id ][ $f ] = $result[ $f ];
            }
        }
    }

    public function list_items( int $job_id, ?string $state, int $limit, int $offset ): array {
        $rows = array_values( array_filter( $this->items[ $job_id ] ?? [], fn( $i ) => $state === null || $i['state'] === $state ) );

        return array_slice( $rows, $offset, $limit );
    }

    public function count_items( int $job_id, ?string $state ): int {
        return count( $this->list_items( $job_id, $state, PHP_INT_MAX, 0 ) );
    }

    public function counts_by_state( int $job_id ): array {
        $c = array_fill_keys( self::STATES, 0 );
        foreach ( $this->items[ $job_id ] ?? [] as $i ) { $c[ $i['state'] ]++; }

        return $c;
    }

    public function sync_counts( int $job_id ): void {
        $c = $this->counts_by_state( $job_id );
        $this->update_job( $job_id, [ 'converted' => $c['converted'], 'skipped' => $c['skipped'], 'failed' => $c['failed'] ] );
    }

    public function reset_items( int $job_id, array $states, ?array $post_ids ): int {
        $n = 0;
        foreach ( $this->items[ $job_id ] ?? [] as $pid => $i ) {
            if ( ! in_array( $i['state'], $states, true ) || ( $post_ids !== null && ! in_array( $pid, $post_ids, true ) ) ) { continue; }
            $this->items[ $job_id ][ $pid ]['state']   = 'pending';
            $this->items[ $job_id ][ $pid ]['message'] = '';
            $n++;
        }

        return $n;
    }

    public function resolve_posts( array $post_ids, array $from_states, array $result ): array {
        $affected = [];
        foreach ( $this->items as $job_id => $items ) {
            foreach ( $post_ids as $pid ) {
                if ( isset( $items[ $pid ] ) && in_array( $items[ $pid ]['state'], $from_states, true ) ) {
                    $this->set_item_result( $job_id, $pid, $result );
                    $affected[ $job_id ] = $job_id;
                }
            }
        }

        return array_values( $affected );
    }

    public function claim_lock( int $job_id, int $seconds ): bool {
        $until = $this->jobs[ $job_id ]['locked_until'];
        if ( $until !== null && $until >= gmdate( 'Y-m-d H:i:s' ) ) { return false; }
        $this->jobs[ $job_id ]['locked_until'] = gmdate( 'Y-m-d H:i:s', time() + $seconds );

        return true;
    }

    public function release_lock( int $job_id ): void {
        $this->jobs[ $job_id ]['locked_until'] = null;
    }

    public function count_failed_posts(): int {
        $failed = [];
        foreach ( $this->items as $items ) {
            foreach ( $items as $pid => $i ) {
                if ( $i['state'] === 'failed' && ! \ElementorDivi5Converter\Jobs\ConversionFlag::is_converted( $pid ) ) { $failed[ $pid ] = true; }
            }
        }

        return count( $failed );
    }
}
