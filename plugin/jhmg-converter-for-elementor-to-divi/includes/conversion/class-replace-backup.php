<?php
/**
 * The safety net behind "replace the original page".
 *
 * Replacing converts an Elementor page in place, which destroys the Elementor
 * version unless it is kept somewhere. This class keeps it — post content and
 * the post meta that makes Elementor render the page — as a single meta entry
 * on the same post, and puts it back on Undo.
 *
 * Nothing here deletes data the user could not get back: the backup is written
 * and read back before the committer touches the page, and restoring takes a
 * WordPress revision of whatever is there first.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReplaceBackup {

    const META = '_edc_replaced_backup';

    /**
     * What makes Elementor render a post. Removing the edit mode is what hands
     * the page to Divi; the rest goes with it so a half-Elementor page cannot
     * linger. Cache entries (_elementor_css) are not listed: Elementor rebuilds
     * them.
     */
    const ELEMENTOR_META = [
        '_elementor_edit_mode',
        '_elementor_data',
        '_elementor_version',
        '_elementor_template_type',
        '_elementor_page_settings',
    ];

    /** Elementor's canvas / full-width templates need Elementor to render; a Divi page must not keep them. */
    const PAGE_TEMPLATE_META = '_wp_page_template';

    /** Everything a Divi conversion writes, so Undo can put the page's own values back. */
    const DIVI_META = [
        '_et_pb_use_builder',
        '_et_builder_version',
        '_et_pb_use_divi_5',
        '_edc_divi_data',
        '_edc_conversion_report',
        '_edc_import_source',
    ];

    /** Never swap out an Elementor template that other pages may still be using. */
    const NEVER_REPLACED_TYPES = [ 'elementor_library', 'elementor-hf' ];

    /**
     * Write the backup. Returns false when it could not be stored, in which
     * case the caller must leave the page alone.
     */
    public function save( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $meta = [];
        foreach ( self::ELEMENTOR_META as $key ) {
            if ( $this->has_meta( $post_id, $key ) ) {
                $meta[ $key ] = get_post_meta( $post_id, $key, true );
            }
        }

        $template = (string) get_post_meta( $post_id, self::PAGE_TEMPLATE_META, true );
        if ( str_starts_with( $template, 'elementor_' ) ) {
            $meta[ self::PAGE_TEMPLATE_META ] = $template;
        }

        $prior_divi = [];
        foreach ( self::DIVI_META as $key ) {
            if ( $this->has_meta( $post_id, $key ) ) {
                $prior_divi[ $key ] = get_post_meta( $post_id, $key, true );
            }
        }

        $backup = [
            'at'         => gmdate( 'Y-m-d H:i:s' ),
            'content'    => (string) ( $post->post_content ?? '' ),
            'meta'       => $meta,
            'prior_divi' => $prior_divi,
        ];

        // update_post_meta() unslashes its input; without wp_slash() every
        // backslash in Elementor's JSON would be eaten.
        if ( false === update_post_meta( $post_id, self::META, wp_slash( $backup ) ) ) {
            return false;
        }

        // A write that reported success but is not readable back is no backup.
        $stored = get_post_meta( $post_id, self::META, true );

        return is_array( $stored ) && isset( $stored['content'] );
    }

    /** Take Elementor's hold off the page. Only call after save() succeeded. */
    public function strip_elementor( int $post_id ): void {
        foreach ( self::ELEMENTOR_META as $key ) {
            delete_post_meta( $post_id, $key );
        }

        if ( str_starts_with( (string) get_post_meta( $post_id, self::PAGE_TEMPLATE_META, true ), 'elementor_' ) ) {
            update_post_meta( $post_id, self::PAGE_TEMPLATE_META, 'default' );
        }
    }

    public function has( int $post_id ): bool {
        $backup = get_post_meta( $post_id, self::META, true );

        return is_array( $backup ) && isset( $backup['content'] );
    }

    /** Whether a post's type is one replace may touch. */
    public static function replaceable_type( string $post_type ): bool {
        return ! in_array( $post_type, self::NEVER_REPLACED_TYPES, true );
    }

    /**
     * Put the original back and drop the backup. Returns false — touching
     * nothing — when there is no backup to restore from.
     */
    public function restore( int $post_id ): bool {
        $backup = get_post_meta( $post_id, self::META, true );

        if ( ! is_array( $backup ) || ! isset( $backup['content'] ) ) {
            return false;
        }

        // Whatever the page holds now (including Divi edits made since the
        // conversion) stays recoverable from the revisions screen.
        if ( function_exists( 'wp_save_post_revision' ) ) {
            wp_save_post_revision( $post_id );
        }

        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_slash( (string) $backup['content'] ),
        ] );

        $prior_divi = is_array( $backup['prior_divi'] ?? null ) ? $backup['prior_divi'] : [];
        foreach ( self::DIVI_META as $key ) {
            if ( array_key_exists( $key, $prior_divi ) ) {
                update_post_meta( $post_id, $key, wp_slash( $prior_divi[ $key ] ) );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        $meta = is_array( $backup['meta'] ?? null ) ? $backup['meta'] : [];
        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, wp_slash( $value ) );
        }

        // Divi's generated CSS for the page is stale the moment its content changes.
        if ( class_exists( 'ET_Core_PageResource' ) ) {
            \ET_Core_PageResource::remove_static_resources( $post_id, 'all' );
        }
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_modules' );
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_feature_used' );

        delete_post_meta( $post_id, self::META );

        return true;
    }

    /** Distinguishes "no such meta" from "meta whose value is an empty string". */
    private function has_meta( int $post_id, string $key ): bool {
        return ! empty( get_post_meta( $post_id, $key, false ) );
    }
}
