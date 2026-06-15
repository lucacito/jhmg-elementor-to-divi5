<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts the Premium Addons `premium-addon-blog` widget to divi/blog.
 *
 * The Divi blog module is dynamic — it queries posts at render time — so we
 * only map configuration settings, not post content.
 *
 * Categories are skipped: the source uses slugs, Divi needs term IDs, and
 * resolving that requires a database lookup we can't do at export time.
 */
class PremiumBlogConverter extends BaseElementorConverter {

	public function convert( array $element ): array {
		$id       = $element['id'] ?? uniqid( 'divi_blog_' );
		$settings = $element['settings'] ?? [];

		$num_posts      = (string) ( $settings['premium_blog_number_of_posts'] ?? '10' );
		$excerpt_length = (string) ( $settings['premium_blog_excerpt_length'] ?? '270' );
		$show_pagination = ( ( $settings['premium_blog_paging'] ?? '' ) === 'yes' ) ? 'on' : 'off';
		$show_read_more  = ( ( $settings['premium_blog_excerpt_type'] ?? '' ) === 'link' ) ? 'on' : 'off';

		$block_settings = [
			'post' => [
				'advanced' => [
					'number'        => [ 'desktop' => [ 'value' => $num_posts ] ],
					'excerptLength' => [ 'desktop' => [ 'value' => $excerpt_length ] ],
					'showExcerpt'   => [ 'desktop' => [ 'value' => 'on' ] ],
				],
			],
			'readMore'   => [ 'advanced' => [ 'enable' => [ 'desktop' => [ 'value' => $show_read_more ] ] ] ],
			'pagination' => [ 'advanced' => [ 'enable' => [ 'desktop' => [ 'value' => $show_pagination ] ] ] ],
		];

		$this->engine->logConverted( 'blog' );
		$this->logUnmappedSettings( $id, $settings, [
			'premium_blog_number_of_posts',
			'premium_blog_excerpt_length',
			'premium_blog_paging',
			'premium_blog_excerpt_type',
			// Skipped intentionally — slug-to-term-ID resolution not possible at export time.
			'tax_category_post_filter',
			'filter_flag',
			// Visual/style settings not mapped to Divi attrs.
			'premium_blog_hover_image_effect',
			'premium_blog_excerpt_text',
			'premium_blog_article_tag_switcher',
			'max_pages',
			'premium_blog_prev_text',
			'premium_blog_next_text',
			'premium_blog_pagination_align',
			'categories_repeater',
			'__globals__',
		] );

		return [
			'id'       => $id,
			'name'     => 'divi/blog',
			'settings' => $block_settings,
			'elements' => [],
		];
	}
}
