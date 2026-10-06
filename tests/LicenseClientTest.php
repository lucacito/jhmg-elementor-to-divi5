<?php
// tests/LicenseClientTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Pro\Licensing\LicenseClient;

class LicenseClientTest extends TestCase {
    private LicenseClient $client;

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['edc_test_http'] = [ 'queue' => [], 'log' => [] ];
        $GLOBALS['__test_transients'] = [];
        delete_option( 'edcp_license_key' );
        delete_option( 'edcp_license_state' );
        delete_option( 'edcp_update_blocked' );
        $this->client = new LicenseClient( 'elementor-to-divi5-pro', '1.0.0', 'https://divi5lab.com', 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php', 'edcp-kit', 'https://divi5lab.com/plugins/elementor-to-divi-5', 'edcp' );
    }

    public function test_activate_success_stores_key_and_state(): void {
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'status' => 'active', 'product' => 'elementor-to-divi5-pro', 'expires' => '2027-07-11T00:00:00.000Z' ] ] );
        $res = $this->client->activate( 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        $this->assertTrue( $res['ok'] );
        $this->assertSame( 'JHMG-AAAA-BBBB-CCCC-DDDD', $this->client->get_key() );
        $this->assertSame( 'active', $this->client->get_state()['status'] );
        // Wire contract: snake_case params to the right endpoint.
        $call = $GLOBALS['edc_test_http']['log'][0];
        $this->assertSame( 'https://divi5lab.com/api/license/activate', $call['url'] );
        $body = json_decode( $call['args']['body'], true );
        $this->assertSame( [ 'key', 'site_url', 'product', 'plugin_version', 'wp_version' ], array_keys( $body ) );
    }

    public function test_activate_invalid_key_reports_error(): void {
        edc_test_http_queue( [ 'code' => 404, 'body' => [ 'error' => 'invalid_key' ] ] );
        $res = $this->client->activate( 'JHMG-ZZZZ-ZZZZ-ZZZZ-ZZZZ' );
        $this->assertFalse( $res['ok'] );
        $this->assertSame( 'invalid_key', $res['error'] );
        $this->assertNull( $this->client->get_key() );
    }

    public function test_refresh_skips_within_24h_cache(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => time() - 100 ] );
        $this->client->refresh();
        $this->assertCount( 0, $GLOBALS['edc_test_http']['log'] );
    }

    public function test_refresh_network_failure_within_grace_keeps_state(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => time() - 2 * DAY_IN_SECONDS ] );
        edc_test_http_queue( new WP_Error( 'http_failure', 'timeout' ) );
        $this->client->refresh();
        $this->assertSame( 'active', $this->client->get_state()['status'] );
    }

    public function test_inject_update_adds_package_when_licensed(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x', 'package' => 'https://divi5lab.com/api/plugin/download?product=elementor-to-divi5-pro&key=JHMG-AAAA-BBBB-CCCC-DDDD' ] ] );
        $t = $this->client->inject_update( (object) [ 'response' => [] ] );
        $entry = $t->response['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( '1.1.0', $entry->new_version );
        $this->assertStringContainsString( '/api/plugin/download', $entry->package );
        $this->assertSame( 'https://divi5lab.com/plugins/elementor-to-divi-5', $entry->url );
    }

    private const BASENAME = 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php';

    public function test_inject_update_without_package_sets_renewal_flag(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x' ] ] );
        $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertNotEmpty( get_option( 'edcp_update_blocked' ) );
    }

    public function test_a_withheld_update_is_still_listed_so_the_plugins_screen_shows_it(): void {
        // WordPress renders an entry with an empty package as "There is a new
        // version available. Automatic update is unavailable for this plugin."
        // Leaving the entry out meant an unlicensed site was never told.
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x' ] ] );

        $t = $this->client->inject_update( (object) [ 'response' => [] ] ); // no licence key at all

        $entry = $t->response[ self::BASENAME ];
        $this->assertSame( '1.1.0', $entry->new_version );
        $this->assertSame( '', $entry->package, 'there must be nothing for WordPress to download' );
        $this->assertSame( self::BASENAME, $entry->plugin );
    }

    public function test_a_withheld_update_is_not_auto_installed(): void {
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0' ] ] );
        $this->client->inject_update( (object) [ 'response' => [] ] );

        // Core would otherwise try to download an empty package on every
        // background check and email the admin that the update failed.
        $this->assertFalse( $this->client->block_auto_update( true, (object) [ 'plugin' => self::BASENAME ] ) );
    }

    public function test_auto_update_is_left_alone_when_the_update_is_not_withheld(): void {
        $this->assertTrue( $this->client->block_auto_update( true, (object) [ 'plugin' => self::BASENAME ] ) );
        $this->assertNull( $this->client->block_auto_update( null, (object) [ 'plugin' => self::BASENAME ] ) );
    }

    public function test_auto_update_for_another_plugin_is_never_touched(): void {
        update_option( 'edcp_update_blocked', '1.1.0' );

        $this->assertTrue( $this->client->block_auto_update( true, (object) [ 'plugin' => 'some-other/some-other.php' ] ) );
    }

    public function test_a_licensed_update_clears_the_withheld_flag(): void {
        update_option( 'edcp_update_blocked', '1.1.0' );
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] ] );

        $this->client->inject_update( (object) [ 'response' => [] ] );

        $this->assertFalse( get_option( 'edcp_update_blocked' ) );
        $this->assertTrue( $this->client->block_auto_update( true, (object) [ 'plugin' => self::BASENAME ] ) );
    }

    // ------------------------------------------------------------------
    // A reply is only trusted as far as it can be verified
    // ------------------------------------------------------------------

    private function inject_with( array $body ): object {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => $body ] );

        return $this->client->inject_update( (object) [ 'response' => [] ] );
    }

    public function test_a_package_on_another_host_is_never_offered_to_wordpress(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.1.0', 'package' => 'https://evil.example/plugin.zip' ] );

        $this->assertArrayNotHasKey( self::BASENAME, $t->response );
    }

    public function test_a_plain_http_package_is_never_offered(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.1.0', 'package' => 'http://divi5lab.com/api/plugin/download?product=x' ] );

        $this->assertArrayNotHasKey( self::BASENAME, $t->response );
    }

    public function test_the_www_form_of_the_licence_host_is_accepted(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.1.0', 'package' => 'https://www.divi5lab.com/api/plugin/download?product=x' ] );

        $this->assertArrayHasKey( self::BASENAME, $t->response );
    }

    public function test_a_lookalike_host_is_not_the_licence_host(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.1.0', 'package' => 'https://divi5lab.com.evil.example/x.zip' ] );

        $this->assertArrayNotHasKey( self::BASENAME, $t->response );
    }

    public function test_a_version_that_is_not_newer_than_the_installed_one_is_ignored(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.0.0', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] );

        $this->assertArrayNotHasKey( self::BASENAME, $t->response );
    }

    public function test_a_withheld_update_that_is_not_newer_is_not_listed(): void {
        $t = $this->inject_with( [ 'update' => true, 'version' => '1.0.0' ] );

        $this->assertArrayNotHasKey( self::BASENAME, $t->response );
        $this->assertFalse( get_option( 'edcp_update_blocked' ) );
    }

    public function test_a_reply_that_is_not_an_object_changes_nothing(): void {
        $t = $this->inject_with( [] );
        edc_test_http_queue( [ 'code' => 200, 'body' => 'not json at all' ] );
        $GLOBALS['__test_transients'] = [];

        $t = $this->client->inject_update( (object) [ 'response' => [] ] );

        $this->assertSame( [], $t->response );
        $this->assertFalse( property_exists( $t, 'no_update' ), 'an unreadable reply must not assert "up to date"' );
    }

    // ------------------------------------------------------------------
    // A.1 — update-check caching
    // ------------------------------------------------------------------

    public function test_inject_update_caches_update_check_and_skips_second_http_call(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] ] );

        $t1 = $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertCount( 1, $GLOBALS['edc_test_http']['log'] );
        $entry = $t1->response['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( '1.1.0', $entry->new_version );

        // Second call: same product|version|key — must hit the transient cache, no new HTTP.
        $t2 = $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertCount( 1, $GLOBALS['edc_test_http']['log'] );
        $entry2 = $t2->response['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( '1.1.0', $entry2->new_version );
    }

    public function test_force_refresh_busts_update_check_cache(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => time() ] );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] ] );
        $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertCount( 1, $GLOBALS['edc_test_http']['log'] );

        // Force refresh (e.g. "Check again") must bust the update-check transient too.
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'status' => 'active', 'product' => 'elementor-to-divi5-pro', 'expires' => null ] ] );
        $this->client->refresh( true );

        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.2.0', 'changelog' => 'y', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] ] );
        $t = $this->client->inject_update( (object) [ 'response' => [] ] );
        $entry = $t->response['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( '1.2.0', $entry->new_version );
    }

    // ------------------------------------------------------------------
    // A.2 — resilient refresh() on transient server errors
    // ------------------------------------------------------------------

    public function test_refresh_rate_limited_keeps_last_state(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        $checked_at = time() - 2 * DAY_IN_SECONDS;
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => $checked_at ] );
        edc_test_http_queue( [ 'code' => 429, 'body' => [ 'error' => 'rate_limited' ] ] );

        $this->client->refresh();

        $state = $this->client->get_state();
        $this->assertSame( 'active', $state['status'] );
        $this->assertSame( $checked_at, $state['checked_at'] );
    }

    public function test_refresh_server_error_keeps_last_state(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        $checked_at = time() - 2 * DAY_IN_SECONDS;
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => $checked_at ] );
        edc_test_http_queue( [ 'code' => 503, 'body' => [ 'error' => 'server_error' ] ] );

        $this->client->refresh();

        $state = $this->client->get_state();
        $this->assertSame( 'active', $state['status'] );
        $this->assertSame( $checked_at, $state['checked_at'] );
    }

    public function test_refresh_definitive_invalid_key_updates_state(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        update_option( 'edcp_license_state', [ 'status' => 'active', 'expires' => null, 'checked_at' => time() - 2 * DAY_IN_SECONDS ] );
        edc_test_http_queue( [ 'code' => 404, 'body' => [ 'error' => 'invalid_key' ] ] );

        $this->client->refresh();

        $state = $this->client->get_state();
        $this->assertSame( 'invalid', $state['status'] );
    }

    // ------------------------------------------------------------------
    // A.3 — WP auto-update compatibility
    // ------------------------------------------------------------------

    public function test_inject_update_response_entry_has_plugin_and_id(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x', 'package' => 'https://divi5lab.com/api/plugin/download?product=x' ] ] );
        $t = $this->client->inject_update( (object) [ 'response' => [] ] );
        $entry = $t->response['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php', $entry->plugin );
        $this->assertSame( 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php', $entry->id );
    }

    public function test_inject_update_sets_no_update_entry_when_no_update_available(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => false ] ] );
        $t = $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertObjectHasProperty( 'no_update', $t );
        $entry = $t->no_update['jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php'];
        $this->assertSame( 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php', $entry->plugin );
        $this->assertSame( 'jhmg-converter-for-elementor-to-divi-pro', $entry->slug );
        $this->assertSame( '1.0.0', $entry->new_version );
    }

    // ------------------------------------------------------------------
    // option_prefix — cross-plugin storage isolation (Task 7 forward-ref)
    // ------------------------------------------------------------------

    public function test_option_prefix_scopes_license_storage_per_product(): void {
        $other = new LicenseClient(
            'other-product',
            '1.0.0',
            'https://divi5lab.com',
            'other-plugin/other-plugin.php',
            'other-kit',
            'https://divi5lab.com/plugins/other',
            'd2ep'
        );

        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'status' => 'active', 'product' => 'other-product', 'expires' => null ] ] );
        $other->activate( 'JHMG-OTHER-KEY' );

        // A differently-prefixed client (a second Pro plugin on the same site) must not
        // see or clobber this client's ('edcp') license storage.
        $this->assertNull( $this->client->get_key() );
        $this->assertSame( 'JHMG-OTHER-KEY', $other->get_key() );
    }
}
