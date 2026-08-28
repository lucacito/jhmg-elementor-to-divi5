<?php
// tests/ReviewPromptTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\ReviewPrompt;

/**
 * Asks for a wp.org review only once someone has actually got value out of the
 * plugin: three successful conversions (Lucas, 2026-08-28 — "after a few pages"),
 * on the results screen, and never after a run that failed.
 */
class ReviewPromptTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    protected function setUp(): void {
        $GLOBALS['__test_options']      = [];
        $GLOBALS['__test_user_meta']    = [];
        $GLOBALS['__test_current_user'] = 1;
        $GLOBALS['__test_caps']         = true;
        edc_test_reset_hooks();
    }

    private function ok( int $n ): array {
        return array_fill( 0, $n, [ 'success' => true ] );
    }

    public function test_counts_only_successful_conversions(): void {
        $p = new ReviewPrompt();
        $p->record_run( [ [ 'success' => true ], [ 'success' => false ], [ 'success' => true ] ] );
        $this->assertSame( 2, $p->conversion_count() );
    }

    public function test_counter_accumulates_across_runs(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 2 ) );
        $p->record_run( $this->ok( 2 ) );
        $this->assertSame( 4, $p->conversion_count() );
    }

    public function test_silent_below_threshold(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 2 ) );
        $this->assertFalse( $p->should_ask( $this->ok( 2 ) ) );
    }

    public function test_asks_once_threshold_is_reached(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 3 ) );
        $this->assertTrue( $p->should_ask( $this->ok( 3 ) ) );
    }

    public function test_never_asks_after_a_run_with_failures(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 10 ) );
        $failed_run = [ [ 'success' => true ], [ 'success' => false ] ];
        $this->assertFalse(
            $p->should_ask( $failed_run ),
            'a run that failed anything must never trigger the ask'
        );
    }

    public function test_threshold_is_filterable(): void {
        add_filter( 'edc_review_prompt_threshold', fn() => 1 );
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 1 ) );
        $this->assertTrue( $p->should_ask( $this->ok( 1 ) ) );
    }

    public function test_dismiss_forever_silences_it(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 5 ) );
        $p->set_state( ReviewPrompt::STATE_DONE );
        $this->assertFalse( $p->should_ask( $this->ok( 5 ) ) );
    }

    public function test_snooze_hides_it_then_it_returns(): void {
        $p = new ReviewPrompt( '2026-09-01' );
        $p->record_run( $this->ok( 5 ) );
        $p->snooze();
        $this->assertFalse( $p->should_ask( $this->ok( 5 ) ), 'snoozed for 14 days' );

        $later = new ReviewPrompt( '2026-09-20' );
        $this->assertTrue( $later->should_ask( $this->ok( 5 ) ), 'returns after the snooze lapses' );
    }

    public function test_state_is_per_user(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 5 ) );
        $p->set_state( ReviewPrompt::STATE_DONE );

        $GLOBALS['__test_current_user'] = 2;
        $this->assertTrue( $p->should_ask( $this->ok( 5 ) ), 'a different admin has their own state' );
    }

    public function test_hidden_without_manage_options(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 5 ) );
        $GLOBALS['__test_caps'] = false;
        $this->assertFalse( $p->should_ask( $this->ok( 5 ) ) );
    }

    public function test_markup_links_to_wporg_reviews_and_offers_all_three_choices(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 3 ) );
        $html = $p->markup();
        $this->assertStringContainsString( ReviewPrompt::QUERY_ACTION, $html );
        $this->assertStringContainsString( '3', $html, 'names how many pages were converted' );
        $this->assertStringContainsString( 'review', $html );
        $this->assertStringContainsString( 'later', $html );
        $this->assertStringContainsString( 'done', $html );
    }

    public function test_clicking_review_records_it_before_handing_off_to_wporg(): void {
        $p = new class extends ReviewPrompt {
            public bool $redirected = false;
            protected function redirect_to_review(): void { $this->redirected = true; }
        };
        $p->record_run( $this->ok( 3 ) );

        $_GET[ ReviewPrompt::QUERY_ACTION ] = 'review';
        $_GET['_wpnonce'] = wp_create_nonce( ReviewPrompt::NONCE_ACTION );
        $p->maybe_handle_response();

        $this->assertTrue( $p->redirected, 'should hand off to the wp.org review form' );
        $this->assertFalse(
            $p->should_ask( $this->ok( 3 ) ),
            'someone who clicked through to review must never be asked again'
        );
    }

    public function test_response_requires_a_valid_nonce(): void {
        $p = new ReviewPrompt();
        $p->record_run( $this->ok( 3 ) );
        $_GET[ ReviewPrompt::QUERY_ACTION ] = 'done';
        $_GET['_wpnonce'] = 'forged';
        $p->maybe_handle_response();
        $this->assertTrue( $p->should_ask( $this->ok( 3 ) ), 'a forged nonce must not change state' );
    }

    public function test_review_url_points_at_the_wporg_review_form(): void {
        $this->assertStringContainsString(
            'wordpress.org/support/plugin/jhmg-converter-for-elementor-to-divi/reviews/',
            ReviewPrompt::REVIEW_URL
        );
    }

    public function test_copy_offers_no_incentive(): void {
        $src = (string) file_get_contents( self::FREE . '/includes/admin/class-review-prompt.php' );
        foreach ( [ 'discount', 'coupon', 'free Pro', 'in exchange', 'reward' ] as $bribe ) {
            $this->assertStringNotContainsStringIgnoringCase(
                $bribe,
                $src,
                "offering anything for a review breaks wp.org guidelines (found: $bribe)"
            );
        }
    }
}
