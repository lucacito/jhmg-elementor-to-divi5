<?php
/**
 * Integration check for PendingQuery against a REAL database, including the
 * 10k+ posts requirement. Seeds ~12,000 pages with raw SQL, measures, cleans up.
 */
use ElementorDivi5Converter\Jobs\Schema;
use ElementorDivi5Converter\Jobs\PendingQuery;
use ElementorDivi5Converter\Jobs\WpdbJobStore;
use ElementorDivi5Converter\Jobs\ConversionFlag;

global $wpdb;
$fails = 0;
$check = function ( string $label, bool $ok, string $extra = '' ) use ( &$fails ) {
    if ( ! $ok ) { $fails++; }
    echo ( $ok ? 'PASS' : 'FAIL' ) . "  $label" . ( $extra !== '' ? "  [$extra]" : '' ) . "\n";
};
$cleanup = function () use ( $wpdb ) {
    $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE 'edcperf-%'" );
    foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
        $in = implode( ',', array_map( 'intval', $chunk ) );
        $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" );
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($in)" );
    }
};
$cleanup();
Schema::drop();
Schema::install();

// Baseline: the dev site already has Elementor pages of its own.
$q          = new PendingQuery();
$baseline   = $q->count( [ 'page', 'post' ] );

$valid = '[{"id":"a1","elType":"section","elements":[]}]';
$mk = function ( string $suffix, string $type, string $status, ?string $mode, ?string $data, string $title = '' ) use ( $wpdb ) {
    $now = gmdate( 'Y-m-d H:i:s' );
    $wpdb->insert( $wpdb->posts, [
        'post_title' => $title ?: "Perf $suffix", 'post_name' => "edcperf-$suffix", 'post_type' => $type, 'post_status' => $status,
        'post_date' => $now, 'post_date_gmt' => $now, 'post_modified' => $now, 'post_modified_gmt' => $now,
        'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '',
    ] );
    $id = (int) $wpdb->insert_id;
    if ( $mode !== null ) { add_post_meta( $id, '_elementor_edit_mode', $mode ); }
    if ( $data !== null ) { $wpdb->insert( $wpdb->postmeta, [ 'post_id' => $id, 'meta_key' => '_elementor_data', 'meta_value' => $data ] ); }
    return $id;
};

// --- correctness cases -------------------------------------------------------
$ok_page   = $mk( 'ok-page', 'page', 'publish', 'builder', $valid, 'Home' );
$ok_post   = $mk( 'ok-post', 'post', 'draft',   'builder', $valid, 'News' );
$empty     = $mk( 'empty',   'page', 'publish', 'builder', '' );
$emptylist = $mk( 'emptylist', 'page', 'publish', 'builder', '[]' );
$nulldata  = $mk( 'null',    'page', 'publish', 'builder', 'null' );
$garbage   = $mk( 'garbage', 'page', 'publish', 'builder', 'not json at all' );
$nodata    = $mk( 'nodata',  'page', 'publish', 'builder', null );
$notbuilt  = $mk( 'notbuilt', 'page', 'publish', null, $valid );
$wrongmode = $mk( 'wrongmode', 'page', 'publish', 'other', $valid );
$trashed   = $mk( 'trashed', 'page', 'trash', 'builder', $valid );
$autodraft = $mk( 'autodraft', 'page', 'auto-draft', 'builder', $valid );
$library   = $mk( 'library', 'elementor_library', 'publish', 'builder', $valid );
$flagged   = $mk( 'flagged', 'page', 'publish', 'builder', $valid );
$unconv    = $mk( 'unconv', 'page', 'publish', 'builder', $valid );
$failedpg  = $mk( 'failed', 'page', 'publish', 'builder', $valid );
$custom    = $mk( 'custom', 'product', 'publish', 'builder', $valid );
ConversionFlag::mark( $flagged );
ConversionFlag::mark_unconvertible( $unconv, 'x' );
$store = new WpdbJobStore();
$jid   = $store->create_job( [ 'scope' => [], 'mode' => 'copy' ] );
$store->add_items( $jid, [ [ 'post_id' => $failedpg, 'post_type' => 'page', 'title' => 'f' ] ] );
$store->set_item_result( $jid, $failedpg, [ 'state' => 'failed', 'message' => 'boom' ] );

