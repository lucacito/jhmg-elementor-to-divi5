<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "aae--notification" widget (Animation Addons for Elementor,
 * notification.php — get_name() returns 'aae--notification', not
 * 'wcf--notification') to a divi/group with the notice text and its button.
 *
 * Fully static content (notify_text, btn_text, btn_link); only its
 * localStorage-based dismiss/close behaviour has no Divi equivalent and is
 * logged as not carried over.
 */
class AaeNotificationConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_notification_' );
        $settings = $element['settings'] ?? [];

        $text     = is_string( $settings['notify_text'] ?? null ) ? ( $settings['notify_text'] ?? '' ) : '';
        $btn_text = is_string( $settings['btn_text'] ?? null ) ? ( $settings['btn_text'] ?? '' ) : '';
        $btn_link = is_array( $settings['btn_link'] ?? null ) ? $settings['btn_link'] : [];
        $btn_url  = is_string( $btn_link['url'] ?? '' ) ? ( $btn_link['url'] ?? '' ) : '';

        $children = [];
        if ( $text !== '' ) {
            $children[] = [
                'id'       => $id . '-text',
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $text ] ] ] ],
                'elements' => [],
            ];
        }
        if ( $btn_text !== '' || $btn_url !== '' ) {
            $button_value = [];
            if ( $btn_text !== '' ) {
                $button_value['text'] = $btn_text;
            }
            if ( $btn_url !== '' ) {
                $button_value['linkUrl'] = $btn_url;
            }
            $children[] = [
                'id'       => $id . '-button',
                'name'     => 'divi/button',
                'settings' => [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => $button_value ] ] ] ],
                'elements' => [],
            ];
        }

        $this->engine->logNotCarriedOver( 'dismissible_notice', (string) $id, 'the localStorage-based dismiss/close behaviour has no Divi equivalent; the notice always shows' );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'notify_text', 'btn_text', 'btn_link', 'notify_align',
            'close_icon', 'close_icon_pos',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
