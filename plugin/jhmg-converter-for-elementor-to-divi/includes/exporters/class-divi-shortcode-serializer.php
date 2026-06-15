<?php

namespace ElementorDivi5Converter\Exporters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DiviShortcodeSerializer {
    public function serialize( array $divi_data ): string {
        $elements = $this->resolveElements( $divi_data );

        return $this->serializeElements( $elements );
    }

    private function resolveElements( array $divi_data ): array {
        if ( isset( $divi_data['divi']['elements'] ) && is_array( $divi_data['divi']['elements'] ) ) {
            return $divi_data['divi']['elements'];
        }

        if ( isset( $divi_data['elements'] ) && is_array( $divi_data['elements'] ) ) {
            return $divi_data['elements'];
        }

        return $divi_data;
    }

    private function serializeElements( array $elements ): string {
        $output = '';

        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $output .= $this->serializeElement( $element );
        }

        return $output;
    }

    private function serializeElement( array $element ): string {
        $name = $element['name'] ?? '';
        $children = $element['elements'] ?? [];

        switch ( $name ) {
            case 'divi/section':
                return $this->wrapShortcode( 'et_pb_section', $element, $this->serializeSectionChildren( $children ) );
            case 'divi/row':
                return $this->wrapShortcode( 'et_pb_row', $element, $this->serializeRowChildren( $children ) );
            case 'divi/column':
                return $this->wrapShortcode( 'et_pb_column', $element, $this->serializeColumnChildren( $children ), [ 'type' => '4_4' ] );
            case 'divi/text':
                return $this->wrapShortcode( 'et_pb_text', $element, $this->renderTextContent( $element ) );
            case 'divi/button':
                return $this->wrapShortcode( 'et_pb_button', $element, $this->renderButtonText( $element ), $this->serializeButtonAttributes( $element ) );
            case 'divi/image':
                return $this->wrapShortcode( 'et_pb_image', $element, '', $this->serializeImageAttributes( $element ) );
            default:
                return $this->serializeElements( $children );
        }
    }

    private function serializeSectionChildren( array $children ): string {
        $output = '';

        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/row' ) {
                $output .= $this->serializeElement( $child );
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/column' ) {
                $output .= $this->serializeElement( [
                    'name'     => 'divi/row',
                    'settings' => [],
                    'elements' => [ $child ],
                ] );
                continue;
            }

            $output .= $this->serializeElement( [
                'name'     => 'divi/row',
                'settings' => [],
                'elements' => [
                    [
                        'name'     => 'divi/column',
                        'settings' => [],
                        'elements' => [ $child ],
                    ],
                ],
            ] );
        }

        return $output;
    }

    private function serializeRowChildren( array $children ): string {
        $output = '';

        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            if ( isset( $child['name'] ) && $child['name'] === 'divi/column' ) {
                $output .= $this->serializeElement( $child );
                continue;
            }

            $output .= $this->serializeElement( [
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => [ $child ],
            ] );
        }

        return $output;
    }

    private function serializeColumnChildren( array $children ): string {
        return $this->serializeElements( $children );
    }

    private function serializeButtonAttributes( array $element ): array {
        $attributes = [];
        $settings = $element['settings'] ?? [];
        $link = $settings['link'] ?? [];

        if ( is_array( $link ) && ! empty( $link['url'] ) ) {
            $attributes['button_url'] = $link['url'];
        }

        if ( is_array( $link ) && ! empty( $link['isExternal'] ) ) {
            $attributes['url_new_window'] = 'on';
        }

        if ( is_array( $link ) && ! empty( $link['nofollow'] ) ) {
            $attributes['url_nofollow'] = 'on';
        }

        return $attributes;
    }

    private function serializeImageAttributes( array $element ): array {
        $settings = $element['settings'] ?? [];
        $src = $settings['src'] ?? '';

        return [ 'src' => $src ];
    }

    private function renderTextContent( array $element ): string {
        $settings = $element['settings'] ?? [];
        $content = $settings['innerContent'] ?? '';
        $tag = $settings['tagName'] ?? '';

        if ( $tag !== '' && is_string( $tag ) ) {
            return sprintf( '<%1$s>%2$s</%1$s>', $tag, $content );
        }

        return $content;
    }

    private function renderButtonText( array $element ): string {
        return is_string( $element['settings']['text'] ?? '' ) ? $element['settings']['text'] : '';
    }

    private function wrapShortcode( string $shortcode, array $element, string $content, array $additional_attributes = [] ): string {
        $attributes = array_merge( $additional_attributes, $this->serializeShortcodeAttributes( $element ) );
        $attribute_string = $this->buildAttributeString( $attributes );

        return sprintf( '[%s%s]%s[/%s]', $shortcode, $attribute_string, $content, $shortcode );
    }

    private function serializeShortcodeAttributes( array $element ): array {
        $attributes = [];
        $settings = $element['settings'] ?? [];

        if ( isset( $settings['module'] ) && is_array( $settings['module'] ) ) {
            foreach ( $settings['module'] as $key => $value ) {
                if ( is_scalar( $value ) ) {
                    $attributes[ $key ] = $value;
                }
            }
        }

        return $attributes;
    }

    private function buildAttributeString( array $attributes ): string {
        $attribute_pairs = [];

        foreach ( $attributes as $key => $value ) {
            if ( is_bool( $value ) ) {
                $value = $value ? 'on' : 'off';
            }

            if ( $value === '' || $value === null || is_array( $value ) ) {
                continue;
            }

            $attribute_pairs[] = sprintf( ' %s="%s"', $this->escapeAttribute( $key ), $this->escapeAttribute( (string) $value ) );
        }

        return implode( '', $attribute_pairs );
    }

    private function escapeAttribute( string $value ): string {
        return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
    }
}
