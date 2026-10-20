<?php
/**
 * Tools → Conversion Jobs.
 *
 * Built so a customer converting thousands of pages over many sessions can
 * always see where they stand: a summary bar that never leaves the screen,
 * the pages still waiting, and every past run — finished ones folded away,
 * unfinished ones open — each reopenable down to the individual page.
 *
 * Server-rendered PHP with a little jQuery (assets/js/jobs.js) for progress
 * and lazy loading; no build step, no framework.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;
use ElementorDivi5Converter\Jobs\Factory;
use ElementorDivi5Converter\Jobs\JobService;
use ElementorDivi5Converter\Jobs\JobStore;
use ElementorDivi5Converter\Jobs\PendingQuery;
use ElementorDivi5Converter\Jobs\PendingSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JobsPage {

    /** Contains "edc-converter", so AdminPage's stylesheet already covers this screen. */
    const SLUG          = 'edc-converter-jobs';
    const JOBS_PER_PAGE = 20;
    const ITEMS_PER_PAGE = 50;
    const PENDING_PER_PAGE = 50;

    const STATUS_FILTERS = [ 'pending', 'running', 'interrupted', 'done', 'done_errors' ];

    private JobStore $store;
    private JobService $service;
    private PendingSource $pending;

    public function __construct( ?JobStore $store = null, ?JobService $service = null, ?PendingSource $pending = null ) {
        $this->store   = $store   ?? Factory::store();
        $this->pending = $pending ?? Factory::pending();
        $this->service = $service ?? Factory::service();
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu(): void {
        add_management_page(
            __( 'Conversion Jobs', 'jhmg-converter-for-elementor-to-divi' ),
            __( 'Conversion Jobs', 'jhmg-converter-for-elementor-to-divi' ),
            'manage_options',
            self::SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::SLUG ) === false ) {
            return;
        }

        wp_enqueue_style( 'edc-jobs', EDC_PLUGIN_URL . 'assets/css/jobs.css', [], EDC_PLUGIN_VERSION );
        wp_enqueue_script( 'edc-jobs', EDC_PLUGIN_URL . 'assets/js/jobs.js', [ 'jquery' ], EDC_PLUGIN_VERSION, true );
        wp_localize_script( 'edc-jobs', 'edcJobs', JobsAjax::script_config() );
    }

    // ------------------------------------------------------------------
    // The screen
    // ------------------------------------------------------------------

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        echo '<div class="wrap edc-wrap edc-jobs-wrap"><h1>' . esc_html__( 'Conversion Jobs', 'jhmg-converter-for-elementor-to-divi' ) . '</h1>';

        if ( ! \ElementorDivi5Converter\Helpers\DiviRequirement::is_satisfied() ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( \ElementorDivi5Converter\Helpers\DiviRequirement::message() ) . '</p></div></div>';

            return;
        }

        $filters = self::filters_from( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters
        $paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Every fragment below escapes at the point of use.
        echo $this->render_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->render_pending_section( [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->render_jobs_section( $filters, $paged ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    // ------------------------------------------------------------------
    // Sticky summary bar
    // ------------------------------------------------------------------

    /** @return array{converted:int,pending:int,failed:int} */
    public function bar_data(): array {
        return [
            'converted' => $this->pending->converted_count(),
            'pending'   => $this->pending->count( PendingQuery::allowed_types() ),
            'failed'    => $this->store->count_failed_posts(),
        ];
    }

    public function render_bar(): string {
        $d = $this->bar_data();

        $stat = static fn( string $key, int $n, string $label ): string => sprintf(
            '<span class="edc-bar__stat edc-bar__stat--%1$s"><strong data-edc-bar="%1$s">%2$s</strong> %3$s</span>',
            esc_attr( $key ), esc_html( number_format_i18n( $n ) ), esc_html( $label )
        );

        return '<div class="edc-bar" id="edc-bar" role="status" aria-live="polite">'
            . $stat( 'converted', $d['converted'], __( 'converted', 'jhmg-converter-for-elementor-to-divi' ) )
            . $stat( 'pending', $d['pending'], __( 'pending', 'jhmg-converter-for-elementor-to-divi' ) )
            . $stat( 'failed', $d['failed'], __( 'failed', 'jhmg-converter-for-elementor-to-divi' ) )
            . '<span class="edc-bar__live" aria-live="polite"></span>'
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Jobs list
    // ------------------------------------------------------------------

    /** Whitelists request filters: anything unrecognised is dropped, never trusted. */
    public static function filters_from( array $request ): array {
        $filters = [];

        $status = sanitize_key( (string) ( $request['status'] ?? '' ) );
        if ( in_array( $status, self::STATUS_FILTERS, true ) ) {
            $filters['status'] = $status;
        }

        $post_type = sanitize_key( (string) ( $request['post_type'] ?? '' ) );
        if ( $post_type !== '' ) {
            $filters['post_type'] = $post_type;
        }

        foreach ( [ 'date_from', 'date_to' ] as $key ) {
            $value = (string) ( $request[ $key ] ?? '' );
            if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
                $filters[ $key ] = $value;
            }
        }

        return $filters;
    }

    public function render_jobs_section( array $filters, int $paged ): string {
        return '<div class="edc-card edc-jobs"><h2>' . esc_html__( 'Jobs', 'jhmg-converter-for-elementor-to-divi' ) . '</h2>'
            . $this->render_filter_form( $filters )
            . '<div id="edc-jobs-list">' . $this->render_jobs_list( $filters, $paged ) . '</div></div>';
    }

    private function render_filter_form( array $f ): string {
        $statuses = [
            ''            => __( 'Any status', 'jhmg-converter-for-elementor-to-divi' ),
            'pending'     => __( 'Waiting to start', 'jhmg-converter-for-elementor-to-divi' ),
            'running'     => __( 'Running', 'jhmg-converter-for-elementor-to-divi' ),
            'interrupted' => __( 'Interrupted', 'jhmg-converter-for-elementor-to-divi' ),
            'done'        => __( 'Done', 'jhmg-converter-for-elementor-to-divi' ),
            'done_errors' => __( 'Done with errors', 'jhmg-converter-for-elementor-to-divi' ),
        ];

        $html  = '<form method="get" class="edc-jobs-filters"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
        $html .= '<select name="status" aria-label="' . esc_attr__( 'Status', 'jhmg-converter-for-elementor-to-divi' ) . '">';
        foreach ( $statuses as $value => $label ) {
            $html .= '<option value="' . esc_attr( $value ) . '"' . ( ( $f['status'] ?? '' ) === $value ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
        }
        $html .= '</select><select name="post_type" aria-label="' . esc_attr__( 'Post type', 'jhmg-converter-for-elementor-to-divi' ) . '"><option value="">' . esc_html__( 'Any post type', 'jhmg-converter-for-elementor-to-divi' ) . '</option>';
        foreach ( PendingQuery::allowed_types() as $type ) {
            $html .= '<option value="' . esc_attr( $type ) . '"' . ( ( $f['post_type'] ?? '' ) === $type ? ' selected' : '' ) . '>' . esc_html( $type ) . '</option>';
        }
        $html .= '</select>';
        $html .= '<label>' . esc_html__( 'From', 'jhmg-converter-for-elementor-to-divi' ) . ' <input type="date" name="date_from" value="' . esc_attr( $f['date_from'] ?? '' ) . '"></label>';
        $html .= '<label>' . esc_html__( 'To', 'jhmg-converter-for-elementor-to-divi' ) . ' <input type="date" name="date_to" value="' . esc_attr( $f['date_to'] ?? '' ) . '"></label>';
        $html .= '<button type="submit" class="button">' . esc_html__( 'Filter', 'jhmg-converter-for-elementor-to-divi' ) . '</button>';
        if ( $f ) {
            $html .= ' <a href="' . esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'tools.php' ) ) ) . '">' . esc_html__( 'Reset', 'jhmg-converter-for-elementor-to-divi' ) . '</a>';
        }

        return $html . '</form>';
    }

    /** Newest first. */
    public function render_jobs_list( array $filters, int $paged ): string {
        $store_filters = $this->store_filters( $filters );
        $jobs          = $this->store->list_jobs( $store_filters, self::JOBS_PER_PAGE, ( max( 1, $paged ) - 1 ) * self::JOBS_PER_PAGE );

        if ( ! $jobs ) {
            return '<p class="edc-empty">' . esc_html( $filters
                ? __( 'No jobs match these filters.', 'jhmg-converter-for-elementor-to-divi' )
                : __( 'No conversion jobs yet. Pick pages from the pending list to start one.', 'jhmg-converter-for-elementor-to-divi' )
            ) . '</p>';
        }

        $html = '';
        foreach ( $jobs as $job ) {
            $html .= $this->render_job( $job );
        }

        $total = $this->store->count_jobs( $store_filters );

        return $html . $this->render_jobs_pager( $filters, $paged, $total );
    }

    /** "interrupted" and "running" are one stored status told apart by the heartbeat. */
    private function store_filters( array $f ): array {
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - JobService::STALE_AFTER );

        if ( ( $f['status'] ?? '' ) === 'interrupted' ) {
            $f['status']               = 'running';
            $f['heartbeat_older_than'] = $cutoff;
        } elseif ( ( $f['status'] ?? '' ) === 'running' ) {
            $f['heartbeat_newer_than'] = $cutoff;
        }

        return $f;
    }

    private function render_jobs_pager( array $filters, int $paged, int $total ): string {
        $pages = (int) ceil( $total / self::JOBS_PER_PAGE );
        if ( $pages <= 1 ) {
            return '';
        }

        $link = static fn( int $p, string $label ): string => '<a class="button" href="' . esc_url( add_query_arg( array_merge( $filters, [ 'page' => self::SLUG, 'paged' => $p ] ), admin_url( 'tools.php' ) ) ) . '">' . esc_html( $label ) . '</a> ';

        return '<p class="edc-pager">'
            . ( $paged > 1 ? $link( $paged - 1, '« ' . __( 'Newer', 'jhmg-converter-for-elementor-to-divi' ) ) : '' )
            . ( $paged < $pages ? $link( $paged + 1, __( 'Older', 'jhmg-converter-for-elementor-to-divi' ) . ' »' ) : '' )
            . '</p>';
    }

    // ------------------------------------------------------------------
    // One job
    // ------------------------------------------------------------------

    public function render_job( array $job ): string {
        $status     = $this->service->effective_status( $job );
        $unfinished = in_array( $status, [ 'running', 'pending', 'interrupted' ], true );
        $ui         = $this->progress_ui( $job );
        $percent    = $ui['percent'];

        $html  = sprintf( '<details class="edc-job edc-job--%1$s" data-job-id="%2$d" data-status="%1$s"%3$s>', esc_attr( $status ), (int) $job['id'], $unfinished ? ' open' : '' );
        $html .= '<summary>';
        $html .= '<span class="edc-job__title">' . esc_html( sprintf( /* translators: %d: job number */ __( 'Job #%d', 'jhmg-converter-for-elementor-to-divi' ), $job['id'] ) ) . '</span> ';
        $html .= '<span class="edc-job__status edc-job__status--' . esc_attr( $status ) . '">' . esc_html( $this->status_label( $status ) ) . '</span> ';
        $html .= '<span class="edc-job__date">' . esc_html( get_date_from_gmt( $job['created_at'], 'Y-m-d H:i' ) ) . '</span> ';
        $html .= '<span class="edc-job__scope">' . esc_html( $this->scope_label( $job ) ) . '</span> ';
        $html .= '<span class="edc-job__progress"><span class="edc-progress"><span class="edc-progress__fill" style="width:' . (int) $percent . '%"></span></span> ';
        $html .= '<span class="edc-job__counts">' . esc_html( $ui['counts'] ) . '</span>';
        $html .= ' <span class="edc-job__failed">' . esc_html( $ui['failed'] ) . '</span>';
        $html .= '</span></summary>';

        $html .= '<div class="edc-job__body">' . $this->render_job_actions( $job, $status );
        $html .= '<p class="edc-job__filter">' . $this->render_state_filters() . '</p>';
        $html .= '<div class="edc-job__items" data-job-id="' . (int) $job['id'] . '"></div>';
        $html .= '</div></details>';

        return $html;
    }

    /**
     * What the progress line of a job shows. Shared by the server-rendered row
     * and the run endpoint, so what JS paints is what a reload would show.
     *
     * @return array{percent:int,counts:string,failed:string,status:string,status_label:string}
     */
    public function progress_ui( array $job ): array {
        $status   = $this->service->effective_status( $job );
        $finished = $job['converted'] + $job['skipped'];

        return [
            // Integer maths: floor( 158 / 200 * 100 ) can come out as 78.
            'percent'      => $job['total'] > 0 ? intdiv( $finished * 100, $job['total'] ) : 0,
            'counts'       => sprintf( /* translators: 1: pages finished, 2: total pages */ __( '%1$s of %2$s', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $finished ), number_format_i18n( $job['total'] ) ),
            'failed'       => $job['failed'] > 0 ? sprintf( /* translators: %s: failed pages */ __( '%s failed', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $job['failed'] ) ) : '',
            'status'       => $status,
            'status_label' => $this->status_label( $status ),
        ];
    }

    private function status_label( string $status ): string {
        return [
            'pending'     => __( 'Waiting to start', 'jhmg-converter-for-elementor-to-divi' ),
            'running'     => __( 'Running', 'jhmg-converter-for-elementor-to-divi' ),
            'interrupted' => __( 'Interrupted', 'jhmg-converter-for-elementor-to-divi' ),
            'done'        => __( 'Done', 'jhmg-converter-for-elementor-to-divi' ),
            'done_errors' => __( 'Done with errors', 'jhmg-converter-for-elementor-to-divi' ),
        ][ $status ] ?? $status;
    }

    private function scope_label( array $job ): string {
        $types = implode( ', ', $job['scope']['post_types'] ?? [] );
        $what  = ( $job['scope']['type'] ?? '' ) === 'remaining'
            /* translators: %s: post types, e.g. "page, post" */
            ? sprintf( __( 'All remaining (%s)', 'jhmg-converter-for-elementor-to-divi' ), $types )
            /* translators: 1: number of pages, 2: post types */
            : sprintf( __( '%1$d selected (%2$s)', 'jhmg-converter-for-elementor-to-divi' ), $job['total'], $types );
        $how   = $job['mode'] === 'replace'
            ? __( 'originals replaced', 'jhmg-converter-for-elementor-to-divi' )
            : __( 'copies created', 'jhmg-converter-for-elementor-to-divi' );

        return $what . ' · ' . $how;
    }

    private function render_state_filters(): string {
        $states = [
            ''          => __( 'All', 'jhmg-converter-for-elementor-to-divi' ),
            'converted' => __( 'Converted', 'jhmg-converter-for-elementor-to-divi' ),
            'skipped'   => __( 'Skipped', 'jhmg-converter-for-elementor-to-divi' ),
            'failed'    => __( 'Failed', 'jhmg-converter-for-elementor-to-divi' ),
            'pending'   => __( 'Pending', 'jhmg-converter-for-elementor-to-divi' ),
        ];
        $html = '';
        foreach ( $states as $state => $label ) {
            $html .= '<button type="button" class="button-link edc-state-filter" data-edc-state="' . esc_attr( $state ) . '">' . esc_html( $label ) . '</button> ';
        }

        return $html;
    }

    private function render_job_actions( array $job, string $status ): string {
        $html = '<p class="edc-job__actions">';

        if ( in_array( $status, [ 'interrupted', 'pending' ], true ) ) {
            $html .= '<button type="button" class="button button-primary" data-edc-action="resume" data-job-id="' . (int) $job['id'] . '">'
                . esc_html( $status === 'pending' ? __( 'Start', 'jhmg-converter-for-elementor-to-divi' ) : __( 'Resume', 'jhmg-converter-for-elementor-to-divi' ) ) . '</button> ';
        }

        if ( $job['failed'] > 0 && $status !== 'running' ) {
            $html .= '<button type="button" class="button" data-edc-action="retry-failed" data-job-id="' . (int) $job['id'] . '">'
                . esc_html( sprintf( /* translators: %d: failed pages */ __( 'Retry %d failed', 'jhmg-converter-for-elementor-to-divi' ), $job['failed'] ) ) . '</button> ';
        }

        $html .= $this->render_undo( $job, $status );

        return $html . '<span class="edc-job__note" aria-live="polite"></span></p>';
    }

    /** Undo reuses the existing ImportRollback, through the history run a finished job writes. */
    private function render_undo( array $job, string $status ): string {
        if ( ! in_array( $status, [ 'done', 'done_errors' ], true ) ) {
            return '';
        }

        $run = ( new ImportHistory() )->find( 'job-' . $job['id'] );
        if ( $run === null ) {
            return '';
        }
        if ( ! empty( $run['rolled_back'] ) ) {
            return '<span class="edc-job__undone">' . esc_html__( 'Undone', 'jhmg-converter-for-elementor-to-divi' ) . '</span> ';
        }

        $copies = count( $run['post_ids'] ?? [] ) - count( $run['replaced'] ?? [] );
        if ( $copies > 0 && ! ImportRollback::trash_available() ) {
            return '';
        }

        $message = $job['mode'] === 'replace'
            ? __( 'Restore these pages to their original Elementor version?', 'jhmg-converter-for-elementor-to-divi' )
            : __( 'Move the pages this job created to the Trash?', 'jhmg-converter-for-elementor-to-divi' );

        return sprintf(
            '<a class="button" href="%1$s" onclick="%2$s">%3$s</a> ',
            esc_url( add_query_arg( ImportRollback::QUERY_ACTION, 'job-' . $job['id'], admin_url( 'tools.php?page=' . self::SLUG ) ) . '&_wpnonce=' . wp_create_nonce( ImportRollback::NONCE_ACTION ) ),
            esc_attr( sprintf( "return confirm('%s');", addslashes( $message ) ) ),
            esc_html__( 'Undo job', 'jhmg-converter-for-elementor-to-divi' )
        );
    }

    // ------------------------------------------------------------------
    // Per-page results
    // ------------------------------------------------------------------

    public function render_items( int $job_id, ?string $state, int $paged ): string {
        $job   = $this->store->get_job( $job_id );
        $paged = max( 1, $paged );
        $items = $this->store->list_items( $job_id, $state, self::ITEMS_PER_PAGE, ( $paged - 1 ) * self::ITEMS_PER_PAGE );
        $total = $this->store->count_items( $job_id, $state );

        if ( ! $items ) {
            return '<p class="edc-empty">' . esc_html__( 'Nothing here.', 'jhmg-converter-for-elementor-to-divi' ) . '</p>';
        }

        $html  = '<table class="widefat striped edc-items"><thead><tr>';
        $html .= '<th>' . esc_html__( 'Page', 'jhmg-converter-for-elementor-to-divi' ) . '</th><th>' . esc_html__( 'Type', 'jhmg-converter-for-elementor-to-divi' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Result', 'jhmg-converter-for-elementor-to-divi' ) . '</th><th>' . esc_html__( 'Actions', 'jhmg-converter-for-elementor-to-divi' ) . '</th></tr></thead><tbody>';
        foreach ( $items as $item ) {
            $html .= $this->render_item_row( $item, $job );
        }
        $html .= '</tbody></table>';

        $pages = (int) ceil( $total / self::ITEMS_PER_PAGE );
        if ( $pages > 1 ) {
            $html .= '<p class="edc-pager">';
            if ( $paged > 1 ) {
                $html .= '<button type="button" class="button" data-edc-page="' . ( $paged - 1 ) . '">« ' . esc_html__( 'Previous', 'jhmg-converter-for-elementor-to-divi' ) . '</button> ';
            }
            $html .= '<span>' . esc_html( sprintf( /* translators: 1: current page, 2: total pages */ __( 'Page %1$d of %2$d', 'jhmg-converter-for-elementor-to-divi' ), $paged, $pages ) ) . '</span> ';
            if ( $paged < $pages ) {
                $html .= '<button type="button" class="button" data-edc-page="' . ( $paged + 1 ) . '">' . esc_html__( 'Next', 'jhmg-converter-for-elementor-to-divi' ) . ' »</button>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    private function render_item_row( array $item, ?array $job ): string {
        $labels = [
            'converted' => __( 'Converted', 'jhmg-converter-for-elementor-to-divi' ),
            'skipped'   => __( 'Skipped', 'jhmg-converter-for-elementor-to-divi' ),
            'failed'    => __( 'Failed', 'jhmg-converter-for-elementor-to-divi' ),
            'pending'   => __( 'Pending', 'jhmg-converter-for-elementor-to-divi' ),
            'running'   => __( 'Converting…', 'jhmg-converter-for-elementor-to-divi' ),
        ];

        $html  = '<tr data-post-id="' . (int) $item['post_id'] . '">';
        $html .= '<td><strong>' . esc_html( $item['title'] ) . '</strong></td>';
        $html .= '<td>' . esc_html( $item['post_type'] ) . '</td>';
        $html .= '<td><span class="edc-state edc-state--' . esc_attr( $item['state'] ) . '">' . esc_html( $labels[ $item['state'] ] ?? $item['state'] ) . '</span>';
        if ( $item['message'] !== '' ) {
            $html .= '<br><small class="edc-item-message">' . esc_html( $item['message'] ) . '</small>';
        }
        $html .= '</td><td class="edc-item-actions">' . $this->render_item_actions( $item ) . '</td></tr>';

        return $html;
    }

    private function render_item_actions( array $item ): string {
        $links = [];

        if ( $item['state'] === 'converted' ) {
            // A copy's output is result_post_id; a replaced or hand-marked page is the page itself.
            $target = $item['result_post_id'] > 0 ? $item['result_post_id'] : $item['post_id'];
            if ( get_post( $target ) ) {
                $links[] = $this->link( get_edit_post_link( $target ), __( 'Edit', 'jhmg-converter-for-elementor-to-divi' ) );
                $links[] = $this->link( get_permalink( $target ), __( 'View', 'jhmg-converter-for-elementor-to-divi' ), true );
                if ( $item['result_post_id'] > 0 ) {
                    $links[] = $this->link( self::builder_url( $target ), __( 'Open in Divi 5 builder', 'jhmg-converter-for-elementor-to-divi' ), true );
                }
            }
        } elseif ( get_post( $item['post_id'] ) ) {
            $links[] = $this->link( get_edit_post_link( $item['post_id'] ), __( 'Edit original', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        if ( $item['state'] === 'failed' ) {
            $links[] = '<button type="button" class="button-link" data-edc-action="retry-item" data-post-id="' . (int) $item['post_id'] . '">' . esc_html__( 'Retry', 'jhmg-converter-for-elementor-to-divi' ) . '</button>';
        }

        return implode( ' | ', array_filter( $links ) );
    }

    /** Divi 5's Visual Builder opens on the front-end URL with et_fb=1. */
    public static function builder_url( int $post_id ): string {
        return add_query_arg( [ 'et_fb' => '1', 'PageSpeed' => 'Off' ], (string) get_permalink( $post_id ) );
    }

    private function link( $url, string $label, bool $new_tab = false ): string {
        if ( ! $url ) {
            return '';
        }

        return '<a href="' . esc_url( (string) $url ) . '"' . ( $new_tab ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $label ) . '</a>';
    }

    // ------------------------------------------------------------------
    // Pending view
    // ------------------------------------------------------------------

    /**
     * Every Elementor page not yet converted, with the actions that start a job.
     * This is what lets a customer stop remembering what is done.
     *
     * @param array $args types (string[]; default all allowed), search, paged.
     */
    public function render_pending_section( array $args ): string {
        $allowed = PendingQuery::allowed_types();
        $types   = array_values( array_intersect( (array) ( $args['types'] ?? [] ), $allowed ) ) ?: $allowed;
        $search  = trim( (string) ( $args['search'] ?? '' ) );
        $paged   = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $total   = $this->pending->count( $types );
        $limit   = ConversionPreflight::limit();
        $failed  = $this->store->count_failed_posts();

        $html  = '<details class="edc-card edc-pending" id="edc-pending" open>';
        $html .= '<summary><h2>' . esc_html( sprintf( /* translators: %s: number of pages */ __( 'Pending — %s waiting', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $total ) ) ) . '</h2></summary>';
        $html .= '<div class="edc-pending__body">';

        if ( $failed > 0 ) {
            $html .= '<p class="edc-pending__failed notice notice-warning inline">' . esc_html( sprintf(
                /* translators: %s: number of pages */
                _n( '%s page failed and is not listed here — retry it from its job below.', '%s pages failed and are not listed here — retry them from their job below.', $failed, 'jhmg-converter-for-elementor-to-divi' ),
                number_format_i18n( $failed )
            ) ) . '</p>';
        }

        if ( $total === 0 ) {
            return $html . '<p class="edc-empty">' . esc_html__( 'Nothing is waiting to be converted. New Elementor pages will show up here.', 'jhmg-converter-for-elementor-to-divi' ) . '</p></div></details>';
        }

        $html .= $this->render_type_chips( $types, $allowed );
        $html .= '<p class="edc-pending__search"><input type="search" id="edc-pending-search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search by title…', 'jhmg-converter-for-elementor-to-divi' ) . '"> ';
        $html .= '<button type="button" class="button" data-edc-action="pending-search">' . esc_html__( 'Search', 'jhmg-converter-for-elementor-to-divi' ) . '</button></p>';
        $html .= '<div id="edc-pending-table" data-types="' . esc_attr( implode( ',', $types ) ) . '">' . $this->render_pending_table( $types, $search, $paged ) . '</div>';
        $html .= $this->render_pending_actions( $total, $limit );
        $html .= $this->render_start_panel();

        return $html . '</div></details>';
    }

    private function render_type_chips( array $selected, array $allowed ): string {
        $counts = $this->pending->counts_by_type( $allowed );
        $all    = count( $selected ) === count( $allowed );

        $html = '<p class="edc-chips"><button type="button" class="edc-chip' . ( $all ? ' is-active' : '' ) . '" data-edc-type="">'
            . esc_html__( 'All', 'jhmg-converter-for-elementor-to-divi' ) . ' <span class="edc-chip__n">' . (int) array_sum( $counts ) . '</span></button> ';
        foreach ( $counts as $type => $n ) {
            $html .= '<button type="button" class="edc-chip' . ( ! $all && $selected === [ $type ] ? ' is-active' : '' ) . '" data-edc-type="' . esc_attr( $type ) . '">'
                . esc_html( $type ) . ' <span class="edc-chip__n">' . (int) $n . '</span></button> ';
        }

        return $html . '</p>';
    }

    /** The table and its pager: the fragment the pending endpoint swaps in. */
    public function render_pending_table( array $types, string $search, int $paged ): string {
        $paged = max( 1, $paged );
        $rows  = $this->pending->list( $types, self::PENDING_PER_PAGE, ( $paged - 1 ) * self::PENDING_PER_PAGE, $search );
        $total = $this->pending->count_matching( $types, $search );

        if ( ! $rows ) {
            return '<p class="edc-empty">' . esc_html__( 'No pending pages match.', 'jhmg-converter-for-elementor-to-divi' ) . '</p>';
        }

        $html  = '<table class="widefat striped edc-pending-table"><thead><tr>';
        $html .= '<td class="check-column"><input type="checkbox" id="edc-select-all" aria-label="' . esc_attr__( 'Select all on this page', 'jhmg-converter-for-elementor-to-divi' ) . '"></td>';
        $html .= '<th>' . esc_html__( 'Page', 'jhmg-converter-for-elementor-to-divi' ) . '</th><th>' . esc_html__( 'Type', 'jhmg-converter-for-elementor-to-divi' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Status', 'jhmg-converter-for-elementor-to-divi' ) . '</th><th>' . esc_html__( 'Actions', 'jhmg-converter-for-elementor-to-divi' ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $title = trim( (string) $row['post_title'] );
            $html .= '<tr data-post-id="' . (int) $row['ID'] . '">';
            $html .= '<th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="' . (int) $row['ID'] . '"></th>';
            $html .= '<td><strong>' . esc_html( $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ) ) . '</strong></td>';
            $html .= '<td>' . esc_html( $row['post_type'] ) . '</td>';
            $html .= '<td>' . esc_html( (string) ( $row['post_status'] ?? '' ) ) . '</td>';
            $html .= '<td class="edc-item-actions">' . $this->link( get_edit_post_link( (int) $row['ID'] ), __( 'Edit', 'jhmg-converter-for-elementor-to-divi' ) )
                . ' | ' . $this->link( get_permalink( (int) $row['ID'] ), __( 'View', 'jhmg-converter-for-elementor-to-divi' ), true ) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $pages = (int) ceil( $total / self::PENDING_PER_PAGE );
        if ( $pages > 1 ) {
            $html .= '<p class="edc-pager">';
            if ( $paged > 1 ) {
                $html .= '<button type="button" class="button" data-edc-pending-page="' . ( $paged - 1 ) . '">« ' . esc_html__( 'Previous', 'jhmg-converter-for-elementor-to-divi' ) . '</button> ';
            }
            $html .= '<span>' . esc_html( sprintf( /* translators: 1: current page, 2: total pages */ __( 'Page %1$d of %2$d', 'jhmg-converter-for-elementor-to-divi' ), $paged, $pages ) ) . '</span> ';
            if ( $paged < $pages ) {
                $html .= '<button type="button" class="button" data-edc-pending-page="' . ( $paged + 1 ) . '">' . esc_html__( 'Next', 'jhmg-converter-for-elementor-to-divi' ) . ' »</button>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    /**
     * Convert selected, and Convert all remaining. When "all" is more than the
     * licence allows in one job, the button is not rendered at all — an inline
     * upsell that says how many pages are waiting takes its place — so there is
     * no live control for the server to refuse.
     */
    public function render_pending_actions( int $total, int $limit ): string {
        $html  = '<p class="edc-pending__actions">';
        $html .= '<button type="button" class="button" data-edc-action="convert-selected" data-limit="' . esc_attr( (string) $limit ) . '" disabled>'
            . esc_html__( 'Convert selected', 'jhmg-converter-for-elementor-to-divi' ) . ' (<span class="edc-selected-count">0</span>)</button> ';

        if ( $total > $limit ) {
            $html .= '<a class="button button-primary edc-upsell" target="_blank" rel="noopener" href="' . esc_url( JobsAjax::PRO_URL ) . '">'
                . esc_html( sprintf( /* translators: %s: number of pages */ __( 'Convert all %s remaining with Pro', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $total ) ) ) . '</a> ';
            $html .= '<span class="description">' . esc_html__( 'The free plugin converts one page at a time. Pro converts a whole site in one job.', 'jhmg-converter-for-elementor-to-divi' ) . '</span>';
        } else {
            $html .= '<button type="button" class="button button-primary" data-edc-action="convert-all" data-count="' . (int) $total . '">'
                . esc_html( sprintf( /* translators: %s: number of pages */ __( 'Convert all %s remaining', 'jhmg-converter-for-elementor-to-divi' ), number_format_i18n( $total ) ) ) . '</button>';
        }

        return $html . '</p>';
    }

    /**
     * The step between "Convert" and a job existing. Bulk jobs (JobService::BULK_THRESHOLD
     * and above) cannot start without choosing a mode and ticking the box; the
     * server enforces the same rule, this only makes it visible.
     */
    private function render_start_panel(): string {
        $html  = '<div id="edc-start-panel" class="edc-start-panel" hidden>';
        $html .= '<h3>' . esc_html__( 'Start a conversion job', 'jhmg-converter-for-elementor-to-divi' ) . '</h3>';
        $html .= '<p class="edc-start-panel__summary"></p>';
        $html .= '<fieldset><legend class="screen-reader-text">' . esc_html__( 'How should the pages be converted?', 'jhmg-converter-for-elementor-to-divi' ) . '</legend>';
        $html .= '<label><input type="radio" name="edc_mode" value="copy"> ' . esc_html__( 'Create a copy of each page as a draft — your Elementor pages stay exactly as they are', 'jhmg-converter-for-elementor-to-divi' ) . '</label><br>';
        $html .= '<label><input type="radio" name="edc_mode" value="replace"> ' . esc_html__( 'Replace the original pages — they keep their address, ID and menu entries', 'jhmg-converter-for-elementor-to-divi' ) . '</label></fieldset>';
        $html .= '<div class="edc-start-panel__warning notice notice-warning inline" hidden><p>'
            . esc_html__( 'Replacing changes your live pages. A backup of each Elementor version is kept on the page and Undo restores it, but edits you make in Divi afterwards are lost if you undo. Take a full site backup before you start.', 'jhmg-converter-for-elementor-to-divi' )
            . '</p></div>';
        $html .= '<label class="edc-start-panel__confirm" hidden><input type="checkbox" name="edc_confirm" value="1"> <span></span></label>';
        $html .= '<p><button type="button" class="button button-primary" data-edc-action="start-job">' . esc_html__( 'Start conversion', 'jhmg-converter-for-elementor-to-divi' ) . '</button> ';
        $html .= '<button type="button" class="button-link" data-edc-action="cancel-start">' . esc_html__( 'Cancel', 'jhmg-converter-for-elementor-to-divi' ) . '</button></p>';
        $html .= '<p class="edc-start-panel__error" role="alert"></p></div>';

        return $html;
    }
}
