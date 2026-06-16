<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Parsers\ElementorImportParser;
use ElementorDivi5Converter\Premium\GlobalsStore;
use ElementorDivi5Converter\Premium\KitGlobalsParser;
use ElementorDivi5Converter\Premium\PremiumManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminPage {

    const MENU_SLUG             = 'edc-converter';
    const IMPORT_NONCE_NAME     = 'edc_import_nonce';
    const IMPORT_NONCE_ACTION   = 'edc_import';
    const KIT_NONCE_NAME        = 'edc_kit_nonce';
    const KIT_NONCE_ACTION      = 'edc_upload_kit';
    const ACTIVATE_NONCE_ACTION     = 'edc_activate_premium';
    const KIT_CONVERT_NONCE_NAME    = 'edc_kit_convert_nonce';
    const KIT_CONVERT_NONCE_ACTION  = 'edc_convert_kit_pages';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
    }

    public function enqueue_admin_styles( string $hook ): void {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }
        wp_register_style( 'edc-admin', false, [], EDC_PLUGIN_VERSION );
        wp_enqueue_style( 'edc-admin' );
        wp_add_inline_style( 'edc-admin', $this->inline_css() );
    }

    // ------------------------------------------------------------------
    // Menu
    // ------------------------------------------------------------------

    public function register_menu(): void {
        add_management_page(
            __( 'Elementor to Divi 5 Converter', 'jhmg-converter-for-elementor-to-divi' ),
            __( 'Elementor → Divi 5', 'jhmg-converter-for-elementor-to-divi' ),
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- each handler verifies its own nonce
        if ( $action === 'edc_import' ) {
            $this->handle_import();
        }
        if ( $action === 'edc_activate_premium' ) {
            $this->handle_activate_premium();
        }
        if ( $action === 'edc_upload_kit' ) {
            $this->handle_upload_kit();
        }
        if ( $action === 'edc_convert_kit_pages' ) {
            $this->handle_convert_kit_pages();
        }

        $edc_action = sanitize_key( $_GET['edc_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- each handler verifies its own nonce
        if ( $edc_action === 'publish' ) {
            $this->handle_publish();
        }
        if ( $edc_action === 'clear_kit' ) {
            $this->handle_clear_kit();
        }
    }

    // ------------------------------------------------------------------
    // File upload / batch import handler
    // ------------------------------------------------------------------

    private function handle_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME );

        $upload = isset( $_FILES['edc_import_file'] ) && is_array( $_FILES['edc_import_file'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? $_FILES['edc_import_file'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : null;

        if ( ! $upload ) {
            wp_die( esc_html__( 'No file was uploaded.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        if ( $upload['error'] !== UPLOAD_ERR_OK ) {
            wp_die( esc_html( $this->upload_error_message( $upload['error'] ) ) );
        }

        if ( ! PremiumManager::is_active() ) {
            $ext = strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) );
            if ( $ext === 'zip' ) {
                wp_die( esc_html__( 'ZIP kit import requires Premium. Please upgrade to convert full Elementor Kit ZIPs. Single-page JSON conversion is free and unlimited.', 'jhmg-converter-for-elementor-to-divi' ) );
            }
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
        $convert_footers = isset( $_POST['edc_convert_footers'] ) && $_POST['edc_convert_footers'] === '1';

        $parser = new ElementorImportParser();

        try {
            $items = $parser->parse( $upload['tmp_name'], $upload['name'] );
        } catch ( \RuntimeException $e ) {
            wp_die( esc_html__( 'Failed to parse import file: ', 'jhmg-converter-for-elementor-to-divi' ) . esc_html( $e->getMessage() ) );
        }

        if ( empty( $items ) ) {
            wp_die( esc_html__( 'No pages found in the import file.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $importer = new BatchImporter();
        $results  = $importer->import( $items, [
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'convert_headers' => $convert_headers,
            'convert_footers' => $convert_footers,
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

    private function handle_publish(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $post_id   = absint( wp_unslash( $_GET['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        check_admin_referer( 'edc_publish_' . $post_id );

        if ( $post_id <= 0 ) {
            wp_die( esc_html__( 'Invalid post ID.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'publish',
        ] );

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

    private function protect_kit_directory( string $dir ): void {
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "deny from all\n" );
        }
        $index = $dir . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }
    }

    private function generate_import_id(): string {
        return function_exists( 'wp_generate_uuid4' )
            ? wp_generate_uuid4()
            : bin2hex( random_bytes( 16 ) );
    }

    private function upload_error_message( int $code ): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was selected.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server is missing a temporary folder.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to server.', 'jhmg-converter-for-elementor-to-divi' ),
            UPLOAD_ERR_EXTENSION  => __( 'Upload stopped by server extension.', 'jhmg-converter-for-elementor-to-divi' ),
        ];

        /* translators: %d is the numeric upload error code */
        return $messages[ $code ] ?? sprintf( __( 'Unknown upload error (code %d).', 'jhmg-converter-for-elementor-to-divi' ), (int) $code );
    }

    // ------------------------------------------------------------------
    // Main view
    // ------------------------------------------------------------------

    private function render_list(): void {
        if ( ! PremiumManager::is_active() ) {
            ?>
            <div class="wrap edc-wrap edc-wrap--landing">
                <h1><?php esc_html_e( 'Elementor to Divi 5 Converter', 'jhmg-converter-for-elementor-to-divi' ); ?></h1>
                <?php $this->render_notice(); ?>
                <?php $this->render_premium_landing(); ?>
            </div>
            <?php
            return;
        }

        $tab = sanitize_key( $_GET['tab'] ?? 'convert' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $tab, [ 'convert', 'global-kit' ], true ) ) {
            $tab = 'convert';
        }
        $base_url = admin_url( 'tools.php?page=' . self::MENU_SLUG );
        ?>
        <div class="wrap edc-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Elementor to Divi 5 Converter', 'jhmg-converter-for-elementor-to-divi' ); ?></h1>

            <?php $this->render_notice(); ?>

            <nav class="nav-tab-wrapper edc-nav-tabs">
                <a href="<?php echo esc_url( $base_url . '&tab=convert' ); ?>"
                   class="nav-tab<?php echo $tab === 'convert' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Convert', 'jhmg-converter-for-elementor-to-divi' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=global-kit' ); ?>"
                   class="nav-tab<?php echo $tab === 'global-kit' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Global Kit', 'jhmg-converter-for-elementor-to-divi' ); ?>
                </a>
            </nav>

            <div class="edc-tab-content">
                <?php if ( $tab === 'global-kit' ) : ?>
                    <?php $this->render_global_kit_section(); ?>
                <?php else : ?>
                    <?php $this->render_import_section(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_premium_landing(): void {
        ?>
        <div class="edc-lp">

            <div class="edc-lp-hero">
                <p class="edc-lp-subtitle"><?php esc_html_e( 'Easily convert Elementor pages to Divi 5 with 1 click. Free for single page imports. Upgrade to Premium for full kit imports, global headers, and global footers.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                <div class="edc-lp-brand-row">
                    <img src="<?php echo esc_url( EDC_PLUGIN_URL . 'assets/NEW-logo-elementor-to-divi.png' ); ?>" alt="<?php esc_attr_e( 'Elementor to Divi 5 Converter', 'jhmg-converter-for-elementor-to-divi' ); ?>" class="edc-lp-brand-logo">
                </div>
            </div>

            <div class="edc-lp-plans">

                <div class="edc-lp-card edc-lp-card--free">
                    <div class="edc-lp-card-top">
                        <span class="edc-lp-badge edc-lp-badge--free"><?php esc_html_e( 'FREE', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                        <h2 class="edc-lp-plan-name"><?php esc_html_e( 'Free Version', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
                        <p class="edc-lp-plan-tagline"><?php esc_html_e( 'Great for single pages and quick migrations.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>

                    <div class="edc-lp-highlight">
                        <?php esc_html_e( 'Convert single Elementor pages to Divi 5 for free. Upload a JSON export and get a fully converted page instantly — no Elementor required on the destination site. Pages only.', 'jhmg-converter-for-elementor-to-divi' ); ?>
                    </div>

                    <ul class="edc-lp-features">
                        <li><span class="edc-lp-check edc-lp-check--green">✓</span><?php esc_html_e( 'Convert single Elementor pages, one at a time', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--green">✓</span><?php esc_html_e( 'Full layout, content, and style preservation', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--green">✓</span><?php esc_html_e( 'Core Elementor widgets and popular addons', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--green">✓</span><?php esc_html_e( 'No Elementor required on the destination site', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                    </ul>

                    <div class="edc-lp-illustration">
                        <div class="edc-lp-file-box edc-lp-file-json"><span>JSON</span></div>
                        <div class="edc-lp-illu-arrow">→</div>
                        <div class="edc-lp-page-preview"><span>Divi</span></div>
                    </div>

                    <button type="button" class="edc-lp-btn-free" id="edc-toggle-import-form">
                        <?php esc_html_e( 'Import Single Page (JSON)', 'jhmg-converter-for-elementor-to-divi' ); ?>
                    </button>

                    <div class="edc-lp-import-panel" id="edc-import-panel" style="display:none;">
                        <form method="post" enctype="multipart/form-data" action="">
                            <?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
                            <input type="hidden" name="action" value="edc_import">
                            <div class="edc-lp-form-grid">
                                <div class="edc-import-field">
                                    <label for="edc_import_file_lp"><strong><?php esc_html_e( 'JSON File', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></label>
                                    <input type="file" id="edc_import_file_lp" name="edc_import_file" accept=".json" required>
                                    <p class="description"><?php esc_html_e( 'Upload an Elementor page JSON export.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                                </div>
                                <div class="edc-import-field">
                                    <label for="edc_post_type_lp"><strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></label>
                                    <select id="edc_post_type_lp" name="edc_post_type">
                                        <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                        <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                    </select>
                                </div>
                                <div class="edc-import-field">
                                    <label for="edc_post_status_lp"><strong><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></label>
                                    <select id="edc_post_status_lp" name="edc_post_status">
                                        <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                        <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="button button-primary" style="margin-top:16px;">
                                <?php esc_html_e( 'Convert Now', 'jhmg-converter-for-elementor-to-divi' ); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="edc-lp-card edc-lp-card--premium">
                    <div class="edc-lp-card-top">
                        <div class="edc-lp-badges-row">
                            <span class="edc-lp-badge edc-lp-badge--pro"><?php esc_html_e( 'PRO', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                            <span class="edc-lp-badge edc-lp-badge--best"><?php esc_html_e( 'BEST VALUE', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                        </div>
                        <h2 class="edc-lp-plan-name"><?php esc_html_e( 'Premium Version', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
                        <p class="edc-lp-plan-tagline"><?php esc_html_e( 'The complete solution for professionals.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>

                    <div class="edc-lp-highlight edc-lp-highlight--premium">
                        <?php esc_html_e( 'Convert entire Elementor sites in one upload. Import your full kit via ZIP, register a header template as your Divi global header, and set a footer template as your Divi global footer — all without touching your live site.', 'jhmg-converter-for-elementor-to-divi' ); ?>
                    </div>

                    <ul class="edc-lp-features">
                        <li><span class="edc-lp-check edc-lp-check--purple">✓</span><?php esc_html_e( 'Bulk import full Elementor kits via ZIP — entire sites in one upload', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--purple">✓</span><?php esc_html_e( 'Set a header template JSON as your Divi Theme Builder global header', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--purple">✓</span><?php esc_html_e( 'Set a footer template JSON as your Divi Theme Builder global footer', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--purple">✓</span><?php esc_html_e( 'Apply global kit colors and typography across all conversions', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                        <li><span class="edc-lp-check edc-lp-check--purple">✓</span><?php esc_html_e( 'Priority support and regular updates', 'jhmg-converter-for-elementor-to-divi' ); ?></li>
                    </ul>

                    <div class="edc-lp-illustration">
                        <div class="edc-lp-file-box edc-lp-file-zip"><span>ZIP</span></div>
                        <div class="edc-lp-illu-arrow">→</div>
                        <div class="edc-lp-site-preview">
                            <div class="edc-lp-site-bar"></div>
                            <div class="edc-lp-site-pages">
                                <div class="edc-lp-site-page"></div>
                                <div class="edc-lp-site-page"></div>
                                <div class="edc-lp-site-page"></div>
                            </div>
                        </div>
                    </div>

                    <div class="edc-lp-upgrade-form">
                        <button type="button" class="edc-lp-btn-premium" id="edc-toggle-kit-form">
                            <?php esc_html_e( 'Import Full Kit or Template — Upgrade to Premium', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </button>

                        <div class="edc-lp-import-panel" id="edc-kit-panel" style="display:none;">
                            <div class="edc-upload-options">

                                <div class="edc-upload-option edc-upload-option--kit">
                                    <div class="edc-upload-option-title">
                                        <strong><?php esc_html_e( 'Full Kit (ZIP)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                                        <p class="description"><?php esc_html_e( 'Import your entire Elementor Export Kit — pages, templates, global styles.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                                    </div>
                                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                                        <input type="hidden" name="action" value="edc_upload_kit">
                                        <input type="hidden" name="edc_upload_type" value="kit">
                                        <input type="file" name="edc_kit_file" accept=".zip" required>
                                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Upload Kit', 'jhmg-converter-for-elementor-to-divi' ); ?></button>
                                    </form>
                                </div>

                                <div class="edc-upload-option edc-upload-option--header">
                                    <div class="edc-upload-option-title">
                                        <strong><?php esc_html_e( 'Header Template (JSON)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                                        <p class="description"><?php esc_html_e( 'Import a single Elementor header template and register it as a Divi Theme Builder global header.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                                    </div>
                                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                                        <input type="hidden" name="action" value="edc_upload_kit">
                                        <input type="hidden" name="edc_upload_type" value="header">
                                        <input type="file" name="edc_kit_file" accept=".json" required>
                                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Set as Global Header', 'jhmg-converter-for-elementor-to-divi' ); ?></button>
                                    </form>
                                </div>

                                <div class="edc-upload-option edc-upload-option--footer">
                                    <div class="edc-upload-option-title">
                                        <strong><?php esc_html_e( 'Footer Template (JSON)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                                        <p class="description"><?php esc_html_e( 'Import a single Elementor footer template and register it as a Divi Theme Builder global footer.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                                    </div>
                                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                                        <input type="hidden" name="action" value="edc_upload_kit">
                                        <input type="hidden" name="edc_upload_type" value="footer">
                                        <input type="file" name="edc_kit_file" accept=".json" required>
                                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Set as Global Footer', 'jhmg-converter-for-elementor-to-divi' ); ?></button>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="edc-lp-comparison">
                <h2 class="edc-lp-section-title"><?php esc_html_e( 'Side by Side', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
                <table class="edc-lp-compare-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Feature', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                            <th><?php esc_html_e( 'Free Version', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                            <th class="edc-lp-col-premium"><?php esc_html_e( 'Premium Version', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Import Method', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></td>
                            <td><?php esc_html_e( 'JSON — one page at a time', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                            <td class="edc-lp-col-premium"><?php esc_html_e( 'ZIP (full kit) + JSON (header & footer templates)', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'What You Can Import', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></td>
                            <td><?php esc_html_e( 'Single pages only', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                            <td class="edc-lp-col-premium"><?php esc_html_e( 'Full kits, global headers, global footers, and all assets', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Speed', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></td>
                            <td><?php esc_html_e( 'Manual, one page at a time', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                            <td class="edc-lp-col-premium"><?php esc_html_e( 'Fast, entire site in one go', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Header & Footer Templates', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></td>
                            <td><?php esc_html_e( '✗ Not available', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                            <td class="edc-lp-col-premium"><?php esc_html_e( '✓ Convert to Divi Theme Builder — ZIP or JSON', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Best For', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></td>
                            <td><?php esc_html_e( 'Testing, Small Projects', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                            <td class="edc-lp-col-premium"><?php esc_html_e( 'Agencies, Freelancers, Large Sites', 'jhmg-converter-for-elementor-to-divi' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="edc-lp-cta-banner">
                <div class="edc-lp-cta-left">
                    <span class="edc-lp-rocket">🚀</span>
                    <div>
                        <h3 class="edc-lp-cta-title"><?php esc_html_e( 'Unlock the Full Power', 'jhmg-converter-for-elementor-to-divi' ); ?></h3>
                        <p class="edc-lp-cta-desc"><?php esc_html_e( 'Import full kits, set global headers and footers, and convert entire Elementor sites to Divi 5 in one go.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>
                </div>
                <div class="edc-lp-cta-right">
                    <form method="post" action="">
                        <?php wp_nonce_field( self::ACTIVATE_NONCE_ACTION, 'edc_activate_nonce' ); ?>
                        <input type="hidden" name="action" value="edc_activate_premium">
                        <button type="submit" class="edc-lp-btn-premium-large">
                            <?php esc_html_e( 'Upgrade to Premium Now →', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function() {
            function wireToggle( btnId, panelId, labelOpen, labelClose, openClass ) {
                var btn   = document.getElementById( btnId );
                var panel = document.getElementById( panelId );
                if ( ! btn || ! panel ) { return; }
                btn.addEventListener('click', function() {
                    var open = panel.style.display !== 'none';
                    panel.style.display = open ? 'none' : 'block';
                    btn.textContent = open ? labelOpen : labelClose;
                    if ( openClass ) {
                        if ( ! open ) { btn.classList.add( openClass ); } else { btn.classList.remove( openClass ); }
                    }
                });
            }

            wireToggle(
                'edc-toggle-import-form',
                'edc-import-panel',
                '<?php echo esc_js( __( 'Import Single Page (JSON)', 'jhmg-converter-for-elementor-to-divi' ) ); ?>',
                '<?php echo esc_js( __( '✕  Close Form', 'jhmg-converter-for-elementor-to-divi' ) ); ?>',
                'edc-lp-btn-free--open'
            );

            wireToggle(
                'edc-toggle-kit-form',
                'edc-kit-panel',
                '<?php echo esc_js( __( 'Import Full Kit or Template — Upgrade to Premium', 'jhmg-converter-for-elementor-to-divi' ) ); ?>',
                '<?php echo esc_js( __( '✕  Close Form', 'jhmg-converter-for-elementor-to-divi' ) ); ?>',
                null
            );

        })();
        </script>
        <?php
    }

    private function render_import_section(): void {
        ?>
        <div class="edc-import-section">
            <h2><?php esc_html_e( 'Import from Elementor JSON / ZIP', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
            <p class="edc-description">
                <?php esc_html_e( 'Upload an Elementor JSON export or a full-site Kit ZIP. No Elementor plugin required — this converts the exported file directly. Pages will be created in this Divi site.', 'jhmg-converter-for-elementor-to-divi' ); ?>
            </p>

            <form method="post" enctype="multipart/form-data" action="" class="edc-import-form">
                <?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
                <input type="hidden" name="action" value="edc_import">

                <div class="edc-import-fields">
                    <div class="edc-import-field">
                        <label for="edc_import_file">
                            <strong><?php esc_html_e( 'File', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        </label>
                        <input type="file" id="edc_import_file" name="edc_import_file" accept=".json,.zip" required>
                        <p class="description"><?php esc_html_e( 'Accepted: .json (single page or template) or .zip (Elementor Kit export)', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                        <?php if ( ! PremiumManager::is_active() ) : ?>
                        <p class="description edc-free-notice"><?php esc_html_e( 'ZIP kit import, header template, and footer template conversion require Premium. Single-page JSON conversion is free and unlimited.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_type">
                            <strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        </label>
                        <select id="edc_post_type" name="edc_post_type">
                            <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                            <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                        </select>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_status">
                            <strong><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        </label>
                        <select id="edc_post_status" name="edc_post_status">
                            <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                        </select>
                    </div>

                </div>

                <div class="edc-import-field edc-import-field--checkbox">
                    <label>
                        <input type="checkbox" name="edc_convert_headers" value="1">
                        <strong><?php esc_html_e( 'Convert header templates as Divi Theme Builder headers', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When checked, Elementor header templates are imported as Divi Theme Builder global headers. Uncheck to import them as regular draft pages instead.', 'jhmg-converter-for-elementor-to-divi' ); ?>
                    </p>
                </div>

                <div class="edc-import-field edc-import-field--checkbox">
                    <label>
                        <input type="checkbox" name="edc_convert_footers" value="1">
                        <strong><?php esc_html_e( 'Convert footer templates as Divi Theme Builder footers', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When checked, Elementor footer templates are imported as Divi Theme Builder global footers. Uncheck to import them as regular draft pages instead.', 'jhmg-converter-for-elementor-to-divi' ); ?>
                    </p>
                </div>

                <div class="edc-import-submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Import and Convert', 'jhmg-converter-for-elementor-to-divi' ); ?>
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
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $import_id === '' ) {
            wp_die( esc_html__( 'No import ID provided.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $results = get_transient( 'edc_batch_' . $import_id );

        if ( ! is_array( $results ) ) {
            wp_die( esc_html__( 'Import results not found or expired. Results are kept for one hour.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $total     = count( $results );
        $succeeded = count( array_filter( $results, fn( $r ) => $r['success'] ) );
        $failed    = $total - $succeeded;
        ?>
        <div class="wrap edc-wrap">
            <h1><?php esc_html_e( 'Batch Import Results', 'jhmg-converter-for-elementor-to-divi' ); ?></h1>

            <div class="edc-result-actions">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="button">
                    &larr; <?php esc_html_e( 'Back to converter', 'jhmg-converter-for-elementor-to-divi' ); ?>
                </a>
            </div>

            <div class="edc-batch-summary">
                <span class="edc-summary-stat edc-summary-stat--total">
                    <?php
                    /* translators: %d is the number of pages processed */
                    printf( esc_html__( '%d page(s) processed', 'jhmg-converter-for-elementor-to-divi' ), absint( $total ) );
                    ?>
                </span>
                <?php if ( $succeeded > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--ok">
                    <?php
                    /* translators: %d is the number of successfully converted pages */
                    printf( esc_html__( '%d converted', 'jhmg-converter-for-elementor-to-divi' ), absint( $succeeded ) );
                    ?>
                </span>
                <?php endif; ?>
                <?php if ( $failed > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--fail">
                    <?php
                    /* translators: %d is the number of pages that failed to convert */
                    printf( esc_html__( '%d failed', 'jhmg-converter-for-elementor-to-divi' ), absint( $failed ) );
                    ?>
                </span>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped edc-batch-table">
                <thead>
                    <tr>
                        <th class="column-title column-primary"><?php esc_html_e( 'Title', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                        <th class="column-status"><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                        <th class="column-issues"><?php esc_html_e( 'Issues', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Actions', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
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
                            <strong><?php echo esc_html( $result['title'] ?: __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ) ); ?></strong>
                        </td>
                        <td class="column-status">
                            <?php if ( $result['success'] ) : ?>
                                <span class="edc-status edc-status--converted">&#10003; <?php esc_html_e( 'Converted', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                            <?php else : ?>
                                <span class="edc-status edc-status--error">&#10007; <?php esc_html_e( 'Failed', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                                <?php if ( ! empty( $result['error'] ) ) : ?>
                                    <br><small class="edc-error-msg"><?php echo esc_html( $result['error'] ); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="column-issues">
                            <?php if ( $result['success'] && $warn_count > 0 ) : ?>
                                <details class="edc-issues-details">
                                    <summary class="edc-issues-summary">
                                        <span class="edc-badge edc-badge--warn"><?php echo (int) $warn_count; ?></span>
                                        <?php esc_html_e( 'issues', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                    </summary>
                                    <ul class="edc-issues-list">
                                        <?php foreach ( $result['report']['warnings'] ?? [] as $warning ) : ?>
                                            <li class="edc-issue edc-issue--warn"><?php echo esc_html( $warning ); ?></li>
                                        <?php endforeach; ?>
                                        <?php foreach ( $result['unsupported'] ?? [] as $item ) : ?>
                                            <li class="edc-issue edc-issue--unsupported">
                                                <?php esc_html_e( 'Unsupported:', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                                <code><?php echo esc_html( $item['widgetType'] ?? $item['elType'] ?? 'unknown' ); ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php foreach ( $result['report']['skipped_settings'] ?? [] as $setting ) : ?>
                                            <li class="edc-issue edc-issue--skipped">
                                                <?php esc_html_e( 'Skipped:', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                                <code><?php echo esc_html( $setting ); ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php elseif ( $result['success'] ) : ?>
                                <span class="edc-status--clean">&#10003; <?php esc_html_e( 'Clean', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <?php if ( $result['success'] && $result['post_id'] > 0 ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $result['post_id'] ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                </a>
                                <a href="<?php echo esc_url( get_permalink( $result['post_id'] ) ); ?>" class="button button-small" target="_blank" rel="noopener">
                                    <?php esc_html_e( 'View', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                </a>
                                <?php if ( get_post_status( $result['post_id'] ) !== 'publish' ) :
                                    $publish_url = wp_nonce_url(
                                        add_query_arg(
                                            [
                                                'page'       => self::MENU_SLUG,
                                                'action'     => 'batch_result',
                                                'import_id'  => $import_id,
                                                'edc_action' => 'publish',
                                                'post_id'    => $result['post_id'],
                                            ],
                                            admin_url( 'tools.php' )
                                        ),
                                        'edc_publish_' . $result['post_id']
                                    );
                                ?>
                                    <a href="<?php echo esc_url( $publish_url ); ?>" class="button button-small button-primary">
                                        <?php esc_html_e( 'Publish', 'jhmg-converter-for-elementor-to-divi' ); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="edc-published-label">&#10003; <?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi' ); ?></span>
                                <?php endif; ?>
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
            <h2><?php esc_html_e( 'Converted Elements', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Type', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                        <th><?php esc_html_e( 'Count', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
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
                <?php esc_html_e( 'Unsupported Elements', 'jhmg-converter-for-elementor-to-divi' ); ?>
                <span class="edc-badge"><?php echo (int) count( $report['unsupported'] ); ?></span>
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
                <?php esc_html_e( 'Warnings', 'jhmg-converter-for-elementor-to-divi' ); ?>
                <span class="edc-badge"><?php echo (int) count( $report['warnings'] ); ?></span>
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
                <?php esc_html_e( 'Skipped Settings', 'jhmg-converter-for-elementor-to-divi' ); ?>
                <span class="edc-badge edc-badge--info"><?php echo (int) count( $report['skipped_settings'] ); ?></span>
            </h2>
            <p class="description"><?php esc_html_e( 'These Elementor settings have no Divi 5 mapping yet and were not converted.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
            <ul>
            <?php foreach ( $report['skipped_settings'] as $setting ) : ?>
                <li><code><?php echo esc_html( $setting ); ?></code></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif;

        if ( empty( $report['unsupported'] ) && empty( $report['warnings'] ) && empty( $report['skipped_settings'] ) ) : ?>
        <div class="edc-card edc-card--success">
            <h2><?php esc_html_e( 'Clean Conversion', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
            <p><?php esc_html_e( 'No warnings, unsupported elements, or skipped settings.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
        </div>
        <?php endif;
    }

    // ------------------------------------------------------------------
    // Global Kit handlers
    // ------------------------------------------------------------------

    private function handle_activate_premium(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }
        check_admin_referer( self::ACTIVATE_NONCE_ACTION, 'edc_activate_nonce' );
        PremiumManager::activate();
        wp_safe_redirect( add_query_arg(
            [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'premium_activated' ],
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    private function handle_upload_kit(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }
        check_admin_referer( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME );

        $upload = isset( $_FILES['edc_kit_file'] ) && is_array( $_FILES['edc_kit_file'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? $_FILES['edc_kit_file'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : null;

        if ( ! $upload || $upload['error'] !== UPLOAD_ERR_OK ) {
            $error = ( $upload && is_array( $upload ) )
                ? $this->upload_error_message( $upload['error'] )
                : __( 'No file was uploaded.', 'jhmg-converter-for-elementor-to-divi' );
            set_transient( 'edc_kit_upload_error_' . get_current_user_id(), $error, 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_error' ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $upload_type = sanitize_key( $_POST['edc_upload_type'] ?? 'kit' );

        if ( $upload_type === 'header' || $upload_type === 'footer' ) {
            $parser = new ElementorImportParser();
            try {
                $items = $parser->parse( $upload['tmp_name'], $upload['name'] );
            } catch ( \RuntimeException $e ) {
                set_transient( 'edc_kit_upload_error_' . get_current_user_id(), $e->getMessage(), 60 );
                wp_safe_redirect( add_query_arg(
                    [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_error' ],
                    admin_url( 'tools.php' )
                ) );
                exit;
            }

            if ( empty( $items ) ) {
                set_transient( 'edc_kit_upload_error_' . get_current_user_id(), __( 'No templates found in the uploaded JSON file.', 'jhmg-converter-for-elementor-to-divi' ), 60 );
                wp_safe_redirect( add_query_arg(
                    [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_error' ],
                    admin_url( 'tools.php' )
                ) );
                exit;
            }

            $importer = new BatchImporter();
            $results  = $importer->import( $items, [
                'post_type'       => 'page',
                'post_status'     => 'draft',
                'convert_headers' => ( $upload_type === 'header' ),
                'convert_footers' => ( $upload_type === 'footer' ),
            ] );

            $import_id = $this->generate_import_id();
            set_transient( 'edc_batch_' . $import_id, $results, HOUR_IN_SECONDS );

            wp_safe_redirect( add_query_arg(
                [
                    'page'      => self::MENU_SLUG,
                    'action'    => 'batch_result',
                    'import_id' => $import_id,
                ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $upload_dir = wp_upload_dir();
        $kit_dir    = $upload_dir['basedir'] . '/edc-kits/';
        wp_mkdir_p( $kit_dir );
        $this->protect_kit_directory( $kit_dir );

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $kit_dir_path        = $kit_dir;
        $kit_dir_filter      = static function ( $dirs ) use ( $kit_dir_path ) {
            $dirs['path']   = untrailingslashit( $kit_dir_path );
            $dirs['url']    = '';
            $dirs['subdir'] = '';
            return $dirs;
        };
        add_filter( 'upload_dir', $kit_dir_filter );
        $upload['name'] = 'kit.zip';
        $moved = wp_handle_upload(
            $upload,
            [
                'test_form'                => false,
                'mimes'                    => [ 'zip' => 'application/zip' ],
                'unique_filename_callback' => static function () { return 'kit.zip'; },
            ]
        );
        remove_filter( 'upload_dir', $kit_dir_filter );

        if ( ! empty( $moved['error'] ) || empty( $moved['file'] ) ) {
            set_transient( 'edc_kit_upload_error_' . get_current_user_id(), __( 'Failed to save the uploaded file. Check directory permissions.', 'jhmg-converter-for-elementor-to-divi' ), 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_error' ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $kit_path = $moved['file'];

        $parser = new KitGlobalsParser();
        try {
            $parsed = $parser->parse( $kit_path );
            $pages  = $parser->extract_pages( $kit_path );
        } catch ( \RuntimeException $e ) {
            if ( file_exists( $kit_path ) ) {
                wp_delete_file( $kit_path );
            }
            set_transient( 'edc_kit_upload_error_' . get_current_user_id(), $e->getMessage(), 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_error' ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $kit_name = $parsed['name'] ?: sanitize_file_name( pathinfo( $upload['name'], PATHINFO_FILENAME ) );
        GlobalsStore::save( $parsed['colors'], $parsed['typography'], $kit_name, $kit_path, $pages );
        PremiumManager::activate();

        wp_safe_redirect( add_query_arg(
            [
                'page'        => self::MENU_SLUG,
                'tab'         => 'global-kit',
                'edc_notice'  => 'kit_loaded',
                'kit_name'    => $kit_name,
                'color_count' => count( $parsed['colors'] ),
            ],
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    private function handle_convert_kit_pages(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        check_admin_referer( self::KIT_CONVERT_NONCE_ACTION, self::KIT_CONVERT_NONCE_NAME );

        if ( ! PremiumManager::is_active() ) {
            wp_die( esc_html__( 'Kit page conversion requires Premium.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $kit = GlobalsStore::load();
        if ( ! $kit ) {
            wp_die( esc_html__( 'No Global Kit loaded.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $zip_path     = $kit['zip_path'] ?? '';
        $stored_pages = $kit['pages']    ?? [];

        if ( $zip_path === '' || ! is_readable( $zip_path ) ) {
            wp_die( esc_html__( 'Kit ZIP file is not available. Please re-upload the kit.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $valid_entries   = array_column( $stored_pages, 'zip_entry' );
        $raw_kit_pages   = isset( $_POST['edc_kit_pages'] ) ? wp_unslash( (array) $_POST['edc_kit_pages'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $selected        = array_filter(
            array_map( 'sanitize_text_field', $raw_kit_pages ),
            fn( $e ) => in_array( $e, $valid_entries, true )
        );

        if ( empty( $selected ) ) {
            wp_die( esc_html__( 'No pages were selected.', 'jhmg-converter-for-elementor-to-divi' ) );
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
        $convert_footers = isset( $_POST['edc_convert_footers'] ) && $_POST['edc_convert_footers'] === '1';

        $zip = new \ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            wp_die( esc_html__( 'Could not open kit ZIP file.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $parser = new ElementorImportParser();
        $items  = [];

        foreach ( $selected as $entry ) {
            $raw = $zip->getFromName( $entry );
            if ( $raw === false ) {
                continue;
            }
            $tmp = tempnam( sys_get_temp_dir(), 'edc_' );
            if ( $tmp === false || file_put_contents( $tmp, $raw ) === false ) {
                continue;
            }
            try {
                $entry_items = $parser->parse( $tmp, basename( $entry ) );
                $items       = array_merge( $items, $entry_items );
            } catch ( \RuntimeException $e ) {
                // skip unreadable entries
            } finally {
                if ( $tmp !== false && file_exists( $tmp ) ) {
                    wp_delete_file( $tmp );
                }
            }
        }

        $zip->close();

        if ( empty( $items ) ) {
            wp_die( esc_html__( 'No convertible pages were found in the selected entries.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $importer = new BatchImporter();
        $results  = $importer->import( $items, [
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'convert_headers' => $convert_headers,
            'convert_footers' => $convert_footers,
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

    private function handle_clear_kit(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi' ) );
        }
        check_admin_referer( 'edc_clear_kit' );
        GlobalsStore::clear();
        wp_safe_redirect( add_query_arg(
            [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_notice' => 'kit_cleared' ],
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    // ------------------------------------------------------------------
    // Global Kit views
    // ------------------------------------------------------------------

    private function render_notice(): void {
        $notice = sanitize_key( $_GET['edc_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display parameter
        if ( ! $notice ) {
            return;
        }
        switch ( $notice ) {
            case 'kit_loaded':
                $name  = sanitize_text_field( wp_unslash( $_GET['kit_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $count = absint( wp_unslash( $_GET['color_count'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html( sprintf(
                        /* translators: 1: kit name, 2: number of colors */
                        __( 'Global Kit "%1$s" loaded — %2$d colors imported.', 'jhmg-converter-for-elementor-to-divi' ),
                        $name,
                        $count
                    ) )
                );
                break;
            case 'kit_cleared':
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Global Kit removed.', 'jhmg-converter-for-elementor-to-divi' ) . '</p></div>';
                break;
            case 'premium_activated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Premium Preview activated — you can now upload a Global Kit.', 'jhmg-converter-for-elementor-to-divi' ) . '</p></div>';
                break;
            case 'kit_error':
                $error = get_transient( 'edc_kit_upload_error_' . get_current_user_id() );
                if ( $error ) {
                    delete_transient( 'edc_kit_upload_error_' . get_current_user_id() );
                    printf(
                        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                        esc_html( $error )
                    );
                }
                break;
        }
    }

    private function render_global_kit_section(): void {
        if ( ! PremiumManager::is_active() ) {
            $this->render_premium_upsell();
            return;
        }

        $kit = GlobalsStore::load();

        if ( $kit ) {
            if ( empty( $kit['pages'] ) && ! empty( $kit['zip_path'] ) && is_readable( $kit['zip_path'] ) ) {
                $parser = new KitGlobalsParser();
                try {
                    $pages = $parser->extract_pages( $kit['zip_path'] );
                    if ( ! empty( $pages ) ) {
                        GlobalsStore::save(
                            $kit['colors']     ?? [],
                            $kit['typography'] ?? [],
                            $kit['loaded_from'] ?? '',
                            $kit['zip_path'],
                            $pages
                        );
                        $kit['pages'] = $pages;
                    }
                } catch ( \RuntimeException $e ) {
                    // zip unreadable — show kit status without pages
                }
            }
            $this->render_kit_status( $kit );
        }

        $this->render_kit_upload_form();
    }

    private function render_premium_upsell(): void {
        ?>
        <div class="edc-premium-panel">
            <div class="edc-premium-badge"><?php esc_html_e( 'Premium', 'jhmg-converter-for-elementor-to-divi' ); ?></div>
            <h2><?php esc_html_e( 'Global Kit, Header & Footer Templates', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
            <p><?php esc_html_e( 'Upload your Elementor Export Kit ZIP to extract global colors and typography. Or upload a single header or footer template JSON to register it directly in the Divi Theme Builder as a global header or footer.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
            <p><strong><?php esc_html_e( 'Free Preview — activate now at no cost.', 'jhmg-converter-for-elementor-to-divi' ); ?></strong></p>
            <form method="post" action="">
                <?php wp_nonce_field( self::ACTIVATE_NONCE_ACTION, 'edc_activate_nonce' ); ?>
                <input type="hidden" name="action" value="edc_activate_premium">
                <button type="submit" class="button button-primary button-large">
                    <?php esc_html_e( 'Activate Premium Preview (Free)', 'jhmg-converter-for-elementor-to-divi' ); ?>
                </button>
            </form>
        </div>
        <?php
    }

    private function render_kit_status( array $kit ): void {
        $meta      = GlobalsStore::get_meta();
        $colors    = $kit['colors']    ?? [];
        $typo      = $kit['typography'] ?? [];
        $kit_name  = $meta['loaded_from'] ?? '';
        $date      = ! empty( $meta['loaded_at'] )
            ? date_i18n( get_option( 'date_format' ), $meta['loaded_at'] )
            : '';
        $clear_url = wp_nonce_url(
            add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'global-kit', 'edc_action' => 'clear_kit' ],
                admin_url( 'tools.php' )
            ),
            'edc_clear_kit'
        );
        ?>
        <div class="edc-kit-status">
            <div class="edc-kit-status-header">
                <div>
                    <h2>
                        <?php esc_html_e( 'Active Kit', 'jhmg-converter-for-elementor-to-divi' ); ?>:
                        <span class="edc-kit-name"><?php echo esc_html( $kit_name ); ?></span>
                    </h2>
                    <?php if ( $date ) : ?>
                        <p class="edc-kit-date"><?php
                        /* translators: %s is the date the kit was loaded */
                        printf( esc_html__( 'Loaded on %s', 'jhmg-converter-for-elementor-to-divi' ), esc_html( $date ) ); ?></p>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url( $clear_url ); ?>" class="button edc-btn-remove-kit"
                   onclick="return confirm('<?php esc_attr_e( 'Remove the active Global Kit?', 'jhmg-converter-for-elementor-to-divi' ); ?>')">
                    <?php esc_html_e( 'Remove Kit', 'jhmg-converter-for-elementor-to-divi' ); ?>
                </a>
            </div>

            <?php if ( ! empty( $colors ) ) : ?>
            <div class="edc-kit-section">
                <h3><?php
                /* translators: %d is the number of colors in the kit */
                printf( esc_html__( 'Colors (%d)', 'jhmg-converter-for-elementor-to-divi' ), absint( count( $colors ) ) ); ?></h3>
                <div class="edc-swatches">
                    <?php foreach ( $colors as $id => $hex ) : ?>
                    <div class="edc-swatch">
                        <span class="edc-swatch-color" style="background-color:<?php echo esc_attr( $hex ); ?>;"></span>
                        <span class="edc-swatch-label"><?php echo esc_html( $id ); ?></span>
                        <code class="edc-swatch-hex"><?php echo esc_html( $hex ); ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $typo ) ) : ?>
            <div class="edc-kit-section">
                <h3><?php
                /* translators: %d is the number of typography styles in the kit */
                printf( esc_html__( 'Typography (%d)', 'jhmg-converter-for-elementor-to-divi' ), absint( count( $typo ) ) ); ?></h3>
                <ul class="edc-typo-list">
                    <?php foreach ( $typo as $id => $props ) : ?>
                    <li class="edc-typo-item">
                        <strong><?php echo esc_html( $id ); ?></strong> &mdash;
                        <?php
                        $raw_parts = [];
                        if ( ! empty( $props['family'] ) ) {
                            $raw_parts[] = $props['family'];
                        }
                        if ( ! empty( $props['weight'] ) ) {
                            $raw_parts[] = $props['weight'];
                        }
                        if ( ! empty( $props['size'] ) ) {
                            $raw_parts[] = $props['size'];
                        }
                        echo esc_html( implode( ', ', $raw_parts ) );
                        ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php
            $kit_pages = $kit['pages'] ?? [];
            if ( ! empty( $kit_pages ) ) : ?>
            <div class="edc-kit-section">
                <h3><?php
                /* translators: %d is the number of pages in the kit */
                printf( esc_html__( 'Pages in Kit (%d)', 'jhmg-converter-for-elementor-to-divi' ), absint( count( $kit_pages ) ) ); ?></h3>
                <form method="post" action="" class="edc-kit-pages-form">
                    <?php wp_nonce_field( self::KIT_CONVERT_NONCE_ACTION, self::KIT_CONVERT_NONCE_NAME ); ?>
                    <input type="hidden" name="action" value="edc_convert_kit_pages">

                    <div class="edc-kit-pages-controls">
                        <label class="edc-select-all-label">
                            <input type="checkbox" id="edc-select-all-pages">
                            <?php esc_html_e( 'Select all / Deselect all', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </label>
                    </div>

                    <table class="wp-list-table widefat striped edc-pages-table">
                        <thead>
                            <tr>
                                <th class="check-column"></th>
                                <th><?php esc_html_e( 'Title', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                                <th class="edc-type-col"><?php esc_html_e( 'Type', 'jhmg-converter-for-elementor-to-divi' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $kit_pages as $kit_page ) : ?>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" name="edc_kit_pages[]" value="<?php echo esc_attr( $kit_page['zip_entry'] ); ?>" class="edc-page-checkbox" checked>
                                </td>
                                <td><?php echo esc_html( $kit_page['title'] ); ?></td>
                                <td>
                                    <span class="edc-type-badge edc-type-badge--<?php echo esc_attr( $kit_page['type'] ); ?>">
                                        <?php echo esc_html( ucfirst( $kit_page['type'] ) ); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="edc-kit-convert-options">
                        <div class="edc-import-fields">
                            <div class="edc-import-field">
                                <label for="edc_kit_post_type">
                                    <strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                                </label>
                                <select id="edc_kit_post_type" name="edc_post_type">
                                    <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                    <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                </select>
                            </div>
                            <div class="edc-import-field">
                                <label for="edc_kit_post_status">
                                    <strong><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                                </label>
                                <select id="edc_kit_post_status" name="edc_post_status">
                                    <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                    <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="edc-import-field edc-import-field--checkbox">
                            <label>
                                <input type="checkbox" name="edc_convert_headers" value="1">
                                <strong><?php esc_html_e( 'Convert header templates as Divi Theme Builder headers', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                            </label>
                        </div>

                        <div class="edc-import-field edc-import-field--checkbox">
                            <label>
                                <input type="checkbox" name="edc_convert_footers" value="1">
                                <strong><?php esc_html_e( 'Convert footer templates as Divi Theme Builder footers', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                            </label>
                        </div>

                        <div class="edc-import-submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Convert Selected', 'jhmg-converter-for-elementor-to-divi' ); ?>
                            </button>
                        </div>
                    </div>
                </form>
                <script>
                (function() {
                    var selectAll = document.getElementById('edc-select-all-pages');
                    if ( ! selectAll ) { return; }
                    selectAll.checked = true;
                    selectAll.addEventListener('change', function() {
                        document.querySelectorAll('.edc-page-checkbox').forEach(function(cb) {
                            cb.checked = selectAll.checked;
                        });
                    });
                })();
                </script>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_kit_upload_form(): void {
        $replacing = GlobalsStore::has_kit();
        ?>
        <div class="edc-kit-upload-section<?php echo $replacing ? ' edc-kit-upload-replace' : ''; ?>">
            <?php if ( $replacing ) : ?>
                <h3><?php esc_html_e( 'Replace Kit or Import Template', 'jhmg-converter-for-elementor-to-divi' ); ?></h3>
            <?php else : ?>
                <h2><?php esc_html_e( 'Upload Elementor Export Kit or Template', 'jhmg-converter-for-elementor-to-divi' ); ?></h2>
                <p class="edc-description"><?php esc_html_e( 'Upload your full Elementor Export Kit to extract global styles and pages, or import a single header/footer template JSON directly into Divi Theme Builder.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
            <?php endif; ?>

            <div class="edc-upload-options">

                <div class="edc-upload-option edc-upload-option--kit">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Full Kit (ZIP)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import your entire Elementor Export Kit — pages, templates, global colors and typography.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="kit">
                        <input type="file" name="edc_kit_file" accept=".zip" required>
                        <button type="submit" class="button button-primary">
                            <?php echo $replacing
                                ? esc_html__( 'Replace Kit', 'jhmg-converter-for-elementor-to-divi' )
                                : esc_html__( 'Upload Kit', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </button>
                    </form>
                </div>

                <div class="edc-upload-option edc-upload-option--header">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Header Template (JSON)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import a single Elementor header template and register it as a Divi Theme Builder global header.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="header">
                        <input type="file" name="edc_kit_file" accept=".json" required>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Set as Global Header', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </button>
                    </form>
                </div>

                <div class="edc-upload-option edc-upload-option--footer">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Footer Template (JSON)', 'jhmg-converter-for-elementor-to-divi' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import a single Elementor footer template and register it as a Divi Theme Builder global footer.', 'jhmg-converter-for-elementor-to-divi' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="footer">
                        <input type="file" name="edc_kit_file" accept=".json" required>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Set as Global Footer', 'jhmg-converter-for-elementor-to-divi' ); ?>
                        </button>
                    </form>
                </div>

            </div>
        </div>
        <?php
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
.edc-batch-table .column-status  { width: 140px; }
.edc-batch-table .column-issues  { width: 230px; }
.edc-batch-table .column-actions { width: 210px; }

/* Issues expand/collapse */
.edc-issues-details summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 5px; }
.edc-issues-details summary::-webkit-details-marker { display: none; }
.edc-issues-summary::before { content: "▶"; font-size: 9px; color: #888; transition: transform .15s; display: inline-block; }
.edc-issues-details[open] .edc-issues-summary::before { transform: rotate(90deg); }
.edc-issues-list { margin: 8px 0 0; padding-left: 14px; font-size: 12px; }
.edc-issues-list li { margin-bottom: 4px; line-height: 1.4; }
.edc-issue--warn { color: #7a4f00; }
.edc-issue--unsupported { color: #c62828; }
.edc-issue--skipped { color: #555; }

/* Publish action */
.edc-published-label { color: #2e7d32; font-size: 12px; font-weight: 600; }

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

/* Tabs */
.edc-nav-tabs { margin: 16px 0 0; }
.edc-tab-content { margin-top: 0; }

/* Premium upsell panel */
.edc-premium-panel { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #f0b429; border-radius: 3px; padding: 24px 28px; margin: 20px 0; max-width: 640px; }
.edc-premium-panel h2 { margin: 8px 0 10px; font-size: 16px; }
.edc-premium-panel p { margin: 0 0 14px; color: #444; }
.edc-premium-panel form { margin-top: 18px; }
.edc-premium-badge { display: inline-block; background: #f0b429; color: #7a4f00; border-radius: 3px; padding: 2px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }

/* Kit status card */
.edc-kit-status { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #2e7d32; border-radius: 3px; padding: 20px 24px; margin: 20px 0; }
.edc-kit-status-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.edc-kit-status-header h2 { margin: 0 0 4px; font-size: 15px; }
.edc-kit-name { color: #2271b1; }
.edc-kit-date { margin: 0; color: #757575; font-size: 12px; }
.edc-btn-remove-kit { color: #c62828 !important; border-color: #c62828 !important; flex-shrink: 0; }
.edc-btn-remove-kit:hover { background: #fde8e8 !important; }

/* Kit sections */
.edc-kit-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f1; }
.edc-kit-section h3 { margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .04em; }

/* Color swatches */
.edc-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
.edc-swatch { display: flex; align-items: center; gap: 6px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 3px; padding: 4px 8px; font-size: 12px; }
.edc-swatch-color { display: inline-block; width: 22px; height: 22px; border-radius: 3px; border: 1px solid rgba(0,0,0,.12); flex-shrink: 0; }
.edc-swatch-label { color: #333; font-weight: 500; }
.edc-swatch-hex { color: #757575; font-size: 11px; background: transparent; border: none; padding: 0; }

/* Typography list */
.edc-typo-list { margin: 0; padding: 0; list-style: none; }
.edc-typo-item { padding: 4px 0; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
.edc-typo-item:last-child { border-bottom: none; }

/* Kit upload section */
.edc-kit-upload-section { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #2271b1; border-radius: 3px; padding: 20px 24px; margin: 20px 0; }
.edc-kit-upload-section h2 { margin: 0 0 6px; font-size: 15px; }
.edc-kit-upload-section h3 { margin: 0 0 12px; font-size: 14px; }
.edc-kit-upload-replace { border-left-color: #888; }

/* Three-option upload layout */
.edc-upload-options { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.edc-upload-option { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #cbd5e1; border-radius: 6px; padding: 12px 14px; }
.edc-upload-option--kit    { border-left-color: #2271b1; }
.edc-upload-option--header { border-left-color: #7c3aed; }
.edc-upload-option--footer { border-left-color: #e44d26; }
.edc-upload-option-title { margin-bottom: 8px; }
.edc-upload-option-title strong { display: block; font-size: 13px; margin-bottom: 2px; }
.edc-upload-option-title .description { margin: 0; font-size: 11px; color: #666; }
.edc-upload-option-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.edc-upload-option-form input[type="file"] { flex: 1; min-width: 160px; padding: 3px 0; font-size: 12px; }

/* Landing page upload options (compact) */
.edc-lp-import-panel .edc-upload-options { gap: 8px; }
.edc-lp-import-panel .edc-upload-option { padding: 10px 12px; }
.edc-lp-import-panel .edc-upload-option-form { gap: 6px; }
.edc-lp-import-panel .edc-upload-option-form input[type="file"] { font-size: 11px; }

/* Kit pages table */
.edc-kit-pages-controls { margin-bottom: 8px; }
.edc-select-all-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; }
.edc-pages-table { margin: 0 0 0; }
.edc-pages-table .check-column { width: 30px; }
.edc-type-col { width: 80px; }
.edc-type-badge { display: inline-block; border-radius: 3px; padding: 1px 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.edc-type-badge--page { background: #e8f0fe; color: #1a56db; }
.edc-type-badge--post { background: #fef3c7; color: #92400e; }
.edc-kit-convert-options { margin-top: 16px; }

/* Free tier notice */
.edc-free-notice { color: #946f00; }

/* ================================================================
   PREMIUM LANDING PAGE
   ================================================================ */

.edc-wrap--landing { max-width: 1400px; }
.edc-wrap--landing h1 { font-size: 28px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }

/* Hero row */
.edc-lp { margin-top: 4px; }
.edc-lp-hero { display: flex; justify-content: space-between; align-items: center; margin: 0 0 28px; flex-wrap: wrap; gap: 16px; }
.edc-lp-subtitle { margin: 0; color: #1e293b; font-size: 19px; font-weight: 700; line-height: 1.5; }
.edc-lp-brand-row { display: flex; align-items: center; flex-shrink: 0; }
.edc-lp-brand-logo { height: 48px; width: auto; display: block; }

/* Plans grid */
.edc-lp-plans { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px; align-items: stretch; }

/* Plan card base */
.edc-lp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.06); display: flex; flex-direction: column; gap: 20px; }
.edc-lp-card--free { border-top: 4px solid #22c55e; }
.edc-lp-card--premium { border-top: 4px solid #7c3aed; box-shadow: 0 4px 28px rgba(124,58,237,.14); }

/* Badges */
.edc-lp-badge { display: inline-block; width: fit-content; align-self: flex-start; border-radius: 99px; padding: 2px 10px; font-size: 10px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
.edc-lp-badge--free { background: #dcfce7; color: #15803d; }
.edc-lp-badge--pro  { background: #ede9fe; color: #6d28d9; }
.edc-lp-badge--best { background: #fef3c7; color: #92400e; }
.edc-lp-badges-row  { display: flex; gap: 6px; flex-wrap: wrap; }

/* Card top */
.edc-lp-card-top  { display: flex; flex-direction: column; gap: 5px; }
.edc-lp-plan-name { margin: 0; font-size: 20px; font-weight: 700; color: #1e293b; }
.edc-lp-plan-tagline { margin: 0; color: #64748b; font-size: 13px; }

/* Highlight box */
.edc-lp-highlight { background: #f0fdf4; border-left: 3px solid #22c55e; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #166534; line-height: 1.6; }
.edc-lp-highlight--premium { background: #f5f3ff; border-left-color: #7c3aed; color: #4c1d95; }

/* Feature list */
.edc-lp-features { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.edc-lp-features li { display: flex; align-items: flex-start; gap: 9px; font-size: 13px; color: #334155; line-height: 1.5; }
.edc-lp-check { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; font-size: 10px; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
.edc-lp-check--green  { background: #22c55e; color: #fff; }
.edc-lp-check--purple { background: #7c3aed; color: #fff; }

/* Illustrations */
.edc-lp-illustration { display: flex; align-items: center; gap: 14px; }
.edc-lp-file-box { display: flex; align-items: center; justify-content: center; width: 58px; height: 66px; border-radius: 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em; border: 2px solid; flex-shrink: 0; }
.edc-lp-file-json { border-color: #22c55e; background: #f0fdf4; color: #15803d; }
.edc-lp-file-zip  { border-color: #7c3aed; background: #f5f3ff; color: #6d28d9; }
.edc-lp-illu-arrow { font-size: 20px; color: #cbd5e1; flex-shrink: 0; }
.edc-lp-page-preview { width: 66px; height: 66px; border-radius: 8px; background: #f8fafc; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #7c3aed; }
.edc-lp-site-preview { width: 80px; height: 66px; border-radius: 8px; background: #f5f3ff; border: 2px solid #7c3aed; overflow: hidden; display: flex; flex-direction: column; }
.edc-lp-site-bar   { height: 11px; background: #7c3aed; }
.edc-lp-site-pages { flex: 1; display: flex; flex-direction: column; gap: 4px; padding: 6px; }
.edc-lp-site-page  { flex: 1; background: #ddd8fe; border-radius: 2px; }

/* Buttons */
.edc-lp-btn-free { display: block; width: 100%; padding: 11px 20px; background: #22c55e; border: none; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; transition: background .15s; }
.edc-lp-btn-free:hover { background: #16a34a; }
.edc-lp-btn-free--open { background: #64748b; }
.edc-lp-btn-free--open:hover { background: #475569; }

.edc-lp-upgrade-form { margin-top: auto; }
.edc-lp-btn-premium { display: block; width: 100%; padding: 13px 24px; background: linear-gradient(135deg, #7c3aed, #6d28d9); border: none; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; box-shadow: 0 4px 14px rgba(124,58,237,.35); transition: box-shadow .15s, background .15s; }
.edc-lp-btn-premium:hover { background: linear-gradient(135deg, #6d28d9, #5b21b6); box-shadow: 0 6px 20px rgba(124,58,237,.45); }

/* Inline import form panel */
.edc-lp-import-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; }
.edc-lp-form-grid { display: flex; flex-wrap: wrap; gap: 16px; }

/* Comparison table */
.edc-lp-comparison { margin-bottom: 40px; }
.edc-lp-section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 16px; }
.edc-lp-compare-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.05); }
.edc-lp-compare-table thead tr { background: #f8fafc; }
.edc-lp-compare-table th { padding: 13px 18px; text-align: left; font-size: 13px; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
.edc-lp-compare-table td { padding: 13px 18px; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9; }
.edc-lp-compare-table tbody tr:last-child td { border-bottom: none; }
.edc-lp-compare-table tbody tr:nth-child(even) { background: #fafafa; }
.edc-lp-col-premium { color: #6d28d9 !important; font-weight: 600; }
.edc-lp-compare-table th.edc-lp-col-premium { background: #f5f3ff; }

/* CTA banner */
.edc-lp-cta-banner { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border-radius: 16px; padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; margin-bottom: 24px; }
.edc-lp-cta-left  { display: flex; align-items: center; gap: 20px; }
.edc-lp-rocket    { font-size: 42px; flex-shrink: 0; line-height: 1; }
.edc-lp-cta-title { margin: 0 0 6px; font-size: 22px; font-weight: 700; color: #fff; }
.edc-lp-cta-desc  { margin: 0; color: #c7d2fe; font-size: 14px; line-height: 1.5; }
.edc-lp-cta-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.edc-lp-btn-premium-large { display: block; padding: 14px 28px; background: linear-gradient(135deg, #7c3aed, #6d28d9); border: none; border-radius: 8px; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; white-space: nowrap; box-shadow: 0 4px 20px rgba(0,0,0,.3); transition: box-shadow .15s; }
.edc-lp-btn-premium-large:hover { box-shadow: 0 6px 28px rgba(0,0,0,.4); }
.edc-lp-trust-badge { margin: 0; color: #a5b4fc; font-size: 12px; }
        ';
    }
}
