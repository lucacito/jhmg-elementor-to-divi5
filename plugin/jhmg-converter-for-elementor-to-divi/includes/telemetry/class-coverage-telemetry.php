<?php
/**
 * Opt-in, anonymous report of Elementor widgets this site could not convert.
 *
 * Sends widget type NAMES only — no counts, no versions, no site identifier,
 * no URLs, no post content. Off by default; nothing leaves the site until the
 * user ticks the box on the coverage panel.
 *
 * Distinct names rather than counts is deliberate: each site then contributes
 * at most one vote per widget type per weekly report, so the resulting ranking
 * reflects what most users need rather than whoever converts most.
 */

namespace ElementorDivi5Converter\Telemetry;

use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CoverageTelemetry {

    const CONSENT_OPTION   = 'edc_telemetry_consent';
    const LAST_SENT_OPTION = 'edc_telemetry_last_sent';
    const QUERY_ACTION     = 'edc_telemetry_consent_set';
    const NONCE_ACTION     = 'edc_telemetry_consent';
    const PRODUCT          = 'elementor-to-divi5';
    const ENDPOINT         = 'https://divi5lab.com/api/plugin/coverage';
    const INTERVAL_DAYS    = 7;

    // Mirror the receiving endpoint's zod schema exactly:
    // widget_types: z.array( z.string().min(1).max(64) ).min(1).max(100)
    // Change one side, change the other.
    const MAX_TYPE_LENGTH  = 64;
    const MAX_TYPES        = 100;

    private ImportHistory $history;
    private string $today;

    public function __construct( ?ImportHistory $history = null, ?string $today = null ) {
        $this->history = $history ?? new ImportHistory();
        $this->today   = $today ?? gmdate( 'Y-m-d' );
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_consent' ] );
        // Never during a conversion: a slow endpoint must not delay the work
        // the user actually came here to do.
        add_action( 'admin_init', [ $this, 'maybe_send' ] );
    }

    public function has_consent(): bool {
        return (string) get_option( self::CONSENT_OPTION, '' ) === '1';
    }

    public function due(): bool {
        $last = (string) get_option( self::LAST_SENT_OPTION, '' );

        if ( $last === '' ) {
            return true;
        }

        return $this->today >= gmdate( 'Y-m-d', strtotime( $last . ' +' . self::INTERVAL_DAYS . ' days' ) );
    }

    /**
     * Clamped to the receiving endpoint's contract (see MAX_TYPE_LENGTH,
     * MAX_TYPES above). ImportHistory::coverage() is already sorted by
     * imports-affected descending, so taking the first MAX_TYPES after the
     * length filter keeps the most significant gaps without re-sorting.
     *
     * @return array{product:string, widget_types:string[]}
     */
    public function payload(): array {
        $types = array_column( $this->history->coverage(), 'type' );

        $types = array_values( array_filter(
            $types,
            static fn( string $type ): bool => strlen( $type ) <= self::MAX_TYPE_LENGTH
        ) );

        return [
            'product'      => self::PRODUCT,
            'widget_types' => array_slice( $types, 0, self::MAX_TYPES ),
        ];
    }

    public function maybe_send(): void {
        if ( ! $this->has_consent() || ! $this->due() ) {
            return;
        }

        $payload = $this->payload();

        if ( empty( $payload['widget_types'] ) ) {
            return;
        }

        wp_remote_post( self::ENDPOINT, [
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => [ 'content-type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
        ] );

        update_option( self::LAST_SENT_OPTION, $this->today );
    }

    public function maybe_handle_consent(): void {
        if ( ! isset( $_GET[ self::QUERY_ACTION ] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        update_option(
            self::CONSENT_OPTION,
            sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) === '1' ? '1' : '0'
        );
    }
}
