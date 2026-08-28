<?php
// tests/CoveragePanelTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\CoveragePanel;
use ElementorDivi5Converter\History\ImportHistory;

class CoveragePanelTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_caps']    = true;
    }

    private function record( ImportHistory $h, string $id, array $types ): void {
        $h->record( $id, [ [
            'success' => true, 'post_id' => 1,
            'unsupported' => array_map(
                fn( $t ) => [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => $t ],
                $types
            ),
        ] ] );
    }

    public function test_renders_nothing_before_any_import(): void {
        $this->assertSame( '', ( new CoveragePanel( new ImportHistory() ) )->markup() );
    }

    public function test_lists_unsupported_types_ranked(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [ 'lottie', 'hotspot' ] );
        $this->record( $h, 'b', [ 'lottie' ] );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'lottie', $html );
        $this->assertStringContainsString( 'hotspot', $html );
        $this->assertLessThan(
            strpos( $html, 'hotspot' ),
            strpos( $html, 'lottie' ),
            'the type that broke more runs must be listed first'
        );
    }

    public function test_celebrates_full_coverage(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [] );
        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Everything converted', $html );
    }

    public function test_escapes_widget_type_names(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [ '<script>alert(1)</script>' ] );
        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_lists_recent_imports_with_an_undo_control(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [ [ 'success' => true, 'post_id' => 7, 'unsupported' => [] ] ] );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Recent imports', $html );
        $this->assertStringContainsString( \ElementorDivi5Converter\History\ImportRollback::QUERY_ACTION, $html );
        $this->assertStringContainsString( 'run1', $html );
    }

    public function test_rolled_back_runs_show_no_undo_control(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [ [ 'success' => true, 'post_id' => 7, 'unsupported' => [] ] ] );
        $h->mark_rolled_back( 'run1' );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Undone', $html );
        $this->assertStringNotContainsString(
            \ElementorDivi5Converter\History\ImportRollback::QUERY_ACTION,
            $html,
            'an already-undone run must not offer Undo again'
        );
    }
}
