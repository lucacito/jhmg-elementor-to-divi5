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
            // Elementor / WordPress internals.
            '__globals__', '__dynamic__', '_id', '_element_id', 'css_classes', '_css_classes',
            '_css_custom_property', 'widget_type',
            // Element-width / layout overrides — no Divi equivalent via block attrs.
            '_element_width', '_element_custom_width',
            // Elementor render cache hint.
            '_element_cache',
            // Section/column content-width hint — no Divi equivalent; suppressed globally
            // so stray copies on non-section elements don't generate noise.
            'content_width',
            // Advanced-tab spacing keys (_padding, _margin) with all standard responsive
            // suffixes. Converters that use StyleMapper already mark these via handled_keys;
            // this entry catches converters (e.g. menu-anchor) that skip StyleMapper.
            '_padding', '_padding_tablet', '_padding_mobile',
            '_margin', '_margin_tablet', '_margin_mobile',
            // Premium Addons for Elementor UI tooltips — third-party plugin, no Divi mapping.
            'premium_tooltip_text', 'premium_tooltip_position',
            // Custom-link plugin field.
            'ra_element_link',
            // Entrance animations — Divi has its own animation system; not mappable as static attrs.
            '_animation', 'animation', '_animation_widescreen',
            '_animation_mobile', 'animation_mobile',
            'animation_duration', '_animation_delay', 'animation_delay',
            // Responsive visibility toggles — no Divi 5 block attr equivalent.
            'hide_tablet', 'hide_mobile',
            // Column order reversal on tablet — no Divi 5 block attr equivalent.
            'reverse_order_tablet',
            // CSS overflow — no block attr equivalent.
            'overflow',
            // CSS z-index — no block attr equivalent.
            '_z_index', '_z_index_tablet', '_z_index_mobile',
            // CSS absolute positioning and offset — no block attr equivalent.
            '_position',
            '_offset_x', '_offset_x_end', '_offset_x_tablet',
            '_offset_y', '_offset_y_end',
            '_offset_orientation_v', '_offset_orientation_h',
            // Image-specific controls without Divi 5 block-attr equivalents.
            'object-fit', 'css_filters_css_filter',
            'css_filters_brightness', 'css_filters_contrast',
            'css_filters_saturate', 'css_filters_hue',
            'image_custom_dimension',
            // Image link controls — not yet mapped.
            'link_to', 'open_lightbox',
            // Motion FX / transform effects — no block attr equivalent.
            '_transform_scale_effect',
            'motion_fx_transform_x_anchor_point', 'motion_fx_transform_y_anchor_point',
            // Heading size preset ("xxl", "xl", etc.) — no Divi 5 block-attr equivalent.
            'size',
            // Arbitrary width / space overrides on positioned elements.
            'width', 'space',
            // Hover / interaction states — cannot be expressed as static Divi 5 block attributes.
            'hover_color', 'button_background_hover_color',
            'button_hover_box_shadow_box_shadow_type', 'button_hover_box_shadow_box_shadow',
            'button_hover_transition_duration',
            'background_hover_background', 'background_hover_color',
            // Shadow effects — not yet mapped to Divi 5 schema.
            'text_shadow_text_shadow_type', 'text_shadow_text_shadow',
            'button_box_shadow_box_shadow_type', 'button_box_shadow_box_shadow',
            'box_shadow_box_shadow_type', 'box_shadow_box_shadow',
        ];

        // Elementor custom breakpoints (laptop, tablet_extra, widescreen …) that
        // Divi 5 does not expose; suppress these keys without mapping.
        static $extra_bp_suffixes = [
            '_laptop', '_tablet_extra', '_widescreen', '_mobile_extra', '_mobile_intermediate',
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
            foreach ( $extra_bp_suffixes as $suffix ) {
                if ( str_ends_with( $key, $suffix ) ) {
                    continue 2;
                }
            }
            $this->engine->logSkippedSetting( "{$element_id}: {$key}" );
        }
    }
}
