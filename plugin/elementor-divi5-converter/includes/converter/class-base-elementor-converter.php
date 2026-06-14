<?php

namespace ElementorDivi5Converter\Converter;

use ElementorDivi5Converter\StyleMapper\StyleMapper;

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
     * CSS Grid containers produce a row with grid layout settings. Flex-row
     * containers with container children produce a multi-column flex row.
     * Otherwise falls back to the original behaviour.
     */
    protected function convertInnerAsRow( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_row_' );
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        if ( $this->isGridContainer( $settings ) ) {
            $columns = $this->convertGridChildren( $children );
            return [
                'id'       => $id,
                'name'     => 'divi/row',
                'settings' => $this->rowGridSettingsFromContainer( $settings ),
                'elements' => $columns,
            ];
        }

        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            $columns      = $this->convertFlexRowChildren( $children );
            $row_settings = $this->deepMergeSettings(
                $this->rowSettingsFromColumns( $columns ),
                $this->rowFlexSettingsFromContainer( $settings )
            );

            return [
                'id'       => $id,
                'name'     => 'divi/row',
                'settings' => $row_settings,
                'elements' => $columns,
            ];
        }

        $inner        = $this->convertStructureChildren( $children );
        $row_elements = $this->ensureColumnChildren( $id, $inner );

        return [
            'id'       => $id,
            'name'     => 'divi/row',
            'settings' => $this->rowSettingsFromColumns( $row_elements ),
            'elements' => $row_elements,
        ];
    }

    /**
     * Returns true when the Elementor element is a CSS Grid container.
     * Detected by `container_type === 'grid'` in the settings JSON.
     */
    protected function isGridContainer( array $settings ): bool {
        return ( $settings['container_type'] ?? '' ) === 'grid';
    }

    /**
     * Returns true when the Elementor element is a flex container whose main
     * axis is horizontal (row or row-reverse). This is the default for all
     * containers when no explicit direction is set.
     *
     * Grid containers are excluded — they use a different layout model.
     */
    protected function isFlexRowContainer( array $settings ): bool {
        if ( ( $settings['container_type'] ?? 'flex' ) === 'grid' ) {
            return false;
        }
        $dir = $settings['flex_direction'] ?? '';
        return ! in_array( $dir, [ 'column', 'column-reverse' ], true );
    }

    /**
     * Returns true when $elements contains at least one element with
     * elType === 'container'. Used to detect whether a flex-row's children
     * are column-like containers rather than plain widgets.
     */
    protected function hasContainerChildren( array $elements ): bool {
        foreach ( $elements as $el ) {
            if ( ( $el['elType'] ?? '' ) === 'container' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converts a list of Elementor elements where every direct child should
     * become a divi/column flex item. Container children are converted via
     * convertContainerAsColumn(); widget children are auto-wrapped in a column;
     * column-type children are dispatched normally.
     */
    protected function convertFlexRowChildren( array $elements ): array {
        $columns = [];

        foreach ( $elements as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            $el_type = $child['elType'] ?? '';

            if ( $el_type === 'container' ) {
                $columns[] = $this->convertContainerAsColumn( $child );
            } elseif ( $el_type === 'column' ) {
                $converted = $this->engine->convertElement( $child );
                if ( ! empty( $converted ) ) {
                    $columns[] = $converted;
                }
            } else {
                // Widget or unknown element — auto-wrap in a column.
                $converted = $this->engine->convertElement( $child );
                if ( empty( $converted ) ) {
                    continue;
                }
                $widget_id = $child['id'] ?? uniqid( 'divi_widget_' );
                $columns[] = [
                    'id'       => $widget_id . '-col',
                    'name'     => 'divi/column',
                    'settings' => [],
                    'elements' => isset( $converted['name'] ) ? [ $converted ] : $converted,
                ];
            }
        }

        return $columns;
    }

    /**
     * Converts an Elementor container element into a divi/column block so it
     * acts as a flex item within a parent flex-row row.
     *
     * If the container itself is a flex-row with container children a nested
     * divi/row is created inside the column so those children stay side-by-side.
     * Otherwise the container's children are converted normally (stacked in the
     * column via the standard block flow).
     */
    protected function convertContainerAsColumn( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_col_' );
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        $col_settings = $this->extractFlexItemColumnSettings( $settings );

        // Nested grid container: wrap grid items in a grid-mode row inside the column.
        if ( $this->isGridContainer( $settings ) ) {
            $grid_items      = $this->convertGridChildren( $children );
            $nested_settings = $this->rowGridSettingsFromContainer( $settings );

            return [
                'id'       => $id,
                'name'     => 'divi/column',
                'settings' => $col_settings,
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $nested_settings,
                        'elements' => $grid_items,
                    ],
                ],
            ];
        }

        // Nested flex-row: wrap the sub-columns in a divi/row inside this column.
        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            $sub_columns      = $this->convertFlexRowChildren( $children );
            $nested_settings  = $this->deepMergeSettings(
                $this->rowSettingsFromColumns( $sub_columns ),
                $this->rowFlexSettingsFromContainer( $settings )
            );

            return [
                'id'       => $id,
                'name'     => 'divi/column',
                'settings' => $col_settings,
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $nested_settings,
                        'elements' => $sub_columns,
                    ],
                ],
            ];
        }

        // Plain column: convert children normally (stacked vertically).
        $converted_children = $this->convertStructureChildren( $children );

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => $col_settings,
            'elements' => $converted_children,
        ];
    }

    /**
     * Extracts Divi column sizing from Elementor flex-item settings.
     *
     * Checks _inline_size (Elementor's flex-basis override) then falls back to
     * the explicit width control. Only standard percentage sizes that map to a
     * Divi fraction string are written.
     */
    protected function extractFlexItemColumnSettings( array $settings ): array {
        $inline = $settings['_inline_size'] ?? null;
        if ( is_array( $inline ) && isset( $inline['size'] ) && $inline['size'] !== '' && $inline['size'] !== null ) {
            $fraction = StyleMapper::columnSizeToFraction( (int) round( (float) $inline['size'] ) );
            if ( $fraction !== null ) {
                return [ 'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => $fraction ] ] ] ] ];
            }
        }

        $width = $settings['width'] ?? null;
        if ( is_array( $width ) && isset( $width['size'] ) && $width['size'] !== '' && ( $width['unit'] ?? '%' ) === '%' ) {
            $fraction = StyleMapper::columnSizeToFraction( (int) round( (float) $width['size'] ) );
            if ( $fraction !== null ) {
                return [ 'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => $fraction ] ] ] ] ];
            }
        }

        return [];
    }

    /**
     * Builds Divi row flex layout settings from an Elementor container's flex
     * settings. The resulting array can be merged into a divi/row's settings at
     * `module.decoration.layout.desktop.value`.
     *
     * Only responsive desktop settings are mapped; Elementor tablet/mobile flex
     * direction overrides are intentionally deferred.
     */
    protected function rowFlexSettingsFromContainer( array $settings ): array {
        $layout = [];

        $dir = $settings['flex_direction'] ?? '';
        // Only write when direction is non-default (Divi rows default to row).
        if ( is_string( $dir ) && $dir !== '' && $dir !== 'row' ) {
            $layout['flexDirection'] = $dir;
        }

        $justify = $settings['flex_justify_content'] ?? '';
        if ( is_string( $justify ) && $justify !== '' ) {
            $layout['justifyContent'] = $justify;
        }

        $align_items = $settings['flex_align_items'] ?? '';
        if ( is_string( $align_items ) && $align_items !== '' ) {
            $layout['alignItems'] = $align_items;
        }

        $wrap = $settings['flex_wrap'] ?? '';
        if ( is_string( $wrap ) && $wrap !== '' ) {
            $layout['flexWrap'] = $wrap;
        }

        $align_content = $settings['flex_align_content'] ?? '';
        if ( is_string( $align_content ) && $align_content !== '' ) {
            $layout['alignContent'] = $align_content;
        }

        $gap = $settings['flex_gap'] ?? null;
        if ( is_array( $gap ) ) {
            $unit    = is_string( $gap['unit'] ?? '' ) ? ( $gap['unit'] ?? 'px' ) : 'px';
            $col_gap = $gap['column'] ?? '';
            $row_gap = $gap['row'] ?? '';
            if ( $col_gap !== '' && $col_gap !== null ) {
                $layout['columnGap'] = (string) $col_gap . $unit;
            }
            if ( $row_gap !== '' && $row_gap !== null ) {
                $layout['rowGap'] = (string) $row_gap . $unit;
            }
        }

        if ( empty( $layout ) ) {
            return [];
        }

        return [
            'module' => [
                'decoration' => [
                    'layout' => [
                        'desktop' => [ 'value' => $layout ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds Divi row grid layout settings from an Elementor grid container's
     * settings. Maps `grid_columns_grid`, `grid_gaps`, and their responsive
     * variants to Divi's `module.decoration.layout` attribute path.
     *
     * Elementor keys (prefixed with `grid_` from Group_Control_Grid_Container):
     *   grid_columns_grid / _tablet / _mobile  — columns slider {size, unit}
     *   grid_rows_grid / _tablet / _mobile      — rows slider {size, unit}
     *   grid_gaps / _tablet / _mobile           — gaps {column, row, unit}
     *   grid_auto_flow                          — 'row' | 'column'
     *   grid_justify_items                      — justify-items value
     *   grid_align_items                        — align-items value
     */
    protected function rowGridSettingsFromContainer( array $settings ): array {
        $bp_map = [
            'desktop' => '',
            'tablet'  => '_tablet',
            'phone'   => '_mobile',
        ];

        $responsive = [];

        foreach ( $bp_map as $divi_bp => $suffix ) {
            $value = [ 'display' => 'grid' ];

            // Column count / template.
            $cols = $settings[ 'grid_columns_grid' . $suffix ] ?? null;
            if ( is_array( $cols ) ) {
                $unit = $cols['unit'] ?? 'fr';
                $size = isset( $cols['size'] ) ? (string) $cols['size'] : '';
                if ( $size !== '' ) {
                    if ( $unit === 'fr' ) {
                        $value['gridColumnWidths'] = 'equal';
                        $value['gridColumnCount']  = $size;
                    } else {
                        $value['gridColumnWidths']    = 'manual';
                        $value['gridTemplateColumns'] = $size;
                    }
                }
            }

            // Row count / template.
            $rows = $settings[ 'grid_rows_grid' . $suffix ] ?? null;
            if ( is_array( $rows ) ) {
                $unit = $rows['unit'] ?? 'fr';
                $size = isset( $rows['size'] ) ? (string) $rows['size'] : '';
                if ( $size !== '' && $unit === 'fr' ) {
                    $value['gridRowCount'] = $size;
                }
            }

            // Gaps.
            $gaps = $settings[ 'grid_gaps' . $suffix ] ?? null;
            if ( is_array( $gaps ) ) {
                $gap_unit = is_string( $gaps['unit'] ?? '' ) ? ( $gaps['unit'] ?? 'px' ) : 'px';
                $col_gap  = $gaps['column'] ?? '';
                $row_gap  = $gaps['row'] ?? '';
                if ( $col_gap !== '' && $col_gap !== null ) {
                    $value['columnGap'] = (string) $col_gap . $gap_unit;
                }
                if ( $row_gap !== '' && $row_gap !== null ) {
                    $value['rowGap'] = (string) $row_gap . $gap_unit;
                }
            }

            // Auto flow (desktop only; 'row' is the default, omit it).
            if ( $divi_bp === 'desktop' ) {
                $auto_flow = $settings['grid_auto_flow'] ?? '';
                if ( is_string( $auto_flow ) && $auto_flow !== '' && $auto_flow !== 'row' ) {
                    $value['gridAutoFlow'] = $auto_flow;
                }

                $justify_items = $settings['grid_justify_items'] ?? '';
                if ( is_string( $justify_items ) && $justify_items !== '' ) {
                    $value['gridJustifyItems'] = $justify_items;
                }

                $align_items = $settings['grid_align_items'] ?? '';
                if ( is_string( $align_items ) && $align_items !== '' ) {
                    $value['alignItems'] = $align_items;
                }
            }

            // Only write non-desktop breakpoints when they carry something beyond
            // the bare `display:grid` marker (which desktop always emits).
            if ( $divi_bp !== 'desktop' && count( $value ) <= 1 ) {
                continue;
            }

            $responsive[ $divi_bp ] = [ 'value' => $value ];
        }

        return [
            'module' => [
                'decoration' => [
                    'layout' => $responsive,
                ],
            ],
        ];
    }

    /**
     * Converts children of an Elementor grid container so that each direct
     * child becomes a `divi/column` grid item.
     *
     * - Container children are converted via convertContainerAsGridItem().
     * - Widget / other children are converted normally then auto-wrapped.
     */
    protected function convertGridChildren( array $elements ): array {
        $columns = [];

        foreach ( $elements as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            $el_type = $child['elType'] ?? '';

            if ( $el_type === 'container' ) {
                $columns[] = $this->convertContainerAsGridItem( $child );
            } elseif ( $el_type === 'column' ) {
                $converted = $this->engine->convertElement( $child );
                if ( ! empty( $converted ) ) {
                    $columns[] = $converted;
                }
            } else {
                $converted = $this->engine->convertElement( $child );
                if ( empty( $converted ) ) {
                    continue;
                }
                $widget_id = $child['id'] ?? uniqid( 'divi_gi_' );
                $columns[] = [
                    'id'       => $widget_id . '-gi',
                    'name'     => 'divi/column',
                    'settings' => [],
                    'elements' => isset( $converted['name'] ) ? [ $converted ] : $converted,
                ];
            }
        }

        return $columns;
    }

    /**
     * Converts an Elementor container that is a direct child of a grid container
     * into a divi/column that acts as a grid item.
     *
     * If the child container is itself a grid, its grid settings are applied
     * as a nested divi/row inside the column. Otherwise children are stacked
     * normally inside the column.
     */
    protected function convertContainerAsGridItem( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gi_' );
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        // Nested grid container → row-with-grid inside this column.
        if ( $this->isGridContainer( $settings ) ) {
            $sub_columns     = $this->convertGridChildren( $children );
            $nested_settings = $this->rowGridSettingsFromContainer( $settings );

            return [
                'id'       => $id,
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $nested_settings,
                        'elements' => $sub_columns,
                    ],
                ],
            ];
        }

        // Nested flex row with container children → a flex row inside the column.
        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            $sub_columns     = $this->convertFlexRowChildren( $children );
            $nested_settings = $this->deepMergeSettings(
                $this->rowSettingsFromColumns( $sub_columns ),
                $this->rowFlexSettingsFromContainer( $settings )
            );

            return [
                'id'       => $id,
                'name'     => 'divi/column',
                'settings' => [],
                'elements' => [
                    [
                        'id'       => $id . '-row',
                        'name'     => 'divi/row',
                        'settings' => $nested_settings,
                        'elements' => $sub_columns,
                    ],
                ],
            ];
        }

        // Plain container grid item: children stacked in the column.
        $converted_children = $this->convertStructureChildren( $children );

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => [],
            'elements' => $converted_children,
        ];
    }

    /**
     * Recursively merges two settings arrays without losing sibling keys.
     * Unlike array_merge, nested arrays are merged rather than replaced.
     */
    protected function deepMergeSettings( array $a, array $b ): array {
        foreach ( $b as $k => $v ) {
            if ( is_array( $v ) && isset( $a[ $k ] ) && is_array( $a[ $k ] ) ) {
                $a[ $k ] = $this->deepMergeSettings( $a[ $k ], $v );
            } else {
                $a[ $k ] = $v;
            }
        }
        return $a;
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
            // Button-specific shadow (separate from module box shadow — not yet mapped).
            'button_box_shadow_box_shadow_type', 'button_box_shadow_box_shadow',
            // Elementor Flexbox Container layout — handled by rowFlexSettingsFromContainer().
            'container_type',
            'flex_direction', 'flex_direction_tablet', 'flex_direction_mobile',
            'flex_justify_content', 'flex_justify_content_tablet', 'flex_justify_content_mobile',
            'flex_align_items', 'flex_align_items_tablet', 'flex_align_items_mobile',
            'flex_gap', 'flex_gap_tablet', 'flex_gap_mobile',
            'flex_wrap', 'flex_wrap_tablet', 'flex_wrap_mobile',
            'flex_align_content', 'flex_align_content_tablet', 'flex_align_content_mobile',
            // Hidden direction-mode fields produced by the flex-container group control.
            'flex__is_row', 'flex__is_column',
            // Boxed/full container width controls.
            'boxed_width', 'boxed_width_tablet', 'boxed_width_mobile',
            // Container min-height (handled by StyleMapper for section/column).
            'min_height', 'min_height_tablet', 'min_height_mobile',
            // Container HTML tag.
            'html_tag',
            // Elementor Grid Container controls — handled by rowGridSettingsFromContainer().
            'grid_columns_grid', 'grid_columns_grid_tablet', 'grid_columns_grid_mobile',
            'grid_rows_grid', 'grid_rows_grid_tablet', 'grid_rows_grid_mobile',
            'grid_gaps', 'grid_gaps_tablet', 'grid_gaps_mobile',
            'grid_auto_flow', 'grid_auto_flow_tablet', 'grid_auto_flow_mobile',
            'grid_justify_items', 'grid_justify_items_tablet', 'grid_justify_items_mobile',
            'grid_align_items', 'grid_align_items_tablet', 'grid_align_items_mobile',
            'grid_justify_content', 'grid_justify_content_tablet', 'grid_justify_content_mobile',
            'grid_align_content', 'grid_align_content_tablet', 'grid_align_content_mobile',
            'grid_outline', 'grid__is_row', 'grid__is_column',
            // Flex-item (child) controls — handled via extractFlexItemColumnSettings().
            '_inline_size', '_inline_size_tablet', '_inline_size_mobile',
            '_flex_basis_type', '_flex_basis_type_tablet', '_flex_basis_type_mobile',
            '_flex_basis', '_flex_basis_tablet', '_flex_basis_mobile',
            '_flex_align_self', '_flex_align_self_tablet', '_flex_align_self_mobile',
            '_flex_order', '_flex_order_tablet', '_flex_order_mobile',
            '_flex_order_custom', '_flex_order_custom_tablet', '_flex_order_custom_mobile',
            // Width / tablet / mobile for container (responsive).
            'width_tablet', 'width_mobile',
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
