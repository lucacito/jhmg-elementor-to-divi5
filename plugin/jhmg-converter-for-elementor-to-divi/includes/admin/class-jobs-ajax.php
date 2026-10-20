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
use ElementorDivi5Converter\Jobs\ConversionFlag;
use ElementorDivi5Converter\Jobs\Factory;
use ElementorDivi5Converter\Jobs\JobService;
use ElementorDivi5Converter\Jobs\JobStore;
use ElementorDivi5Converter\Jobs\PendingQuery;
use ElementorDivi5Converter\Jobs\PendingSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JobsAjax {

    const NONCE_ACTION = 'edc_jobs';
    const CAPABILITY   = 'manage_options';

    /** Action names without the edc_jobs_ prefix. */
    const ACTIONS = [ 'items', 'bar', 'pending', 'create', 'run', 'retry', 'mark', 'job' ];

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
                'converting' => __( 'Converting…', 'jhmg-converter-for-elementor-to-divi' ),
                'busy'      => __( 'Another window is running this job. Waiting…', 'jhmg-converter-for-elementor-to-divi' ),
                'finished'  => __( 'Finished.', 'jhmg-converter-for-elementor-to-divi' ),
                'resume'    => __( 'Resume', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'confirmMark' => __( 'Mark %s pages as converted? They will leave the pending list.', 'jhmg-converter-for-elementor-to-divi' ),
                'chooseMode' => __( 'Choose how the pages should be converted.', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'summary'   => __( '%s pages will be converted.', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'summaryOne' => __( '1 page will be converted.', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'confirmCopy' => __( 'I understand this creates %s new draft pages.', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'confirmReplace' => __( 'I understand this replaces %s live pages and have taken a backup.', 'jhmg-converter-for-elementor-to-divi' ),
                /* translators: %s: number of pages */
                'upsellSelected' => __( 'Convert %s selected pages with Pro', 'jhmg-converter-for-elementor-to-divi' ),
                'upsellNote' => __( 'The free plugin converts one page at a time.', 'jhmg-converter-for-elementor-to-divi' ),
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
            'ui'      => $this->page->progress_ui( $this->store->get_job( $job_id ) ),
        ];
    }

    /** A job's header, buttons and progress, freshly rendered: used after retries and marks change them. */
    private function handle_job( array $request ): array {
        $job = $this->store->get_job( (int) ( $request['job_id'] ?? 0 ) );
        if ( ! $job ) {
            return $this->error( 'not_found', __( 'That job no longer exists.', 'jhmg-converter-for-elementor-to-divi' ), 404 );
        }

        return [ 'ok' => true, 'html' => $this->page->render_job( $job ) ];
    }

    private function handle_bar( array $request ): array {
        return [ 'ok' => true, 'bar' => $this->page->bar_data() ];
    }

    // ------------------------------------------------------------------
    // Pending list
    // ------------------------------------------------------------------

    /** Request post types, narrowed to the ones this screen may touch (default: all of them). */
    private function types_from( array $request ): array {
        $allowed = PendingQuery::allowed_types();
        $types   = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $request['types'] ?? [] ) ), $allowed ) );

        return $types ?: $allowed;
    }

    private function handle_pending( array $request ): array {
        $types = $this->types_from( $request );
        $count = $this->pending->count( $types );

        // The whole section, not just its table: after pages are marked, retried or
        // un-done the section can go from "nothing waiting" (which has no table to
        // swap) to a list, and its failed-pages note changes too.
        if ( ! empty( $request['section'] ) ) {
            return [
                'ok'    => true,
                'count' => $count,
                'html'  => $this->page->render_pending_section( [
                    'types'  => $types,
                    'search' => sanitize_text_field( (string) ( $request['search'] ?? '' ) ),
                    'paged'  => max( 1, (int) ( $request['paged'] ?? 1 ) ),
                ] ),
            ];
        }

        return [
            'ok'    => true,
            'html'  => $this->page->render_pending_table( $types, sanitize_text_field( (string) ( $request['search'] ?? '' ) ), max( 1, (int) ( $request['paged'] ?? 1 ) ) ),
            'count' => $count,
            // The "Convert all N remaining" row depends on the filter: N changes, and
            // so can whether it is a button or the Pro upsell.
            'actions' => $this->page->render_pending_actions( $count, ConversionPreflight::limit() ),
        ];
    }

    // ------------------------------------------------------------------
    // Starting and running jobs
    // ------------------------------------------------------------------

    private function handle_create( array $request ): array {
        $mode      = isset( $request['mode'] ) && $request['mode'] !== '' ? (string) $request['mode'] : null;
        $confirmed = in_array( $request['confirmed'] ?? null, [ '1', 1, true ], true );
        $types     = $this->types_from( $request );

        if ( ! empty( $request['remaining'] ) ) {
            $spec = [ 'remaining' => true, 'post_types' => $types ];
        } else {
            $spec = [ 'post_ids' => $this->eligible_ids( (array) ( $request['post_ids'] ?? [] ) ) ];
        }

        $result = $this->service->create( $spec + [ 'mode' => $mode, 'confirmed' => $confirmed, 'created_by' => get_current_user_id() ] );

        if ( ! $result['ok'] ) {
            if ( $result['code'] === 'pro_required' ) {
                $result['upsell'] = ! empty( $request['remaining'] )
                    ? sprintf( /* translators: %s: number of pages */ __( 'Convert all %s remaining with Pro', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $result['count'] ) )
                    : sprintf( /* translators: %s: number of pages */ __( 'Convert %s selected pages with Pro', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $result['count'] ) );
                $result['proUrl'] = self::PRO_URL;
            }

            return $result + [ 'status' => 200 ];
        }

        return [
            'ok'       => true,
            'job_id'   => $result['job_id'],
            'job_html' => $this->page->render_job( $this->store->get_job( $result['job_id'] ) ),
            'bar'      => $this->page->bar_data(),
        ];
    }

    /** The submitted IDs are never trusted: each must still be a pending Elementor page of an allowed type. */
    private function eligible_ids( array $ids ): array {
        $allowed = PendingQuery::allowed_types();
        $ok      = [];

        foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
            $post = get_post( $id );
            if ( $post
                && in_array( (string) ( $post->post_type ?? '' ), $allowed, true )
                && get_post_meta( $id, '_elementor_edit_mode', true ) === 'builder'
                && ! ConversionFlag::is_converted( $id ) ) {
                $ok[] = $id;
            }
        }

        return $ok;
    }

    private function handle_run( array $request ): array {
        $job_id = (int) ( $request['job_id'] ?? 0 );
        $result = $this->service->run_chunk( $job_id );

        if ( empty( $result['ok'] ) ) {
            return $this->error( 'not_found', __( 'That job no longer exists.', 'jhmg-converter-for-elementor-to-divi' ), 404 );
        }

        return [
            'ok'      => true,
            'busy'    => ! empty( $result['busy'] ),
            'summary' => $result,
            'ui'      => $this->page->progress_ui( $this->store->get_job( $job_id ) ),
            'bar'     => $this->page->bar_data(),
        ];
    }

    // ------------------------------------------------------------------
    // Retry and manual marks
    // ------------------------------------------------------------------

    /** Re-queues failed pages (all of the job's, or just the ones named). The browser then runs the job. */
    private function handle_retry( array $request ): array {
        $job_id = (int) ( $request['job_id'] ?? 0 );
        if ( ! $this->store->get_job( $job_id ) ) {
            return $this->error( 'not_found', __( 'That job no longer exists.', 'jhmg-converter-for-elementor-to-divi' ), 404 );
        }

        $ids = isset( $request['post_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $request['post_ids'] ) ) ) : null;

        return [
            'ok'      => true,
            'retried' => $this->service->retry( $job_id, $ids ),
            'ui'      => $this->page->progress_ui( $this->store->get_job( $job_id ) ),
            'bar'     => $this->page->bar_data(),
        ];
    }

    /** The user fixed these pages by hand (or changed their mind: undo=1). */
    private function handle_mark( array $request ): array {
        $allowed = PendingQuery::allowed_types();
        $ids     = [];
        foreach ( array_unique( array_filter( array_map( 'intval', (array) ( $request['post_ids'] ?? [] ) ) ) ) as $id ) {
            $post = get_post( $id );
            if ( $post && in_array( (string) ( $post->post_type ?? '' ), $allowed, true ) ) {
                $ids[] = $id;
            }
        }

        $undo  = in_array( $request['undo'] ?? null, [ '1', 1, true ], true );
        $count = $undo ? $this->service->unmark_converted( $ids ) : $this->service->mark_converted( $ids );

        return [ 'ok' => true, 'count' => $count, 'bar' => $this->page->bar_data() ];
    }

    private function error( string $code, string $message, int $status, array $extra = [] ): array {
        return array_merge( [ 'ok' => false, 'code' => $code, 'message' => $message, 'status' => $status ], $extra );
    }
}
