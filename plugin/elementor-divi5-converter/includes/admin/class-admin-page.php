<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminPage {

    const MENU_SLUG    = 'edc-converter';
    const NONCE_NAME   = 'edc_convert_nonce';
    const NONCE_ACTION = 'edc_convert';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
    }

    // ------------------------------------------------------------------
    // Menu
    // ------------------------------------------------------------------

    public function register_menu(): void {
        add_management_page(
            __( 'Elementor to Divi 5 Converter', 'elementor-divi5-converter' ),
            __( 'Elementor → Divi 5', 'elementor-divi5-converter' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    // ------------------------------------------------------------------
    // Router
    // ------------------------------------------------------------------

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-divi5-converter' ) );
        }

        $action = sanitize_key( $_GET['action'] ?? '' );

        echo '<style>' . $this->inline_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput

        if ( $action === 'result' ) {
            $this->render_result();
        } else {
            $this->render_list();
        }
    }

    // ------------------------------------------------------------------
    // POST handler (runs on admin_init, before any output)
    // ------------------------------------------------------------------

    public function handle_post(): void {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'edc_convert' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'elementor-divi5-converter' ) );
        }

        check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

        $source_id     = (int) ( $_POST['source_id'] ?? 0 );
        $preserve_slug = ! empty( $_POST['preserve_slug'] );

        if ( $source_id <= 0 ) {
            wp_die( esc_html__( 'Invalid source page ID.', 'elementor-divi5-converter' ) );
        }

        $source = get_post( $source_id );

        if ( ! $source ) {
            wp_die( esc_html__( 'Source post not found.', 'elementor-divi5-converter' ) );
        }

        $elementor_json = get_post_meta( $source_id, '_elementor_data', true );

        if ( empty( $elementor_json ) ) {
            wp_die( esc_html__( 'Source post has no Elementor data.', 'elementor-divi5-converter' ) );
        }

        $payload = json_decode( $elementor_json, true );

        if ( ! is_array( $payload ) ) {
            wp_die( esc_html__( 'Elementor data is not valid JSON.', 'elementor-divi5-converter' ) );
        }

        $new_post_args = [
            'post_type'   => $source->post_type,
            'post_status' => 'publish',
            'post_title'  => $source->post_title,
        ];

        if ( $preserve_slug ) {
            $new_post_args['post_name'] = $source->post_name;
        }

        $new_id = wp_insert_post( $new_post_args );

        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ) );
        }

        $engine    = new ConverterEngine();
        $converted = $engine->convert( $payload );
        $exporter  = new DiviExporter();
        $exporter->save( $new_id, $converted );

        // Link source ↔ converted so the list screen can show status.
        update_post_meta( $new_id,    '_edc_source_page_id',    $source_id );
        update_post_meta( $source_id, '_edc_converted_page_id', $new_id );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => self::MENU_SLUG,
                    'action'       => 'result',
                    'converted_id' => $new_id,
                ],
                admin_url( 'tools.php' )
            )
        );
        exit;
    }

    // ------------------------------------------------------------------
    // List view
    // ------------------------------------------------------------------

    private function render_list(): void {
        $posts = $this->query_elementor_posts();
        ?>
        <div class="wrap edc-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Elementor to Divi 5 Converter', 'elementor-divi5-converter' ); ?></h1>
            <p class="edc-description"><?php esc_html_e( 'Pages and posts that contain Elementor data. Click "Convert to Divi 5" to create a new Divi 5 page from any entry.', 'elementor-divi5-converter' ); ?></p>

            <?php if ( empty( $posts ) ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'No pages or posts with Elementor data were found.', 'elementor-divi5-converter' ); ?></p></div>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped edc-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-title column-primary"><?php esc_html_e( 'Title', 'elementor-divi5-converter' ); ?></th>
                        <th scope="col" class="column-type"><?php esc_html_e( 'Type', 'elementor-divi5-converter' ); ?></th>
                        <th scope="col" class="column-status"><?php esc_html_e( 'Conversion Status', 'elementor-divi5-converter' ); ?></th>
                        <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'elementor-divi5-converter' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $posts as $post ) :
                    $converted_id   = (int) get_post_meta( $post->ID, '_edc_converted_page_id', true );
                    $converted_post = $converted_id ? get_post( $converted_id ) : null;
                ?>
                    <tr>
                        <td class="column-title column-primary">
                            <strong>
                                <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
                                    <?php echo esc_html( $post->post_title ?: __( '(no title)', 'elementor-divi5-converter' ) ); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener">
                                        <?php esc_html_e( 'View', 'elementor-divi5-converter' ); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-type"><?php echo esc_html( $post->post_type ); ?></td>
                        <td class="column-status"><?php $this->render_status( $converted_id, $converted_post ); ?></td>
                        <td class="column-actions"><?php $this->render_convert_form( $post->ID, (bool) $converted_post ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description edc-count"><?php printf( esc_html__( '%d page(s) with Elementor data found.', 'elementor-divi5-converter' ), count( $posts ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_status( int $converted_id, ?\WP_Post $converted_post ): void {
        if ( $converted_post ) {
            $report_raw = get_post_meta( $converted_id, '_edc_conversion_report', true );
            $report     = $report_raw ? json_decode( $report_raw, true ) : [];
            $warn_count = count( $report['warnings'] ?? [] ) + count( $report['unsupported'] ?? [] );
            ?>
            <span class="edc-status edc-status--converted">&#10003; <?php esc_html_e( 'Converted', 'elementor-divi5-converter' ); ?></span>
            <?php if ( $warn_count > 0 ) : ?>
                <span class="edc-badge edc-badge--warn" title="<?php echo esc_attr( sprintf( __( '%d warnings/unsupported', 'elementor-divi5-converter' ), $warn_count ) ); ?>">
                    <?php echo $warn_count; ?>
                </span>
            <?php endif; ?>
            <div class="row-actions">
                <span><a href="<?php echo esc_url( get_edit_post_link( $converted_id ) ); ?>"><?php esc_html_e( 'Edit Divi page', 'elementor-divi5-converter' ); ?></a></span>
                <span> | <a href="<?php echo esc_url( get_permalink( $converted_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'elementor-divi5-converter' ); ?></a></span>
                <span> | <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'action' => 'result', 'converted_id' => $converted_id ], admin_url( 'tools.php' ) ) ); ?>"><?php esc_html_e( 'View report', 'elementor-divi5-converter' ); ?></a></span>
            </div>
            <?php
        } else {
            echo '<span class="edc-status edc-status--pending">&mdash; ' . esc_html__( 'Not converted', 'elementor-divi5-converter' ) . '</span>';
        }
    }

    private function render_convert_form( int $source_id, bool $already_converted ): void {
        ?>
        <form method="post" action="" class="edc-convert-form">
            <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
            <input type="hidden" name="action"    value="edc_convert">
            <input type="hidden" name="source_id" value="<?php echo esc_attr( $source_id ); ?>">

            <label class="edc-preserve-slug">
                <input type="checkbox" name="preserve_slug" value="1">
                <?php esc_html_e( 'Same slug', 'elementor-divi5-converter' ); ?>
            </label>

            <button type="submit" class="button <?php echo $already_converted ? 'button-secondary' : 'button-primary'; ?>">
                <?php echo $already_converted
                    ? esc_html__( 'Reconvert', 'elementor-divi5-converter' )
                    : esc_html__( 'Convert to Divi 5', 'elementor-divi5-converter' ); ?>
            </button>
        </form>
        <?php
    }

    // ------------------------------------------------------------------
    // Result view
    // ------------------------------------------------------------------

    private function render_result(): void {
        $converted_id = (int) ( $_GET['converted_id'] ?? 0 );

        if ( $converted_id <= 0 ) {
            wp_die( esc_html__( 'No converted page ID provided.', 'elementor-divi5-converter' ) );
        }

        $converted_post = get_post( $converted_id );

        if ( ! $converted_post ) {
            wp_die( esc_html__( 'Converted page not found.', 'elementor-divi5-converter' ) );
        }

        $raw_report  = get_post_meta( $converted_id, '_edc_conversion_report', true );
        $report      = $raw_report ? json_decode( $raw_report, true ) : [];
        $source_id   = (int) get_post_meta( $converted_id, '_edc_source_page_id', true );
        $source_post = $source_id ? get_post( $source_id ) : null;
        ?>
        <div class="wrap edc-wrap">
            <h1><?php esc_html_e( 'Conversion Result', 'elementor-divi5-converter' ); ?></h1>

            <div class="edc-result-actions">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="button">
                    &larr; <?php esc_html_e( 'Back to list', 'elementor-divi5-converter' ); ?>
                </a>
                <?php if ( $source_post ) : ?>
                <a href="<?php echo esc_url( get_edit_post_link( $source_id ) ); ?>" class="button">
                    <?php esc_html_e( 'Edit source page', 'elementor-divi5-converter' ); ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( get_edit_post_link( $converted_id ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Edit converted Divi page', 'elementor-divi5-converter' ); ?>
                </a>
                <a href="<?php echo esc_url( get_permalink( $converted_id ) ); ?>" class="button" target="_blank" rel="noopener">
                    <?php esc_html_e( 'View converted page', 'elementor-divi5-converter' ); ?>
                </a>
            </div>

            <div class="edc-result-grid">

                <div class="edc-card">
                    <h2><?php esc_html_e( 'Converted Page', 'elementor-divi5-converter' ); ?></h2>
                    <table class="widefat striped">
                        <tr>
                            <th><?php esc_html_e( 'Title', 'elementor-divi5-converter' ); ?></th>
                            <td><?php echo esc_html( $converted_post->post_title ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Slug', 'elementor-divi5-converter' ); ?></th>
                            <td><code><?php echo esc_html( $converted_post->post_name ); ?></code></td>
                        </tr>
                        <?php if ( $source_post ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Source page', 'elementor-divi5-converter' ); ?></th>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $source_id ) ); ?>">
                                    <?php echo esc_html( $source_post->post_title ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if ( ! empty( $report['converted'] ) ) : ?>
                <div class="edc-card">
                    <h2><?php esc_html_e( 'Converted Elements', 'elementor-divi5-converter' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Type', 'elementor-divi5-converter' ); ?></th>
                                <th><?php esc_html_e( 'Count', 'elementor-divi5-converter' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $report['converted'] as $type => $count ) : ?>
                            <tr>
                                <td><?php echo esc_html( ucfirst( $type ) ); ?></td>
                                <td><strong><?php echo (int) $count; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $report['unsupported'] ) ) : ?>
                <div class="edc-card edc-card--warn">
                    <h2>
                        <?php esc_html_e( 'Unsupported Elements', 'elementor-divi5-converter' ); ?>
                        <span class="edc-badge"><?php echo count( $report['unsupported'] ); ?></span>
                    </h2>
                    <ul>
                    <?php foreach ( $report['unsupported'] as $item ) : ?>
                        <li>
                            <code><?php echo esc_html( $item['widgetType'] ?? $item['elType'] ?? 'unknown' ); ?></code>
                            <?php if ( ! empty( $item['id'] ) ) : ?>
                                <span class="edc-item-id">(id: <?php echo esc_html( $item['id'] ); ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $report['warnings'] ) ) : ?>
                <div class="edc-card edc-card--warn">
                    <h2>
                        <?php esc_html_e( 'Warnings', 'elementor-divi5-converter' ); ?>
                        <span class="edc-badge"><?php echo count( $report['warnings'] ); ?></span>
                    </h2>
                    <ul>
                    <?php foreach ( $report['warnings'] as $warning ) : ?>
                        <li><?php echo esc_html( $warning ); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $report['skipped_settings'] ) ) : ?>
                <div class="edc-card edc-card--info">
                    <h2>
                        <?php esc_html_e( 'Skipped Settings', 'elementor-divi5-converter' ); ?>
                        <span class="edc-badge edc-badge--info"><?php echo count( $report['skipped_settings'] ); ?></span>
                    </h2>
                    <p class="description"><?php esc_html_e( 'These Elementor settings have no Divi 5 mapping yet and were not converted.', 'elementor-divi5-converter' ); ?></p>
                    <ul>
                    <?php foreach ( $report['skipped_settings'] as $setting ) : ?>
                        <li><code><?php echo esc_html( $setting ); ?></code></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( empty( $report['unsupported'] ) && empty( $report['warnings'] ) && empty( $report['skipped_settings'] ) ) : ?>
                <div class="edc-card edc-card--success">
                    <h2><?php esc_html_e( 'Clean Conversion', 'elementor-divi5-converter' ); ?></h2>
                    <p><?php esc_html_e( 'No warnings, unsupported elements, or skipped settings.', 'elementor-divi5-converter' ); ?></p>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    private function query_elementor_posts(): array {
        return get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                [
                    'key'     => '_elementor_data',
                    'compare' => 'EXISTS',
                ],
            ],
        ] );
    }

    // ------------------------------------------------------------------
    // Styles
    // ------------------------------------------------------------------

    private function inline_css(): string {
        return '
.edc-wrap { max-width: 1200px; }
.edc-description { margin: 8px 0 20px; color: #555; }
.edc-count { margin-top: 8px; color: #888; }

.edc-table th, .edc-table td { vertical-align: middle; padding: 10px 12px; }
.edc-table .column-type   { width: 90px; }
.edc-table .column-status { width: 260px; }
.edc-table .column-actions { width: 260px; }

.edc-convert-form { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
.edc-preserve-slug { display: flex; align-items: center; gap: 4px; font-size: 12px; white-space: nowrap; color: #555; cursor: pointer; }
.edc-preserve-slug input { margin: 0; }

.edc-status { font-weight: 600; }
.edc-status--converted { color: #2e7d32; }
.edc-status--pending   { color: #888; font-weight: 400; }

.edc-badge { display: inline-block; background: #f0b429; color: #7a4f00; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; vertical-align: middle; }
.edc-badge--warn { background: #f0b429; color: #7a4f00; }
.edc-badge--info { background: #3b82f6; color: #fff; }

.edc-result-actions { display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap; }

.edc-result-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.edc-card { background: #fff; border: 1px solid #dcdcde; border-radius: 3px; padding: 16px 20px; }
.edc-card h2 { margin: 0 0 14px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #444; display: flex; align-items: center; gap: 8px; }
.edc-card table th { width: 120px; font-weight: 500; }
.edc-card ul { margin: 0; padding-left: 18px; }
.edc-card ul li { margin-bottom: 5px; font-size: 13px; line-height: 1.5; }
.edc-card p.description { margin: 0 0 10px; font-size: 12px; }
.edc-card--warn    { border-left: 4px solid #dba617; }
.edc-card--info    { border-left: 4px solid #3b82f6; }
.edc-card--success { border-left: 4px solid #2e7d32; }
.edc-item-id { color: #888; font-size: 11px; }
        ';
    }
}
