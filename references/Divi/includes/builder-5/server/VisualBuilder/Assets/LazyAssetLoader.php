<?php
/**
 * LazyAssetLoader class.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\TextTransform;

/**
 * Utility for registering inline lazy loaders for scripts/styles.
 *
 * @since ??
 */
class LazyAssetLoader {
	/**
	 * Runtime script handle.
	 *
	 * @var string
	 */
	private const RUNTIME_SCRIPT_HANDLE = 'divi-script-library-lazy-asset-loader';

	/**
	 * Ensure runtime script is registered.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _maybe_register_runtime_script(): void {
		if ( wp_script_is( self::RUNTIME_SCRIPT_HANDLE, 'registered' ) ) {
			return;
		}

		$runtime_package = PackageBuildManager::get_package_build( self::RUNTIME_SCRIPT_HANDLE );
		$runtime_script  = $runtime_package['script'] ?? [];
		$runtime_src     = $runtime_script['src'] ?? '';

		if ( '' === $runtime_src ) {
			return;
		}

		wp_register_script(
			self::RUNTIME_SCRIPT_HANDLE,
			$runtime_src,
			$runtime_script['deps'] ?? [],
			$runtime_package['version'] ?? '',
			$runtime_script['args'] ?? [ 'in_footer' => true ]
		);
	}

	/**
	 * Enqueue a reusable lazy asset loader.
	 *
	 * @since ??
	 *
	 * @param string $loader_handle Loader script handle.
	 * @param array  $assets        Assets configuration.
	 * @param array  $options       Loader options.
	 *
	 * @return void
	 */
	public static function enqueue_loader( $loader_handle, $assets = [], $options = [] ): void {
		$default_assets  = [
			'scripts' => [],
			'styles'  => [],
			'globals' => [],
		];
		$default_options = [
			'trigger_event'        => '',
			'trigger_message_type' => '',
			'trigger_window_flag'  => '',
			'wait_for_preloader'   => false,
			'auto_attempt'         => true,
		];

		$assets      = wp_parse_args( $assets, $default_assets );
		$options     = wp_parse_args( $options, $default_options );
		$data_object = TextTransform::pascal_case( "{$loader_handle}_config" );
		$config      = [
			'id'      => $loader_handle,
			'assets'  => $assets,
			'options' => $options,
		];

		self::_maybe_register_runtime_script();

		// Pass loader config to the shared runtime, then register the loader instance by object name.
		wp_enqueue_script( self::RUNTIME_SCRIPT_HANDLE );
		wp_localize_script( self::RUNTIME_SCRIPT_HANDLE, $data_object, $config );
		wp_add_inline_script(
			self::RUNTIME_SCRIPT_HANDLE,
			"window.DiviLazyAssetLoader&&window.DiviLazyAssetLoader.register(window.{$data_object});",
			'after'
		);
	}
}
