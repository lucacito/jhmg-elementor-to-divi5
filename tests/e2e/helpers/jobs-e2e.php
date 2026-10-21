<?php
/**
 * Test helper for tests/e2e/conversion-jobs.spec.ts, run with `wp eval-file`.
 *   seed <n> [prefix]   create n Elementor pages
 *   reset               delete everything the specs created, clear jobs + history
 *   state               print JSON about jobs/items/posts
 *   stale <job_id>      pretend the browser died: heartbeat 15 minutes old
 *   fail <job_id> <post_id>   force one item into the failed state
 *   post <id>           print JSON about one post (status, meta, content head)
 */
global $wpdb;
$cmd = $args[0] ?? '';
$out = static function ( $v ) { echo "JSON:" . wp_json_encode( $v ) . "\n"; };
$jobs  = $wpdb->prefix . 'edc_jobs';
$items = $wpdb->prefix . 'edc_job_items';

if ( $cmd === 'seed' ) {
    $n      = (int) ( $args[1] ?? 1 );
    $prefix = $args[2] ?? 'E2E Job Page';
    $json   = json_encode( json_decode( file_get_contents( '/var/www/html/fixtures/elementor/simple-container.json' ), true ) );
    $ids    = [];
    for ( $i = 1; $i <= $n; $i++ ) {
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => sprintf( '%s %03d', $prefix, $i ), 'post_content' => 'elementor-html-' . $i ] );
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
        update_post_meta( $id, '_edc_e2e', '1' );
        $ids[] = $id;
    }
    $out( $ids );
} elseif ( $cmd === 'reset' ) {
    $ids = $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e'" );
    // Copies made from those pages carry the source id.
    foreach ( $ids as $id ) {
        foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_source_post_id' AND meta_value = %d", $id ) ) as $copy ) {
            wp_delete_post( (int) $copy, true );
        }
    }
    foreach ( $ids as $id ) { wp_delete_post( (int) $id, true ); }
    $wpdb->query( "DELETE FROM $items" );
    $wpdb->query( "DELETE FROM $jobs" );
    delete_option( 'edc_import_history' );
    $out( [ 'deleted' => count( $ids ) ] );
} elseif ( $cmd === 'state' ) {
    $out( [
        'jobs'  => $wpdb->get_results( "SELECT id, status, mode, total, converted, skipped, failed FROM $jobs ORDER BY id", ARRAY_A ),
        'items' => $wpdb->get_results( "SELECT job_id, state, COUNT(*) n FROM $items GROUP BY job_id, state ORDER BY job_id", ARRAY_A ),
        'e2e_pages' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e'" ),
        'copies'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_edc_source_post_id' AND meta_value IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e')" ),
        'flagged'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} f WHERE f.meta_key = '_edc_converted_at' AND f.post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e')" ),
        'replaced'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} r WHERE r.meta_key = '_edc_import_source' AND r.meta_value = 'replaced' AND r.post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e')" ),
        'still_elementor' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} r WHERE r.meta_key = '_elementor_edit_mode' AND r.post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_edc_e2e')" ),
    ] );
} elseif ( $cmd === 'stale' ) {
    $wpdb->update( $jobs, [ 'status' => 'running', 'heartbeat' => gmdate( 'Y-m-d H:i:s', time() - 900 ), 'locked_until' => null ], [ 'id' => (int) $args[1] ] );
    $out( [ 'ok' => true ] );
} elseif ( $cmd === 'fail' ) {
    $wpdb->update( $items, [ 'state' => 'failed', 'message' => 'Forced failure for the e2e test' ], [ 'job_id' => (int) $args[1], 'post_id' => (int) $args[2] ] );
    $wpdb->query( $wpdb->prepare( "UPDATE $jobs SET failed = (SELECT COUNT(*) FROM $items WHERE job_id = %d AND state = 'failed') WHERE id = %d", (int) $args[1], (int) $args[1] ) );
    delete_post_meta( (int) $args[2], '_edc_converted_at' );
    $out( [ 'ok' => true ] );
} elseif ( $cmd === 'post' ) {
    $p = get_post( (int) $args[1] );
    $out( $p ? [ 'status' => $p->post_status, 'divi' => get_post_meta( $p->ID, '_et_pb_use_divi_5', true ), 'edit_mode' => get_post_meta( $p->ID, '_elementor_edit_mode', true ),
        'flag' => get_post_meta( $p->ID, '_edc_converted_at', true ), 'content_head' => substr( $p->post_content, 0, 40 ) ] : null );
}
