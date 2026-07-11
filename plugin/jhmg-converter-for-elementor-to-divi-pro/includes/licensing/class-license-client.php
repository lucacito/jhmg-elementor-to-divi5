<?php
/**
 * JHMG License Client — CANONICAL COPY.
 * Source of truth: layoutlab repo, lib/license-server/php-client/class-license-client.php
 * Synced into Pro plugins via scripts/sync-license-client.sh — DO NOT edit the plugin copies.
 * API contract (frozen): /api/license/{activate,validate,deactivate}, /api/plugin/update-check
 * Error codes: invalid_key | product_mismatch | license_not_usable | rate_limited | invalid_request
 * Enforcement policy (frozen): SOFT. License state gates update delivery + admin notices only —
 * it must never lock plugin features. status_notice() only ever informs; callers must not use
 * get_state()/get_key() to disable functionality.
 */

namespace ElementorDivi5Converter\Pro\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LicenseClient {
    private const OPT_KEY     = 'edcp_license_key';
    private const OPT_STATE   = 'edcp_license_state';
    private const OPT_BLOCKED = 'edcp_update_blocked';
    private const CACHE_TTL   = DAY_IN_SECONDS;
    private const GRACE_TTL   = 3 * DAY_IN_SECONDS;

    public function __construct(
        private string $product,
        private string $plugin_version,
        private string $api_base,
        private string $plugin_basename
    ) {}

    public function get_key(): ?string { $k = get_option( self::OPT_KEY, '' ); return $k !== '' ? $k : null; }
    public function get_state(): ?array { $s = get_option( self::OPT_STATE, null ); return is_array( $s ) ? $s : null; }

    public function activate( string $key ): array {
        $res = $this->post( '/api/license/activate', [
            'key'            => $key,
            'site_url'       => home_url(),
            'product'        => $this->product,
            'plugin_version' => $this->plugin_version,
            'wp_version'     => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
        ] );
        if ( $res['ok'] ) {
            update_option( self::OPT_KEY, $key, false );
            $this->store_state( $res['body'] );
            return [ 'ok' => true, 'error' => null, 'status' => $res['body']['status'] ?? null ];
        }
        return [ 'ok' => false, 'error' => $res['error'], 'status' => $res['body']['status'] ?? null ];
    }

    public function deactivate(): void {
        $key = $this->get_key();
        if ( $key ) {
            $this->post( '/api/license/deactivate', [ 'key' => $key, 'site_url' => home_url() ] );
        }
        delete_option( self::OPT_KEY );
        delete_option( self::OPT_STATE );
        delete_option( self::OPT_BLOCKED );
    }

    public function refresh( bool $force = false ): void {
        $key = $this->get_key();
        if ( ! $key ) { return; }
        $state = $this->get_state();
        $age   = time() - (int) ( $state['checked_at'] ?? 0 );
        if ( ! $force && $age < self::CACHE_TTL ) { return; }

        $res = $this->post( '/api/license/validate', [ 'key' => $key, 'site_url' => home_url(), 'product' => $this->product ] );
        if ( $res['network_error'] ) {
            // Offline grace: keep last-known state up to GRACE_TTL past the cache window.
            if ( $age < self::CACHE_TTL + self::GRACE_TTL && $state ) { return; }
            return; // Beyond grace we STILL keep last state (soft enforcement) — notices handle messaging.
        }
        if ( $res['ok'] ) {
            $this->store_state( $res['body'] );
        } else {
            $this->store_state( [ 'status' => $res['body']['status'] ?? 'invalid', 'expires' => $state['expires'] ?? null ] );
        }
    }

