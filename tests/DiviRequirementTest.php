<?php
// tests/DiviRequirementTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Helpers\DiviRequirement;

/**
 * Neither plugin checked that Divi was present, or which version was running.
 *
 * The converter writes Divi 5 block content and stamps `_et_pb_use_divi_5 = on`.
 * On Divi 4, or with no Divi at all, that markup renders as nothing: no error,
 * no warning, just a blank page and a report saying it converted cleanly.
 */
final class DiviRequirementTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    protected function tearDown(): void {
        edc_test_reset_divi();
    }

    // -------------------------------------------------------------------------
    // Detection
    // -------------------------------------------------------------------------

    public function test_a_divi_5_site_satisfies_the_requirement(): void {
        $GLOBALS['__test_divi_version'] = '5.7.4';

        $this->assertTrue( DiviRequirement::is_satisfied() );
        $this->assertSame( '', DiviRequirement::failure_reason() );
        $this->assertSame( '', DiviRequirement::message() );
    }

    public function test_a_site_without_divi_fails(): void {
        $GLOBALS['__test_divi_present'] = false;

        $this->assertFalse( DiviRequirement::is_satisfied() );
        $this->assertSame( 'missing', DiviRequirement::failure_reason() );
        $this->assertStringContainsString( 'no Divi installation was found', DiviRequirement::message() );
    }

    public function test_divi_4_fails_as_too_old(): void {
        $GLOBALS['__test_divi_version'] = '4.27.4';

        $this->assertFalse( DiviRequirement::is_satisfied() );
        $this->assertSame( 'too_old', DiviRequirement::failure_reason() );

        $message = DiviRequirement::message();
        $this->assertStringContainsString( '4.27.4', $message );
        $this->assertStringContainsString( DiviRequirement::MINIMUM_DIVI_VERSION, $message );
    }

    public function test_exactly_the_minimum_is_accepted(): void {
        $GLOBALS['__test_divi_version'] = DiviRequirement::MINIMUM_DIVI_VERSION;

        $this->assertTrue( DiviRequirement::is_satisfied() );
    }

    /**
     * Divi shipped its 5.0 line as builds like '5.0.0-public-alpha.18.2'. PHP
     * ranks that ABOVE '5.0.0' — "public" is not one of the qualifiers
     * version_compare() sorts below a release — so the floor does not lock out
     * anyone still running one. Pinned because it is surprising enough that a
     * future reader would otherwise "fix" it.
     */
    public function test_a_public_alpha_build_is_not_locked_out(): void {
        $GLOBALS['__test_divi_version'] = '5.0.0-public-alpha.18.2';

        $this->assertTrue( DiviRequirement::is_satisfied() );
    }

    public function test_a_conventional_prerelease_below_the_floor_is_rejected(): void {
        $GLOBALS['__test_divi_version'] = '5.0.0-alpha.1';

        $this->assertFalse( DiviRequirement::is_satisfied() );
    }

    // -------------------------------------------------------------------------
    // The guard actually stops work
    // -------------------------------------------------------------------------

    public function test_direct_conversion_does_not_run_without_divi(): void {
        $GLOBALS['__test_divi_present'] = false;

        $page = new class extends DirectConversionPage {
            public bool $handled = false;
            protected function handle_check(): void { $this->handled = true; }
            protected function handle_convert(): void { $this->handled = true; }
            protected function redirect( string $location ): void {}
        };

        $_POST = [ 'action' => DirectConversionPage::CHECK_ACTION ];
        $page->maybe_handle_request();
        $_POST = [];

        $this->assertFalse( $page->handled, 'No conversion may start without a Divi that can render it' );
    }

    public function test_direct_conversion_runs_when_divi_is_present(): void {
        $GLOBALS['__test_divi_version'] = '5.7.4';

        $page = new class extends DirectConversionPage {
            public bool $handled = false;
            protected function handle_check(): void { $this->handled = true; }
            protected function handle_convert(): void { $this->handled = true; }
            protected function redirect( string $location ): void {}
        };

        $_POST = [ 'action' => DirectConversionPage::CHECK_ACTION ];
        $page->maybe_handle_request();
        $_POST = [];

        $this->assertTrue( $page->handled );
    }

    // -------------------------------------------------------------------------
    // Activation
    // -------------------------------------------------------------------------

    public function test_activation_records_a_failure_for_the_next_admin_load(): void {
        $GLOBALS['__test_divi_present'] = false;

        DiviRequirement::on_activation();

        $this->assertSame( 'missing', get_option( DiviRequirement::ACTIVATION_NOTICE_OPTION ) );
    }

    public function test_activation_clears_a_stale_failure_once_divi_is_installed(): void {
        update_option( DiviRequirement::ACTIVATION_NOTICE_OPTION, 'missing' );
        $GLOBALS['__test_divi_version'] = '5.7.4';

        DiviRequirement::on_activation();

        $this->assertFalse( get_option( DiviRequirement::ACTIVATION_NOTICE_OPTION ) );
    }

    // -------------------------------------------------------------------------
    // Notice
    // -------------------------------------------------------------------------

    public function test_the_notice_renders_only_when_something_is_wrong(): void {
        $GLOBALS['__test_divi_version'] = '5.7.4';

        ob_start();
        DiviRequirement::render_notice();
        $this->assertSame( '', ob_get_clean() );

        $GLOBALS['__test_divi_present'] = false;

        ob_start();
        DiviRequirement::render_notice();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'notice-error', $html );
        $this->assertStringContainsString( 'Divi', $html );
    }

    // -------------------------------------------------------------------------
    // Pro reaches the converter through its own handlers
    // -------------------------------------------------------------------------

    private function proKitPage(): object {
        return new class extends \ElementorDivi5Converter\Pro\Admin\KitPage {
            public array $ran = [];
            public function __construct() {}
            protected function handle_import(): void { $this->ran[] = 'import'; }
            protected function handle_convert_kit_pages(): void { $this->ran[] = 'convert_kit'; }
            protected function handle_upload_kit(): void { $this->ran[] = 'upload_kit'; }
        };
    }

    private function postToPro( object $page, string $action ): void {
        $_GET  = [ 'page' => \ElementorDivi5Converter\Pro\Admin\KitPage::MENU_SLUG ];
        $_POST = [ 'action' => $action ];
        $page->handle_post();
        $_GET  = [];
        $_POST = [];
    }

    /**
     * Pro's kit page does not route through free's AdminPage::handle_post(), so
     * free's guard never sees its converting actions. Without a check of its
     * own, every paying customer could still write Divi 5 blocks into a site
     * that cannot render them.
     */
    public function test_pro_does_not_convert_without_divi(): void {
        $GLOBALS['__test_divi_present'] = false;

        foreach ( [ 'edcp_import', 'edcp_convert_kit_pages' ] as $action ) {
            $page = $this->proKitPage();
            $this->postToPro( $page, $action );

            $this->assertSame( [], $page->ran, "'{$action}' must not run without a Divi that can render the result" );
        }
    }

    public function test_pro_converts_normally_when_divi_is_present(): void {
        $GLOBALS['__test_divi_version'] = '5.7.4';

        $page = $this->proKitPage();
        $this->postToPro( $page, 'edcp_import' );

        $this->assertSame( [ 'import' ], $page->ran );
    }

    /**
     * Only the two converting actions are gated. Storing a kit is not a
     * conversion, and locking someone out of the rest of the plugin because
     * their theme is wrong would be its own bug.
     */
    public function test_pro_still_accepts_a_kit_upload_without_divi(): void {
        $GLOBALS['__test_divi_present'] = false;

        $page = $this->proKitPage();
        $this->postToPro( $page, 'edcp_upload_kit' );

        $this->assertSame( [ 'upload_kit' ], $page->ran );
    }
}
