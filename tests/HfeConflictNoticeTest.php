<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\HfeConflictNotice;

/**
 * With Divi active and a Theme Builder header in place, Header Footer Elementor's
 * remove_all_actions('wp_head') runs inside Divi's header buffer and every page
 * fatals ("Call to a member function do_action() on array", docs/known-issues.md).
 * Not our bug, but the first thing a user hits after converting a site that used HFE.
 */
final class HfeConflictNoticeTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['__test_options']  = [];
        unset( $GLOBALS['__test_template'] );
    }

    private function site( bool $hfe, string $theme, bool $header ): void {
        $GLOBALS['__test_posts'] = [];
        update_option( 'active_plugins', $hfe ? [ 'header-footer-elementor/header-footer-elementor.php' ] : [] );
        $GLOBALS['__test_template'] = $theme;
        if ( $header ) {
            $GLOBALS['__test_posts'][ 500 ] = (object) [ 'ID' => 500, 'post_type' => 'et_header_layout', 'post_status' => 'publish', 'post_title' => 'H' ];
        }
    }

    public function test_applies_only_with_hfe_divi_and_a_theme_builder_layout(): void {
        $this->site( true, 'Divi', true );
        $this->assertTrue( ( new HfeConflictNotice() )->applies() );

        $this->site( false, 'Divi', true );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );

        $this->site( true, 'hello-elementor', true );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );

        $this->site( true, 'Divi', false );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );
    }

    public function test_message_tells_the_user_what_to_do_and_why(): void {
        $message = ( new HfeConflictNotice() )->message();
        $this->assertStringContainsString( 'Deactivate Header Footer Elementor', $message );
        $this->assertStringContainsString( 'Theme Builder', $message );
    }

    public function test_renders_an_admin_notice_when_it_applies(): void {
        $this->site( true, 'Divi', true );
        ob_start();
        ( new HfeConflictNotice() )->render();
        $html = ob_get_clean();
        $this->assertStringContainsString( 'notice-warning', $html );
        $this->assertStringContainsString( 'Deactivate Header Footer Elementor', $html );
    }
}
