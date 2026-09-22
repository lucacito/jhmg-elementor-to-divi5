<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--author-box" widget (Animation Addons for Elementor,
 * author-box.php) to a divi/group with image/heading/text/button children —
 * but only for 'source' => 'custom'.
 *
 * The widget's default and other mode, 'source' => 'current', pulls the
 * *current post's author* at render time (get_the_author_meta(),
 * get_author_posts_url(), count_user_posts(), a live WP_Comment_Query — see
 * author-box.php's render()). None of that is present in the Elementor
 * settings JSON, and ConverterEngine gives handlers no post context to look
 * it up, so 'current' source has no static equivalent and is deliberately
 * left to GenericFallbackConverter's placeholder rather than fabricating an
 * author identity.
 */
class WcfAuthorBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_author_box_' );
        $settings = $element['settings'] ?? [];

        $source = is_string( $settings['source'] ?? null ) ? ( $settings['source'] ?? 'current' ) : 'current';

        if ( $source !== 'custom' ) {
            return ( new GenericFallbackConverter( $this->engine, 'wcf--author-box' ) )->convert( $element );
        }

        $name_tag = is_string( $settings['author_name_tag'] ?? null ) ? ( $settings['author_name_tag'] ?? 'h4' ) : 'h4';
        $name     = is_string( $settings['author_name'] ?? null ) ? ( $settings['author_name'] ?? '' ) : '';
        $bio      = is_string( $settings['author_bio'] ?? null ) ? ( $settings['author_bio'] ?? '' ) : '';
        $link_text = is_string( $settings['link_text'] ?? null ) ? ( $settings['link_text'] ?? '' ) : '';

        $avatar    = is_array( $settings['author_avatar'] ?? null ) ? $settings['author_avatar'] : [];
        $avatar_url = is_string( $avatar['url'] ?? '' ) ? ( $avatar['url'] ?? '' ) : '';

        $posts_url_setting = is_array( $settings['posts_url'] ?? null ) ? $settings['posts_url'] : [];
        $posts_url         = is_string( $posts_url_setting['url'] ?? '' ) ? ( $posts_url_setting['url'] ?? '' ) : '';

        $children = [];

        if ( $avatar_url !== '' ) {
            $children[] = [
                'id'       => $id . '-image',
                'name'     => 'divi/image',
                'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => $avatar_url ] ] ] ] ],
                'elements' => [],
            ];
            $this->engine->logWarning( "Image missing alt text: {$id}-image" );
        }

        if ( $name !== '' ) {
            $children[] = [
                'id'       => $id . '-name',
                'name'     => 'divi/heading',
                'settings' => [
                    'title' => [
                        'innerContent' => [ 'desktop' => [ 'value' => $name ] ],
                        'decoration'   => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => $name_tag ] ] ] ] ],
                    ],
                ],
                'elements' => [],
            ];
        }

        if ( $bio !== '' ) {
            $children[] = [
                'id'       => $id . '-bio',
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $bio ] ] ] ],
                'elements' => [],
            ];
        }

        if ( $posts_url !== '' && $link_text !== '' ) {
            $children[] = [
                'id'       => $id . '-button',
                'name'     => 'divi/button',
                'settings' => [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'text' => $link_text, 'linkUrl' => $posts_url ] ] ] ] ],
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'source', 'author_avatar', 'author_name', 'author_name_tag', 'author_website',
            'author_bio', 'posts_url', 'link_text',
            // 'current'-only controls, irrelevant once 'source' is 'custom' (author-box.php).
            'avatar_size', 'show_avatar', 'show_name', 'show_meta', 'show_biography', 'show_link',
            'link_to', 'show_contact', 'contact_title', 'email_label', 'phone_label',
            'show_social_media', 'social_title',
            // Layout and style controls (author-box.php).
            'layout', 'alignment', 'image_vertical_align', 'button_hover_animation',
            'name_typography_typography', 'bio_typography_typography', 'button_typography_typography',
            'meta_typo_typography', 'con_info_typo_typography', 'con_label_typo_typography',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
