<?php
/**
 * Module Library: Contact Form 7 Module REST Controller class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactForm7;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles Contact Form 7 module REST endpoints.
 *
 * @since ??
 */
class ContactForm7Controller extends RESTController {

	/**
	 * Normalize class tokens and remove invalid/empty values.
	 *
	 * @since ??
	 *
	 * @param string $raw_class_names Raw class attribute value.
	 *
	 * @return string[]
	 */
	private static function _normalize_class_names( string $raw_class_names ): array {
		$class_tokens = preg_split( '/\s+/', trim( $raw_class_names ) );

		if ( ! is_array( $class_tokens ) ) {
			return [];
		}

		$normalized_class_tokens = [];

		foreach ( $class_tokens as $class_token ) {
			$sanitized_class_token = sanitize_html_class( (string) $class_token );

			if ( '' !== $sanitized_class_token ) {
				$normalized_class_tokens[ $sanitized_class_token ] = true;
			}
		}

		return array_keys( $normalized_class_tokens );
	}

	/**
	 * Get allowed button attributes for kses sanitization.
	 *
	 * Build from HTMLUtility central attribute definitions and explicitly
	 * remove event-handler attributes to prevent script injection.
	 *
	 * @since ??
	 *
	 * @return array<string, bool>
	 */
	private static function _get_allowed_button_attributes_for_kses(): array {
		static $allowed_button_attributes = null;

		if ( is_array( $allowed_button_attributes ) ) {
			return $allowed_button_attributes;
		}

		$allowed_button_attributes = [
			'type' => true,
		];

		foreach ( HTMLUtility::get_fixed_name_attributes() as $attribute_name => $attribute_details ) {
			$elements = $attribute_details['elements'] ?? [];

			if ( ! is_array( $elements ) || [] === $elements || in_array( 'button', $elements, true ) ) {
				$allowed_button_attributes[ $attribute_name ] = true;
			}
		}

		foreach ( HTMLUtility::get_wildcard_name_attributes() as $attribute_name => $attribute_details ) {
			$elements = $attribute_details['elements'] ?? [];

			if ( ! is_array( $elements ) || [] === $elements || in_array( 'button', $elements, true ) ) {
				$allowed_button_attributes[ $attribute_name ] = true;
			}
		}

		// Never allow event-handler attributes in transformed preview markup.
		foreach ( array_keys( HTMLUtility::get_event_handler_attributes() ) as $attribute_name ) {
			unset( $allowed_button_attributes[ $attribute_name ] );
		}

		return $allowed_button_attributes;
	}

