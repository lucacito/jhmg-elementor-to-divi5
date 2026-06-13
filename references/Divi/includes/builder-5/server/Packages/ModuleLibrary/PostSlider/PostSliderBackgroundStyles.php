<?php
/**
 * Shared slide background helpers for Post Slider modules.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostSlider;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils as StyleUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;

/**
 * Shared background style generation for Post Slider variants.
 *
 * @since ??
 */
class PostSliderBackgroundStyles {
	/**
	 * Builds background attributes for slide background image blending.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $slide_image      Slide background image URL.
	 *
	 * @return array
	 */
	public static function build_slide_background_attrs( array $background_attrs, array $enabled_attr, string $slide_image ): array {
		foreach ( $enabled_attr as $attr_breakpoint => $attr_state_values ) {
			foreach ( $attr_state_values as $attr_state => $enabled ) {
				if ( ! array_key_exists( $attr_breakpoint, $background_attrs ) ) {
					$background_attrs[ $attr_breakpoint ] = [];
				}

				if ( ! array_key_exists( $attr_state, $background_attrs[ $attr_breakpoint ] ) ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ] = [];
				}

				if ( ! array_key_exists( 'image', $background_attrs[ $attr_breakpoint ][ $attr_state ] ) ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image'] = [];
				}

				if ( 'on' === $enabled ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url']     = $slide_image;
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['enabled'] = 'on';
				} else {
					$url = $background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url'] ?? null;

					if ( null === $url && ( 'desktop' !== $attr_breakpoint || 'value' !== $attr_state ) ) {
						$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url'] = '';
					}
				}
			}
		}

		return $background_attrs;
	}

	/**
	 * Gets slide background styles for background image blending.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $image_placement  Image placement setting.
	 * @param string $slide_image      Slide background image URL.
	 *
	 * @return array
	 */
	public static function get_slide_background_styles(
		array $background_attrs,
		array $enabled_attr,
		string $image_placement,
		string $slide_image
	): array {
		if ( 'background' !== $image_placement || empty( $slide_image ) ) {
			return [];
		}

		$background_attrs      = self::build_slide_background_attrs( $background_attrs, $enabled_attr, $slide_image );
		$background_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $background_attrs,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'getAndInheritAll',
				'defaultValue' => [],
			]
		);

		$background_styles = Declarations::background_style_declaration(
			[
				'attr'       => $background_attrs,
				'attrValue'  => $background_attr_value,
				'breakpoint' => 'desktop',
				'state'      => 'value',
				'returnType' => 'key_value_pair',
				'keyFormat'  => 'param-case',
			]
		);

		return is_array( $background_styles ) ? $background_styles : [];
	}

	/**
	 * Gets responsive slide background styles for breakpoint-specific overrides.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $image_placement  Image placement setting.
	 * @param string $slide_image      Slide background image URL.
	 * @param string $slide_selector   Slide selector.
	 *
	 * @return array
	 */
	public static function get_slide_background_responsive_styles(
		array $background_attrs,
		array $enabled_attr,
		string $image_placement,
		string $slide_image,
		string $slide_selector
	): array {
		if ( 'background' !== $image_placement || empty( $slide_image ) || '' === $slide_selector ) {
			return [];
		}

		$background_attrs = self::build_slide_background_attrs( $background_attrs, $enabled_attr, $slide_image );
		$breakpoints      = self::_get_slide_background_breakpoint_settings();
		$styles           = [];

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			if ( Breakpoint::get_base_breakpoint_name() === $breakpoint ) {
				continue;
			}

			$background_attr_value = ModuleUtils::use_attr_value(
				[
					'attr'         => $background_attrs,
					'breakpoint'   => $breakpoint,
					'state'        => 'value',
					'mode'         => 'getAndInheritAll',
					'defaultValue' => [],
				]
			);

			$background_style = Declarations::background_style_declaration(
				[
					'attr'       => $background_attrs,
					'attrValue'  => $background_attr_value,
					'breakpoint' => $breakpoint,
					'state'      => 'value',
					'important'  => true,
				]
			);

			if ( ! is_string( $background_style ) || '' === trim( $background_style ) ) {
				continue;
			}

			$at_rules = StyleUtils::get_at_rules( $breakpoint, $breakpoints );

			$styles[] = array_filter(
				[
					'selector'    => $slide_selector,
					'declaration' => $background_style,
					'atRules'     => $at_rules,
				]
			);
		}

		return $styles;
	}

	/**
	 * Build breakpoint settings for responsive slide styles.
	 *
	 * @return array
	 */
	private static function _get_slide_background_breakpoint_settings(): array {
		$breakpoint_settings = [];

		foreach ( Breakpoint::get_enabled_breakpoints() as $breakpoint ) {
			$breakpoint_name = $breakpoint['name'] ?? '';

			if ( '' === $breakpoint_name ) {
				continue;
			}

			$breakpoint_settings[ $breakpoint_name ] = [
				'baseDevice' => ! empty( $breakpoint['baseDevice'] ),
				'order'      => $breakpoint['order'] ?? 0,
				'maxWidth'   => [
					'value' => $breakpoint['maxWidth']['value'] ?? null,
				],
				'minWidth'   => [
					'value' => $breakpoint['minWidth']['value'] ?? null,
				],
			];
		}

		return $breakpoint_settings;
	}
}
