<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;

class ImportHistoryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
    }

    private function make_result( bool $ok, int $post_id, array $types = [] ): array {
        return [
            'success'     => $ok,
            'post_id'     => $post_id,
            'unsupported' => array_map(
                fn( $t ) => [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => $t ],
                $types
            ),
        ];
    }

    public function test_records_post_ids_counts_and_distinct_widget_types(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [
            $this->make_result( true, 11, [ 'lottie', 'lottie' ] ),
            $this->make_result( true, 12, [ 'hotspot' ] ),
            $this->make_result( false, 0 ),
        ] );

        $run = $h->find( 'run1' );
        $this->assertSame( [ 11, 12 ], $run['post_ids'] );
        $this->assertSame( 2, $run['succeeded'] );
        $this->assertSame( 1, $run['failed'] );
        $this->assertSame( [ 'lottie', 'hotspot' ], $run['unsupported'] );
        $this->assertFalse( $run['rolled_back'] );
    }

    public function test_falls_back_to_eltype_when_widget_type_is_null(): void {
        $h = new ImportHistory();
        $h->record( 'r', [ [
            'success' => true, 'post_id' => 5,
            'unsupported' => [ [ 'id' => 'a', 'elType' => 'container', 'widgetType' => null ] ],
        ] ] );
        $this->assertSame( [ 'container' ], $h->find( 'r' )['unsupported'] );
    }

    public function test_newest_run_comes_first(): void {
        $h = new ImportHistory();
        $h->record( 'old', [ $this->make_result( true, 1 ) ] );
        $h->record( 'new', [ $this->make_result( true, 2 ) ] );
        $this->assertSame( 'new', $h->all()[0]['id'] );
    }

    public function test_prunes_to_the_most_recent_25_runs(): void {
        $h = new ImportHistory();
        for ( $i = 0; $i < 30; $i++ ) {
            $h->record( "run$i", [ $this->make_result( true, $i + 1 ) ] );
        }
        $all = $h->all();
        $this->assertCount( ImportHistory::MAX_RUNS, $all );
        $this->assertSame( 'run29', $all[0]['id'] );
        $this->assertNull( $h->find( 'run0' ), 'oldest runs are pruned' );
    }

    public function test_coverage_ranks_by_number_of_runs_a_type_appeared_in(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->make_result( true, 1, [ 'lottie', 'hotspot' ] ) ] );
        $h->record( 'b', [ $this->make_result( true, 2, [ 'lottie' ] ) ] );
        $h->record( 'c', [ $this->make_result( true, 3, [ 'lottie' ] ) ] );

        $coverage = $h->coverage();
        $this->assertSame( 'lottie', $coverage[0]['type'] );
        $this->assertSame( 3, $coverage[0]['runs'] );
        $this->assertSame( 'hotspot', $coverage[1]['type'] );
        $this->assertSame( 1, $coverage[1]['runs'] );
    }

    public function test_coverage_is_empty_when_everything_converted(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->make_result( true, 1 ) ] );
        $this->assertSame( [], $h->coverage() );
    }

    public function test_mark_rolled_back_flags_only_that_run(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->make_result( true, 1 ) ] );
        $h->record( 'b', [ $this->make_result( true, 2 ) ] );
        $h->mark_rolled_back( 'a' );
        $this->assertTrue( $h->find( 'a' )['rolled_back'] );
        $this->assertFalse( $h->find( 'b' )['rolled_back'] );
    }
}
