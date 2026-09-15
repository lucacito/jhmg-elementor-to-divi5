<?php
// tests/AddonSettingNamesSweepTest.php
use PHPUnit\Framework\TestCase;

/**
 * Converters must not read setting names their add-on never defines — every
 * such name is content a conversion silently drops. See
 * scripts/addon-setting-names.php.
 */
final class AddonSettingNamesSweepTest extends TestCase {

    public static function setUpBeforeClass(): void {
        $script = __DIR__ . '/../scripts/addon-setting-names.php';
        if ( is_file( $script ) ) {
            require_once $script;
        }
    }

    public function test_a_fallback_chain_is_read_as_one_read(): void {
        $php = '<?php $a = $settings[\'eael_new_name\'] ?? $settings[\'eael_old_name\'] ?? \'\'; $b = $item[\'single\'];';

        $this->assertSame( [ [ 'eael_new_name', 'eael_old_name' ], [ 'single' ] ], asn_reads( $php ) );
    }

    public function test_no_converter_reads_a_name_essential_addons_or_hfe_does_not_define(): void {
        $root = dirname( __DIR__ );

        $gaps = addon_setting_name_gaps( $root, [
            $root . '/references/essential-addons-for-elementor-lite.6.6.7.zip',
            $root . '/references/header-footer-elementor.2.8.8.zip',
        ] );

        $this->assertSame( [], $gaps, 'Converters read setting names the add-on never defines: ' . json_encode( $gaps, JSON_PRETTY_PRINT ) );
    }
}
