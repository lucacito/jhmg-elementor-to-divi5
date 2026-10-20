<?php
/**
 * Gives pages converted before Conversion Jobs existed their "converted" flag.
 *
 * Before this, the only trace of a conversion was on the output: a copy
 * carrying _edc_source_post_id (the page it came from), or — from 3.2.0 — a
 * page converted in place with _edc_import_source = replaced. This turns those
 * into _edc_converted_at on the source, so a customer who already converted
 * 400 pages does not open the new panel to find all 400 listed as pending.
 *
 * Two set-based statements rather than a PHP loop, so it is quick on large
 * sites, and both only insert where no flag exists, so it is safe to repeat
 * and never overwrites a flag the converter wrote.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Backfill {

    const DONE_OPTION = 'edc_flags_backfilled';

    /** Hooked to admin_init: runs once per site, after the tables exist. */
    public static function maybe_run(): bool {
        if ( get_option( self::DONE_OPTION ) !== false ) {
            return false;
        }

        self::run();
        update_option( self::DONE_OPTION, gmdate( 'Y-m-d H:i:s' ), false );

        return true;
    }

    /** @return int Source pages newly flagged. */
    public static function run(): int {
        global $wpdb;

        $pm = $wpdb->postmeta;
        $ps = $wpdb->posts;
        $at = ConversionFlag::AT;

        // 1. Pages that have a live (non-trashed) converted copy. A trashed
        //    copy is an undone conversion, so it does not count. Dated by the
        //    earliest copy.
        $from_copies = (int) $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            "INSERT INTO {$pm} (post_id, meta_key, meta_value)
             SELECT s.sid, '{$at}', s.first_copy
             FROM (
                 SELECT CAST(m.meta_value AS UNSIGNED) AS sid, MIN(c.post_date_gmt) AS first_copy
                 FROM {$pm} m
                 INNER JOIN {$ps} c ON c.ID = m.post_id
                 WHERE m.meta_key = '_edc_source_post_id' AND c.post_status <> 'trash'
                 GROUP BY sid
             ) s
             INNER JOIN {$ps} src ON src.ID = s.sid
             WHERE s.sid > 0
               AND NOT EXISTS (SELECT 1 FROM {$pm} x WHERE x.post_id = s.sid AND x.meta_key = '{$at}')"
        );

        // 2. Pages converted in place (3.2.0 "Replace the original page").
        $from_replaced = (int) $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            "INSERT INTO {$pm} (post_id, meta_key, meta_value)
             SELECT p.ID, '{$at}', p.post_modified_gmt
             FROM {$ps} p
             INNER JOIN {$pm} i ON i.post_id = p.ID AND i.meta_key = '_edc_import_source' AND i.meta_value = 'replaced'
             WHERE p.post_status <> 'trash'
               AND NOT EXISTS (SELECT 1 FROM {$pm} x WHERE x.post_id = p.ID AND x.meta_key = '{$at}')"
        );

        // Label every flag that has no version as legacy, so the UI can say
        // "converted before job tracking" instead of showing a blank.
        $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare(
                "INSERT INTO {$pm} (post_id, meta_key, meta_value)
                 SELECT f.post_id, %s, %s FROM {$pm} f
                 WHERE f.meta_key = '{$at}'
                   AND NOT EXISTS (SELECT 1 FROM {$pm} v WHERE v.post_id = f.post_id AND v.meta_key = %s)",
                ConversionFlag::VERSION, ConversionFlag::LEGACY_VERSION, ConversionFlag::VERSION
            )
        );

        return $from_copies + $from_replaced;
    }
}
