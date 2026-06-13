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
     * Converts a flat list of Elementor elements with explicit structure awareness.
     *
     * This is the single routing point for layout conversion:
     * - elType "section" or "container" → convertInnerAsRow() (nested divi/row)
     * - elType "column" or widget      → engine.convertElement() (normal dispatch)
     *
     * Use this in any converter whose children might include inner sections or
     * containers (ColumnConverter, ContainerConverter). Never rely on nesting
     * depth counters for this decision — the elType in the JSON is the
     * authoritative signal.
     */
    protected function convertStructureChildren( array $elements ): array {
        $result = [];

        foreach ( $elements as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            $elType = $child['elType'] ?? '';

            if ( $elType === 'section' || $elType === 'container' ) {
                $result[] = $this->convertInnerAsRow( $child );
            } else {
                $converted = $this->engine->convertElement( $child );

                if ( empty( $converted ) ) {
                    continue;
                }

                // Converters may return a single block (has 'name') or a list.
                if ( isset( $converted['name'] ) ) {
                    $result[] = $converted;
                } else {
                    foreach ( $converted as $block ) {
                        if ( ! empty( $block ) ) {
                            $result[] = $block;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Converts an inner Elementor section or container into a divi/row block.
     *
     * The element's own children are converted recursively with structure
     * awareness via convertStructureChildren(). Non-column children (e.g. a
     * container whose children are plain widgets) are auto-wrapped in a single
     * divi/column so that the row always contains only columns.
     */
    protected function convertInnerAsRow( array $element ): array {
        $id           = $element['id'] ?? uniqid( 'divi_row_' );
        $inner        = $this->convertStructureChildren( $element['elements'] ?? [] );
        $row_elements = $this->ensureColumnChildren( $id, $inner );

        return [
            'id'       => $id,
            'name'     => 'divi/row',
            'settings' => $this->rowSettingsFromColumns( $row_elements ),
            'elements' => $row_elements,
        ];
    }

    /**
     * Guarantees a list contains only divi/column blocks.
     *
     * When every item is already a divi/column the list is returned unchanged.
     * Otherwise all items are wrapped in one auto-generated divi/column so that
     * Divi's invariant (rows contain only columns) is preserved.
     */
    protected function ensureColumnChildren( string $id, array $children ): array {
        if ( empty( $children ) ) {
            return [];
        }

        $all_columns = array_reduce(
            $children,
            static fn( bool $carry, array $child ) => $carry && ( $child['name'] ?? '' ) === 'divi/column',
            true
        );

        if ( $all_columns ) {
            return $children;
        }

        return [
            [
                'id'       => $id . '-col',
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => $children,
            ],
        ];
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
