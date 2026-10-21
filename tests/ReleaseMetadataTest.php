<?php
// tests/ReleaseMetadataTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;

class ReleaseMetadataTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    public function test_version_is_consistent_across_header_constant_and_readme(): void {
        $main   = (string) file_get_contents( self::FREE . '/jhmg-converter-for-elementor-to-divi.php' );
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( 'Version:     3.2.1', $main );
        $this->assertStringContainsString( "EDC_PLUGIN_VERSION', '3.2.1'", $main );
        $this->assertStringContainsString( 'Stable tag: 3.2.1', $readme );
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

    public function test_changelog_does_not_promise_edit_based_rollback_protection(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringNotContainsString(
            'edited since',
            $readme,
            'the rollback guard only checks plugin ownership, not whether the page was edited'
        );
        $this->assertStringNotContainsString( 'never touches a page you have edited', $readme );
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

    public function test_version_is_three_two_one_everywhere(): void {
        $main   = (string) file_get_contents( self::FREE . '/jhmg-converter-for-elementor-to-divi.php' );
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*3\.2\.1\s*$/m', $main );
        $this->assertMatchesRegularExpression( '/^Stable tag:\s*3\.2\.1\s*$/m', $readme );
    }

    public function test_readme_documents_3_2_1_in_changelog_and_upgrade_notice(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertSame( 2, substr_count( $readme, '= 3.2.1 =' ), 'a changelog entry and an upgrade notice' );
        $this->assertStringContainsString( '$47/yr', $readme );
    }

    public function test_readme_documents_3_2_0_in_changelog_and_upgrade_notice(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertSame( 2, substr_count( $readme, '= 3.2.0 =' ), 'a changelog entry and an upgrade notice' );
    }

    public function test_readme_no_longer_promises_the_original_is_never_modified_unconditionally(): void {
        // 3.2.0 can convert a page in place when the user ticks the checkbox,
        // so the FAQ and the description must say "unless you ask".
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        // Past changelog entries were true when written; only the live
        // description and FAQ, above the changelog, have to stay accurate.
        $live = substr( $readme, 0, (int) strpos( $readme, '== Changelog ==' ) );

        $this->assertStringNotContainsString( 'always creates a new Divi draft', $live );
        $this->assertStringContainsString( 'Replace the original page', $live );
    }

    public function test_pro_version_is_consistent(): void {
        $pro = (string) file_get_contents( __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php' );

        $this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*1\.2\.2\s*$/m', $pro );
        $this->assertStringContainsString( "EDCP_PLUGIN_VERSION', '1.2.2'", $pro );
    }

    public function test_readme_documents_3_1_0_in_changelog_and_upgrade_notice(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertSame( 2, substr_count( $readme, '= 3.1.0 =' ), 'a changelog entry and an upgrade notice' );
    }

    public function test_readme_documents_3_0_2_in_changelog_and_upgrade_notice(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertSame( 2, substr_count( $readme, '= 3.0.2 =' ), 'a changelog entry and an upgrade notice' );
    }

    public function test_readme_changelog_documents_3_0_0(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( '= 3.0.0 =', $readme );
    }

    public function test_readme_does_not_promise_a_visual_preview(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringNotContainsString( 'visual preview', strtolower( $readme ), 'checking a page reports structure, not pixels' );
    }

    public function test_readme_documents_the_accumulated_count_fix(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( 'accumulated', $readme, 'the per-page conversion count fix must be disclosed for Pro kit-import users' );
    }
}
