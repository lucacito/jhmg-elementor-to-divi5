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
        $text     = is_string( $this->getSettingValue( $settings, 'text', '' ) )
            ? $this->getSettingValue( $settings, 'text', '' )
            : '';
        $link       = is_array( $settings['link'] ?? null ) ? $settings['link'] : [];
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

        $style = ( new StyleMapper() )->map( 'button', $settings );
        // Inject innerContent alongside any button.decoration from StyleMapper.
        // array_merge would clobber the entire 'button' key.
        $attrs = $style['divi_attrs'];
        $attrs['button']['innerContent']['desktop']['value'] = $button_value;

        $this->engine->logConverted( 'button' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'text', 'link' ],
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
