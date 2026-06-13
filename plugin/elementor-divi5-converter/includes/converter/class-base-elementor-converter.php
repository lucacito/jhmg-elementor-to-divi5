<?php

namespace ElementorDivi5Converter\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class BaseElementorConverter implements ConverterInterface {
    protected ConverterEngine $engine;

    public function __construct( ConverterEngine $engine ) {
        $this->engine = $engine;
    }

    protected function convertChildren( array $element ): array {
        return $this->engine->convertChildren( $element['elements'] ?? [] );
    }

    protected function getSettingValue( array $settings, string $key, $default = '' ) {
        if ( ! isset( $settings[ $key ] ) ) {
            return $default;
        }

        $value = $settings[ $key ];

        if ( is_array( $value ) && isset( $value['value'] ) ) {
            return $value['value'];
        }

        return $value;
    }

    protected function preserveResponsiveValue( $value ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        $preserved = [];
        foreach ( $value as $key => $child ) {
            if ( in_array( $key, [ 'desktop', 'tablet', 'mobile' ], true ) && is_array( $child ) ) {
                $preserved[ $key ] = $this->preserveResponsiveValue( $child );
                continue;
            }
            $preserved[ $key ] = $this->preserveResponsiveValue( $child );
        }

        return $preserved;
    }

    protected function normalizeSettings( array $settings ): array {
        $normalized = [];

        foreach ( $settings as $key => $value ) {
            $normalized[ $key ] = $this->preserveResponsiveValue( $value );
        }

        return $normalized;
    }

    protected function logKnownSkippedSettings( string $element_id, array $settings ): void {
        static $known = [
            'background_color',
            'background_image',
            'background_video_link',
            'custom_padding',
            'custom_margin',
        ];

        foreach ( $known as $key ) {
            if ( ! empty( $settings[ $key ] ) ) {
                $this->engine->logSkippedSetting( "{$element_id}: {$key}" );
            }
        }
    }
}
