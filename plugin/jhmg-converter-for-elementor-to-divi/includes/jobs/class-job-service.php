<?php
/**
 * Conversion Jobs: create them, run them a time-boxed chunk at a time, retry
 * what failed, and let the user mark a page done by hand.
 *
 * Conversion itself is untouched — each page goes through the same
 * ConversionPreflight + ConversionCommitter the picker uses. This class only
 * decides which pages, in which order, and writes down what happened, so a
 * site of thousands of pages can be converted over many sessions without the
 * user having to remember where they stopped.
 */

namespace ElementorDivi5Converter\Jobs;

use ElementorDivi5Converter\Admin\ReviewPrompt;
use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JobService {

    /** Above this many pages the user must choose a mode and confirm it. */
    const BULK_THRESHOLD = 50;

    const MODES = [ 'copy', 'replace' ];

    /** Seconds of conversion work per request, well inside common 30s host limits. */
    const CHUNK_BUDGET = 8.0;

    /** A hard cap per request even when pages convert very quickly. */
    const MAX_ITEMS_PER_CHUNK = 25;

    /** Seconds without a heartbeat before a "running" job is called interrupted. */
    const STALE_AFTER = 120;

    /** A crashed chunk's lock frees itself after this long. */
    const LOCK_SECONDS = 60;

    const SNAPSHOT_BATCH = 500;

    private JobStore $store;
    private PendingSource $pending;
    /** @var callable(int,array):array */
    private $convert;
    /** @var callable():float */
    private $clock;

    public function __construct( JobStore $store, PendingSource $pending, ?callable $convert = null, ?callable $clock = null ) {
        $this->store   = $store;
        $this->pending = $pending;
        $this->convert = $convert ?? [ $this, 'convert_one' ];
        $this->clock   = $clock ?? static fn(): float => microtime( true );
    }

    // ------------------------------------------------------------------
    // Creating
    // ------------------------------------------------------------------

    /**
     * @param array $request Either 'post_ids' (int[]) or 'remaining' => true
     *   with 'post_types' (string[]); plus 'mode' (copy|replace), 'confirmed'
     *   (bool) and 'created_by' (int).
     * @return array{ok:true,job_id:int}|array{ok:false,code:string,message:string,count?:int,limit?:int}
     */
    public function create( array $request ): array {
        $mode = $request['mode'] ?? null;
        if ( $mode !== null && ! in_array( $mode, self::MODES, true ) ) {
            return $this->refuse( 'choose_mode', __( 'Choose whether to create copies or replace the original pages.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $remaining = ! empty( $request['remaining'] );
        $types     = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $request['post_types'] ?? [] ) ) ) ) );
        $rows      = [];

        if ( $remaining ) {
            $count = $types ? $this->pending->count( $types ) : 0;
        } else {
            $rows  = $this->rows_for_ids( (array) ( $request['post_ids'] ?? [] ) );
            $count = count( $rows );
        }

        if ( $count === 0 ) {
            return $this->refuse( 'empty', __( 'There is nothing to convert.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $limit = ConversionPreflight::limit();
        if ( $count > $limit ) {
            return $this->refuse( 'pro_required', __( 'Converting more than one page at a time is a Pro feature.', 'jhmg-converter-for-elementor-to-divi' ), [ 'count' => $count, 'limit' => $limit ] );
        }

        // Bulk jobs never fall back to a default: replacing or creating
        // hundreds of pages has to be something the user said out loud.
        if ( $count > self::BULK_THRESHOLD ) {
            if ( $mode === null ) {
                return $this->refuse( 'choose_mode', __( 'This job is large. Choose whether to create copies or replace the original pages.', 'jhmg-converter-for-elementor-to-divi' ), [ 'count' => $count ] );
            }
            if ( empty( $request['confirmed'] ) ) {
                return $this->refuse( 'confirm_required', __( 'Tick the confirmation box to start this job.', 'jhmg-converter-for-elementor-to-divi' ), [ 'count' => $count ] );
            }
        }

        $job_id = $this->store->create_job( [
            'mode'       => $mode ?? 'copy',
            'created_by' => (int) ( $request['created_by'] ?? 0 ),
            'scope'      => $remaining
                ? [ 'type' => 'remaining', 'post_types' => $types ]
                : [ 'type' => 'ids', 'post_ids' => array_column( $rows, 'post_id' ), 'post_types' => array_values( array_unique( array_column( $rows, 'post_type' ) ) ) ],
        ] );

        if ( $remaining ) {
            $this->snapshot_remaining( $job_id, $types );
        } else {
            $this->store->add_items( $job_id, $rows );
        }

        return [ 'ok' => true, 'job_id' => $job_id ];
    }

    private function refuse( string $code, string $message, array $extra = [] ): array {
        return array_merge( [ 'ok' => false, 'code' => $code, 'message' => $message ], $extra );
    }

    /** @return array[] post_id, post_type, title — for posts that exist. */
    private function rows_for_ids( array $ids ): array {
        $rows = [];
        foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
            $post = get_post( $id );
            if ( ! $post ) {
                continue;
            }
            $title  = trim( (string) ( $post->post_title ?? '' ) );
            $rows[] = [
                'post_id'   => $id,
                'post_type' => (string) ( $post->post_type ?? 'page' ),
                'title'     => $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ),
            ];
        }

        return $rows;
    }

    /** Freezes the pending list at this moment so the job has a fixed size to report progress against. */
    private function snapshot_remaining( int $job_id, array $types ): void {
        $after = 0;
        do {
            $page = $this->pending->page( $types, $after, self::SNAPSHOT_BATCH );
            if ( ! $page ) {
                break;
            }
            $this->store->add_items( $job_id, array_map( static function ( $row ) {
                $title = trim( (string) $row['post_title'] );

                return [
                    'post_id'   => (int) $row['ID'],
                    'post_type' => (string) $row['post_type'],
                    'title'     => $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ),
                ];
            }, $page ) );
            $after = (int) end( $page )['ID'];
        } while ( count( $page ) === self::SNAPSHOT_BATCH );
    }

    // ------------------------------------------------------------------
    // Running
    // ------------------------------------------------------------------

    /**
     * Converts as many pending pages as fit in the time budget, then returns.
     * Called repeatedly by the browser; every call is independently safe to
     * lose, and calling it on a finished job is a no-op.
     *
     * @return array Progress summary, plus 'busy' when another request holds the job.
     */
    public function run_chunk( int $job_id, float $budget = self::CHUNK_BUDGET ): array {
        $job = $this->store->get_job( $job_id );
        if ( ! $job ) {
            return [ 'ok' => false, 'error' => 'not_found' ];
        }

        if ( ! $this->store->claim_lock( $job_id, self::LOCK_SECONDS ) ) {
            return array_merge( $this->summary( $job_id ), [ 'busy' => true, 'processed' => 0 ] );
        }

        $processed = 0;

        try {
            $this->fail_orphaned_items( $job_id );

            $this->store->update_job( $job_id, [ 'status' => 'running', 'finished_at' => null, 'heartbeat' => gmdate( 'Y-m-d H:i:s' ) ] );

            $started     = ( $this->clock )();
            $options     = [ 'skip_converted' => true, 'replace_original' => $job['mode'] === 'replace', 'post_status' => 'draft' ];
            $unsupported = $job['unsupported'];

            while ( $processed < self::MAX_ITEMS_PER_CHUNK && ( ( $this->clock )() - $started ) < $budget ) {
                $next = $this->store->next_pending_items( $job_id, 1 );
                if ( ! $next ) {
                    break;
                }

                $post_id = $next[0]['post_id'];
                // Marked before converting: if this page takes the server down,
                // the next chunk finds it still "running" and fails it instead
                // of retrying the same page forever.
                $this->store->set_item_result( $job_id, $post_id, [ 'state' => 'running' ] );

                $result = $this->convert_safely( $post_id, $options );
                $this->store->set_item_result( $job_id, $post_id, $this->item_result( $post_id, $result ) );
                $unsupported = array_values( array_unique( array_merge( $unsupported, ImportHistory::widget_types( [ $result ] ) ) ) );
                $processed++;
            }

            $this->store->sync_counts( $job_id );
            $this->store->update_job( $job_id, [ 'unsupported' => $unsupported, 'heartbeat' => gmdate( 'Y-m-d H:i:s' ) ] );

            if ( $this->store->counts_by_state( $job_id )['pending'] === 0 ) {
                $this->finalize( $job_id );
            }
        } finally {
            $this->store->release_lock( $job_id );
        }

        return array_merge( $this->summary( $job_id ), [ 'processed' => $processed ] );
    }

    private function convert_safely( int $post_id, array $options ): array {
        try {
            return ( $this->convert )( $post_id, $options );
        } catch ( \Throwable $e ) {
            return [ 'success' => false, 'error' => $e->getMessage(), 'report' => [], 'unsupported' => [] ];
        }
    }

    /** The real conversion: identical to what the picker runs, one page at a time. */
    public function convert_one( int $post_id, array $options ): array {
        $plan    = ( new ConversionPreflight() )->runUnlimited( new InstalledPostSource( [ $post_id ] ) );
        $results = ( new ConversionCommitter() )->commit( $plan, $options );

        return $results[0] ?? [ 'success' => false, 'error' => 'No result', 'report' => [], 'unsupported' => [] ];
    }

    /** Maps a committer result onto the stored item columns. */
    private function item_result( int $post_id, array $r ): array {
        $error = (string) ( $r['error'] ?? '' );

        if ( ! empty( $r['success'] ) ) {
            return [
                'state'          => 'converted',
                'result_post_id' => (int) ( $r['post_id'] ?? 0 ),
                'message'        => '',
                'modules'        => (int) array_sum( $r['report']['converted'] ?? [] ),
                'warnings'       => count( $r['report']['warnings'] ?? [] ),
            ];
        }

        if ( ! empty( $r['skipped'] ) ) {
            return [ 'state' => 'skipped', 'message' => $error ];
        }

        if ( $error === InstalledPostSource::no_content_message() ) {
            // Nothing to convert is not a failure, but it must also stop
            // counting as "pending", or the number would never reach zero.
            ConversionFlag::mark_unconvertible( $post_id, $error );

            return [ 'state' => 'skipped', 'message' => $error ];
        }

        return [ 'state' => 'failed', 'message' => $error !== '' ? $error : __( 'The conversion failed.', 'jhmg-converter-for-elementor-to-divi' ) ];
    }

    /** Items a crashed request left "running" become failures the user can retry. */
    private function fail_orphaned_items( int $job_id ): void {
        foreach ( $this->store->list_items( $job_id, 'running', 100, 0 ) as $item ) {
            $this->store->set_item_result( $job_id, $item['post_id'], [
                'state'   => 'failed',
                'message' => __( 'The server stopped while converting this page (a timeout or memory limit). It was not retried automatically — use Retry to try it again.', 'jhmg-converter-for-elementor-to-divi' ),
            ] );
        }
    }

    private function finalize( int $job_id ): void {
        $this->settle_status( $job_id );

        $job       = $this->store->get_job( $job_id );
        $history   = new ImportHistory();
        $run_id    = 'job-' . $job_id;
        $first_run = $history->find( $run_id ) === null;
        $results   = $this->history_results( $job );

        if ( $results ) {
            $history->record( $run_id, $results );
            if ( $first_run ) {
                // Counted once per job: a retried job finishing again must not double the total.
                ( new ReviewPrompt() )->record_run( $results );
            }
        }
    }

    /** Sets done / done_errors from the item states, once nothing is left to do. */
    private function settle_status( int $job_id ): void {
        $counts = $this->store->counts_by_state( $job_id );
        if ( $counts['pending'] > 0 || $counts['running'] > 0 ) {
            return;
        }

        $job = $this->store->get_job( $job_id );
        $this->store->update_job( $job_id, [
            'status'      => $counts['failed'] > 0 ? 'done_errors' : 'done',
            'finished_at' => $job['finished_at'] ?? gmdate( 'Y-m-d H:i:s' ),
        ] );
    }

    /** ImportHistory-shaped results, so the existing Undo and coverage screens cover a job. */
    private function history_results( array $job ): array {
        $results  = [];
        $replaced = $job['mode'] === 'replace';
        $offset   = 0;

        do {
            $page = $this->store->list_items( $job['id'], 'converted', 500, $offset );
            foreach ( $page as $item ) {
                if ( $item['result_post_id'] > 0 ) {
                    $results[] = [ 'success' => true, 'post_id' => $item['result_post_id'], 'replaced' => $replaced, 'unsupported' => [] ];
                }
            }
            $offset += 500;
        } while ( count( $page ) === 500 );

        if ( $results && $job['unsupported'] ) {
            $results[0]['unsupported'] = array_map( static fn( $t ) => [ 'widgetType' => $t ], $job['unsupported'] );
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Status, retry, manual marks
    // ------------------------------------------------------------------

    /** A job that says "running" but has gone quiet is "interrupted": resumable, not live. */
    public function effective_status( array $job ): string {
        // A finished job is "with errors" exactly when something in it has failed,
        // whatever the stored status last said: the counts are the truth.
        if ( in_array( $job['status'], [ 'done', 'done_errors' ], true ) ) {
            return $job['failed'] > 0 ? 'done_errors' : 'done';
        }

        if ( $job['status'] !== 'running' ) {
            return $job['status'];
        }

        $beat = $job['heartbeat'] ? strtotime( $job['heartbeat'] . ' UTC' ) : 0;

        return ( time() - $beat ) > self::STALE_AFTER ? 'interrupted' : 'running';
    }

    public function summary( int $job_id ): array {
        $job = $this->store->get_job( $job_id );
        if ( ! $job ) {
            return [ 'ok' => false, 'error' => 'not_found' ];
        }

        $counts = $this->store->counts_by_state( $job_id );

        return [
            'ok'        => true,
            'job_id'    => $job_id,
            'status'    => $this->effective_status( $job ),
            'total'     => $job['total'],
            'converted' => $counts['converted'],
            'skipped'   => $counts['skipped'],
            'failed'    => $counts['failed'],
            'pending'   => $counts['pending'] + $counts['running'],
            'done'      => $counts['pending'] === 0 && $counts['running'] === 0,
        ];
    }

    /**
     * Puts failed items (or, with $states, others) back in the queue.
     *
     * @param int[]|null $post_ids Restrict to these pages; null = every matching item.
     * @param string[]   $states
     * @return int Items re-queued.
     */
    public function retry( int $job_id, ?array $post_ids, array $states = [ 'failed' ] ): int {
        if ( in_array( 'skipped', $states, true ) ) {
            // A page skipped for having no content may have been fixed since.
            foreach ( $this->store->list_items( $job_id, 'skipped', PHP_INT_MAX, 0 ) as $item ) {
                if ( $post_ids === null || in_array( $item['post_id'], $post_ids, true ) ) {
                    ConversionFlag::clear_unconvertible( $item['post_id'] );
                }
            }
        }

        $n = $this->store->reset_items( $job_id, $states, $post_ids );
        if ( $n > 0 ) {
            $this->store->update_job( $job_id, [ 'status' => 'pending', 'finished_at' => null ] );
            $this->store->sync_counts( $job_id );
        }

        return $n;
    }

    /**
     * The user fixed these pages by hand. They leave the pending list, and any
     * job still waiting on them stops waiting.
     *
     * @param int[] $post_ids
     * @return int Pages marked.
     */
    public function mark_converted( array $post_ids ): int {
        $marked = [];
        foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $id ) {
            if ( get_post( $id ) ) {
                ConversionFlag::mark_manual( $id );
                $marked[] = $id;
            }
        }

        if ( $marked ) {
            $jobs = $this->store->resolve_posts( $marked, [ 'pending', 'failed', 'skipped' ], [
                'state'          => 'converted',
                'result_post_id' => 0,
                'message'        => __( 'Marked as converted manually.', 'jhmg-converter-for-elementor-to-divi' ),
            ] );
            foreach ( $jobs as $job_id ) {
                $this->store->sync_counts( $job_id );
                $this->settle_status( $job_id );
            }
        }

        return count( $marked );
    }

    /**
     * Takes back a manual mark. A page the converter really converted keeps its
     * flag. A job that had counted the page as converted-by-hand now counts it
     * as skipped, so its totals match the page being pending again.
     */
    public function unmark_converted( array $post_ids ): int {
        $cleared = [];
        foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $id ) {
            if ( ConversionFlag::is_manual( $id ) ) {
                ConversionFlag::clear( $id );
                $cleared[] = $id;
            }
        }

        if ( $cleared ) {
            // result_post_id 0 = "marked by hand"; an item the converter built (> 0) is left alone.
            $jobs = $this->store->resolve_posts( $cleared, [ 'converted' ], [
                'state'   => 'skipped',
                'message' => __( 'The manual mark was removed — this page has not been converted.', 'jhmg-converter-for-elementor-to-divi' ),
            ], 0 );
            foreach ( $jobs as $job_id ) {
                $this->store->sync_counts( $job_id );
            }
        }

        return count( $cleared );
    }
}
