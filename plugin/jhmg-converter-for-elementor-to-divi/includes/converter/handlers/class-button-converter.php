<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ButtonConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_button_' );
        $settings = $element['settings'] ?? [];
        // Animation Addons' wcf--button (Aaeaddon_Button_Trait.php) uses its own
        // 'btn_text'/'btn_link' keys for the same TEXT/URL controls.
        $text = is_string( $this->getSettingValue( $settings, 'text', '' ) )
            ? $this->getSettingValue( $settings, 'text', '' )
            : '';
        if ( $text === '' ) {
            $text = is_string( $this->getSettingValue( $settings, 'btn_text', '' ) )
                ? $this->getSettingValue( $settings, 'btn_text', '' )
                : '';
        }
        $link       = is_array( $settings['link'] ?? null ) ? $settings['link'] : ( is_array( $settings['btn_link'] ?? null ) ? $settings['btn_link'] : [] );
        $url        = is_string( $link['url'] ?? '' ) ? ( $link['url'] ?? '' ) : '';
        // Elementor fixtures use camelCase 'isExternal'; real exports use snake_case 'is_external'.
        $new_window = ! empty( $link['isExternal'] ) || ( ( $link['is_external'] ?? '' ) === 'on' );
        $nofollow   = ! empty( $link['nofollow'] ) && $link['nofollow'] !== 'off';

        $button_value = [];
        if ( $text !== '' ) {
            $button_value['text'] = $text;
        }
        if ( $url !== '' ) {
            $button_value['linkUrl'] = $url;
        }
        if ( $new_window ) {
            $button_value['linkTarget'] = '_blank';
        }
        if ( $nofollow ) {
            $button_value['rel'] = [ 'nofollow' ];
        }

        // The standalone button widget gets Elementor's default look and the kit's
        // button theme style for whatever it left unset (StyleMapper::applyButtonDefaults()).
        $style = ( new StyleMapper() )->map( 'button', $settings, [ 'elementor_defaults' => true ] );
        // Inject innerContent alongside any button.decoration from StyleMapper.
        // array_merge would clobber the entire 'button' key.
        $attrs = $style['divi_attrs'];
        $attrs['button']['innerContent']['desktop']['value'] = $button_value;

        $this->engine->logConverted( 'button' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'text', 'link', 'btn_text', 'btn_link', 'current_link', 'btn_element_list',
                // aae--advanced-button's own hover-animation style controls (button-pro.php).
                'btn_style', 'btn_icon', 'btn_icon_position',
            ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/button',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
