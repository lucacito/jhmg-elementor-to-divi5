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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DirectConversionPage {

    const CHECK_ACTION   = 'edc_direct_check';
    const CONVERT_ACTION = 'edc_direct_convert';
    const CHECK_NONCE    = 'edc_direct_check_nonce';
    const CONVERT_NONCE  = 'edc_direct_convert_nonce';

    /** Elementor's own marker for a post built with its editor. */
    const EDIT_MODE_META = '_elementor_edit_mode';

    const CAPABILITY = 'manage_options';

    private ElementorPageRepository $repo;

    public function __construct( ?ElementorPageRepository $repo = null ) {
        $this->repo = $repo ?? new ElementorPageRepository();
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
    }

    /**
     * Sanitize, verify and cap a submitted selection.
     *
     * @param array $request Typically $_POST.
     * @return int[] Post IDs safe to convert.
     */
    public function selected_post_ids( array $request ): array {
        $raw = $request['edc_post_ids'] ?? [];
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }

        $ids = [];
        foreach ( $raw as $value ) {
            $id = absint( $value );
            if ( $id <= 0 || in_array( $id, $ids, true ) ) {
                continue;
            }
            if ( ! $this->is_elementor_post( $id ) ) {
                continue;
            }
            $ids[] = $id;
        }

        return array_slice( $ids, 0, ConversionPreflight::limit() );
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

        return get_post_meta( $post_id, self::EDIT_MODE_META, true ) === 'builder';
    }

    public function maybe_handle_request(): void {
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

    /** Filled in by Task 13, which owns rendering and the redirect targets. */
    protected function handle_check(): void {}

    /** Filled in by Task 13. */
    protected function handle_convert(): void {}
}
