<?php

namespace ElementorDivi5Converter\Pro\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * License tab UI + POST dispatch, rendered inside Admin\KitPage's own page
 * (Pro has a single Tools admin page — see Admin\KitPage).
 *
 * All dispatch identifiers are edcp_-prefixed for the same reason as
 * Admin\KitPage's: free's AdminPage::handle_post() hooks admin_init
 * unscoped and dispatches purely on the POST action / GET edc_action
 * params, so Pro's own identifiers must never collide with free's.
 *
 * Enforcement policy (frozen): SOFT. This UI only ever informs the site
 * owner about license/update status — it never disables or hides any
 * conversion feature.
 */
class LicensePage {

    const NONCE_NAME       = 'edcp_license_nonce';
    const NONCE_ACTION     = 'edcp_license';
    const DEACTIVATE_ACTION = 'edcp_deactivate_license';
    const REFRESH_ACTION    = 'edcp_refresh_license';

    public function __construct( private LicenseClient $license ) {}

    // ------------------------------------------------------------------
    // admin_notices
    // ------------------------------------------------------------------

    public function maybe_render_notice(): void {
        $this->license->status_notice();
    }

    // ------------------------------------------------------------------
    // POST / GET dispatch — called from Admin\KitPage::handle_post()
    // ------------------------------------------------------------------

    public function handle_post(): void {
        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked per-branch below
        if ( $action === 'edcp_save_license' ) {
            $this->handle_activate();
            return;
        }

        $edcp_action = sanitize_key( $_GET['edcp_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked per-branch below
        if ( $edcp_action === 'deactivate_license' ) {
            $this->handle_deactivate();
            return;
        }
        if ( $edcp_action === 'refresh_license' ) {
            $this->handle_refresh();
        }
    }

    private function handle_activate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

        $key = sanitize_text_field( wp_unslash( $_POST['edcp_license_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = $key !== '' ? $this->license->activate( $key ) : [ 'ok' => false, 'error' => 'invalid_request' ];

        $this->redirect( [
            'edc_notice' => $result['ok'] ? 'license_activated' : 'license_error',
            'edc_error'  => $result['ok'] ? '' : ( $result['error'] ?? 'unknown' ),
        ] );
    }

    private function handle_deactivate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }
        check_admin_referer( self::DEACTIVATE_ACTION );

        $this->license->deactivate();

        $this->redirect( [ 'edc_notice' => 'license_deactivated' ] );
    }

    private function handle_refresh(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-elementor-to-divi-pro' ) );
        }
        check_admin_referer( self::REFRESH_ACTION );

        $this->license->refresh( true );

        $this->redirect( [ 'edc_notice' => 'license_checked' ] );
    }

    private function redirect( array $extra_args ): void {
        wp_safe_redirect( add_query_arg(
            array_merge( [ 'page' => \ElementorDivi5Converter\Pro\Admin\KitPage::MENU_SLUG, 'tab' => 'license' ], $extra_args ),
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    // ------------------------------------------------------------------
    // View
    // ------------------------------------------------------------------

    public function render(): void {
        $this->render_notice();

        $key   = $this->license->get_key();
        $state = $this->license->get_state();
        ?>
        <div class="edc-license-section">
            <?php if ( $key ) : ?>
                <?php $this->render_status( $key, $state ); ?>
            <?php else : ?>
                <?php $this->render_activation_form(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_notice(): void {
        $notice = sanitize_key( $_GET['edc_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display parameter
        if ( ! $notice ) {
            return;
        }
        switch ( $notice ) {
            case 'license_activated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'License activated.', 'jhmg-converter-for-elementor-to-divi-pro' ) . '</p></div>';
                break;
            case 'license_deactivated':
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'License deactivated.', 'jhmg-converter-for-elementor-to-divi-pro' ) . '</p></div>';
                break;
            case 'license_checked':
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'License status refreshed.', 'jhmg-converter-for-elementor-to-divi-pro' ) . '</p></div>';
                break;
            case 'license_error':
                $error = sanitize_text_field( wp_unslash( $_GET['edc_error'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html( sprintf(
                        /* translators: %s is the error code returned by the license server */
                        __( 'Could not activate license: %s', 'jhmg-converter-for-elementor-to-divi-pro' ),
                        $error !== '' ? $error : __( 'unknown error', 'jhmg-converter-for-elementor-to-divi-pro' )
                    ) )
                );
                break;
        }
    }

    private function render_status( string $key, ?array $state ): void {
        $status  = $state['status'] ?? 'unknown';
        $expires = $state['expires'] ?? null;
        $masked  = substr( $key, 0, 8) . str_repeat( '*', max( 0, strlen( $key ) - 12 ) ) . substr( $key, -4 );

        $deactivate_url = wp_nonce_url(
            add_query_arg(
                [ 'page' => \ElementorDivi5Converter\Pro\Admin\KitPage::MENU_SLUG, 'tab' => 'license', 'edcp_action' => 'deactivate_license' ],
                admin_url( 'tools.php' )
            ),
            self::DEACTIVATE_ACTION
        );
        $refresh_url = wp_nonce_url(
            add_query_arg(
                [ 'page' => \ElementorDivi5Converter\Pro\Admin\KitPage::MENU_SLUG, 'tab' => 'license', 'edcp_action' => 'refresh_license' ],
                admin_url( 'tools.php' )
            ),
            self::REFRESH_ACTION
        );
        ?>
        <div class="edc-license-status">
            <h2><?php esc_html_e( 'License', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Key', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                    <td><code><?php echo esc_html( $masked ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                    <td><span class="edc-license-status-badge edc-license-status-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                </tr>
                <?php if ( $expires ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Expires', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></th>
                    <td><?php echo esc_html( $expires ); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <p>
                <a href="<?php echo esc_url( $refresh_url ); ?>" class="button"><?php esc_html_e( 'Check again', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></a>
                <a href="<?php echo esc_url( $deactivate_url ); ?>" class="button"
                   onclick="return confirm('<?php esc_attr_e( 'Deactivate this license on this site?', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>')">
                    <?php esc_html_e( 'Deactivate', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_activation_form(): void {
        ?>
        <div class="edc-license-activation">
            <h2><?php esc_html_e( 'Activate Your License', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></h2>
            <p class="edc-description">
                <?php esc_html_e( 'Enter your license key to enable automatic updates and support. Every conversion feature works whether or not a license is active.', 'jhmg-converter-for-elementor-to-divi-pro' ); ?>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
                <input type="hidden" name="page" value="<?php echo esc_attr( \ElementorDivi5Converter\Pro\Admin\KitPage::MENU_SLUG ); ?>">
                <input type="hidden" name="action" value="edcp_save_license">
                <p>
                    <input type="text" name="edcp_license_key" class="regular-text" placeholder="JHMG-XXXX-XXXX-XXXX-XXXX" required>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Activate', 'jhmg-converter-for-elementor-to-divi-pro' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}
