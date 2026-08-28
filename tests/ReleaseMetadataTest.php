<?php
// tests/ReleaseMetadataTest.php
use PHPUnit\Framework\TestCase;

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
}
