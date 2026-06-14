<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the Elementor Alert widget to divi/text with inline styled HTML.
 *
 * Divi 5 has no native alert/notice module, so the alert is rendered as a
 * styled `<div>` block preserving the type colour and content.
 */
class AlertConverter extends BaseElementorConverter {
    private const TYPE_COLORS = [
        'info'    => '#d1ecf1',
        'success' => '#d4edda',
        'warning' => '#fff3cd',
        'danger'  => '#f8d7da',
    ];

    private const TYPE_BORDER_COLORS = [
        'info'    => '#bee5eb',
        'success' => '#c3e6cb',
        'warning' => '#ffeeba',
        'danger'  => '#f5c6cb',
    ];

    private const TYPE_TEXT_COLORS = [
        'info'    => '#0c5460',
        'success' => '#155724',
        'warning' => '#856404',
        'danger'  => '#721c24',
    ];

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_alert_' );
        $settings = $element['settings'] ?? [];

        $type        = is_string( $settings['alert_type'] ?? '' ) ? ( $settings['alert_type'] ?? 'info' ) : 'info';
        $title       = is_string( $settings['alert_title'] ?? '' ) ? ( $settings['alert_title'] ?? '' ) : '';
        $description = is_string( $settings['alert_description'] ?? '' ) ? ( $settings['alert_description'] ?? '' ) : '';

        $bg     = self::TYPE_COLORS[ $type ] ?? self::TYPE_COLORS['info'];
        $border = self::TYPE_BORDER_COLORS[ $type ] ?? self::TYPE_BORDER_COLORS['info'];
        $color  = self::TYPE_TEXT_COLORS[ $type ] ?? self::TYPE_TEXT_COLORS['info'];

        $style = "background-color:{$bg};border:1px solid {$border};color:{$color};padding:12px 20px;border-radius:4px;";

        $html = '<div style="' . $style . '">';
        if ( $title !== '' ) {
            $html .= '<strong>' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '</strong> ';
        }
        if ( $description !== '' ) {
            $html .= htmlspecialchars( $description, ENT_QUOTES, 'UTF-8' );
        }
        $html .= '</div>';

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, [
            'alert_type', 'alert_title', 'alert_description',
            'dismiss_button', 'dismiss_icon',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $html ],
                    ],
                ],
            ],
            'elements' => [],
        ];
    }
}
