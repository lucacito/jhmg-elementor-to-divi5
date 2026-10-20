<?php
/**
 * The admin-ajax endpoints behind the Conversion Jobs screen.
 *
 * Every endpoint passes one guard: the user must be able to manage options,
 * carry the screen's nonce, and Divi 5 must be present (converting without it
 * writes pages nothing can render). handle() is the testable core — it takes
 * the request as an array and returns one — and dispatch() is the thin
 * admin-ajax wrapper around it.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Helpers\DiviRequirement;
use ElementorDivi5Converter\Jobs\Factory;
use ElementorDivi5Converter\Jobs\JobService;
use ElementorDivi5Converter\Jobs\JobStore;
use ElementorDivi5Converter\Jobs\PendingSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JobsAjax {

    const NONCE_ACTION = 'edc_jobs';
    const CAPABILITY   = 'manage_options';

    /** Action names without the edc_jobs_ prefix. */
    const ACTIONS = [ 'items', 'bar' ];

    const PRO_URL = 'https://divi5lab.com/plugins/elementor-to-divi-5?utm_source=plugin&utm_medium=upsell&utm_campaign=jobs-panel';

    private JobsPage $page;
    private JobService $service;
    private JobStore $store;
    private PendingSource $pending;

    public function __construct( ?JobsPage $page = null, ?JobService $service = null, ?JobStore $store = null, ?PendingSource $pending = null ) {
        $this->store   = $store   ?? Factory::store();
        $this->pending = $pending ?? Factory::pending();
        $this->service = $service ?? Factory::service();
        $this->page    = $page    ?? new JobsPage( $this->store, $this->service, $this->pending );
    }

    public function init(): void {
        foreach ( self::ACTIONS as $action ) {
            add_action( 'wp_ajax_edc_jobs_' . $action, function () use ( $action ) {
                $this->dispatch( $action );
            } );
        }
    }

    /** What assets/js/jobs.js is given. */
    public static function script_config(): array {
        return [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
            'bulkThreshold' => JobService::BULK_THRESHOLD,
            'limit'         => ConversionPreflight::limit(),
            'proUrl'        => self::PRO_URL,
            'i18n'          => [
                'loading'   => __( 'Loading…', 'jhmg-converter-for-elementor-to-divi' ),
                'error'     => __( 'Something went wrong. Please try again.', 'jhmg-converter-for-elementor-to-divi' ),
                'connection' => __( 'The connection dropped. The job is saved — use Resume to continue where it stopped.', 'jhmg-converter-for-elementor-to-divi' ),
            ],
        ];
    }

    private function dispatch( string $action ): void {
        $result = $this->handle( $action, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard()
        $status = (int) ( $result['status'] ?? 200 );
        unset( $result['status'] );

        wp_send_json( $result, $status );
    }

    /** @return array|null An error result, or null when the request may proceed. */
    public function guard( array $request ): ?array {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return $this->error( 'forbidden', __( 'You do not have permission to do that.', 'jhmg-converter-for-elementor-to-divi' ), 403 );
        }
        if ( ! wp_verify_nonce( (string) ( $request['nonce'] ?? '' ), self::NONCE_ACTION ) ) {
            return $this->error( 'bad_nonce', __( 'This page has expired. Reload it and try again.', 'jhmg-converter-for-elementor-to-divi' ), 403 );
        }
        if ( ! DiviRequirement::is_satisfied() ) {
            return $this->error( 'divi_required', DiviRequirement::message(), 409 );
        }

        return null;
    }

    public function handle( string $action, array $request ): array {
        if ( ! in_array( $action, self::ACTIONS, true ) ) {
            return $this->error( 'unknown_action', __( 'Unknown action.', 'jhmg-converter-for-elementor-to-divi' ), 400 );
        }

        $denied = $this->guard( $request );
        if ( $denied !== null ) {
            return $denied;
        }

        return $this->{'handle_' . $action}( $request );
    }

    // ------------------------------------------------------------------
    // Read-only
    // ------------------------------------------------------------------

    private function handle_items( array $request ): array {
        $job_id = (int) ( $request['job_id'] ?? 0 );
        if ( ! $this->store->get_job( $job_id ) ) {
            return $this->error( 'not_found', __( 'That job no longer exists.', 'jhmg-converter-for-elementor-to-divi' ), 404 );
        }

        $state = (string) ( $request['state'] ?? '' );
        $state = in_array( $state, JobStore::STATES, true ) ? $state : null;

        return [
            'ok'      => true,
            'html'    => $this->page->render_items( $job_id, $state, max( 1, (int) ( $request['paged'] ?? 1 ) ) ),
            'summary' => $this->service->summary( $job_id ),
        ];
    }

    private function handle_bar( array $request ): array {
        return [ 'ok' => true, 'bar' => $this->page->bar_data() ];
    }

    private function error( string $code, string $message, int $status, array $extra = [] ): array {
        return array_merge( [ 'ok' => false, 'code' => $code, 'message' => $message, 'status' => $status ], $extra );
    }
}
