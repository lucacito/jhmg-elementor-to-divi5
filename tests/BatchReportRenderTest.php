<?php
// tests/BatchReportRenderTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\AdminPage;

/**
 * The batch report screen had no rendering test at all, which is how the
 * "Not carried over" section could have been wired in wrongly and still shipped
 * green: NotCarriedOverRenderer's own unit tests pass whether or not any screen
 * calls it.
 *
 * The second thing pinned here is the issue count that gates the "Clean" label.
 * A run whose only losses were dropped animations would otherwise be labelled
 * Clean, with the details sealed inside a panel the label gives nobody a reason
 * to open — the same silence this release set out to remove, one level up.
 */
final class BatchReportRenderTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_postmeta'] = [];
    }

    protected function tearDown(): void {
        $_GET = [];
    }

    /** Renders the batch screen for one result row and returns its HTML. */
    private function render( array $result ): string {
        $import_id = 'testbatch';
        set_transient( 'edc_batch_' . $import_id, [ $result ], 3600 );
        $_GET['import_id'] = $import_id;

        ob_start();
        ( new ReflectionMethod( AdminPage::class, 'render_batch_result' ) )
            ->invoke( new AdminPage() );

        return (string) ob_get_clean();
    }

    private function successfulResult( array $report = [], array $unsupported = [] ): array {
        return [
            'title'       => 'Home',
            'post_id'     => 0,
            'success'     => true,
            'error'       => '',
            'report'      => $report,
            'unsupported' => $unsupported,
        ];
    }

    public function test_the_batch_report_lists_what_could_not_be_carried_over(): void {
        $html = $this->render( $this->successfulResult( [
            'converted'           => [ 'heading' => 1 ],
            'warnings'            => [],
            'not_carried_over'    => [
                [ 'kind' => 'dynamic',     'element_id' => 'abc123', 'detail' => 'title' ],
                [ 'kind' => 'form_fields', 'element_id' => 'ghi789', 'detail' => '4 fields' ],
            ],
            'approximate_matches' => [
                [ 'element_id' => 'jkl012', 'widget_type' => 'odd-box', 'matched_to' => 'IconBoxConverter' ],
            ],
        ] ) );

        $this->assertStringContainsString( 'Not carried over', $html );
        $this->assertStringContainsString( 'abc123', $html );
        $this->assertStringContainsString( '4 fields', $html );
        $this->assertStringContainsString( 'odd-box', $html );
    }

    /**
     * The losses have to open the issues panel as well as appear inside it.
     * Counting only warnings, unsupported and skipped settings left a page whose
     * sole problem was a dropped animation reading as Clean.
     */
    public function test_a_page_whose_only_loss_is_uncarried_content_is_not_clean(): void {
        $html = $this->render( $this->successfulResult( [
            'converted'        => [ 'heading' => 1 ],
            'warnings'         => [],
            'skipped_settings' => [],
            'not_carried_over' => [
                [ 'kind' => 'animation', 'element_id' => 'def456', 'detail' => 'fadeInUp' ],
            ],
        ] ) );

        $this->assertStringNotContainsString( 'edc-status--clean', $html );
        $this->assertStringContainsString( 'Not carried over', $html );
        $this->assertStringContainsString( 'def456', $html );
    }

    public function test_an_unresolved_global_also_opens_the_issues_panel(): void {
        $html = $this->render( $this->successfulResult( [
            'converted'          => [ 'heading' => 1 ],
            'warnings'           => [],
            'unresolved_globals' => [
                [ 'element_id' => 'mno345', 'setting_key' => 'title_color', 'ref' => 'globals/colors?id=primary' ],
            ],
        ] ) );

        $this->assertStringNotContainsString( 'edc-status--clean', $html );
        $this->assertStringContainsString( 'mno345', $html );
        $this->assertStringContainsString( 'title_color', $html );
    }

    public function test_a_genuinely_clean_page_is_still_labelled_clean(): void {
        $html = $this->render( $this->successfulResult( [
            'converted'        => [ 'heading' => 1 ],
            'warnings'         => [],
            'skipped_settings' => [],
        ] ) );

        $this->assertStringContainsString( 'edc-status--clean', $html );
        $this->assertStringNotContainsString( 'Not carried over', $html );
    }
}
