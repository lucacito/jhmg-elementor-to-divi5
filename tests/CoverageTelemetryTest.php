<?php
// tests/CoverageTelemetryTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;

class CoverageTelemetryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_caps']    = true;
        $_GET                      = [];
        edc_test_reset_hooks();
        $GLOBALS['edc_test_http'] = [ 'queue' => [], 'log' => [] ];
    }

    private function seeded(): ImportHistory {
        $h = new ImportHistory();
        $h->record( 'a', [ [
            'success' => true, 'post_id' => 1,
            'unsupported' => [ [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => 'lottie' ] ],
        ] ] );
        return $h;
    }

    public function test_sends_nothing_without_consent(): void {
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );
        $this->assertFalse( $t->has_consent() );
        $t->maybe_send();
        $this->assertSame( '', (string) get_option( CoverageTelemetry::LAST_SENT_OPTION, '' ) );
    }

    public function test_payload_is_names_only(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $payload = ( new CoverageTelemetry( $this->seeded(), '2026-09-01' ) )->payload();

        $this->assertSame( [ 'product', 'widget_types' ], array_keys( $payload ) );
        $this->assertSame( [ 'lottie' ], $payload['widget_types'] );
        $this->assertSame( 'elementor-to-divi5', $payload['product'] );
    }

    public function test_sends_once_consented_and_records_the_date(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        edc_test_http_queue( [ 'code' => 200, 'body' => [] ] );
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );
        $this->assertTrue( $t->due() );
        $t->maybe_send();
        $this->assertSame( '2026-09-01', get_option( CoverageTelemetry::LAST_SENT_OPTION ) );
    }

    public function test_throttled_to_one_report_a_week(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        update_option( CoverageTelemetry::LAST_SENT_OPTION, '2026-09-01' );

        $this->assertFalse( ( new CoverageTelemetry( $this->seeded(), '2026-09-05' ) )->due() );
        $this->assertTrue( ( new CoverageTelemetry( $this->seeded(), '2026-09-09' ) )->due() );
    }

    public function test_sends_nothing_when_there_is_no_gap_to_report(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $empty = new ImportHistory();
        $empty->record( 'a', [ [ 'success' => true, 'post_id' => 1, 'unsupported' => [] ] ] );

        ( new CoverageTelemetry( $empty, '2026-09-01' ) )->maybe_send();
        $this->assertSame( '', (string) get_option( CoverageTelemetry::LAST_SENT_OPTION, '' ) );
    }

    public function test_consent_toggle_requires_nonce_and_capability(): void {
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );

        $_GET[ CoverageTelemetry::QUERY_ACTION ] = '1';
        $_GET['_wpnonce'] = 'forged';
        $t->maybe_handle_consent();
        $this->assertFalse( $t->has_consent() );

        $_GET['_wpnonce'] = wp_create_nonce( CoverageTelemetry::NONCE_ACTION );
        $GLOBALS['__test_caps'] = false;
        $t->maybe_handle_consent();
        $this->assertFalse( $t->has_consent() );

        $GLOBALS['__test_caps'] = true;
        $t->maybe_handle_consent();
        $this->assertTrue( $t->has_consent() );
    }

    public function test_payload_carries_no_identifying_field(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $src = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/includes/telemetry/class-coverage-telemetry.php'
        );
        foreach ( [ 'home_url', 'site_url', 'get_bloginfo', 'wp_get_current_user', 'md5' ] as $forbidden ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $src,
                "telemetry must send nothing that could identify a site (found: $forbidden)"
            );
        }
    }
}
