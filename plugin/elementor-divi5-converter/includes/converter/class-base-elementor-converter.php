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
            $preserved[ $key ] = $this->preserveResponsiveValue( $child );
        }

        return $preserved;
    }

    /**
     * Build row `settings` from an array of already-converted divi/column children.
     *
     * Reads the `module.advanced.type.desktop.value` fraction from each column
     * and assembles the `module.advanced.columnStructure.desktop.value` string
     * (e.g. "1_2,1_2") that Divi uses to render multi-column rows.
     *
     * Returns an empty array when no column has a type set (no `_column_size`).
     */
    protected function rowSettingsFromColumns( array $children ): array {
        $fractions = [];

        foreach ( $children as $child ) {
            if ( ( $child['name'] ?? '' ) !== 'divi/column' ) {
                continue;
            }
            $fraction = $child['settings']['module']['advanced']['type']['desktop']['value'] ?? null;
            if ( $fraction !== null ) {
                $fractions[] = $fraction;
            }
        }

        if ( empty( $fractions ) ) {
            return [];
        }

        return [
            'module' => [
                'advanced' => [
                    'columnStructure' => [
                        'desktop' => [ 'value' => implode( ',', $fractions ) ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Log every settings key that the converter did not explicitly handle.
     *
     * Elementor-internal bookkeeping keys (prefixed with __ or known system keys)
     * are silently ignored since they have no conversion value.
     *
     * @param string   $element_id  ID of the element being converted (for context in the report).
     * @param array    $settings    The full Elementor settings array.
     * @param string[] $mapped_keys Keys the converter already extracted; these are not logged.
     */
    protected function logUnmappedSettings( string $element_id, array $settings, array $mapped_keys = [] ): void {
        static $always_ignore = [
            '__globals__', '__dynamic__', '_id', 'css_classes',
            '_css_custom_property', 'widget_type',
        ];

        foreach ( $settings as $key => $value ) {
            if ( in_array( $key, $mapped_keys, true ) ) {
                continue;
            }
            if ( str_starts_with( $key, '__' ) ) {
                continue;
            }
            if ( in_array( $key, $always_ignore, true ) ) {
                continue;
            }
            if ( $value === '' || $value === [] || $value === null ) {
                continue;
            }
            $this->engine->logSkippedSetting( "{$element_id}: {$key}" );
        }
    }
}
