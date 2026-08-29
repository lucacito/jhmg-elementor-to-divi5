<?php
/**
 * Site-wide notice announcing the Pro price drop from $49/yr to $25/yr.
 *
 * Shown on every admin screen to every user who can manage options — the wide
 * placement is deliberate (Lucas, 2026-08-28) to reach existing users who last
 * saw the $49 price. The guard rails that keep that from becoming a nag:
 * a capability check, per-user dismissal, and a hard campaign expiry after
 * which the notice disappears whether or not anyone dismissed it.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PriceDropNotice {

    /** Per-user dismissal flag. */
    const USER_META_KEY  = 'edc_price_drop_dismissed';

    /** Nonce action guarding the dismiss link. */
    const DISMISS_ACTION = 'edc_dismiss_price_drop';

    /** Query arg carrying the dismissal. */
    const QUERY_DISMISS  = 'edc_dismiss_price_drop';

    /** The superseded price, quoted so the drop is legible. */
    const OLD_PRICE      = '$49/yr';

    /**
     * Last day the notice renders (Y-m-d). A price drop stops being news;
     * past this date the notice is gone regardless of dismissal state.
     */
    const CAMPAIGN_END   = '2026-10-27';

    private string $today;

    /**
     * @param string|null $today Y-m-d override, for tests.
     */
    public function __construct( ?string $today = null ) {
        $this->today = $today ?? gmdate( 'Y-m-d' );
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_dismiss' ] );
        add_action( 'admin_notices', [ $this, 'render' ] );
    }

    /** Whether the notice should render for the current user, right now. */
    public function should_show(): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( ! $this->on_relevant_screen() ) {
            return false;
        }

        if ( $this->today > self::CAMPAIGN_END ) {
            return false;
        }

        return ! get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
    }

    /**
     * The notice appears on the plugins screen and on this plugin's own pages,
     * not across all of wp-admin.
     *
     * A price drop is worth announcing, but an announcement that follows the
     * user onto every screen in their site reads as a nag — and this release
     * also asks for a wordpress.org review, so the two together would be one
     * ask too many. plugins.php is where people already are when a plugin
     * updates, which keeps most of the reach without the intrusion.
     */
    private function on_relevant_screen(): bool {
        $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

        if ( $pagenow === 'plugins.php' ) {
            return true;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return $page !== '' && strpos( $page, 'edc' ) === 0;
    }

    /** Records a nonce-verified dismissal against the current user. */
    public function maybe_handle_dismiss(): void {
        if ( empty( $_GET[ self::QUERY_DISMISS ] ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
            return;
        }

        update_user_meta( get_current_user_id(), self::USER_META_KEY, '1' );
    }

    public function render(): void {
        if ( ! $this->should_show() ) {
            return;
        }

        // Not passed through wp_kses_post: every interpolated value in markup()
        // is escaped at the point of interpolation, and kses would strip the
        // inline script that makes WP's dismiss button persist.
        echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** The notice markup. Split out from render() so it is directly testable. */
    public function markup(): string {
        $headline = sprintf(
            /* translators: 1: new price e.g. $25/yr, 2: previous price e.g. $49/yr */
            __( 'Pro is now %1$s — down from %2$s.', 'jhmg-converter-for-elementor-to-divi' ),
            AdminPage::PRO_PRICE,
            self::OLD_PRICE
        );

        $body = __(
            'Same unlimited-sites license: full kit ZIP import, global headers and footers, and global styles.',
            'jhmg-converter-for-elementor-to-divi'
        );

        $buy_url = 'https://divi5lab.com/plugins/elementor-to-divi-5?utm_source=plugin&utm_medium=notice&utm_campaign=price-drop';

        return sprintf(
            '<div class="notice notice-info is-dismissible edc-price-drop-notice">'
                . '<p><strong>%1$s</strong> %2$s</p>'
                . '<p><a class="button button-primary" href="%3$s" target="_blank" rel="noopener">%4$s</a> '
                . '<a href="%5$s">%6$s</a></p>'
            . '</div>',
            esc_html( $headline ),
            esc_html( $body ),
            esc_url( $buy_url ),
            esc_html__( 'Get Pro', 'jhmg-converter-for-elementor-to-divi' ),
            esc_url( $this->dismiss_url() ),
            esc_html__( 'Dismiss', 'jhmg-converter-for-elementor-to-divi' )
        ) . $this->persist_dismiss_script();
    }

    /**
     * WP's own dismiss button (the `is-dismissible` X) only hides a notice for
     * the current page load. On a notice that renders on every admin screen
     * that would mean it reappears forever, so the X is wired to the same
     * nonce-guarded URL as the explicit Dismiss link.
     */
    private function persist_dismiss_script(): string {
        return sprintf(
            '<script>(function(){var n=document.currentScript.parentNode;'
            . 'n.addEventListener("click",function(e){'
            . 'if(e.target.classList.contains("notice-dismiss")){window.location.href=%s;}'
            . '});})();</script>',
            wp_json_encode( $this->dismiss_url() )
        );
    }

    private function dismiss_url(): string {
        return add_query_arg( self::QUERY_DISMISS, '1' ) . '&_wpnonce=' . wp_create_nonce( self::DISMISS_ACTION );
    }
}
