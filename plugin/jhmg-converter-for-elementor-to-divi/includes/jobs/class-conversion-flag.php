<?php
/**
 * The record, on the SOURCE post, that it has been converted.
 *
 * Deliberately post meta rather than a lookup in the jobs tables: a site that
 * converted pages before Conversion Jobs existed has no job rows, and the
 * pending list has to work for it too. `(post_id, meta_key)` is indexed by
 * core, so "which Elementor posts have no flag" stays a cheap anti-join at
 * 10k+ posts.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConversionFlag {

    const AT            = '_edc_converted_at';
    const VERSION       = '_edc_converted_version';
    const MANUAL        = '_edc_converted_manually';
    const UNCONVERTIBLE = '_edc_unconvertible';

    /** The version stored for flags backfilled from conversions made before this existed. */
    const LEGACY_VERSION = 'legacy';

    public static function mark( int $post_id, ?string $version = null ): void {
        update_post_meta( $post_id, self::AT, gmdate( 'Y-m-d H:i:s' ) );
        update_post_meta( $post_id, self::VERSION, $version ?? ( defined( 'EDC_PLUGIN_VERSION' ) ? EDC_PLUGIN_VERSION : 'unknown' ) );
    }

    /** The user fixed this page by hand. Undo leaves such a flag alone. */
    public static function mark_manual( int $post_id ): void {
        self::mark( $post_id, 'manual' );
        update_post_meta( $post_id, self::MANUAL, '1' );
        delete_post_meta( $post_id, self::UNCONVERTIBLE );
    }

    public static function clear( int $post_id ): void {
        delete_post_meta( $post_id, self::AT );
        delete_post_meta( $post_id, self::VERSION );
        delete_post_meta( $post_id, self::MANUAL );
    }

    /**
     * Undo removed a conversion of this page. The flag goes only if nothing
     * else still vouches for it: not a manual mark, and not another live copy.
     */
    public static function clear_after_undo( int $post_id ): void {
        if ( self::is_manual( $post_id ) ) {
            return;
        }

        $live_copies = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_key'       => '_edc_source_post_id',
            'meta_value'     => $post_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( empty( $live_copies ) ) {
            self::clear( $post_id );
        }
    }

    // The (string) casts matter: get_post_meta() returns false, not '', for an
    // invalid post ID, and false !== '' would make post 0 look converted.
    public static function is_converted( int $post_id ): bool {
        return (string) get_post_meta( $post_id, self::AT, true ) !== '';
    }

    public static function converted_at( int $post_id ): string {
        return (string) get_post_meta( $post_id, self::AT, true );
    }

    public static function converted_version( int $post_id ): string {
        return (string) get_post_meta( $post_id, self::VERSION, true );
    }

    public static function is_manual( int $post_id ): bool {
        return (string) get_post_meta( $post_id, self::MANUAL, true ) !== '';
    }

    /** The page has no usable Elementor content: it leaves the pending list without being "converted". */
    public static function mark_unconvertible( int $post_id, string $reason ): void {
        update_post_meta( $post_id, self::UNCONVERTIBLE, $reason );
    }

    public static function is_unconvertible( int $post_id ): bool {
        return (string) get_post_meta( $post_id, self::UNCONVERTIBLE, true ) !== '';
    }

    public static function clear_unconvertible( int $post_id ): void {
        delete_post_meta( $post_id, self::UNCONVERTIBLE );
    }
}
