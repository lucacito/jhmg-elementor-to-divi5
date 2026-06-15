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
                $child_settings = $child['settings'] ?? [];
                $pos            = $child_settings['_position'] ?? $child_settings['position'] ?? '';

                if ( $pos === 'absolute' ) {
                    // Absolute-positioned container: unwrap it and apply position:absolute
                    // to its child modules so they overlay the parent rather than create a
                    // full-width row that disrupts the normal flow layout.
                    foreach ( $this->convertAbsoluteContainerChildren( $child ) as $abs_block ) {
                        $result[] = $abs_block;
                    }
                } else {
                    $result[] = $this->convertInnerAsRow( $child );
                }
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
     * Converts an absolute-positioned Elementor container by unwrapping it:
     * the container's own styles (background, padding, border-radius, etc.) are
     * forwarded onto each child module, and `position: absolute` CSS is appended
     * so the module overlays the parent rather than creating a full-width row.
     *
     * @return array<int, array> Flat list of converted Divi module blocks.
     */
    protected function convertAbsoluteContainerChildren( array $element ): array {
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];
        $blocks   = [];

        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }

            $elType = $child['elType'] ?? '';

            if ( $elType === 'section' || $elType === 'container' ) {
                // Recurse: nested absolute containers are also unwrapped.
                foreach ( $this->convertAbsoluteContainerChildren( $child ) as $b ) {
                    $blocks[] = $b;
                }
            } else {
                $converted = $this->engine->convertElement( $child );
                if ( empty( $converted ) ) {
                    continue;
                }
                if ( isset( $converted['name'] ) ) {
                    $blocks[] = $converted;
                } else {
                    foreach ( $converted as $b ) {
                        if ( ! empty( $b ) ) {
                            $blocks[] = $b;
                        }
                    }
                }
            }
        }

        // Map the container's own styles (background, border-radius, padding, etc.)
        // so they are not lost when the wrapper is discarded.
        $container_style = ( new StyleMapper() )->map( 'column', $settings );
        $container_attrs = $container_style['divi_attrs'];

        foreach ( $blocks as &$block ) {
            if ( ! isset( $block['settings'] ) ) {
                $block['settings'] = [];
            }

            // Merge container decoration (background, border, spacing) as the base;
            // the child module's own settings take priority where keys overlap.
            $block['settings'] = $this->deepMergeSettings( $container_attrs, $block['settings'] );

            // Append position: absolute after the merge so it always wins.
            $existing = $block['settings']['css']['desktop']['value']['main'] ?? '';
            $rule     = 'position: absolute;';
            $block['settings']['css']['desktop']['value']['main'] = $existing !== ''
                ? rtrim( $existing, '; ' ) . '; ' . $rule
                : $rule;
        }
        unset( $block );

        return $blocks;
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

        // Apply the container's own styles (background, padding, min-height) to the row.
        $style      = ( new StyleMapper() )->map( 'row', $settings );
        $row_styles = $style['divi_attrs'];

        if ( $this->isGridContainer( $settings ) ) {
            $columns      = $this->convertGridChildren( $children );
            $row_settings = $this->applyBoxedWidthToRow(
                $this->deepMergeSettings( $row_styles, $this->rowGridSettingsFromContainer( $settings ) ),
                $settings
            );
            return [
                'id'       => $id,
                'name'     => 'divi/row',
                'settings' => $row_settings,
                'elements' => $columns,
            ];
        }

        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            // When child containers don't all have clean Divi fractions, Divi's
            // columnStructure would be incomplete and render incorrectly. Use the
            // group-in-column approach instead: a single 4_4 column with flex
            // layout containing divi/group blocks for each sub-container.
            if ( ! $this->allChildContainersHaveCleanFraction( $children ) ) {
                [ $row_settings, $single_col ] = $this->buildGroupColumnLayout( $id, $row_styles, $settings, $children );
                $row_settings = $this->applyBoxedWidthToRow( $row_settings, $settings );
                return [
                    'id'       => $id,
                    'name'     => 'divi/row',
                    'settings' => $row_settings,
                    'elements' => [ $single_col ],
                ];
            }

            $columns      = $this->convertFlexRowChildren( $children );
            $row_settings = $this->applyBoxedWidthToRow(
                $this->deepMergeSettings(
                    $row_styles,
                    $this->deepMergeSettings(
                        $this->rowSettingsFromColumns( $columns ),
                        $this->rowFlexSettingsFromContainer( $settings )
                    )
                ),
                $settings
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

        // Propagate flex-column layout (gap, align-items, justify-content) to the
        // single auto-generated wrapping column so that Elementor's flex-column
        // intent (centered children, controlled spacing) is preserved in Divi.
        if ( count( $row_elements ) === 1 && ( $row_elements[0]['name'] ?? '' ) === 'divi/column' ) {
            $col_flex = $this->columnFlexSettingsFromContainer( $settings );
            if ( ! empty( $col_flex ) ) {
                $row_elements[0]['settings'] = $this->deepMergeSettings(
                    $row_elements[0]['settings'] ?? [],
                    $col_flex
                );
            }
        }

        $row_settings = $this->applyBoxedWidthToRow(
            $this->deepMergeSettings( $row_styles, $this->rowSettingsFromColumns( $row_elements ) ),
            $settings
        );

        return [
            'id'       => $id,
            'name'     => 'divi/row',
            'settings' => $row_settings,
            'elements' => $row_elements,
        ];
    }

    /**
     * Returns false when any container child has an explicit width that cannot
     * be expressed as a standard Divi column fraction, signalling that the
     * group-in-column approach should be used instead of a multi-column row.
     *
     * Containers with NO explicit width (auto-sized flex items) are considered
     * clean — Divi handles them as equal-width flex children by default.
     * Containers with a percentage width that maps to a known fraction are also
     * clean. Only containers with a non-% unit (custom, px, em, …) or a %
     * value that falls outside COLUMN_SIZE_MAP trigger the group fallback.
     */
    protected function allChildContainersHaveCleanFraction( array $elements ): bool {
        foreach ( $elements as $el ) {
            if ( ( $el['elType'] ?? '' ) !== 'container' ) {
                continue;
            }
            $settings = $el['settings'] ?? [];

            // _inline_size takes priority (mirrors extractFlexItemColumnSettings).
            $inline = $settings['_inline_size'] ?? null;
            if ( is_array( $inline ) && isset( $inline['size'] ) && $inline['size'] !== '' && $inline['size'] !== null ) {
                $fraction = StyleMapper::columnSizeToFraction( (int) round( (float) $inline['size'] ) );
                if ( $fraction === null ) {
                    return false;
                }
                continue;
            }

            $width = $settings['width'] ?? null;
            if ( ! is_array( $width ) || ! isset( $width['size'] ) || $width['size'] === '' || $width['size'] === null ) {
                // No explicit width → auto-sized flex item → OK for multi-column.
                continue;
            }

            if ( ( $width['unit'] ?? '' ) !== '%' ) {
                // Explicit non-% width (custom, px, em, …) → cannot be a fraction.
                return false;
            }

            $fraction = StyleMapper::columnSizeToFraction( (int) round( (float) $width['size'] ) );
            if ( $fraction === null ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts an Elementor container to a divi/group block.
     *
     * Used when sub-containers cannot be expressed as Divi columns (arbitrary
     * widths) and must instead live as flex children inside a parent column.
     *
     * Handles:
     * - Container decoration (background, padding, border) via StyleMapper.
     * - Percentage width → inline CSS `width: N%`.
     * - `_flex_align_self` → inline CSS `align-self: value`.
     * - Flex layout when the container is a flex-row with widget-only children.
     * - Nested flex-row containers with sub-containers are recursed as groups.
     */
    protected function convertContainerAsGroup( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_group_' );
        $settings = $element['settings'] ?? [];
        $children = $element['elements'] ?? [];

        $style       = ( new StyleMapper() )->map( 'column', $settings );
        $group_attrs = $style['divi_attrs'];

        // Percentage width and align-self cannot be expressed as block attrs —
        // emit them as freeForm CSS on the group selector.
        $css_rules = [];

        $width = $settings['width'] ?? null;
        if ( is_array( $width ) && isset( $width['size'] ) && $width['size'] !== '' && ( $width['unit'] ?? '' ) === '%' ) {
            $css_rules[] = 'width:' . $width['size'] . '%;';
        }

        $align_self = $settings['_flex_align_self'] ?? '';
        if ( is_string( $align_self ) && $align_self !== '' ) {
            $css_rules[] = 'align-self: ' . $align_self . ';';
        }

        if ( ! empty( $css_rules ) ) {
            $group_attrs = $this->deepMergeSettings( $group_attrs, [
                'css' => [ 'desktop' => [ 'value' => [ 'freeForm' => 'selector { ' . implode( ' ', $css_rules ) . ' }' ] ] ],
            ] );
        }

        // Flex-row with only widget children: apply flex layout to the group
        // so its module children flow horizontally.
        if ( $this->isFlexRowContainer( $settings ) && ! $this->hasContainerChildren( $children ) ) {
            $flex = $this->rowFlexSettingsFromContainer( $settings );
            if ( ! empty( $flex ) ) {
                $group_attrs = $this->deepMergeSettings( $group_attrs, $flex );
            }
        }

        // Nested flex-row with container children: recurse with groups and
        // apply the flex layout to this group's own decoration.
        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            $flex = $this->rowFlexSettingsFromContainer( $settings );
            if ( ! empty( $flex ) ) {
                $group_attrs = $this->deepMergeSettings( $group_attrs, $flex );
            }
            $elements = [];
            foreach ( $children as $child ) {
                if ( ! is_array( $child ) ) {
                    continue;
                }
                if ( ( $child['elType'] ?? '' ) === 'container' ) {
                    $elements[] = $this->convertContainerAsGroup( $child );
                } else {
                    $converted = $this->engine->convertElement( $child );
                    if ( empty( $converted ) ) {
                        continue;
                    }
                    if ( isset( $converted['name'] ) ) {
                        $elements[] = $converted;
                    } else {
                        foreach ( $converted as $block ) {
                            if ( ! empty( $block ) ) {
                                $elements[] = $block;
                            }
                        }
                    }
                }
            }
            return [
                'id'       => $id,
                'name'     => 'divi/group',
                'settings' => $group_attrs,
                'elements' => $elements,
            ];
        }

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => $group_attrs,
            'elements' => $this->convertStructureChildren( $children ),
        ];
    }

    /**
     * Builds a single-column (4_4) + groups layout from a flex-row container
     * whose child containers do not all have standard Divi column fractions.
     *
     * The parent container's `alignItems` (desktop) is placed on the row so
     * columns centre vertically within a min-height row. All other flex
     * settings (direction, justifyContent, gap, responsive breakpoints) are
     * placed on the wrapping column, which becomes the true flex container
     * for the group children.
     *
     * @return array{0: array, 1: array} [row_settings, divi/column element]
     */
    protected function buildGroupColumnLayout( string $id, array $row_styles, array $container_settings, array $children ): array {
        $all_flex        = $this->rowFlexSettingsFromContainer( $container_settings );
        $all_flex_layout = $all_flex['module']['decoration']['layout'] ?? [];

        $row_layout = [];
        $col_layout = [];

        foreach ( $all_flex_layout as $bp => $bp_data ) {
            $value     = $bp_data['value'] ?? [];
            $row_value = [];
            $col_value = [];

            foreach ( $value as $k => $v ) {
                // Desktop alignItems: centres the column vertically inside
                // the hero row's min-height. Everything else (direction,
                // justifyContent, gap, responsive alignItems) goes on the column.
                if ( $k === 'alignItems' && $bp === 'desktop' ) {
                    $row_value[ $k ] = $v;
                } else {
                    $col_value[ $k ] = $v;
                }
            }

            if ( ! empty( $row_value ) ) {
                $row_layout[ $bp ] = [ 'value' => $row_value ];
            }
            if ( ! empty( $col_value ) ) {
                $col_layout[ $bp ] = [ 'value' => $col_value ];
            }
        }

        // Row: StyleMapper output + columnStructure 4_4 + alignItems-only layout.
        $row_settings = $row_styles;
        $row_settings['module']['advanced']['columnStructure'] = [ 'desktop' => [ 'value' => '4_4' ] ];
        if ( ! empty( $row_layout ) ) {
            $row_settings = $this->deepMergeSettings( $row_settings, [
                'module' => [ 'decoration' => [ 'layout' => $row_layout ] ],
            ] );
        } elseif ( isset( $row_settings['module']['decoration']['layout'] ) ) {
            unset( $row_settings['module']['decoration']['layout'] );
        }

        // Column: type 4_4 + remaining flex settings.
        $col_settings = [
            'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ],
        ];
        if ( ! empty( $col_layout ) ) {
            $col_settings = $this->deepMergeSettings( $col_settings, [
                'module' => [ 'decoration' => [ 'layout' => $col_layout ] ],
            ] );
        }

        // Convert child containers as groups, widget children normally.
        $elements = [];
        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            if ( ( $child['elType'] ?? '' ) === 'container' ) {
                $elements[] = $this->convertContainerAsGroup( $child );
            } else {
                $converted = $this->engine->convertElement( $child );
                if ( empty( $converted ) ) {
                    continue;
                }
                if ( isset( $converted['name'] ) ) {
                    $elements[] = $converted;
                } else {
                    foreach ( $converted as $block ) {
                        if ( ! empty( $block ) ) {
                            $elements[] = $block;
                        }
                    }
                }
            }
        }

        $column = [
            'id'       => $id . '-col',
            'name'     => 'divi/column',
            'settings' => $col_settings,
            'elements' => $elements,
        ];

        return [ $row_settings, $column ];
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
     * axis is horizontal (row or row-reverse).
     *
     * Elementor's Flexbox Container CSS sets `--flex-direction: column` as the
     * default, so containers with no explicit direction are column containers.
     * We therefore only treat the container as a row when `flex_direction` is
     * explicitly set to "row" or "row-reverse".
     *
     * Grid containers are excluded — they use a different layout model.
     */
    protected function isFlexRowContainer( array $settings ): bool {
        if ( ( $settings['container_type'] ?? 'flex' ) === 'grid' ) {
            return false;
        }
        $dir = $settings['flex_direction'] ?? '';
        return in_array( $dir, [ 'row', 'row-reverse' ], true );
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

        // Apply the container's own styles (background, padding, min-height, border, etc.)
        $style        = ( new StyleMapper() )->map( 'column', $settings );
        $col_settings = $this->deepMergeSettings(
            $this->extractFlexItemColumnSettings( $settings ),
            $style['divi_attrs']
        );

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

        // Nested flex-row: wrap the sub-columns in a divi/row inside this column,
        // or use groups when the children don't have clean Divi fractions.
        if ( $this->isFlexRowContainer( $settings ) && $this->hasContainerChildren( $children ) ) {
            if ( ! $this->allChildContainersHaveCleanFraction( $children ) ) {
                // Apply the flex layout to this column and put groups inside it.
                $flex = $this->rowFlexSettingsFromContainer( $settings );
                if ( ! empty( $flex ) ) {
                    $col_settings = $this->deepMergeSettings( $col_settings, $flex );
                }
                $groups = [];
                foreach ( $children as $child ) {
                    if ( ! is_array( $child ) ) {
                        continue;
                    }
                    if ( ( $child['elType'] ?? '' ) === 'container' ) {
                        $groups[] = $this->convertContainerAsGroup( $child );
                    } else {
                        $converted = $this->engine->convertElement( $child );
                        if ( empty( $converted ) ) {
                            continue;
                        }
                        if ( isset( $converted['name'] ) ) {
                            $groups[] = $converted;
                        } else {
                            foreach ( $converted as $block ) {
                                if ( ! empty( $block ) ) {
                                    $groups[] = $block;
                                }
                            }
                        }
                    }
                }
                return [
                    'id'       => $id,
                    'name'     => 'divi/column',
                    'settings' => $col_settings,
                    'elements' => $groups,
                ];
            }

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
        // Apply column-direction flex settings (justify-content, align-items).
        $col_settings       = $this->deepMergeSettings( $col_settings, $this->columnFlexSettingsFromContainer( $settings ) );
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
     * settings across all three breakpoints.
     *
     * Elementor uses `_tablet` / `_mobile` suffixes for responsive overrides.
     * Each breakpoint's value is only written when at least one flex property
     * differs from the desktop value (or is explicitly set for that breakpoint).
     */
    protected function rowFlexSettingsFromContainer( array $settings ): array {
        $bp_suffix = [
            'desktop' => '',
            'tablet'  => '_tablet',
            'phone'   => '_mobile',
        ];

        $responsive = [];

        foreach ( $bp_suffix as $divi_bp => $suffix ) {
            $layout = [];

            $dir = $settings[ 'flex_direction' . $suffix ] ?? '';
            if ( is_string( $dir ) && $dir !== '' ) {
                $layout['flexDirection'] = $dir;
            }

            $justify = $settings[ 'flex_justify_content' . $suffix ] ?? '';
            if ( is_string( $justify ) && $justify !== '' ) {
                $layout['justifyContent'] = $justify;
            }

            $align_items = $settings[ 'flex_align_items' . $suffix ] ?? '';
            if ( is_string( $align_items ) && $align_items !== '' ) {
                $layout['alignItems'] = $align_items;
            }

            $wrap = $settings[ 'flex_wrap' . $suffix ] ?? '';
            if ( is_string( $wrap ) && $wrap !== '' ) {
                $layout['flexWrap'] = $wrap;
            }

            $align_content = $settings[ 'flex_align_content' . $suffix ] ?? '';
            if ( is_string( $align_content ) && $align_content !== '' ) {
                $layout['alignContent'] = $align_content;
            }

            $gap = $settings[ 'flex_gap' . $suffix ] ?? null;
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

            if ( ! empty( $layout ) ) {
                $responsive[ $divi_bp ] = [ 'value' => $layout ];
            }
        }

        if ( empty( $responsive ) ) {
            return [];
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
     * Builds Divi column layout settings from an Elementor column-direction
     * container's flex settings across all three breakpoints.
     *
     * Used when the container becomes a divi/column rather than a divi/row.
     * Maps justify-content and align-items per breakpoint so that responsive
     * alignment overrides (e.g. tablet centering on a card) are preserved.
     */
    protected function columnFlexSettingsFromContainer( array $settings ): array {
        $bp_suffix = [
            'desktop' => '',
            'tablet'  => '_tablet',
            'phone'   => '_mobile',
        ];

        $responsive = [];

        foreach ( $bp_suffix as $divi_bp => $suffix ) {
            $layout = [];

            $justify = $settings[ 'flex_justify_content' . $suffix ] ?? '';
            if ( is_string( $justify ) && $justify !== '' ) {
                $layout['justifyContent'] = $justify;
            }

            $align_items = $settings[ 'flex_align_items' . $suffix ] ?? '';
            if ( is_string( $align_items ) && $align_items !== '' ) {
                $layout['alignItems'] = $align_items;
            }

            // flex_gap.row controls vertical spacing between stacked children in a
            // column-direction container. Maps to rowGap in the column layout.
            $gap = $settings[ 'flex_gap' . $suffix ] ?? null;
            if ( is_array( $gap ) ) {
                $unit    = is_string( $gap['unit'] ?? '' ) ? ( $gap['unit'] ?? 'px' ) : 'px';
                $row_gap = $gap['row'] ?? '';
                if ( $row_gap !== '' && $row_gap !== null ) {
                    $layout['rowGap'] = (string) $row_gap . $unit;
                }
            }

            if ( ! empty( $layout ) ) {
                $responsive[ $divi_bp ] = [ 'value' => $layout ];
            }
        }

        if ( empty( $responsive ) ) {
            return [];
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

        // Apply the container's own styles to the grid-item column.
        $style        = ( new StyleMapper() )->map( 'column', $settings );
        $col_settings = $style['divi_attrs'];

        // Nested grid container → row-with-grid inside this column.
        if ( $this->isGridContainer( $settings ) ) {
            $sub_columns     = $this->convertGridChildren( $children );
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

        // Plain container grid item: children stacked in the column.
        // Apply any column-direction flex settings (justify-content, align-items)
        // so that, e.g., flex-end pushes children to the bottom of a fixed-height card.
        $col_settings       = $this->deepMergeSettings( $col_settings, $this->columnFlexSettingsFromContainer( $settings ) );
        $converted_children = $this->convertStructureChildren( $children );

        return [
            'id'       => $id,
            'name'     => 'divi/column',
            'settings' => $col_settings,
            'elements' => $converted_children,
        ];
    }

    /**
     * Extracts `module.decoration.sizing` and `module.decoration.layout` from
     * section attrs and returns them separately so they can be placed on the row.
     *
     * In Divi 5 sections carry background and padding; min-height and flex
     * alignment live on the row (see the Divi 5 block format).
     *
     * @return array{0: array, 1: array}  [stripped section attrs, row sizing/layout]
     */
    protected function extractRowSizingLayout( array $section_attrs ): array {
        $row_extra = [];

        foreach ( [ 'sizing', 'layout' ] as $key ) {
            if ( isset( $section_attrs['module']['decoration'][ $key ] ) ) {
                $row_extra['module']['decoration'][ $key ] = $section_attrs['module']['decoration'][ $key ];
                unset( $section_attrs['module']['decoration'][ $key ] );
            }
        }

        if ( isset( $section_attrs['module']['decoration'] ) && empty( $section_attrs['module']['decoration'] ) ) {
            unset( $section_attrs['module']['decoration'] );
        }
        if ( isset( $section_attrs['module'] ) && empty( $section_attrs['module'] ) ) {
            unset( $section_attrs['module'] );
        }

        return [ $section_attrs, $row_extra ];
    }

    /**
     * Merges a `max-width` CSS rule into a row's settings when the source
     * Elementor container uses `content_width = 'boxed'` with a `boxed_width` value.
     *
     * Elementor's boxed mode constrains only the inner content area, so this CSS
     * belongs on the divi/row (content layer), not the divi/section (background layer).
     * Responsive tablet/mobile values are applied to the corresponding breakpoints when set.
     */
    protected function applyBoxedWidthToRow( array $row_settings, array $container_settings ): array {
        if ( ( $container_settings['content_width'] ?? '' ) !== 'boxed' ) {
            return $row_settings;
        }

        $bp_map = [
            'desktop' => 'boxed_width',
            'tablet'  => 'boxed_width_tablet',
            'phone'   => 'boxed_width_mobile',
        ];

        foreach ( $bp_map as $divi_bp => $key ) {
            $raw = $container_settings[ $key ] ?? null;
            if ( ! is_array( $raw ) || ! isset( $raw['size'] ) || $raw['size'] === '' || $raw['size'] === null ) {
                continue;
            }
            $unit  = is_string( $raw['unit'] ?? '' ) ? ( $raw['unit'] ?? 'px' ) : 'px';
            $rule  = 'max-width: ' . (string) $raw['size'] . $unit . ';';

            $existing = $row_settings['css'][ $divi_bp ]['value']['main'] ?? '';
            $merged   = ( is_string( $existing ) && $existing !== '' )
                ? rtrim( $existing, '; ' ) . '; ' . $rule
                : $rule;

            $row_settings['css'][ $divi_bp ]['value']['main'] = $merged;
        }

        return $row_settings;
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
     * Returns true when every element in $items has the given block name.
     * Used to detect when converted children are already rows/columns so that
     * an extra wrapping layer can be avoided.
     */
    protected function allNamedAs( array $items, string $name ): bool {
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || ( $item['name'] ?? '' ) !== $name ) {
                return false;
            }
        }
        return true;
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
            // CSS absolute positioning and offset — no block attr equivalent.
            '_position', 'position',
            '_offset_x', '_offset_x_end', '_offset_x_tablet',
            '_offset_y', '_offset_y_end',
            '_offset_orientation_v', '_offset_orientation_h',
            // Image-specific controls without Divi 5 block-attr equivalents.
            'object-fit', 'image_custom_dimension',
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

        // Key prefixes that are globally unimplementable in Divi 5, regardless of
        // widget type. These mirror the suppressUnimplementable() list in StyleMapper
        // and catch widget converters that do not route through StyleMapper.
        static $always_ignore_prefixes = [
            'motion_fx_',        // Motion Effects — no Divi 5 block-attr equivalent.
            'sticky_',           // Sticky behaviour — handled separately; raw keys ignored.
            '_background_',      // Advanced-tab background overrides (underscore prefix).
            '_mask_',            // Advanced-tab CSS mask.
            '_element_',         // Advanced-tab element-width / visibility overrides.
            '_flex_size',        // Advanced-tab flex-size override.
            '_transform_',       // Advanced-tab CSS transform.
            '_offset_',          // Advanced-tab position offset.
            '_box_shadow_',      // Advanced-tab box-shadow (vs. widget-level box_shadow_*).
            '_border_radius',    // Advanced-tab border-radius override.
            'ekit_testimonial_', // ElementsKit Testimonial — complex third-party widget; not fully mappable.
            'ekit_video_',       // ElementsKit Video — complex third-party widget; not fully mappable.
            'arrows_',           // Slider/carousel arrow styling — no Divi 5 block-attr equivalent.
            'dots_',             // Slider/carousel dots styling — no Divi 5 block-attr equivalent.
            'icon_typography_',  // Icon font styling — no icon-text font path in divi/icon.
        ];

        // Additional exact keys that are globally suppressible but not yet in $always_ignore.
        static $always_ignore_exact = [
            // Height selector (the pixel value is handled by mapMinHeight; the mode selector is not).
            'height', 'height_tablet', 'height_mobile',
            // Heading level — already handled as 'tag'/'header_size' in HeadingConverter.
            'title_tag',
            // Slider/layout flow direction.
            'direction',
            // Image lazy-load mode — browser behaviour, no block attr.
            'lazyload',
            // Mobile position mode selector — no block attr equivalent.
            'position_mobile',
            // Image widget spacing (margin above/below image) — no Divi 5 block attr equivalent.
            'image_spacing_custom', 'image_spacing_custom_tablet', 'image_spacing_custom_mobile',
            // Icon widget sizing and alignment controls not mapped by IconConverter.
            'icon_align', 'icon_indent', 'icon_spacing',
            'icon_size', 'icon_size_tablet', 'icon_size_mobile',
            'icon_space_mobile', 'icon_space_tablet',
            'icon_self_vertical_align_tablet', 'icon_vertical_offset_tablet',
            'size_tablet', 'size_mobile',
            // Icon widget style controls mapped to view/secondary_color but not block attrs.
            'view', 'shape', 'secondary_color',
            // Blurb / icon-box layout modes.
            'number_position',
            // Button icon alignment — not yet mapped.
            'button_icon_align',
            // Icon value that appears in icon-box / blurb / feature-list widgets.
            'selected_icon',
            // Primary color that appears in multiple icon-type widgets.
            'primary_color',
            // Title/description spacing controls on blurb and icon-box — not mapped.
            'title_spacing', 'title_spacing_tablet', 'title_spacing_mobile',
            // Typography controls on sub-elements (title_typography_*, description_typography_*).
            'title_typography_letter_spacing',
            'description_typography_font_family', 'description_typography_letter_spacing',
            // Video poster image fallback.
            'self_poster_image',
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
            if ( in_array( $key, $always_ignore_exact, true ) ) {
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
            foreach ( $always_ignore_prefixes as $prefix ) {
                if ( str_starts_with( $key, $prefix ) ) {
                    continue 2;
                }
            }
            $this->engine->logSkippedSetting( "{$element_id}: {$key}" );
        }
    }
}
