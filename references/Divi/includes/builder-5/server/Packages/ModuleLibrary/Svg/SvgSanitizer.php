<?php
/**
 * SVG sanitizer utility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SvgSanitizer class.
 *
 * Applies a strict allowlist for inline SVG markup.
 *
 * @since ??
 */
class SvgSanitizer {
	/**
	 * Same-document fragment reference pattern for `<use>` href attributes.
	 */
	private const SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN = '/^#[A-Za-z_][\w:.-]*$/';

	/**
	 * Sanitize inline SVG markup.
	 *
	 * @param string $markup Raw SVG markup.
	 *
	 * @return string
	 */
	public static function sanitize_markup( string $markup ): string {
		$markup = trim( $markup );

		if ( '' === $markup || false === stripos( $markup, '<svg' ) ) {
			return '';
		}

		$sanitized_markup = wp_kses( $markup, SvgAllowedList::get_allowed_svg_html() );

		// Apply a value-level guard for `<use>` references after attribute-name allowlisting.
		// This prevents external/non-fragment references from surviving sanitization.
		return self::sanitize_use_reference_attributes( $sanitized_markup );
	}

	/**
	 * Restrict `<use>` href/xlink:href values to same-document fragments.
	 *
	 * @param string $markup Sanitized SVG markup.
	 *
	 * @return string
	 */
	private static function sanitize_use_reference_attributes( string $markup ): string {
		if ( '' === $markup || false === stripos( $markup, '<use' ) ) {
			return $markup;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();
		$loaded          = $document->loadXML( $markup, LIBXML_NONET | LIBXML_NOBLANKS );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		$use_nodes = $document->getElementsByTagName( 'use' );

		for ( $index = 0; $index < $use_nodes->length; $index++ ) {
			$use_node = $use_nodes->item( $index );

			if ( null === $use_node || ! $use_node->hasAttributes() ) {
				continue;
			}

			$attributes_to_remove = [];

			foreach ( $use_node->attributes as $attribute ) {
				$attribute_name = strtolower( $attribute->nodeName );

				if ( ! in_array( $attribute_name, [ 'href', 'xlink:href' ], true ) ) {
					continue;
				}

				$attribute_value = trim( (string) $attribute->nodeValue );
				$is_fragment_ref = 1 === preg_match( self::SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN, $attribute_value );

				if ( ! $is_fragment_ref ) {
					$attributes_to_remove[] = $attribute->nodeName;
				}
			}

			foreach ( $attributes_to_remove as $attribute_name ) {
				$use_node->removeAttribute( $attribute_name );
			}
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : $markup;
	}
}
