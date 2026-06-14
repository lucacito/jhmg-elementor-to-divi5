<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Parsers\ElementorImportParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminPage {

    const MENU_SLUG           = 'edc-converter';
    const IMPORT_NONCE_NAME   = 'edc_import_nonce';
    const IMPORT_NONCE_ACTION = 'edc_import';

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

        echo '<style>' . $this->inline_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput

        $action = sanitize_key( $_GET['action'] ?? '' );

        if ( $action === 'batch_result' ) {
            $this->render_batch_result();
        } else {
            $this->render_list();
        }
    }

    // ------------------------------------------------------------------
    // POST dispatcher
    // ------------------------------------------------------------------

    public function handle_post(): void {
        if ( ( $_POST['action'] ?? '' ) === 'edc_import' ) {
            $this->handle_import();
        }
    }

    // ------------------------------------------------------------------
    // File upload / batch import handler
    // ------------------------------------------------------------------

    private function handle_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'elementor-divi5-converter' ) );
        }

        check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME );

        $upload = $_FILES['edc_import_file'] ?? null;

        if ( ! $upload || ! is_array( $upload ) ) {
            wp_die( esc_html__( 'No file was uploaded.', 'elementor-divi5-converter' ) );
        }

        if ( $upload['error'] !== UPLOAD_ERR_OK ) {
            wp_die( esc_html( $this->upload_error_message( $upload['error'] ) ) );
        }

        $post_type = sanitize_key( $_POST['edc_post_type'] ?? 'page' );
        if ( ! in_array( $post_type, [ 'page', 'post' ], true ) ) {
            $post_type = 'page';
        }

        $post_status = sanitize_key( $_POST['edc_post_status'] ?? 'draft' );
        if ( ! in_array( $post_status, [ 'draft', 'publish' ], true ) ) {
            $post_status = 'draft';
        }

        $convert_headers = isset( $_POST['edc_convert_headers'] ) && $_POST['edc_convert_headers'] === '1';

        $parser = new ElementorImportParser();

        try {
            $items = $parser->parse( $upload['tmp_name'], $upload['name'] );
        } catch ( \RuntimeException $e ) {
            wp_die( esc_html__( 'Failed to parse import file: ', 'elementor-divi5-converter' ) . esc_html( $e->getMessage() ) );
        }

        if ( empty( $items ) ) {
            wp_die( esc_html__( 'No pages found in the import file.', 'elementor-divi5-converter' ) );
        }

        $importer = new BatchImporter();
        $results  = $importer->import( $items, [
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'convert_headers' => $convert_headers,
        ] );

        $import_id = $this->generate_import_id();
        set_transient( 'edc_batch_' . $import_id, $results, HOUR_IN_SECONDS );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'      => self::MENU_SLUG,
                    'action'    => 'batch_result',
                    'import_id' => $import_id,
                ],
                admin_url( 'tools.php' )
            )
        );
        exit;
    }

    private function generate_import_id(): string {
        return function_exists( 'wp_generate_uuid4' )
            ? wp_generate_uuid4()
            : bin2hex( random_bytes( 16 ) );
    }

    private function upload_error_message( int $code ): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was selected.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server is missing a temporary folder.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to server.', 'elementor-divi5-converter' ),
            UPLOAD_ERR_EXTENSION  => __( 'Upload stopped by server extension.', 'elementor-divi5-converter' ),
        ];

        return $messages[ $code ] ?? sprintf( __( 'Unknown upload error (code %d).', 'elementor-divi5-converter' ), $code );
    }

    // ------------------------------------------------------------------
    // Main view
    // ------------------------------------------------------------------

    private function render_list(): void {
        ?>
        <div class="wrap edc-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Elementor to Divi 5 Converter', 'elementor-divi5-converter' ); ?></h1>
            <?php $this->render_import_section(); ?>
        </div>
        <?php
    }

    private function render_import_section(): void {
        ?>
        <div class="edc-import-section">
            <h2><?php esc_html_e( 'Import from Elementor JSON / ZIP', 'elementor-divi5-converter' ); ?></h2>
            <p class="edc-description">
                <?php esc_html_e( 'Upload an Elementor JSON export or a full-site Kit ZIP. No Elementor plugin required — this converts the exported file directly. Pages will be created in this Divi site.', 'elementor-divi5-converter' ); ?>
            </p>

            <form method="post" enctype="multipart/form-data" action="" class="edc-import-form">
                <?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
                <input type="hidden" name="action" value="edc_import">

                <div class="edc-import-fields">
                    <div class="edc-import-field">
                        <label for="edc_import_file">
                            <strong><?php esc_html_e( 'File', 'elementor-divi5-converter' ); ?></strong>
                        </label>
                        <input type="file" id="edc_import_file" name="edc_import_file" accept=".json,.zip" required>
                        <p class="description"><?php esc_html_e( 'Accepted: .json (single page or template) or .zip (Elementor Kit export)', 'elementor-divi5-converter' ); ?></p>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_type">
                            <strong><?php esc_html_e( 'Create as', 'elementor-divi5-converter' ); ?></strong>
                        </label>
                        <select id="edc_post_type" name="edc_post_type">
                            <option value="page"><?php esc_html_e( 'Page', 'elementor-divi5-converter' ); ?></option>
                            <option value="post"><?php esc_html_e( 'Post', 'elementor-divi5-converter' ); ?></option>
                        </select>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_status">
                            <strong><?php esc_html_e( 'Status', 'elementor-divi5-converter' ); ?></strong>
                        </label>
                        <select id="edc_post_status" name="edc_post_status">
                            <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'elementor-divi5-converter' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Published', 'elementor-divi5-converter' ); ?></option>
                        </select>
                    </div>

                </div>

                <div class="edc-import-field edc-import-field--checkbox">
                    <label>
                        <input type="checkbox" name="edc_convert_headers" value="1">
                        <strong><?php esc_html_e( 'Convert header templates as Divi Theme Builder headers', 'elementor-divi5-converter' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When checked, Elementor header templates are imported as Divi Theme Builder global headers. Uncheck to import them as regular draft pages instead.', 'elementor-divi5-converter' ); ?>
                    </p>
                </div>

                <div class="edc-import-submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Import and Convert', 'elementor-divi5-converter' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Batch import result view
    // ------------------------------------------------------------------

    private function render_batch_result(): void {
        $import_id = sanitize_key( $_GET['import_id'] ?? '' );

        if ( $import_id === '' ) {
            wp_die( esc_html__( 'No import ID provided.', 'elementor-divi5-converter' ) );
        }

        $results = get_transient( 'edc_batch_' . $import_id );

        if ( ! is_array( $results ) ) {
            wp_die( esc_html__( 'Import results not found or expired. Results are kept for one hour.', 'elementor-divi5-converter' ) );
        }

        $total     = count( $results );
        $succeeded = count( array_filter( $results, fn( $r ) => $r['success'] ) );
        $failed    = $total - $succeeded;
        ?>
        <div class="wrap edc-wrap">
            <h1><?php esc_html_e( 'Batch Import Results', 'elementor-divi5-converter' ); ?></h1>

            <div class="edc-result-actions">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="button">
                    &larr; <?php esc_html_e( 'Back to converter', 'elementor-divi5-converter' ); ?>
                </a>
            </div>

            <div class="edc-batch-summary">
                <span class="edc-summary-stat edc-summary-stat--total">
                    <?php printf( esc_html__( '%d page(s) processed', 'elementor-divi5-converter' ), $total ); ?>
                </span>
                <?php if ( $succeeded > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--ok">
                    <?php printf( esc_html__( '%d converted', 'elementor-divi5-converter' ), $succeeded ); ?>
                </span>
                <?php endif; ?>
                <?php if ( $failed > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--fail">
                    <?php printf( esc_html__( '%d failed', 'elementor-divi5-converter' ), $failed ); ?>
                </span>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped edc-batch-table">
                <thead>
                    <tr>
                        <th class="column-title column-primary"><?php esc_html_e( 'Title', 'elementor-divi5-converter' ); ?></th>
                        <th class="column-status"><?php esc_html_e( 'Status', 'elementor-divi5-converter' ); ?></th>
                        <th class="column-issues"><?php esc_html_e( 'Issues', 'elementor-divi5-converter' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Actions', 'elementor-divi5-converter' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $results as $result ) :
                    $warn_count = count( $result['report']['warnings']          ?? [] )
                                + count( $result['unsupported']                 ?? [] )
                                + count( $result['report']['skipped_settings']  ?? [] );
                ?>
                    <tr>
                        <td class="column-title column-primary">
                            <strong><?php echo esc_html( $result['title'] ?: __( '(no title)', 'elementor-divi5-converter' ) ); ?></strong>
                        </td>
                        <td class="column-status">
                            <?php if ( $result['success'] ) : ?>
                                <span class="edc-status edc-status--converted">&#10003; <?php esc_html_e( 'Converted', 'elementor-divi5-converter' ); ?></span>
                            <?php else : ?>
                                <span class="edc-status edc-status--error">&#10007; <?php esc_html_e( 'Failed', 'elementor-divi5-converter' ); ?></span>
                                <?php if ( ! empty( $result['error'] ) ) : ?>
                                    <br><small class="edc-error-msg"><?php echo esc_html( $result['error'] ); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="column-issues">
                            <?php if ( $result['success'] && $warn_count > 0 ) : ?>
                                <span class="edc-badge edc-badge--warn"><?php echo $warn_count; ?></span>
                            <?php elseif ( $result['success'] ) : ?>
                                <span class="edc-status--clean">&#10003;</span>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <?php if ( $result['success'] && $result['post_id'] > 0 ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $result['post_id'] ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'elementor-divi5-converter' ); ?>
                                </a>
                                <a href="<?php echo esc_url( get_permalink( $result['post_id'] ) ); ?>" class="button button-small" target="_blank" rel="noopener">
                                    <?php esc_html_e( 'View', 'elementor-divi5-converter' ); ?>
                                </a>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Shared report card rendering
    // ------------------------------------------------------------------

    private function render_report_cards( array $report ): void {
        if ( ! empty( $report['converted'] ) ) : ?>
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
        <?php endif;

        if ( ! empty( $report['unsupported'] ) ) : ?>
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
        <?php endif;

        if ( ! empty( $report['warnings'] ) ) : ?>
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
        <?php endif;

        if ( ! empty( $report['skipped_settings'] ) ) : ?>
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
        <?php endif;

        if ( empty( $report['unsupported'] ) && empty( $report['warnings'] ) && empty( $report['skipped_settings'] ) ) : ?>
        <div class="edc-card edc-card--success">
            <h2><?php esc_html_e( 'Clean Conversion', 'elementor-divi5-converter' ); ?></h2>
            <p><?php esc_html_e( 'No warnings, unsupported elements, or skipped settings.', 'elementor-divi5-converter' ); ?></p>
        </div>
        <?php endif;
    }

    // ------------------------------------------------------------------
    // Styles
    // ------------------------------------------------------------------

    private function inline_css(): string {
        return '
.edc-wrap { max-width: 1200px; }
.edc-description { margin: 8px 0 20px; color: #555; }

/* Import section */
.edc-import-section { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #2271b1; border-radius: 3px; padding: 20px 24px; margin: 20px 0 0; }
.edc-import-section h2 { margin: 0 0 6px; font-size: 15px; }
.edc-import-form { margin-top: 16px; }
.edc-import-fields { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }
.edc-import-field { display: flex; flex-direction: column; gap: 4px; }
.edc-import-field label strong { display: block; margin-bottom: 2px; }
.edc-import-field input[type="file"] { padding: 4px 0; }
.edc-import-field .description { margin: 4px 0 0; font-size: 11px; color: #757575; }
.edc-import-field--checkbox { margin-top: 14px; }
.edc-import-field--checkbox label { display: flex; align-items: center; gap: 7px; cursor: pointer; }
.edc-import-field--checkbox input[type="checkbox"] { margin: 0; flex-shrink: 0; }
.edc-import-field--checkbox .description { margin: 5px 0 0 22px; }
.edc-import-submit { display: flex; align-items: flex-end; padding-bottom: 2px; margin-top: 16px; }

/* Status badges */
.edc-status { font-weight: 600; }
.edc-status--converted { color: #2e7d32; }
.edc-status--error     { color: #c62828; }
.edc-status--clean     { color: #2e7d32; }
.edc-error-msg         { color: #c62828; font-size: 11px; }

.edc-badge { display: inline-block; background: #f0b429; color: #7a4f00; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; vertical-align: middle; }
.edc-badge--warn { background: #f0b429; color: #7a4f00; }
.edc-badge--info { background: #3b82f6; color: #fff; }

/* Batch summary */
.edc-batch-summary { display: flex; gap: 12px; margin: 16px 0 20px; flex-wrap: wrap; }
.edc-summary-stat { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 3px; font-weight: 600; font-size: 13px; }
.edc-summary-stat--total { background: #f0f0f1; color: #3c434a; }
.edc-summary-stat--ok    { background: #d1e7dd; color: #0a3622; }
.edc-summary-stat--fail  { background: #f8d7da; color: #58151c; }

/* Batch table */
.edc-batch-table .column-status  { width: 160px; }
.edc-batch-table .column-issues  { width: 90px; }
.edc-batch-table .column-actions { width: 160px; }

/* Result views */
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
