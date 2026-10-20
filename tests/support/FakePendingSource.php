<?php
// tests/support/FakePendingSource.php
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\PendingSource;

/** Mirrors PendingQuery's rules over the in-memory post store. */
final class FakePendingSource implements PendingSource {
    public function rows( array $types ): array {
        $rows = [];
        foreach ( $GLOBALS['__test_posts'] as $id => $p ) {
            if ( in_array( $p->post_type, $types, true ) && get_post_meta( $id, '_elementor_edit_mode', true ) === 'builder'
                && ! ConversionFlag::is_converted( $id ) && ! ConversionFlag::is_unconvertible( $id ) ) {
                $rows[] = [ 'ID' => $id, 'post_type' => $p->post_type, 'post_title' => $p->post_title ];
            }
        }
        usort( $rows, fn( $a, $b ) => $a['ID'] <=> $b['ID'] );

        return $rows;
    }
    public function count( array $post_types ): int { return count( $this->rows( $post_types ) ); }
    public function list( array $post_types, int $limit, int $offset, string $search = '' ): array {
        $rows = array_values( array_filter( $this->rows( $post_types ), fn( $r ) => $search === '' || stripos( $r['post_title'], $search ) !== false ) );

        return array_slice( $rows, $offset, $limit );
    }
    public function count_matching( array $post_types, string $search ): int { return count( $this->list( $post_types, PHP_INT_MAX, 0, $search ) ); }
    public function counts_by_type( array $post_types ): array {
        $out = [];
        foreach ( $this->rows( $post_types ) as $r ) { $out[ $r['post_type'] ] = ( $out[ $r['post_type'] ] ?? 0 ) + 1; }

        return $out;
    }
    public function converted_count(): int {
        $n = 0;
        foreach ( array_keys( $GLOBALS['__test_posts'] ) as $id ) { $n += ConversionFlag::is_converted( $id ) ? 1 : 0; }

        return $n;
    }
    public function page( array $post_types, int $after_id, int $limit ): array {
        return array_slice( array_values( array_filter( $this->rows( $post_types ), fn( $r ) => $r['ID'] > $after_id ) ), 0, $limit );
    }
}
