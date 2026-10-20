<?php
/**
 * The two tables behind Conversion Jobs, and their versioned migration.
 *
 * Custom tables rather than options or post meta: a job holds one row per
 * page, and a site with thousands of pages would otherwise put that into a
 * single serialized option that every request loads and every chunk rewrites.
 */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Schema {

    /** Bump when the table definitions change; install() is then re-run once. */
    const DB_VERSION     = '1';
    const VERSION_OPTION = 'edc_db_version';

    public static function jobs_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'edc_jobs';
    }

    public static function items_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'edc_job_items';
    }

    /** Creates or updates both tables (dbDelta is additive and idempotent). */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $jobs    = self::jobs_table();
        $items   = self::items_table();

        dbDelta( "CREATE TABLE {$jobs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  finished_at datetime DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  mode varchar(10) NOT NULL DEFAULT 'copy',
  scope longtext NOT NULL,
  total int(11) unsigned NOT NULL DEFAULT 0,
  converted int(11) unsigned NOT NULL DEFAULT 0,
  skipped int(11) unsigned NOT NULL DEFAULT 0,
  failed int(11) unsigned NOT NULL DEFAULT 0,
  unsupported longtext DEFAULT NULL,
  heartbeat datetime DEFAULT NULL,
  locked_until datetime DEFAULT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY status_created (status,created_at),
  KEY created_at (created_at)
) {$charset};" );

        dbDelta( "CREATE TABLE {$items} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  job_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  post_type varchar(40) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  state varchar(12) NOT NULL DEFAULT 'pending',
  result_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  message text DEFAULT NULL,
  modules int(11) unsigned NOT NULL DEFAULT 0,
  warnings int(11) unsigned NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY job_post (job_id,post_id),
  KEY job_state (job_id,state,id),
  KEY post_id (post_id)
) {$charset};" );

        update_option( self::VERSION_OPTION, self::DB_VERSION, false );
    }

    /** Hooked to admin_init: a plugin update does not fire the activation hook. */
    public static function maybe_upgrade(): void {
        if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    /** Uninstall only. */
    public static function drop(): void {
        global $wpdb;

        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::items_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are built from $wpdb->prefix
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::jobs_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        delete_option( self::VERSION_OPTION );
    }
}
