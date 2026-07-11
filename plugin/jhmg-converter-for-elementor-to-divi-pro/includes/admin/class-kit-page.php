<?php

namespace ElementorDivi5Converter\Pro\Admin;

use ElementorDivi5Converter\Pro\Kit\GlobalsStore;
use ElementorDivi5Converter\Pro\Kit\KitGlobalsParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro Tools page: Elementor kit ZIP import, global colors/typography, and
 * header/footer template conversion into the Divi Theme Builder.
 *
 * These are the premium features ported from the free plugin's AdminPage.
 * Pro's presence is the gate — there is no separate activation/license check
 * here (soft enforcement: Pro installed + active == features on).
 */
class KitPage {

    const MENU_SLUG                = 'edcp-kit';
    const IMPORT_NONCE_NAME        = 'edc_import_nonce';
    const IMPORT_NONCE_ACTION      = 'edc_import';
    const KIT_NONCE_NAME           = 'edc_kit_nonce';
    const KIT_NONCE_ACTION         = 'edc_upload_kit';
    const KIT_CONVERT_NONCE_NAME   = 'edc_kit_convert_nonce';
    const KIT_CONVERT_NONCE_ACTION = 'edc_convert_kit_pages';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
    }

    // ------------------------------------------------------------------
    // Menu
    // ------------------------------------------------------------------

    public function register_menu(): void {
        add_management_page(
            __( 'Elementor to Divi 5 Pro', 'jhmg-converter-for-elementor-to-divi-pro' ),
            __( 'Elementor → Divi 5 Pro', 'jhmg-converter-for-elementor-to-divi-pro' ),
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $action === 'batch_result' ) {
            $this->render_batch_result();
            return;
        }

        $tab = sanitize_key( $_GET['tab'] ?? 'kit' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $tab, [ 'kit', 'convert' ], true ) ) {
            $tab = 'kit';
        }
        $base_url = admin_url( 'tools.php?page=' . self::MENU_SLUG );
        ?>
        <div class="wrap edcp-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Elementor to Divi 5 — Pro', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h1>

            <?php $this->render_notice(); ?>

            <nav class="nav-tab-wrapper edcp-nav-tabs">
                <a href="<?php echo esc_url( $base_url . '&tab=kit' ); ?>"
                   class="nav-tab<?php echo $tab === 'kit' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Global Kit', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=convert' ); ?>"
                   class="nav-tab<?php echo $tab === 'convert' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Convert', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                </a>
            </nav>

            <div class="edcp-tab-content">
                <?php if ( $tab === 'convert' ) : ?>
                    <?php $this->render_convert_section(); ?>
                <?php else : ?>
                    <?php $this->render_global_kit_section(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // POST dispatcher
    // ------------------------------------------------------------------

    public function handle_post(): void {
        // Scope to this page's own submissions — other admin pages (including
        // the free plugin's) also hook admin_init and should not be double-processed.
        $page = sanitize_key( $_GET['page'] ?? ( $_POST['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing check, each handler verifies its own nonce
        if ( $page !== self::MENU_SLUG ) {
            return;
        }

        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- each handler verifies its own nonce
        if ( $action === 'edc_import' ) {
            $this->handle_import();
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
    // Convert tab: JSON/ZIP import handler (ported from the free plugin's
    // premium-gated handle_import(); the ZIP branch was gated behind
    // PremiumManager::is_active() there — Pro has no such gate).
    // ------------------------------------------------------------------

    private function handle_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME );

        $upload = isset( $_FILES['edc_import_file'] ) && is_array( $_FILES['edc_import_file'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? $_FILES['edc_import_file'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : null;

        if ( ! $upload ) {
            wp_die( esc_html__( 'No file was uploaded.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
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
        $convert_footers = isset( $_POST['edc_convert_footers'] ) && $_POST['edc_convert_footers'] === '1';

        $parser = new \ElementorDivi5Converter\Parsers\ElementorImportParser();

        try {
            $items = $parser->parse( $upload['tmp_name'], $upload['name'] );
        } catch ( \RuntimeException $e ) {
            wp_die( esc_html__( 'Failed to parse import file: ', 'jhmg-converter-for-elementor-to-divi-pro' ) . esc_html( $e->getMessage() ) );
        }

        if ( empty( $items ) ) {
            wp_die( esc_html__( 'No pages found in the import file.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $importer = new \ElementorDivi5Converter\Admin\BatchImporter();
        $results  = $importer->import( $items, [
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'convert_headers' => $convert_headers,
            'convert_footers' => $convert_footers,
        ] );

        $import_id = $this->generate_import_id();
        set_transient( 'edcp_batch_' . $import_id, $results, HOUR_IN_SECONDS );

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
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $post_id   = absint( wp_unslash( $_GET['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        check_admin_referer( 'edc_publish_' . $post_id );

        if ( $post_id <= 0 ) {
            wp_die( esc_html__( 'Invalid post ID.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
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

    // ------------------------------------------------------------------
    // Global Kit handlers
    // ------------------------------------------------------------------

    private function handle_upload_kit(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }
        check_admin_referer( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME );

        $upload = isset( $_FILES['edc_kit_file'] ) && is_array( $_FILES['edc_kit_file'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? $_FILES['edc_kit_file'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : null;

        if ( ! $upload || $upload['error'] !== UPLOAD_ERR_OK ) {
            $error = ( $upload && is_array( $upload ) )
                ? $this->upload_error_message( $upload['error'] )
                : __( 'No file was uploaded.', 'jhmg-converter-for-elementor-to-divi-pro' );
            set_transient( 'edcp_kit_upload_error_' . get_current_user_id(), $error, 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_error' ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $upload_type = sanitize_key( $_POST['edc_upload_type'] ?? 'kit' );

        if ( $upload_type === 'header' || $upload_type === 'footer' ) {
            $parser = new \ElementorDivi5Converter\Parsers\ElementorImportParser();
            try {
                $items = $parser->parse( $upload['tmp_name'], $upload['name'] );
            } catch ( \RuntimeException $e ) {
                set_transient( 'edcp_kit_upload_error_' . get_current_user_id(), $e->getMessage(), 60 );
                wp_safe_redirect( add_query_arg(
                    [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_error' ],
                    admin_url( 'tools.php' )
                ) );
                exit;
            }

            if ( empty( $items ) ) {
                set_transient( 'edcp_kit_upload_error_' . get_current_user_id(), __( 'No templates found in the uploaded JSON file.', 'jhmg-converter-for-elementor-to-divi-pro' ), 60 );
                wp_safe_redirect( add_query_arg(
                    [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_error' ],
                    admin_url( 'tools.php' )
                ) );
                exit;
            }

            $importer = new \ElementorDivi5Converter\Admin\BatchImporter();
            $results  = $importer->import( $items, [
                'post_type'       => 'page',
                'post_status'     => 'draft',
                'convert_headers' => ( $upload_type === 'header' ),
                'convert_footers' => ( $upload_type === 'footer' ),
            ] );

            $import_id = $this->generate_import_id();
            set_transient( 'edcp_batch_' . $import_id, $results, HOUR_IN_SECONDS );

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
            set_transient( 'edcp_kit_upload_error_' . get_current_user_id(), __( 'Failed to save the uploaded file. Check directory permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ), 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_error' ],
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
            set_transient( 'edcp_kit_upload_error_' . get_current_user_id(), $e->getMessage(), 60 );
            wp_safe_redirect( add_query_arg(
                [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_error' ],
                admin_url( 'tools.php' )
            ) );
            exit;
        }

        $kit_name = $parsed['name'] ?: sanitize_file_name( pathinfo( $upload['name'], PATHINFO_FILENAME ) );
        GlobalsStore::save( $parsed['colors'], $parsed['typography'], $kit_name, $kit_path, $pages );

        wp_safe_redirect( add_query_arg(
            [
                'page'        => self::MENU_SLUG,
                'tab'         => 'kit',
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
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        check_admin_referer( self::KIT_CONVERT_NONCE_ACTION, self::KIT_CONVERT_NONCE_NAME );

        $kit = GlobalsStore::load();
        if ( ! $kit ) {
            wp_die( esc_html__( 'No Global Kit loaded.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $zip_path     = $kit['zip_path'] ?? '';
        $stored_pages = $kit['pages']    ?? [];

        if ( $zip_path === '' || ! is_readable( $zip_path ) ) {
            wp_die( esc_html__( 'Kit ZIP file is not available. Please re-upload the kit.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $valid_entries   = array_column( $stored_pages, 'zip_entry' );
        $raw_kit_pages   = isset( $_POST['edc_kit_pages'] ) ? wp_unslash( (array) $_POST['edc_kit_pages'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $selected        = array_filter(
            array_map( 'sanitize_text_field', $raw_kit_pages ),
            fn( $e ) => in_array( $e, $valid_entries, true )
        );

        if ( empty( $selected ) ) {
            wp_die( esc_html__( 'No pages were selected.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
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
            wp_die( esc_html__( 'Could not open kit ZIP file.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $parser = new \ElementorDivi5Converter\Parsers\ElementorImportParser();
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
            wp_die( esc_html__( 'No convertible pages were found in the selected entries.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $importer = new \ElementorDivi5Converter\Admin\BatchImporter();
        $results  = $importer->import( $items, [
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'convert_headers' => $convert_headers,
            'convert_footers' => $convert_footers,
        ] );

        $import_id = $this->generate_import_id();
        set_transient( 'edcp_batch_' . $import_id, $results, HOUR_IN_SECONDS );

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
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }
        check_admin_referer( 'edc_clear_kit' );
        GlobalsStore::clear();
        wp_safe_redirect( add_query_arg(
            [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_notice' => 'kit_cleared' ],
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

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
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was selected.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server is missing a temporary folder.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to server.', 'jhmg-converter-for-elementor-to-divi-pro' ),
            UPLOAD_ERR_EXTENSION  => __( 'Upload stopped by server extension.', 'jhmg-converter-for-elementor-to-divi-pro' ),
        ];

        /* translators: %d is the numeric upload error code */
        return $messages[ $code ] ?? sprintf( __( 'Unknown upload error (code %d).', 'jhmg-converter-for-elementor-to-divi-pro' ), (int) $code );
    }

    // ------------------------------------------------------------------
    // Views
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
                        __( 'Global Kit "%1$s" loaded — %2$d colors imported.', 'jhmg-converter-for-elementor-to-divi-pro' ),
                        $name,
                        $count
                    ) )
                );
                break;
            case 'kit_cleared':
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Global Kit removed.', 'jhmg-converter-for-elementor-to-divi-pro' ) . '</p></div>';
                break;
            case 'kit_error':
                $error = get_transient( 'edcp_kit_upload_error_' . get_current_user_id() );
                if ( $error ) {
                    delete_transient( 'edcp_kit_upload_error_' . get_current_user_id() );
                    printf(
                        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                        esc_html( $error )
                    );
                }
                break;
        }
    }

    private function render_global_kit_section(): void {
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
                [ 'page' => self::MENU_SLUG, 'tab' => 'kit', 'edc_action' => 'clear_kit' ],
                admin_url( 'tools.php' )
            ),
            'edc_clear_kit'
        );
        ?>
        <div class="edc-kit-status">
            <div class="edc-kit-status-header">
                <div>
                    <h2>
                        <?php esc_html_e( 'Active Kit', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>:
                        <span class="edc-kit-name"><?php echo esc_html( $kit_name ); ?></span>
                    </h2>
                    <?php if ( $date ) : ?>
                        <p class="edc-kit-date"><?php
                        /* translators: %s is the date the kit was loaded */
                        printf( esc_html__( 'Loaded on %s', 'jhmg-converter-for-elementor-to-divi-pro' ), esc_html( $date ) ); ?></p>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url( $clear_url ); ?>" class="button edc-btn-remove-kit"
                   onclick="return confirm('<?php esc_attr_e( 'Remove the active Global Kit?', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>')">
                    <?php esc_html_e( 'Remove Kit', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                </a>
            </div>

            <?php if ( ! empty( $colors ) ) : ?>
            <div class="edc-kit-section">
                <h3><?php
                /* translators: %d is the number of colors in the kit */
                printf( esc_html__( 'Colors (%d)', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( count( $colors ) ) ); ?></h3>
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
                printf( esc_html__( 'Typography (%d)', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( count( $typo ) ) ); ?></h3>
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
                printf( esc_html__( 'Pages in Kit (%d)', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( count( $kit_pages ) ) ); ?></h3>
                <form method="post" action="" class="edc-kit-pages-form">
                    <?php wp_nonce_field( self::KIT_CONVERT_NONCE_ACTION, self::KIT_CONVERT_NONCE_NAME ); ?>
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                    <input type="hidden" name="action" value="edc_convert_kit_pages">

                    <div class="edc-kit-pages-controls">
                        <label class="edc-select-all-label">
                            <input type="checkbox" id="edc-select-all-pages">
                            <?php esc_html_e( 'Select all / Deselect all', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                        </label>
                    </div>

                    <table class="wp-list-table widefat striped edc-pages-table">
                        <thead>
                            <tr>
                                <th class="check-column"></th>
                                <th><?php esc_html_e( 'Title', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                                <th class="edc-type-col"><?php esc_html_e( 'Type', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
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
                                    <strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                                </label>
                                <select id="edc_kit_post_type" name="edc_post_type">
                                    <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                                    <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                                </select>
                            </div>
                            <div class="edc-import-field">
                                <label for="edc_kit_post_status">
                                    <strong><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                                </label>
                                <select id="edc_kit_post_status" name="edc_post_status">
                                    <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                                    <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="edc-import-field edc-import-field--checkbox">
                            <label>
                                <input type="checkbox" name="edc_convert_headers" value="1">
                                <strong><?php esc_html_e( 'Convert header templates as Divi Theme Builder headers', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                            </label>
                        </div>

                        <div class="edc-import-field edc-import-field--checkbox">
                            <label>
                                <input type="checkbox" name="edc_convert_footers" value="1">
                                <strong><?php esc_html_e( 'Convert footer templates as Divi Theme Builder footers', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                            </label>
                        </div>

                        <div class="edc-import-submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Convert Selected', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
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
                <h3><?php esc_html_e( 'Replace Kit or Import Template', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h3>
            <?php else : ?>
                <h2><?php esc_html_e( 'Upload Elementor Export Kit or Template', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h2>
                <p class="edc-description"><?php esc_html_e( 'Upload your full Elementor Export Kit to extract global styles and pages, or import a single header/footer template JSON directly into Divi Theme Builder.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></p>
            <?php endif; ?>

            <div class="edc-upload-options">

                <div class="edc-upload-option edc-upload-option--kit">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Full Kit (ZIP)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import your entire Elementor Export Kit — pages, templates, global colors and typography.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="kit">
                        <input type="file" name="edc_kit_file" accept=".zip" required>
                        <button type="submit" class="button button-primary">
                            <?php echo $replacing
                                ? esc_html__( 'Replace Kit', 'jhmg-converter-for-elementor-to-divi-pro' )
                                : esc_html__( 'Upload Kit', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                        </button>
                    </form>
                </div>

                <div class="edc-upload-option edc-upload-option--header">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Header Template (JSON)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import a single Elementor header template and register it as a Divi Theme Builder global header.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="header">
                        <input type="file" name="edc_kit_file" accept=".json" required>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Set as Global Header', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                        </button>
                    </form>
                </div>

                <div class="edc-upload-option edc-upload-option--footer">
                    <div class="edc-upload-option-title">
                        <strong><?php esc_html_e( 'Footer Template (JSON)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Import a single Elementor footer template and register it as a Divi Theme Builder global footer.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></p>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="" class="edc-upload-option-form">
                        <?php wp_nonce_field( self::KIT_NONCE_ACTION, self::KIT_NONCE_NAME ); ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                        <input type="hidden" name="action" value="edc_upload_kit">
                        <input type="hidden" name="edc_upload_type" value="footer">
                        <input type="file" name="edc_kit_file" accept=".json" required>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Set as Global Footer', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                        </button>
                    </form>
                </div>

            </div>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Convert tab view (ported from the free plugin's render_import_section();
    // the "requires Premium" ZIP notice is gone — Pro always allows ZIP).
    // ------------------------------------------------------------------

    private function render_convert_section(): void {
        ?>
        <div class="edc-import-section">
            <h2><?php esc_html_e( 'Import from Elementor JSON / ZIP', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h2>
            <p class="edc-description">
                <?php esc_html_e( 'Upload an Elementor JSON export or a full-site Kit ZIP. No Elementor plugin required — this converts the exported file directly. Pages will be created in this Divi site.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
            </p>

            <form method="post" enctype="multipart/form-data" action="" class="edc-import-form">
                <?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
                <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                <input type="hidden" name="action" value="edc_import">

                <div class="edc-import-fields">
                    <div class="edc-import-field">
                        <label for="edc_import_file">
                            <strong><?php esc_html_e( 'File', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        </label>
                        <input type="file" id="edc_import_file" name="edc_import_file" accept=".json,.zip" required>
                        <p class="description"><?php esc_html_e( 'Accepted: .json (single page or template) or .zip (Elementor Kit export)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></p>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_type">
                            <strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        </label>
                        <select id="edc_post_type" name="edc_post_type">
                            <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                            <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                        </select>
                    </div>

                    <div class="edc-import-field">
                        <label for="edc_post_status">
                            <strong><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                        </label>
                        <select id="edc_post_status" name="edc_post_status">
                            <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></option>
                        </select>
                    </div>

                </div>

                <div class="edc-import-field edc-import-field--checkbox">
                    <label>
                        <input type="checkbox" name="edc_convert_headers" value="1">
                        <strong><?php esc_html_e( 'Convert header templates as Divi Theme Builder headers', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When checked, Elementor header templates are imported as Divi Theme Builder global headers. Uncheck to import them as regular draft pages instead.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                    </p>
                </div>

                <div class="edc-import-field edc-import-field--checkbox">
                    <label>
                        <input type="checkbox" name="edc_convert_footers" value="1">
                        <strong><?php esc_html_e( 'Convert footer templates as Divi Theme Builder footers', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></strong>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When checked, Elementor footer templates are imported as Divi Theme Builder global footers. Uncheck to import them as regular draft pages instead.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                    </p>
                </div>

                <div class="edc-import-submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Import and Convert', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Batch import result view (ported from the free plugin's
    // render_batch_result(); needed so both the Convert tab and the Global
    // Kit page/header/footer template uploads have somewhere to land).
    // ------------------------------------------------------------------

    private function render_batch_result(): void {
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $import_id === '' ) {
            wp_die( esc_html__( 'No import ID provided.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $results = get_transient( 'edcp_batch_' . $import_id );

        if ( ! is_array( $results ) ) {
            wp_die( esc_html__( 'Import results not found or expired. Results are kept for one hour.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }

        $total     = count( $results );
        $succeeded = count( array_filter( $results, fn( $r ) => $r['success'] ) );
        $failed    = $total - $succeeded;
        ?>
        <div class="wrap edcp-wrap">
            <h1><?php esc_html_e( 'Batch Import Results', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h1>

            <div class="edc-result-actions">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="button">
                    &larr; <?php esc_html_e( 'Back to converter', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                </a>
            </div>

            <div class="edc-batch-summary">
                <span class="edc-summary-stat edc-summary-stat--total">
                    <?php
                    /* translators: %d is the number of pages processed */
                    printf( esc_html__( '%d page(s) processed', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( $total ) );
                    ?>
                </span>
                <?php if ( $succeeded > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--ok">
                    <?php
                    /* translators: %d is the number of successfully converted pages */
                    printf( esc_html__( '%d converted', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( $succeeded ) );
                    ?>
                </span>
                <?php endif; ?>
                <?php if ( $failed > 0 ) : ?>
                <span class="edc-summary-stat edc-summary-stat--fail">
                    <?php
                    /* translators: %d is the number of pages that failed to convert */
                    printf( esc_html__( '%d failed', 'jhmg-converter-for-elementor-to-divi-pro' ), absint( $failed ) );
                    ?>
                </span>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped edc-batch-table">
                <thead>
                    <tr>
                        <th class="column-title column-primary"><?php esc_html_e( 'Title', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                        <th class="column-status"><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                        <th class="column-issues"><?php esc_html_e( 'Issues', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Actions', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
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
                            <strong><?php echo esc_html( $result['title'] ?: __( '(no title)', 'jhmg-converter-for-elementor-to-divi-pro' ) ); ?></strong>
                        </td>
                        <td class="column-status">
                            <?php if ( $result['success'] ) : ?>
                                <span class="edc-status edc-status--converted">&#10003; <?php esc_html_e( 'Converted', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></span>
                            <?php else : ?>
                                <span class="edc-status edc-status--error">&#10007; <?php esc_html_e( 'Failed', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></span>
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
                                        <?php esc_html_e( 'issues', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                                    </summary>
                                    <ul class="edc-issues-list">
                                        <?php foreach ( $result['report']['warnings'] ?? [] as $warning ) : ?>
                                            <li class="edc-issue edc-issue--warn"><?php echo esc_html( $warning ); ?></li>
                                        <?php endforeach; ?>
                                        <?php foreach ( $result['unsupported'] ?? [] as $item ) : ?>
                                            <li class="edc-issue edc-issue--unsupported">
                                                <?php esc_html_e( 'Unsupported:', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                                                <code><?php echo esc_html( $item['widgetType'] ?? $item['elType'] ?? 'unknown' ); ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php foreach ( $result['report']['skipped_settings'] ?? [] as $setting ) : ?>
                                            <li class="edc-issue edc-issue--skipped">
                                                <?php esc_html_e( 'Skipped:', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                                                <code><?php echo esc_html( $setting ); ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php elseif ( $result['success'] ) : ?>
                                <span class="edc-status--clean">&#10003; <?php esc_html_e( 'Clean', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></span>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <?php if ( $result['success'] && $result['post_id'] > 0 ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $result['post_id'] ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                                </a>
                                <a href="<?php echo esc_url( get_permalink( $result['post_id'] ) ); ?>" class="button button-small" target="_blank" rel="noopener">
                                    <?php esc_html_e( 'View', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
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
                                        <?php esc_html_e( 'Publish', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="edc-published-label">&#10003; <?php esc_html_e( 'Published', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></span>
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
}
