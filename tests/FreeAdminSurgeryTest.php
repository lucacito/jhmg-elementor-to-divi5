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

    /**
     * The shipped upgrade CTAs quoted $49/yr while divi5lab.com sold at $25/yr.
     * The price is now single-sourced on AdminPage::PRO_PRICE so it can only be
     * wrong in one place; these assertions keep it that way.
     */
    public function test_pro_price_is_single_sourced_and_current(): void {
        $admin = (string) file_get_contents( self::FREE . '/includes/admin/class-admin-page.php' );
        $this->assertStringContainsString(
            "const PRO_PRICE",
            $admin,
            'Pro price must live in one constant, not be inlined in copy.'
        );
        $this->assertMatchesRegularExpression(
            '/const PRO_PRICE\s*=\s*\'\$25\/yr\'/',
            $admin,
            'PRO_PRICE must match the live divi5lab.com price ($25/yr).'
        );
    }

    /**
     * The old price may still be *quoted* — the changelog, the upgrade notice and
     * the price-drop announcement all need it to make the drop legible. What must
     * never come back is a live CTA that asks for the old price.
     */
    public function test_no_stale_price_in_upgrade_ctas(): void {
        $admin = (string) file_get_contents( self::FREE . '/includes/admin/class-admin-page.php' );
        $this->assertStringNotContainsString(
            '$49',
            $admin,
            'The upgrade CTAs must quote PRO_PRICE, never a hardcoded price.'
        );
        $this->assertStringNotContainsString(
            'Get Pro — $',
            $admin,
            'CTA label must interpolate the price constant, not inline a literal.'
        );
    }

    public function test_upsell_links_to_divi5lab(): void {
        $admin = (string) file_get_contents( self::FREE . '/includes/admin/class-admin-page.php' );
        $this->assertStringContainsString( 'divi5lab.com/plugins/elementor-to-divi-5', $admin );
        $this->assertStringNotContainsString( 'edc_activate_premium', $admin );
    }
}
