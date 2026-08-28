<?php
// tests/ReleaseMetadataTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;

class ReleaseMetadataTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    public function test_version_is_consistent_across_header_constant_and_readme(): void {
        $main   = (string) file_get_contents( self::FREE . '/jhmg-converter-for-elementor-to-divi.php' );
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( 'Version:     2.3.0', $main );
        $this->assertStringContainsString( "EDC_PLUGIN_VERSION', '2.3.0'", $main );
        $this->assertStringContainsString( 'Stable tag: 2.3.0', $readme );
    }

    public function test_readme_discloses_the_external_service(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringContainsString( 'External services', $readme );
        $this->assertStringContainsString( 'divi5lab.com/api/plugin/coverage', $readme );
        $this->assertStringContainsString( 'opt-in', $readme );
    }

    public function test_no_2_2_0_section_survives_the_fold(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringNotContainsString( '= 2.2.0 =', $readme, '2.2.0 was never published' );
    }

    public function test_privacy_disclosure_accounts_for_all_payload_keys(): void {
        // Get actual payload keys from CoverageTelemetry
        $payload = ( new CoverageTelemetry() )->payload();
        $payload_keys = array_keys( $payload );

        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        // Each payload key must be mentioned in the External services section
        foreach ( $payload_keys as $key ) {
            $this->assertStringContainsString(
                $key,
                $readme,
                "Payload key '$key' must be disclosed in the readme's External services section"
            );
        }

        // Verify the section explicitly discloses what is NOT sent
        $this->assertStringContainsString(
            'Nothing else',
            $readme,
            'External services section must explicitly state what is NOT sent'
        );
    }
}
