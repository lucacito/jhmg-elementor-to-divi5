<?php
// tests/PriceDropNoticeTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\PriceDropNotice;

/**
 * The Pro price dropped from $49/yr to $25/yr. This notice announces that
 * site-wide (Lucas's call, 2026-08-28: maximum eyeballs, not upgraders only),
 * so the guard rails that matter are capability, per-user dismissal, and a
 * hard expiry so it cannot still be shouting months from now.
 */
class PriceDropNoticeTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    protected function setUp(): void {
        $GLOBALS['__test_user_meta']    = [];
        $GLOBALS['__test_current_user'] = 1;
        $GLOBALS['__test_caps']         = true;
        $_GET                           = [];
        $GLOBALS['pagenow']             = 'plugins.php';
    }

    private function notice( ?string $now = null ): PriceDropNotice {
        return new PriceDropNotice( $now ?? '2026-09-01' );
    }

    /**
     * This shipped as a notice on EVERY admin screen. Narrowed after review:
     * a plugin that nags on every page is how you earn the one-star reviews
     * this release is trying to avoid. plugins.php is where people already
     * are when a plugin updates; the plugin's own screens are where the
     * offer is actually relevant.
     */
    public function test_shows_on_the_plugins_screen(): void {
        $GLOBALS['pagenow'] = 'plugins.php';
        $this->assertTrue( $this->notice()->should_show() );
    }

    public function test_shows_on_the_plugins_own_screen(): void {
        $GLOBALS['pagenow'] = 'tools.php';
        $_GET['page']       = 'edc-converter';
        $this->assertTrue( $this->notice()->should_show() );
    }

    public function test_hidden_on_unrelated_admin_screens(): void {
        foreach ( [ 'index.php', 'edit.php', 'upload.php', 'options-general.php' ] as $screen ) {
            $GLOBALS['pagenow'] = $screen;
            $_GET               = [];
            $this->assertFalse(
                $this->notice()->should_show(),
                "the notice must not appear on {$screen}"
            );
        }
    }

    public function test_shows_for_an_admin_before_expiry(): void {
        $this->assertTrue( $this->notice()->should_show() );
    }

    public function test_hidden_after_campaign_expiry(): void {
        $this->assertFalse( $this->notice( '2027-01-01' )->should_show() );
    }

    public function test_hidden_for_users_without_manage_options(): void {
        $GLOBALS['__test_caps'] = false;
        $this->assertFalse( $this->notice()->should_show() );
    }

    public function test_dismissal_is_per_user_and_sticks(): void {
        $n = $this->notice();
        $this->assertTrue( $n->should_show() );

        $_GET[ PriceDropNotice::QUERY_DISMISS ] = '1';
        $_GET['_wpnonce'] = wp_create_nonce( PriceDropNotice::DISMISS_ACTION );
        $n->maybe_handle_dismiss();

        $this->assertFalse( $n->should_show(), 'dismissal must persist for this user' );

        $GLOBALS['__test_current_user'] = 2;
        $this->assertTrue( $n->should_show(), 'another admin must still see it' );
    }

    public function test_dismissal_requires_a_valid_nonce(): void {
        $n = $this->notice();
        $_GET[ PriceDropNotice::QUERY_DISMISS ] = '1';
        $_GET['_wpnonce'] = 'forged';
        $n->maybe_handle_dismiss();
        $this->assertTrue( $n->should_show(), 'a forged nonce must not dismiss' );
    }

    public function test_markup_names_both_prices_and_links_with_utm(): void {
        $html = $this->notice()->markup();
        $this->assertStringContainsString( '$25/yr', $html );
        $this->assertStringContainsString( '$49', $html );
        $this->assertStringContainsString( 'utm_source=plugin', $html );
        $this->assertStringContainsString( 'is-dismissible', $html );
    }

    public function test_wp_dismiss_button_persists_the_dismissal(): void {
        $html = $this->notice()->markup();
        $this->assertStringContainsString( 'notice-dismiss', $html, 'the X must be wired up' );
        $this->assertStringContainsString(
            PriceDropNotice::QUERY_DISMISS,
            $html,
            'the X must point at the nonce-guarded dismiss URL, not just hide the notice'
        );
    }

    public function test_renders_nothing_once_dismissed(): void {
        $n = $this->notice();
        update_user_meta( get_current_user_id(), PriceDropNotice::USER_META_KEY, '1' );
        ob_start();
        $n->render();
        $this->assertSame( '', trim( (string) ob_get_clean() ) );
    }

    public function test_price_is_sourced_from_admin_page_constant(): void {
        $src = (string) file_get_contents( self::FREE . '/includes/admin/class-price-drop-notice.php' );
        $this->assertStringContainsString(
            'AdminPage::PRO_PRICE',
            $src,
            'the notice must reuse the single-sourced price, not re-inline it'
        );
    }
}