$rows = $q->page( [ 'page', 'post' ], 0, 1000000 );
$ids  = array_map( 'intval', array_column( $rows, 'ID' ) );
$only_ours = array_values( array_intersect( $ids, [ $ok_page, $ok_post, $empty, $emptylist, $nulldata, $garbage, $nodata, $notbuilt, $wrongmode, $trashed, $autodraft, $library, $flagged, $unconv, $failedpg, $custom ] ) );
$check( 'only valid, unconverted builder pages/posts are pending', $only_ours === [ $ok_page, $ok_post ], json_encode( $only_ours ) );
$check( 'count() agrees with page()', $q->count( [ 'page', 'post' ] ) === count( $ids ) );
$check( 'empty / [] / null / garbage / missing _elementor_data are excluded', ! array_intersect( $ids, [ $empty, $emptylist, $nulldata, $garbage, $nodata ] ) );
$check( 'trash, auto-draft and elementor_library are excluded', ! array_intersect( $ids, [ $trashed, $autodraft, $library ] ) );
$check( 'converted, unconvertible and failed posts are excluded', ! array_intersect( $ids, [ $flagged, $unconv, $failedpg ] ) );
$check( 'post type filter', array_column( $q->page( [ 'post' ], 0, 1000000 ), 'ID' ) === array_column( array_filter( $rows, fn( $r ) => $r['post_type'] === 'post' ), 'ID' ) );
$check( 'a type outside the allowed set is ignored', $q->count( [ 'elementor_library' ] ) === 0 );
$check( 'rows carry title and type', ( $q->page( [ 'page' ], $ok_page - 1, 1 )[0]['post_title'] ?? '' ) === 'Home' );
$check( 'page() is keyset ordered after the given id', array_column( $q->page( [ 'page', 'post' ], $ok_page, 1000 ), 'ID' )[0] > $ok_page );
$check( 'search narrows by title', array_map( 'intval', array_column( $q->list( [ 'page', 'post' ], 50, 0, 'News' ), 'ID' ) ) === [ $ok_post ] );

// converting a pending page removes it from the count immediately
$before = $q->count( [ 'page', 'post' ] );
ConversionFlag::mark( $ok_page );
$check( 'flagging a post drops the pending count by one', $q->count( [ 'page', 'post' ] ) === $before - 1 );
ConversionFlag::clear( $ok_page );

$check( 'converted_count counts flagged posts', $q->converted_count() >= 1 );

// --- scale: ~12,000 pages ------------------------------------------------------
$N = 12000;
$now = gmdate( 'Y-m-d H:i:s' );
$t0 = microtime( true );
for ( $i = 0; $i < $N; $i += 500 ) {
    $vals = [];
    for ( $j = $i; $j < min( $N, $i + 500 ); $j++ ) {
        $vals[] = $wpdb->prepare( "(%s, %s, 'page', 'publish', %s, %s, %s, %s, '', '', '', '', '')", "Bulk $j", "edcperf-bulk-$j", $now, $now, $now, $now );
    }
    $wpdb->query( "INSERT INTO {$wpdb->posts} (post_title, post_name, post_type, post_status, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered) VALUES " . implode( ',', $vals ) );
}
$bulk_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE 'edcperf-bulk-%'" );
foreach ( array_chunk( $bulk_ids, 1000 ) as $chunk ) {
    $m = [];
    foreach ( $chunk as $id ) {
        $m[] = $wpdb->prepare( "(%d, '_elementor_edit_mode', 'builder')", $id );
        $m[] = $wpdb->prepare( "(%d, '_elementor_data', %s)", $id, str_repeat( $valid, 1 ) );
    }
    $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $m ) );
}
// 3,000 of them already converted, as a site mid-migration would be
foreach ( array_chunk( array_slice( $bulk_ids, 0, 3000 ), 1000 ) as $chunk ) {
    $m = [];
    foreach ( $chunk as $id ) { $m[] = $wpdb->prepare( "(%d, '_edc_converted_at', %s)", $id, $now ); }
    $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $m ) );
}
echo sprintf( "      (seeded %d pages in %.1fs)\n", $N, microtime( true ) - $t0 );

unset( $m, $vals, $chunk ); gc_collect_cycles();
$mem_before = memory_get_usage();
$t = microtime( true ); $cnt = $q->count( [ 'page', 'post' ] );            $d_count = microtime( true ) - $t;
$t = microtime( true ); $pg  = $q->page( [ 'page', 'post' ], 0, 500 );     $d_page  = microtime( true ) - $t;
$t = microtime( true ); $deep = $q->page( [ 'page', 'post' ], (int) $bulk_ids[ 11000 ], 500 ); $d_deep = microtime( true ) - $t;
$t = microtime( true ); $lst = $q->list( [ 'page', 'post' ], 50, 8000, '' ); $d_list = microtime( true ) - $t;
$t = microtime( true ); $cv  = $q->converted_count();                       $d_conv = microtime( true ) - $t;
$check( sprintf( 'pending count over 12k pages is exact (%d) and fast (%.3fs)', $cnt, $d_count ), $cnt === $baseline + 2 + 9000, "expected " . ( $baseline + 9002 ) . " got $cnt" );
$check( sprintf( 'count < 1.0s (%.3fs)', $d_count ), $d_count < 1.0 );
$check( sprintf( 'first keyset page < 0.5s (%.3fs)', $d_page ), $d_page < 0.5 && count( $pg ) === 500 );
$check( sprintf( 'deep keyset page < 0.5s (%.3fs)', $d_deep ), $d_deep < 0.5 && count( $deep ) > 0 );
$check( sprintf( 'offset page 8000 deep < 1.0s (%.3fs)', $d_list ), $d_list < 1.0 && count( $lst ) === 50 );
$check( sprintf( 'converted_count < 0.5s (%.3fs)', $d_conv ), $d_conv < 0.5 && $cv >= 3000 );
// Memory the queries themselves held on to (the seed loop above is excluded).
$mem = ( memory_get_usage() - $mem_before ) / 1048576;
$check( sprintf( 'count + 500-row pages + list held %.1f MB — nothing loads all posts', $mem ), $mem < 8 );

$cleanup();
Schema::drop();
echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILED\n";
exit( $fails === 0 ? 0 : 1 );
