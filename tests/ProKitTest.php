<?php
// tests/ProKitTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Pro\Kit\GlobalsStore;
use ElementorDivi5Converter\StyleMapper\GlobalsResolver;

class ProKitTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        GlobalsStore::clear();
    }

    public function test_pro_wires_kit_globals_into_free_resolver(): void {
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();
        GlobalsStore::save( [ 'kitcolor1' => '#123456' ], [], 'test-kit' );
        $this->assertSame( '#123456', GlobalsResolver::resolveColor( 'kitcolor1' ) );
    }

    public function test_pro_supplies_theme_builder_exporter(): void {
        \ElementorDivi5Converter\Pro\Plugin::instance()->register_hooks();
        $exporter = apply_filters( 'edc_theme_builder_exporter', null );
        $this->assertInstanceOf( \ElementorDivi5Converter\Pro\Exporters\DiviThemeBuilderExporter::class, $exporter );
    }

    /**
     * Free's AdminPage::handle_post() hooks admin_init unscoped and dispatches
     * purely on $_POST['action'] / $_GET['edc_action'] — and free's plugins_loaded
     * callback runs before Pro's (priority 10 vs 20). If Pro reused free's
     * action/nonce strings, every form on Pro's own page would be intercepted by
     * free first (e.g. Pro's ZIP upload would wp_die on free's Premium gate).
     * Pro must use edcp_-prefixed identifiers that never collide with free's.
     *
     * Free's kit/premium nonce constants (KIT_NONCE_*, KIT_CONVERT_NONCE_*) were
     * removed entirely in the free-plugin surgery (Task 5) — kit dispatch is now
     * Pro-only, so there is no free-side identifier left to collide with. Only
     * IMPORT_NONCE_* still exists on both sides and is checked for divergence.
     */
    public function test_kit_page_dispatch_identifiers_use_edcp_prefix_and_do_not_collide_with_free(): void {
        $kit_page = \ElementorDivi5Converter\Pro\Admin\KitPage::class;
        $free     = \ElementorDivi5Converter\Admin\AdminPage::class;

        $pairs = [
            [ $kit_page::IMPORT_NONCE_ACTION, $free::IMPORT_NONCE_ACTION ],
            [ $kit_page::IMPORT_NONCE_NAME,   $free::IMPORT_NONCE_NAME ],
        ];

        foreach ( $pairs as [ $pro_value, $free_value ] ) {
            $this->assertStringStartsWith( 'edcp_', $pro_value );
            $this->assertNotSame( $free_value, $pro_value );
        }

        $free_reflection = new \ReflectionClass( $free );
        foreach ( [ 'KIT_NONCE_ACTION', 'KIT_NONCE_NAME', 'KIT_CONVERT_NONCE_ACTION', 'KIT_CONVERT_NONCE_NAME' ] as $removed_const ) {
            $this->assertFalse( $free_reflection->hasConstant( $removed_const ), "AdminPage still defines {$removed_const}" );
        }

        $this->assertSame( 'edcp_import', $kit_page::IMPORT_NONCE_ACTION );
        $this->assertSame( 'edcp_upload_kit', $kit_page::KIT_NONCE_ACTION );
        $this->assertSame( 'edcp_convert_kit_pages', $kit_page::KIT_CONVERT_NONCE_ACTION );

        // The remaining dispatch strings are echoed inline (GET dispatcher param,
        // clear-kit + publish nonce actions) — assert none of free's identifiers
        // survive in the source and the edcp_ replacements are present.
        $source = file_get_contents( ( new \ReflectionClass( $kit_page ) )->getFileName() );
        $this->assertStringNotContainsString( "'edc_action'", $source );
        $this->assertStringNotContainsString( "'edc_clear_kit'", $source );
        $this->assertStringNotContainsString( "'edc_publish_'", $source );
        $this->assertStringContainsString( "'edcp_action'", $source );
        $this->assertStringContainsString( "'edcp_clear_kit'", $source );
        $this->assertStringContainsString( "'edcp_publish_'", $source );
    }
}
