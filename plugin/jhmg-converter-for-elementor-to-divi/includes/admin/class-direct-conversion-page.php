<?php
/**
 * "Convert from this site" — pick an installed Elementor page, check what the
 * conversion will produce, then convert it.
 *
 * This is the screen 3.0.0 exists for: it removes the export/upload round trip
 * that was costing users at first run.
 *
 * Two safety properties are enforced here rather than in markup:
 *  - The rendered picker is never trusted on the way back in. Every submitted
 *    post ID is re-verified as an Elementor-built post that exists.
 *  - The selection is capped server-side at edc_direct_conversion_limit. A
 *    limit enforced only by radio buttons is not a limit.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;
use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DirectConversionPage {

    const CHECK_ACTION   = 'edc_direct_check';
    const CONVERT_ACTION = 'edc_direct_convert';
    const CHECK_NONCE    = 'edc_direct_check_nonce';
    const CONVERT_NONCE  = 'edc_direct_convert_nonce';

    const CAPABILITY = 'manage_options';

    /**
     * A checked selection lives here (per-user) between the check step and the
     * report screen's render — the report is reached by a GET redirect, which
     * cannot itself carry the POSTed post IDs.
     */
    const PLAN_IDS_TRANSIENT_PREFIX = 'edc_direct_plan_ids_';

    private ElementorPageRepository $repo;

    public function __construct( ?ElementorPageRepository $repo = null ) {
        $this->repo = $repo ?? new ElementorPageRepository();
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
    }

    /**
     * Sanitize and verify a submitted selection. Does NOT cap it — callers
     * that need to know whether a selection would be truncated (the check
     * handler) must see every verified id, not the already-sliced list.
     *
     * @param array $request Typically $_POST.
     * @return int[] Post IDs that are real, Elementor-built, and deduplicated.
     */
    public function verified_post_ids( array $request ): array {
        $raw = $request['edc_post_ids'] ?? [];
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }

        $ids = [];
        foreach ( $raw as $value ) {
            if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) ) {
                continue;
            }
            $id = (int) $value;
            if ( $id <= 0 || in_array( $id, $ids, true ) ) {
                continue;
            }
            if ( ! $this->is_elementor_post( $id ) ) {
                continue;
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Sanitize, verify and cap a submitted selection.
     *
     * @param array $request Typically $_POST.
     * @return int[] Post IDs safe to convert.
     */
    public function selected_post_ids( array $request ): array {
        return array_slice( $this->verified_post_ids( $request ), 0, ConversionPreflight::limit() );
    }

    /** A dry run over the selection. Writes nothing. */
    public function plan_for( array $post_ids ): ConversionPlan {
        return ( new ConversionPreflight() )->run( new InstalledPostSource( $post_ids ) );
    }

    /**
     * Convert the selection. Creates new posts; never modifies the source.
     *
     * @return array[] BatchImporter-shaped results.
     */
    public function convert( array $post_ids, array $options = [] ): array {
        $plan = $this->plan_for( $post_ids );

        return ( new BatchImporter() )->importPlan( $plan, array_merge( [
            'post_status' => 'draft',
        ], $options ) );
    }

    /**
     * A post the picker could legitimately have offered: it exists, and
     * Elementor marked it as built with its editor.
     */
    private function is_elementor_post( int $post_id ): bool {
        if ( ! get_post( $post_id ) ) {
            return false;
        }

        return get_post_meta( $post_id, ElementorPageRepository::EDIT_MODE_META, true ) === 'builder';
    }

    /** Whether the landing page should offer this screen at all. */
    public function has_elementor_content(): bool {
        return $this->repo->has_any();
    }

    /**
     * The page picker: lists installed Elementor pages and posts the
     * selection to handle_check(). Renders, never echoes.
     *
     * @param array $args Accepts 'search' (string) and 'paged' (int), read by
     *   AdminPage from $_GET['edc_s'] / $_GET['paged'] and sanitized there.
     */
    public function render_picker( array $args = [] ): string {
        $limit    = ConversionPreflight::limit();
        $search   = trim( (string) ( $args['search'] ?? '' ) );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $per_page = ElementorPageRepository::PER_PAGE;

        // One extra row reveals whether a next page exists without the
        // repository needing to report a total count. The offset must still
        // be computed on the real per-page size (PER_PAGE), not the
        // inflated probe count — otherwise it advances by 21 while only 20
        // rows are ever shown, silently skipping a row on every page turn.
        $rows = $this->repo->find( [
            'search'   => $search,
            'paged'    => $paged,
            'per_page' => $per_page + 1,
            'offset'   => ( $paged - 1 ) * $per_page,
        ] );

        $has_next = count( $rows ) > $per_page;
        if ( $has_next ) {
            $rows = array_slice( $rows, 0, $per_page );
        }

        $html = $this->render_search_box( $search );

        if ( empty( $rows ) ) {
            return $html . '<p class="edc-direct-empty">'
                . esc_html__( 'No Elementor pages found on this site. If your pages live elsewhere, use the JSON import above.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        $input_type = $limit > 1 ? 'checkbox' : 'radio';
        $name       = $limit > 1 ? 'edc_post_ids[]' : 'edc_post_ids';

        $html .= '<form method="post" class="edc-direct-picker">';
        $html .= wp_nonce_field( self::CHECK_ACTION, self::CHECK_NONCE, true, false );
        $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CHECK_ACTION ) . '">';
        $html .= '<table class="widefat edc-direct-table"><tbody>';

        foreach ( $rows as $row ) {
            $html .= '<tr><td>';
            $html .= '<label><input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $row['id'] ) . '"> ';
            $html .= '<strong>' . esc_html( $row['title'] ) . '</strong></label>';
            $html .= ' <span class="edc-direct-meta">' . esc_html( $row['post_type'] . ' · ' . $row['status'] . ' · ' . $row['modified'] ) . '</span>';

            if ( ! empty( $row['converted'] ) ) {
                $html .= ' <span class="edc-badge-converted">' . esc_html__( 'already converted', 'jhmg-converter-for-elementor-to-divi' ) . '</span>';
            }

            $html .= '</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p><button type="submit" class="button button-primary">'
            . esc_html__( 'Check this page', 'jhmg-converter-for-elementor-to-divi' )
            . '</button></p>';

        if ( $limit === 1 ) {
            $html .= '<p class="description">'
                . esc_html__( 'Free converts one page at a time, as many times as you like. Pro converts your whole site in one run.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        $html .= '</form>';
        $html .= $this->render_pager( $search, $paged, $has_next );

        return $html;
    }

    /** A GET form so search state (and a fresh page=1) lives in the URL. */
    private function render_search_box( string $search ): string {
        $html  = '<form method="get" class="edc-direct-search">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr( AdminPage::MENU_SLUG ) . '">';
        $html .= '<label class="screen-reader-text" for="edc-direct-search-input">'
            . esc_html__( 'Search Elementor pages', 'jhmg-converter-for-elementor-to-divi' )
            . '</label>';
        $html .= '<input type="search" id="edc-direct-search-input" name="edc_s" value="'
            . esc_attr( $search ) . '" placeholder="'
            . esc_attr( __( 'Search by title…', 'jhmg-converter-for-elementor-to-divi' ) ) . '">';
        $html .= '<button type="submit" class="button">'
            . esc_html__( 'Search', 'jhmg-converter-for-elementor-to-divi' )
            . '</button>';

        return $html . '</form>';
    }

    /** Plain prev/next links; a full WP_List_Table pager is more than this screen needs. */
    private function render_pager( string $search, int $paged, bool $has_next ): string {
        if ( $paged <= 1 && ! $has_next ) {
            return '';
        }

        $base_args = [ 'page' => AdminPage::MENU_SLUG ];
        if ( $search !== '' ) {
            $base_args['edc_s'] = $search;
        }

        $html = '<p class="edc-direct-pager">';

        if ( $paged > 1 ) {
            $prev_url = add_query_arg( $base_args + [ 'paged' => $paged - 1 ], admin_url( 'tools.php' ) );
            $html    .= '<a class="button" href="' . esc_url( $prev_url ) . '">&laquo; '
                . esc_html__( 'Previous', 'jhmg-converter-for-elementor-to-divi' ) . '</a> ';
        }

        if ( $has_next ) {
            $next_url = add_query_arg( $base_args + [ 'paged' => $paged + 1 ], admin_url( 'tools.php' ) );
            $html    .= '<a class="button" href="' . esc_url( $next_url ) . '">'
                . esc_html__( 'Next', 'jhmg-converter-for-elementor-to-divi' ) . ' &raquo;</a>';
        }

        return $html . '</p>';
    }

    /**
     * The conversion report: the structure the conversion would produce, the
     * widgets it could not carry over, and a Convert button. Never renders a
     * visual preview — this screen reports structure, not pixels.
     */
    public function render_report( ConversionPlan $plan ): string {
        $html  = '<div class="edc-direct-report">';
        $html .= '<h2>' . esc_html__( 'Conversion report', 'jhmg-converter-for-elementor-to-divi' ) . '</h2>';
        $html .= '<p class="description">'
            . esc_html__( 'Nothing has been written yet. This is what the conversion will produce.', 'jhmg-converter-for-elementor-to-divi' )
            . '</p>';

        if ( $plan->truncated() ) {
            $html .= '<div class="notice notice-info inline"><p>'
                . esc_html__( 'Only the first page was checked. Converting several pages in one run is a Pro feature.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p></div>';
        }

        $ids = [];

        foreach ( $plan->items() as $item ) {
            $html .= '<h3>' . esc_html( $item['title'] ) . '</h3>';

            if ( $item['error'] !== '' ) {
                $html .= '<div class="notice notice-error inline"><p>' . esc_html( $item['error'] ) . '</p></div>';
                continue;
            }

            $source_id = $item['source_ref']['post_id'] ?? null;
            if ( $source_id ) {
                $ids[] = (int) $source_id;
            }

            $converted = array_sum( $item['report']['converted'] ?? [] );
            $html     .= '<p>' . esc_html( sprintf(
                /* translators: %d: number of Divi modules the conversion produced */
                _n( '%d module converted.', '%d modules converted.', $converted, 'jhmg-converter-for-elementor-to-divi' ),
                $converted
            ) ) . '</p>';

            $html .= OutlineRenderer::render( $item['outline'] );

            if ( ! empty( $item['unsupported'] ) ) {
                $names = [];
                foreach ( $item['unsupported'] as $entry ) {
                    $name = $entry['widgetType'] ?? $entry['elType'] ?? '';
                    if ( $name !== '' && ! in_array( $name, $names, true ) ) {
                        $names[] = $name;
                    }
                }

                if ( ! empty( $names ) ) {
                    $html .= '<p class="edc-direct-unsupported"><strong>'
                        . esc_html__( 'Could not be converted:', 'jhmg-converter-for-elementor-to-divi' )
                        . '</strong> ' . esc_html( implode( ', ', $names ) ) . '</p>';
                }
            }

            // Escaped at every interpolation inside render().
            $html .= NotCarriedOverRenderer::render(
                $item['report']['not_carried_over']    ?? [],
                $item['report']['approximate_matches'] ?? []
            );
        }

        if ( ! empty( $ids ) ) {
            $html .= '<form method="post" class="edc-direct-convert">';
            $html .= wp_nonce_field( self::CONVERT_ACTION, self::CONVERT_NONCE, true, false );
            $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CONVERT_ACTION ) . '">';

            foreach ( $ids as $id ) {
                $html .= '<input type="hidden" name="edc_post_ids[]" value="' . esc_attr( (string) $id ) . '">';
            }

            $html .= '<p><button type="submit" class="button button-primary">'
                . esc_html__( 'Convert to Divi 5', 'jhmg-converter-for-elementor-to-divi' )
                . '</button></p>';
            $html .= '<p class="description">'
                . esc_html__( 'Creates a new Divi draft. Your Elementor page is left exactly as it is.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
            $html .= '</form>';
        }

        return $html . '</div>';
    }

    public function maybe_handle_request(): void {
        // Converting into a site with no Divi 5 produces pages that render
        // blank, so neither the check step nor the commit step runs.
        if ( ! \ElementorDivi5Converter\Helpers\DiviRequirement::is_satisfied() ) {
            return;
        }

        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

        if ( $action !== self::CHECK_ACTION && $action !== self::CONVERT_ACTION ) {
            return;
        }

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        if ( $action === self::CHECK_ACTION ) {
            check_admin_referer( self::CHECK_ACTION, self::CHECK_NONCE );
            $this->handle_check();
            return;
        }

        check_admin_referer( self::CONVERT_ACTION, self::CONVERT_NONCE );
        $this->handle_convert();
    }

    /**
     * The selection is verified and capped, then handed off to the report
     * screen via a redirect. The IDs cannot travel in the redirect's own query
     * string without re-exposing them to tampering, so they are stashed in a
     * per-user transient that AdminPage::render_direct_report() reads back.
     */
    protected function handle_check(): void {
        // Verified but NOT capped: ConversionPreflight::run() (called from
        // plan_for() when the report screen renders) needs the full,
        // uncapped list to correctly compute whether the selection was
        // truncated. Capping here, before that check ever runs, is exactly
        // the bug that made the "truncated" notice unreachable.
        $ids = $this->verified_post_ids( wp_unslash( $_POST ) );

        // Guard before writing: an empty selection must not clobber a
        // previously stashed valid one sitting in this same transient slot.
        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'Pick a page to check first.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        set_transient( self::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id(), $ids, HOUR_IN_SECONDS );

        $this->redirect( add_query_arg(
            [
                'page'   => AdminPage::MENU_SLUG,
                'action' => AdminPage::VIEW_DIRECT_REPORT,
            ],
            admin_url( 'tools.php' )
        ) );
    }

    /**
     * Commits the plan and lands on the *existing* batch result screen —
     * same import ID scheme, same ImportHistory record, same review prompt,
     * same transient and redirect AdminPage::handle_import() uses for an
     * uploaded batch. A direct conversion must be undoable on day one, and
     * Undo reads from ImportHistory, so this cannot grow a parallel result
     * path without silently losing that guarantee.
     */
    protected function handle_convert(): void {
        $ids = $this->selected_post_ids( wp_unslash( $_POST ) );

        // A stale form, a tampered request, or the page being deleted between
        // check and convert can all empty the selection out from under us.
        // Converting nothing must not write a junk run to ImportHistory:
        // that history is capped at MAX_RUNS, so a junk entry can evict a
        // real one and quietly take its Undo affordance with it.
        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'No pages were selected to convert.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $results = $this->convert( $ids );

        // Counted here rather than on the results screen: that view renders
        // from a transient keyed in the URL, so refreshing it would inflate
        // the total.
        ( new ReviewPrompt() )->record_run( $results );

        $import_id = $this->generate_import_id();

        // Durable record: the transient below expires in an hour, but the
        // coverage screen and rollback both need this run afterwards.
        ( new ImportHistory() )->record( $import_id, $results );

        set_transient( 'edc_batch_' . $import_id, $results, HOUR_IN_SECONDS );

        $this->redirect(
            add_query_arg(
                [
                    'page'      => AdminPage::MENU_SLUG,
                    'action'    => 'batch_result',
                    'import_id' => $import_id,
                ],
                admin_url( 'tools.php' )
            )
        );
    }

    /**
     * Mirrors AdminPage::generate_import_id() exactly. That method is
     * private, so it cannot be reused directly; this generates an ID the same
     * way rather than inventing a second scheme.
     */
    private function generate_import_id(): string {
        return function_exists( 'wp_generate_uuid4' )
            ? wp_generate_uuid4()
            : bin2hex( random_bytes( 16 ) );
    }

    /**
     * Isolated so tests can verify a handler chose to redirect (and where)
     * without the process-terminating exit() actually running in-process.
     * Production behaviour is unchanged: every real request still stops here.
     */
    protected function redirect( string $location ): void {
        wp_safe_redirect( $location );
        exit;
    }
}