    public function inject_update( $transient ) {
        $key = $this->get_key();
        $url = sprintf(
            '%s/api/plugin/update-check?product=%s&version=%s%s',
            $this->api_base,
            rawurlencode( $this->product ),
            rawurlencode( $this->plugin_version ),
            $key ? '&key=' . rawurlencode( $key ) : ''
        );
        $raw = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $raw ) || wp_remote_retrieve_response_code( $raw ) !== 200 ) { return $transient; }
        $body = json_decode( wp_remote_retrieve_body( $raw ), true );
        if ( empty( $body['update'] ) ) { delete_option( self::OPT_BLOCKED ); return $transient; }
        if ( empty( $body['package'] ) ) {
            update_option( self::OPT_BLOCKED, $body['version'] ?? '1', false );
            return $transient;
        }
        delete_option( self::OPT_BLOCKED );
        if ( ! is_object( $transient ) ) { $transient = (object) [ 'response' => [] ]; }
        $transient->response[ $this->plugin_basename ] = (object) [
            'slug'        => dirname( $this->plugin_basename ),
            'new_version' => $body['version'],
            'package'     => $body['package'],
            'url'         => 'https://divi5lab.com/plugins/elementor-to-divi-5',
        ];
        return $transient;
    }

    /**
     * Soft-enforcement admin notice. Informational only — never disables
     * features. Hooked indirectly via Licensing\LicensePage::maybe_render_notice().
     */
    public function status_notice(): void {
        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $license_url = function_exists( 'admin_url' ) ? admin_url( 'tools.php?page=edcp-kit&tab=license' ) : '';

        $key = $this->get_key();
        if ( ! $key ) {
            $this->render_notice(
                'notice-warning',
                __( 'JHMG Converter Pro: activate your license to receive automatic updates and support.', 'jhmg-converter-for-elementor-to-divi-pro' ),
                __( 'Activate now', 'jhmg-converter-for-elementor-to-divi-pro' ),
                $license_url
            );
            return;
        }

        $state  = $this->get_state();
        $status = $state['status'] ?? 'unknown';
        if ( in_array( $status, [ 'expired', 'canceled' ], true ) ) {
            $this->render_notice(
                'notice-warning',
                __( 'JHMG Converter Pro: your license has expired. Renew to keep receiving updates.', 'jhmg-converter-for-elementor-to-divi-pro' ),
                __( 'Renew', 'jhmg-converter-for-elementor-to-divi-pro' ),
                $license_url
            );
            return;
        }

        if ( get_option( self::OPT_BLOCKED ) ) {
            $this->render_notice(
                'notice-info',
                __( 'JHMG Converter Pro: an update is available. Renew your license to receive it.', 'jhmg-converter-for-elementor-to-divi-pro' ),
                __( 'Renew', 'jhmg-converter-for-elementor-to-divi-pro' ),
                $license_url
            );
        }
    }

    private function render_notice( string $class, string $message, string $cta, string $url ): void {
        printf(
            '<div class="notice %1$s"><p>%2$s%3$s</p></div>',
            esc_attr( $class ),
            esc_html( $message ),
            $url !== '' ? ' <a href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>' : ''
        );
    }

    private function store_state( array $body ): void {
        update_option( self::OPT_STATE, [
            'status'     => $body['status'] ?? 'unknown',
            'expires'    => $body['expires'] ?? null,
            'checked_at' => time(),
        ], false );
    }

    /** @return array{ok:bool, error:?string, body:array, network_error:bool} */
    private function post( string $path, array $payload ): array {
        $raw = wp_remote_post( $this->api_base . $path, [
            'timeout' => 10,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
        ] );
        if ( is_wp_error( $raw ) ) {
            return [ 'ok' => false, 'error' => 'network_error', 'body' => [], 'network_error' => true ];
        }
        $code = wp_remote_retrieve_response_code( $raw );
        $body = json_decode( wp_remote_retrieve_body( $raw ), true ) ?: [];
        if ( $code === 200 ) {
            return [ 'ok' => true, 'error' => null, 'body' => $body, 'network_error' => false ];
        }
        return [ 'ok' => false, 'error' => $body['error'] ?? "http_$code", 'body' => $body, 'network_error' => false ];
    }
}
