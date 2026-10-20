<?php
/**
 * Back-compat: sites that converted pages before Conversion Jobs existed have
 * no _edc_converted_at flags, only copies carrying _edc_source_post_id (and,
 * since 3.2.0, pages with _edc_import_source = replaced). Backfill must turn
 * those into flags so the pending list is right from day one.
 */
use ElementorDivi5Converter\Jobs\Schema;
use ElementorDivi5Converter\Jobs\Backfill;
use ElementorDivi5Converter\Jobs\PendingQuery;
use ElementorDivi5Converter\Jobs\ConversionFlag;

global $wpdb;
$fails = 0;
$check = function ( string $label, bool $ok, string $extra = '' ) use ( &$fails ) {
    if ( ! $ok ) { $fails++; }
    echo ( $ok ? 'PASS' : 'FAIL' ) . "  $label" . ( $extra !== '' ? "  [$extra]" : '' ) . "\n";
};
$cleanup = function () use ( $wpdb ) {
    $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE 'edcbf-%'" );
    if ( $ids ) {
        $in = implode( ',', array_map( 'intval', $ids ) );
        $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" );
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($in)" );
    }
    delete_option( Backfill::DONE_OPTION );
};
$cleanup();
Schema::drop();
Schema::install();

$valid = '[{"id":"a1","elType":"section","elements":[]}]';
$mk = function ( string $slug, string $status = 'publish', bool $elementor = true, string $date = '2025-03-01 10:00:00' ) use ( $wpdb, $valid ) {
    $wpdb->insert( $wpdb->posts, [
        'post_title' => $slug, 'post_name' => "edcbf-$slug", 'post_type' => 'page', 'post_status' => $status,
        'post_date' => $date, 'post_date_gmt' => $date, 'post_modified' => $date, 'post_modified_gmt' => $date,
        'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '',
    ] );
    $id = (int) $wpdb->insert_id;
    if ( $elementor ) {
        add_post_meta( $id, '_elementor_edit_mode', 'builder' );
        add_post_meta( $id, '_elementor_data', $valid );
    }
    return $id;
};

$src_a    = $mk( 'src-a' );                                  // converted once, copy alive
$src_b    = $mk( 'src-b' );                                  // converted twice (two copies)
$src_c    = $mk( 'src-c' );                                  // its only copy was trashed (Undo)
$src_d    = $mk( 'src-d' );                                  // never converted
$src_e    = $mk( 'src-e' );                                  // already flagged by the new code
$replaced = $mk( 'replaced', 'publish', false );             // converted in place by 3.2.0 (no Elementor meta left)
$copy_a   = $mk( 'copy-a', 'draft', false, '2025-04-01 09:00:00' );
$copy_b1  = $mk( 'copy-b1', 'draft', false, '2025-05-01 09:00:00' );
$copy_b2  = $mk( 'copy-b2', 'draft', false, '2025-04-15 09:00:00' );
$copy_c   = $mk( 'copy-c', 'trash', false );
add_post_meta( $copy_a,  '_edc_source_post_id', $src_a );
add_post_meta( $copy_b1, '_edc_source_post_id', $src_b );
add_post_meta( $copy_b2, '_edc_source_post_id', $src_b );
add_post_meta( $copy_c,  '_edc_source_post_id', $src_c );
add_post_meta( $copy_a,  '_edc_source_post_id', 0 );
add_post_meta( $replaced, '_edc_import_source', 'replaced' );
ConversionFlag::mark( $src_e, '3.2.0' );
$e_at = ConversionFlag::converted_at( $src_e );

$q = new PendingQuery();
$pending_ids = function () use ( $q ) { return array_values( array_intersect( array_map( 'intval', array_column( $q->page( [ 'page' ], 0, 1000000 ), 'ID' ) ), $GLOBALS['__bf_sources'] ) ); };
$GLOBALS['__bf_sources'] = [ $src_a, $src_b, $src_c, $src_d, $src_e ];
$check( 'BEFORE: every pre-existing source looks pending except the flagged one', $pending_ids() === [ $src_a, $src_b, $src_c, $src_d ] );

$n = Backfill::run();
// The dev site may hold older conversions of its own, so count only our fixtures.
$flagged_ours = array_values( array_filter( [ $src_a, $src_b, $src_c, $src_d, $replaced ], fn( $id ) => ConversionFlag::is_converted( $id ) ) );
$check( 'run() flagged exactly the converted fixtures, and reports at least that many', $flagged_ours === [ $src_a, $src_b, $replaced ] && $n >= 3, "flagged $n; ours " . json_encode( $flagged_ours ) );
$check( 'a source with a live copy is now converted', ConversionFlag::is_converted( $src_a ) );
$check( 'the flag carries the copy\'s date', ConversionFlag::converted_at( $src_a ) === '2025-04-01 09:00:00', ConversionFlag::converted_at( $src_a ) );
$check( 'the flag is marked legacy so the UI can say so', ConversionFlag::converted_version( $src_a ) === ConversionFlag::LEGACY_VERSION );
$check( 'two copies give ONE flag, dated by the earliest', ConversionFlag::converted_at( $src_b ) === '2025-04-15 09:00:00' && count( get_post_meta( $src_b, '_edc_converted_at', false ) ) === 1 );
$check( 'a source whose only copy was trashed stays pending', ! ConversionFlag::is_converted( $src_c ) );
$check( 'a never-converted source stays pending', ! ConversionFlag::is_converted( $src_d ) );
$check( 'a flag written by the new code is left exactly as it was', ConversionFlag::converted_at( $src_e ) === $e_at && ConversionFlag::converted_version( $src_e ) === '3.2.0' );
$check( 'a page replaced in place by 3.2.0 is converted', ConversionFlag::is_converted( $replaced ) && ConversionFlag::converted_version( $replaced ) === ConversionFlag::LEGACY_VERSION );
$check( 'an id of 0 in _edc_source_post_id creates nothing', ! ConversionFlag::is_converted( 0 ) );
$check( 'AFTER: only the unconverted sources are pending', $pending_ids() === [ $src_c, $src_d ] );

$check( 'running it again changes nothing (idempotent)', Backfill::run() === 0 && count( get_post_meta( $src_a, '_edc_converted_at', false ) ) === 1 );

delete_option( Backfill::DONE_OPTION );
$ran = Backfill::maybe_run();
$check( 'maybe_run runs once and records it', $ran === true && get_option( Backfill::DONE_OPTION ) !== false );
$check( 'maybe_run does not run twice', Backfill::maybe_run() === false );

$cleanup();
Schema::drop();
echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILED\n";
exit( $fails === 0 ? 0 : 1 );
