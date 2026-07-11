<?php
// tests/FreeAdminSurgeryTest.php
use PHPUnit\Framework\TestCase;

class FreeAdminSurgeryTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    public function test_no_premium_namespace_left_in_free(): void {
        $hits = shell_exec( 'grep -rn "Premium\\\\\\\\PremiumManager\|premium/class-\|PremiumManager::" ' . escapeshellarg( self::FREE . '/includes' ) . ' || true' );
        $this->assertSame( '', trim( (string) $hits ), "Premium references remain:\n$hits" );
    }

    public function test_premium_dir_and_tb_exporter_gone(): void {
        $this->assertDirectoryDoesNotExist( self::FREE . '/includes/premium' );
        $this->assertFileDoesNotExist( self::FREE . '/includes/exporters/class-divi-theme-builder-exporter.php' );
    }

    public function test_upsell_links_to_divi5lab(): void {
        $admin = (string) file_get_contents( self::FREE . '/includes/admin/class-admin-page.php' );
        $this->assertStringContainsString( 'divi5lab.com/plugins/elementor-to-divi-5', $admin );
        $this->assertStringNotContainsString( 'edc_activate_premium', $admin );
    }
}