	/**
	 * Adds Divi button class to CF7 submit controls.
	 *
	 * @since ??
	 *
	 * @param string $html Form HTML output.
	 *
	 * @return string
	 */
	public static function add_divi_button_class_to_submit( string $html ): string {
		$processed_html = preg_replace_callback(
			'/<(input|button)\b[^>]*>/i',
			static function ( array $tag_matches ): string {
				$tag = $tag_matches[0] ?? '';

				if ( '' === $tag ) {
					return '';
				}

				$class_match_result = preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $tag, $class_matches );

				if ( 1 !== $class_match_result ) {
					return $tag;
				}

				$classes = self::_normalize_class_names( (string) ( $class_matches[2] ?? '' ) );

				if ( ! in_array( 'wpcf7-submit', $classes, true ) ) {
					return $tag;
				}

				$is_button_tag   = 1 === preg_match( '/^<button\b/i', trim( $tag ) );
				$is_submit_input = 1 === preg_match( '/^<input\b/i', trim( $tag ) )
					&& 1 === preg_match( '/\btype\s*=\s*(["\']?)submit\1/i', $tag );

				// Only transform submit controls; non-submit inputs should be left untouched.
				if ( ! $is_button_tag && ! $is_submit_input ) {
					return $tag;
				}

				if ( ! in_array( 'et_pb_button', $classes, true ) ) {
					$classes[] = 'et_pb_button';
				}

				if ( ! in_array( 'et_pb_bg_layout_light', $classes, true ) ) {
					$classes[] = 'et_pb_bg_layout_light';
				}

				if ( ! in_array( 'et_pb_module', $classes, true ) ) {
					$classes[] = 'et_pb_module';
				}

				$quote_char      = $class_matches[1] ?? '"';
				$updated_class   = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;
				$full_class_attr = $class_matches[0] ?? '';

				$updated_tag = str_replace( $full_class_attr, $updated_class, $tag );

				if ( $is_button_tag ) {
					$sanitized_button_tag = wp_kses(
						$updated_tag,
						[
							'button' => self::_get_allowed_button_attributes_for_kses(),
						]
					);

					if ( '' === $sanitized_button_tag ) {
						return '<button type="submit"></button>';
					}

					return $sanitized_button_tag;
				}

				$value_match_result = preg_match( '/\bvalue\s*=\s*(?:(["\'])(.*?)\1|([^\s>]+))/i', $updated_tag, $value_matches );
				$button_text        = '';

				if ( 1 === $value_match_result ) {
					$button_text = '' !== ( $value_matches[2] ?? '' ) ? $value_matches[2] : ( $value_matches[3] ?? '' );
				}

				$button_attributes = preg_replace( '/^<input\b/i', '', $updated_tag );
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s*\/?>$/', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s+\btype\s*=\s*(?:(["\'])submit\1|submit)/i', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s+\bvalue\s*=\s*(?:(["\']).*?\1|[^\s>]+)/i', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? trim( $button_attributes ) : '';

				$button_text = esc_html( wp_specialchars_decode( $button_text, ENT_QUOTES ) );
				$button_html = '<button type="submit"';

				if ( '' !== $button_attributes ) {
					$button_html .= ' ' . $button_attributes;
				}

				$button_html .= '>' . $button_text . '</button>';

				// Re-sanitize rebuilt button markup to prevent unsafe attribute passthrough.
				$button_html = wp_kses(
					$button_html,
					[
						'button' => self::_get_allowed_button_attributes_for_kses(),
					]
				);

				if ( '' === $button_html ) {
					return '<button type="submit">' . $button_text . '</button>';
				}

				return $button_html;
			},
			$html
		);

		if ( ! is_string( $processed_html ) ) {
			return $html;
		}

		return $processed_html;
	}

	/**
	 * Adds Divi layout utility class to the first CF7 form element.
	 *
	 * @since ??
	 *
	 * @param string $html           Form HTML output.
	 * @param string $layout_display Layout display value.
	 *
	 * @return string
	 */
	public static function add_divi_layout_class_to_form( string $html, string $layout_display ): string {
		$layout_class = 'et_flex_module';

		if ( 'grid' === $layout_display ) {
			$layout_class = 'et_grid_module';
		} elseif ( 'block' === $layout_display ) {
			$layout_class = 'et_block_module';
		}

		$processed_html = preg_replace_callback(
			'/<form\b[^>]*>/i',
			static function ( array $tag_matches ) use ( $layout_class ): string {
				$tag = $tag_matches[0] ?? '';

				if ( '' === $tag ) {
					return '';
				}

				$class_match_result = preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $tag, $class_matches );

				if ( 1 !== $class_match_result ) {
					$replaced_tag = preg_replace( '/<form\b/i', '<form class="' . esc_attr( $layout_class ) . '"', $tag, 1 );

					if ( is_string( $replaced_tag ) ) {
						return $replaced_tag;
					}

					return $tag;
				}

				$classes = self::_normalize_class_names( (string) ( $class_matches[2] ?? '' ) );

				if ( ! in_array( $layout_class, $classes, true ) ) {
					$classes[] = $layout_class;
				}

				$quote_char      = $class_matches[1] ?? '"';
				$updated_class   = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;
				$full_class_attr = $class_matches[0] ?? '';

				return str_replace( $full_class_attr, $updated_class, $tag );
			},
			$html,
			1
		);

		if ( ! is_string( $processed_html ) ) {
			return $html;
		}

		return $processed_html;
	}

	/**
	 * Retrieve rendered HTML for Contact Form 7 module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$form_id        = sanitize_text_field( (string) $request->get_param( 'formId' ) );
		$include_forms  = rest_sanitize_boolean( $request->get_param( 'includeForms' ) );
		$layout_display = sanitize_text_field( (string) $request->get_param( 'layoutDisplay' ) );

		$response = [
			'html' => self::render_form_preview(
				[
					'formId'        => $form_id,
					'layoutDisplay' => $layout_display,
				]
			),
		];

		if ( $include_forms ) {
			$response['forms'] = self::get_forms_options();
		}

		return self::response_success( $response );
	}

	/**
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'formId'        => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'includeForms'  => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'layoutDisplay' => [
				'type'              => 'string',
				'default'           => 'flex',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Endpoint permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

	/**
	 * Returns Contact Form 7 form options.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_forms_options(): array {
		$options = [];
		$forms   = get_posts(
			[
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		foreach ( $forms as $form ) {
			$options[ (string) $form->ID ] = [
				'label' => $form->post_title,
			];
		}

		return $options;
	}

	/**
	 * Returns the first available Contact Form 7 form ID.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_default_form_id(): string {
		$forms_options = self::get_forms_options();
		$form_ids      = array_keys( $forms_options );

		return isset( $form_ids[0] ) ? (string) $form_ids[0] : '';
	}

	/**
	 * Determine whether rendered markup looks like a usable CF7 preview.
	 *
	 * @since ??
	 *
	 * @param string $rendered_html Rendered form HTML.
	 *
	 * @return bool
	 */
	private static function _is_usable_cf7_preview_markup( string $rendered_html ): bool {
		if ( '' === trim( wp_strip_all_tags( $rendered_html ) ) ) {
			return false;
		}

		if ( ! str_contains( $rendered_html, 'wpcf7-form' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build deterministic preview error markup for invalid/unrenderable forms.
	 *
	 * @since ??
	 *
	 * @param string $form_id Form ID.
	 *
	 * @return string
	 */
	private static function _get_preview_error_markup( string $form_id ): string {
		return sprintf(
			'<div class="wpcf7"><div class="wpcf7-response-output">Unable to load Contact Form 7 preview for form ID %s.</div></div>',
			esc_html( $form_id )
		);
	}

	/**
	 * Renders Contact Form 7 preview html.
	 *
	 * @since ??
	 *
	 * @param array $args Render args.
	 *
	 * @return string
	 */
	public static function render_form_preview( array $args ): string {
		$form_id        = sanitize_text_field( (string) ( $args['formId'] ?? '' ) );
		$layout_display = sanitize_text_field( (string) ( $args['layoutDisplay'] ?? 'flex' ) );

		if ( '' === $form_id ) {
			$form_id = self::_get_default_form_id();
		}

		if ( '' === $form_id ) {
			return '';
		}

		if ( class_exists( '\WPCF7_ContactForm' ) ) {
			$form_post = get_post( (int) $form_id );

			if (
				! $form_post instanceof \WP_Post
				|| 'wpcf7_contact_form' !== $form_post->post_type
			) {
				return self::_get_preview_error_markup( $form_id );
			}

			$rendered = do_shortcode( sprintf( '[contact-form-7 id="%s"]', esc_attr( $form_id ) ) );
			$rendered = self::add_divi_button_class_to_submit( $rendered );
			$rendered = self::add_divi_layout_class_to_form( $rendered, $layout_display );

			if ( self::_is_usable_cf7_preview_markup( $rendered ) ) {
				return $rendered;
			}

			return self::_get_preview_error_markup( $form_id );
		}

		// Deterministic fallback for non-CF7 test environments.
		$fallback_markup = sprintf(
			'<div class="wpcf7"><form class="wpcf7-form"><label>Name<input class="wpcf7-form-control" type="text" /></label><input class="wpcf7-form-control wpcf7-submit" type="submit" value="Send" /><div class="wpcf7-response-output">Preview form %s</div></form></div>',
			esc_html( $form_id )
		);

		return self::add_divi_layout_class_to_form(
			self::add_divi_button_class_to_submit( $fallback_markup ),
			$layout_display
		);
	}
}
