<?php
// tests/LicenseClientTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Pro\Licensing\LicenseClient;

class LicenseClientTest extends TestCase {
    private LicenseClient $client;

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['edc_test_http'] = [ 'queue' => [], 'log' => [] ];
        delete_option( 'edcp_license_key' );
        delete_option( 'edcp_license_state' );
        delete_option( 'edcp_update_blocked' );
        $this->client = new LicenseClient( 'elementor-to-divi5-pro', '1.0.0', 'https://divi5lab.com', 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php' );
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
    }

    public function test_inject_update_without_package_sets_renewal_flag(): void {
        update_option( 'edcp_license_key', 'JHMG-AAAA-BBBB-CCCC-DDDD' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'changelog' => 'x' ] ] );
        $t = $this->client->inject_update( (object) [ 'response' => [] ] );
        $this->assertArrayNotHasKey( 'jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php', $t->response );
        $this->assertNotEmpty( get_option( 'edcp_update_blocked' ) );
    }
}
