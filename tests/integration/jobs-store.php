<?php
/**
 * Integration check for the job storage layer against a REAL database.
 * PHPUnit's harness has no $wpdb, so SQL (dbDelta, filters, indexes, the
 * atomic lock) can only be proven here.
 *
 *   docker exec <wp container> wp eval-file /var/www/html/wp-content/plugins/jhmg-converter-for-elementor-to-divi/../../../tests-integration/jobs-store.php --allow-root
 * (see tests/integration/run.sh)
 */
use ElementorDivi5Converter\Jobs\Schema;
use ElementorDivi5Converter\Jobs\WpdbJobStore;

global $wpdb;
$fails = 0;
$check = function ( string $label, bool $ok, string $extra = '' ) use ( &$fails ) {
    if ( ! $ok ) { $fails++; }
    echo ( $ok ? 'PASS' : 'FAIL' ) . "  $label" . ( $extra !== '' ? "  [$extra]" : '' ) . "\n";
};

// --- schema ---------------------------------------------------------------
Schema::drop();
delete_option( Schema::VERSION_OPTION );
Schema::install();
$check( 'jobs table exists',  $wpdb->get_var( "SHOW TABLES LIKE '" . Schema::jobs_table() . "'" ) === Schema::jobs_table() );
$check( 'items table exists', $wpdb->get_var( "SHOW TABLES LIKE '" . Schema::items_table() . "'" ) === Schema::items_table() );
$check( 'version option written', get_option( Schema::VERSION_OPTION ) === Schema::DB_VERSION );
$idx = array_column( $wpdb->get_results( 'SHOW INDEX FROM ' . Schema::items_table(), ARRAY_A ), 'Key_name' );
$check( 'items indexed on (job_id,post_id) unique, (job_id,state) and post_id',
    in_array( 'job_post', $idx, true ) && in_array( 'job_state', $idx, true ) && in_array( 'post_id', $idx, true ), implode( ',', array_unique( $idx ) ) );
Schema::install(); // idempotent
$check( 're-running install is harmless', get_option( Schema::VERSION_OPTION ) === Schema::DB_VERSION );

// --- store ----------------------------------------------------------------
$s  = new WpdbJobStore();
$id = $s->create_job( [ 'scope' => [ 'type' => 'ids', 'post_types' => [ 'page' ] ], 'mode' => 'copy', 'created_by' => 1 ] );
$check( 'create_job returns an id', $id > 0 );
$job = $s->get_job( $id );
$check( 'new job is pending, zero totals, scope round-trips', $job['status'] === 'pending' && $job['total'] === 0 && $job['scope']['post_types'] === [ 'page' ] );

$added = $s->add_items( $id, [
    [ 'post_id' => 11, 'post_type' => 'page', 'title' => 'Home' ],
    [ 'post_id' => 12, 'post_type' => 'post', 'title' => 'Hello "quoted" \\ world' ],
    [ 'post_id' => 13, 'post_type' => 'page', 'title' => 'About' ],
] );
$check( 'add_items inserts 3', $added === 3 );
$check( 'adding the same post twice is ignored (unique key)', $s->add_items( $id, [ [ 'post_id' => 11, 'post_type' => 'page', 'title' => 'Home' ] ] ) === 0 );
$check( 'job total follows the items', $s->get_job( $id )['total'] === 3 );
$check( 'special characters survive the title round trip', $s->list_items( $id, null, 10, 0 )[1]['title'] === 'Hello "quoted" \\ world' );

$next = $s->next_pending_items( $id, 2 );
$check( 'next_pending_items is ordered and limited', count( $next ) === 2 && $next[0]['post_id'] === 11 && $next[1]['post_id'] === 12 );

