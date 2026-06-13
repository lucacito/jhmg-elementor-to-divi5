<?php
/**
 * Detects when Divi content is rendered only to build a loop post excerpt (#48251).
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET_Post_Stack;

/**
 * Request-scoped context for loop excerpt generation from foreign posts.
 *
 * @since ??
 */
class LoopExcerptRenderContext {

	/**
	 * Whether the current render is another post's layout used only for loop excerpt text
	 * (must not contribute to the host page's styles or Critical CSS collection).
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_foreign_post_loop_excerpt_render(): bool {
		if ( ! isset( $GLOBALS['divi_loop_excerpt_render_post_id'] ) ) {
			return false;
		}

		$excerpt_post_id = (int) $GLOBALS['divi_loop_excerpt_render_post_id'];

		if ( 0 >= $excerpt_post_id ) {
			return false;
		}

		$main_post_id = (int) ET_Post_Stack::get_main_post_id();

		return $main_post_id !== $excerpt_post_id;
	}
}
