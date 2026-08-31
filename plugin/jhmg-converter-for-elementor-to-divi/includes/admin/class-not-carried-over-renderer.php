<?php
/**
 * Renders the "Not carried over" section shared by both report screens.
 *
 * Three categories of loss were previously invisible. Dynamic tags and
 * animation keys are suppressed before they can reach the skipped-settings log,
 * and form_fields is listed as handled by FormConverter while nothing reads it —
 * so a page could lose every animation, every dynamic binding and every form
 * field on it and still be reported as a clean conversion.
 *
 * The wording is deliberately flat. These are facts about what the file no
 * longer contains, and a user reading them is about to go and rebuild each one
 * by hand.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotCarriedOverRenderer {

    /**
     * @param array $entries    From the report's `not_carried_over` key.
     * @param array $approximate From the report's `approximate_matches` key.
     * @return string HTML, or '' when there is nothing to report.
     */
    public static function render( array $entries, array $approximate = [] ): string {
        if ( empty( $entries ) && empty( $approximate ) ) {
            return '';
        }

        $html = '<div class="edc-not-carried"><h3>'
            . esc_html__( 'Not carried over', 'jhmg-converter-for-elementor-to-divi' )
            . '</h3>';

        foreach ( self::groups() as $kind => $label ) {
            $rows = array_values( array_filter(
                $entries,
                static fn( array $e ): bool => ( $e['kind'] ?? '' ) === $kind
            ) );

            if ( empty( $rows ) ) {
                continue;
            }

            $html .= '<p class="edc-not-carried-label"><strong>' . esc_html( $label ) . '</strong></p><ul class="edc-not-carried-list">';

            foreach ( $rows as $row ) {
                $html .= '<li><code>' . esc_html( (string) ( $row['element_id'] ?? '' ) ) . '</code> — '
                    . esc_html( (string) ( $row['detail'] ?? '' ) ) . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= self::approximateList( $approximate );

        return $html . '</div>';
    }

    /**
     * Category headings, in the order they appear. Each says what was lost and
     * nothing about why.
     */
    private static function groups(): array {
        return [
            'dynamic' => __( 'Dynamic content bindings — the converted page keeps only the stored fallback text', 'jhmg-converter-for-elementor-to-divi' ),
            'animation' => __( 'Entrance animations — removed', 'jhmg-converter-for-elementor-to-divi' ),
            'motion' => __( 'Motion and sticky effects — removed', 'jhmg-converter-for-elementor-to-divi' ),
            'form_fields' => __( 'Form fields — discarded; the Divi form is created with its default name, email and message fields', 'jhmg-converter-for-elementor-to-divi' ),
        ];
    }

    /** Widgets that reached a converter by settings-shape guesswork. */
    private static function approximateList( array $approximate ): string {
        if ( empty( $approximate ) ) {
            return '';
        }

        $html = '<p class="edc-not-carried-label"><strong>'
            . esc_html__( 'Approximate matches — these widgets were not mapped; the closest module was guessed from their settings and should be checked', 'jhmg-converter-for-elementor-to-divi' )
            . '</strong></p><ul class="edc-not-carried-list">';

        foreach ( $approximate as $row ) {
            $html .= '<li><code>' . esc_html( (string) ( $row['element_id'] ?? '' ) ) . '</code> — '
                . esc_html( (string) ( $row['widget_type'] ?? '' ) ) . ' → '
                . esc_html( (string) ( $row['matched_to'] ?? '' ) ) . '</li>';
        }

        return $html . '</ul>';
    }
}