$s->set_item_result( $id, 11, [ 'state' => 'converted', 'result_post_id' => 900, 'modules' => 7, 'warnings' => 1 ] );
$s->set_item_result( $id, 12, [ 'state' => 'failed', 'message' => 'boom' ] );
$s->set_item_result( $id, 13, [ 'state' => 'skipped', 'message' => 'Already converted' ] );
$check( 'counts_by_state', $s->counts_by_state( $id ) === [ 'pending' => 0, 'running' => 0, 'converted' => 1, 'skipped' => 1, 'failed' => 1 ], json_encode( $s->counts_by_state( $id ) ) );
$s->sync_counts( $id );
$j = $s->get_job( $id );
$check( 'sync_counts writes converted/skipped/failed onto the job', $j['converted'] === 1 && $j['skipped'] === 1 && $j['failed'] === 1 );
$check( 'list_items filters by state', count( $s->list_items( $id, 'failed', 10, 0 ) ) === 1 && $s->list_items( $id, 'failed', 10, 0 )[0]['message'] === 'boom' );
$check( 'list_items paginates', count( $s->list_items( $id, null, 2, 2 ) ) === 1 );
$check( 'count_items', $s->count_items( $id, null ) === 3 && $s->count_items( $id, 'converted' ) === 1 );

$check( 'reset_items puts failed items back to pending', $s->reset_items( $id, [ 'failed' ], null ) === 1 && $s->counts_by_state( $id )['pending'] === 1 );
$check( 'reset_items can target specific posts', $s->reset_items( $id, [ 'skipped' ], [ 13 ] ) === 1 );

// --- resolve_posts (manual mark) -----------------------------------------
$s->set_item_result( $id, 13, [ 'state' => 'failed', 'message' => 'x' ] );
$affected = $s->resolve_posts( [ 13, 999 ], [ 'pending', 'failed' ], [ 'state' => 'converted', 'message' => 'Marked as converted manually' ] );
$check( 'resolve_posts reports the jobs it changed', $affected === [ $id ], json_encode( $affected ) );
$check( 'resolve_posts moved the item and set its message', $s->list_items( $id, 'converted', 10, 0 )[1]['message'] === 'Marked as converted manually' );
$check( 'resolve_posts leaves other states alone', $s->resolve_posts( [ 13 ], [ 'failed' ], [ 'state' => 'skipped' ] ) === [] );

// --- lock -----------------------------------------------------------------
$check( 'first claim wins',  $s->claim_lock( $id, 60 ) === true );
$check( 'second claim is refused while locked', $s->claim_lock( $id, 60 ) === false );
$s->release_lock( $id );
$check( 'claim works again after release', $s->claim_lock( $id, 60 ) === true );
$s->release_lock( $id );

// --- listing + filters ----------------------------------------------------
$id2 = $s->create_job( [ 'scope' => [ 'type' => 'remaining', 'post_types' => [ 'post' ] ], 'mode' => 'replace', 'created_by' => 1 ] );
$s->add_items( $id2, [ [ 'post_id' => 21, 'post_type' => 'post', 'title' => 'P' ] ] );
$s->update_job( $id2, [ 'status' => 'done' ] );
$all = $s->list_jobs( [], 10, 0 );
$check( 'newest job first', $all[0]['id'] === $id2 && $all[1]['id'] === $id );
$check( 'filter by status', count( $s->list_jobs( [ 'status' => 'done' ], 10, 0 ) ) === 1 && $s->count_jobs( [ 'status' => 'done' ] ) === 1 );
$check( 'filter by post type looks at the job items', array_column( $s->list_jobs( [ 'post_type' => 'post' ], 10, 0 ), 'id' ) === [ $id2, $id ] );
$check( 'filter by post type that no job has', $s->count_jobs( [ 'post_type' => 'product' ] ) === 0 );
$check( 'filter by date (future start excludes all)', $s->count_jobs( [ 'date_from' => gmdate( 'Y-m-d', time() + 86400 ) ] ) === 0 );
$check( 'filter by date (today includes)', $s->count_jobs( [ 'date_from' => gmdate( 'Y-m-d' ), 'date_to' => gmdate( 'Y-m-d' ) ] ) === 2 );
$s->update_job( $id, [ 'status' => 'running', 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ] );
$check( 'stale running job is found by heartbeat filter', $s->count_jobs( [ 'status' => 'running', 'heartbeat_older_than' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ] ) === 1 );
$check( 'fresh-heartbeat filter excludes it', $s->count_jobs( [ 'status' => 'running', 'heartbeat_newer_than' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ] ) === 0 );

// --- uninstall ------------------------------------------------------------
Schema::drop();
$check( 'drop removes both tables and the version option', $wpdb->get_var( "SHOW TABLES LIKE '" . Schema::jobs_table() . "'" ) === null && get_option( Schema::VERSION_OPTION ) === false );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILED\n";
exit( $fails === 0 ? 0 : 1 );
