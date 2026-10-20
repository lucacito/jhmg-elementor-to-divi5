<?php
/**
 * Where jobs and their per-page results live.
 *
 * An interface so the job logic above it (creating, running, retrying) is
 * unit-tested against an in-memory store, and the SQL below it is proven
 * separately against a real database (tests/integration/jobs-store.php).
 *
 * Job rows are arrays: id, created_at, updated_at, finished_at, status
 * (pending|running|done|done_errors), mode (copy|replace), scope (array),
 * total, converted, skipped, failed, unsupported (string[]), heartbeat,
 * locked_until, created_by. Item rows: job_id, post_id, post_type, title,
 * state (pending|running|converted|skipped|failed), result_post_id, message,
 * modules, warnings. Datetimes are UTC 'Y-m-d H:i:s'.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface JobStore {

    const STATES = [ 'pending', 'running', 'converted', 'skipped', 'failed' ];

    /** @param array $job scope (array), mode, created_by. @return int The new job's id. */
    public function create_job( array $job ): int;

    public function get_job( int $id ): ?array;

    /** @param array $fields Any job column except id. */
    public function update_job( int $id, array $fields ): void;

    /**
     * Newest first.
     *
     * @param array $filters status, post_type (a job with an item of that type),
     *   date_from / date_to ('Y-m-d', inclusive), heartbeat_older_than /
     *   heartbeat_newer_than (datetime).
     * @return array[]
     */
    public function list_jobs( array $filters, int $limit, int $offset ): array;

    public function count_jobs( array $filters ): int;

    /**
     * Ignores a post already in the job. Updates the job's total.
     *
     * @param array[] $items post_id, post_type, title.
     * @return int Rows actually added.
     */
    public function add_items( int $job_id, array $items ): int;

    /** Oldest first. @return array[] */
    public function next_pending_items( int $job_id, int $limit ): array;

    /** @param array $result state, and optionally result_post_id, message, modules, warnings. */
    public function set_item_result( int $job_id, int $post_id, array $result ): void;

    /** @return array[] Ordered by id. */
    public function list_items( int $job_id, ?string $state, int $limit, int $offset ): array;

    public function count_items( int $job_id, ?string $state ): int;

    /** @return array<string,int> Every state, zero-filled. */
    public function counts_by_state( int $job_id ): array;

    /** Writes converted / skipped / failed onto the job row from its items. */
    public function sync_counts( int $job_id ): void;

    /**
     * Puts items back to pending.
     *
     * @param string[]   $states   Only items currently in one of these states.
     * @param int[]|null $post_ids Restrict to these posts; null = all.
     * @return int Items reset.
     */
    public function reset_items( int $job_id, array $states, ?array $post_ids ): int;

    /** Atomically takes the job's run lock. False if someone else holds it. */
    public function claim_lock( int $job_id, int $seconds ): bool;

    public function release_lock( int $job_id ): void;

    /** Distinct posts whose latest failure is still unresolved, for the summary bar. */
    public function count_failed_posts(): int;
}
