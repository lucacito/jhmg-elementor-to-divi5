<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--blog--post--comment" widget (Animation Addons for
 * Elementor, post-comment.php) to divi/comments.
 *
 * Its only content control is 'theme_comment_style' (a cosmetic switcher);
 * render() always prints the current post's comment list/form via WordPress's
 * own comment template. divi/comments (CommentsModule.php,
 * fixtures/divi-schema/modules.json) does the same dynamically, so no
 * settings need to be carried over.
 */
class WcfBlogPostCommentConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_comments_' );
        $settings = $element['settings'] ?? [];

        $this->engine->logConverted( 'comments' );
        $this->logUnmappedSettings( $id, $settings, [
            'theme_comment_style', 'comment__padding', 'comment_margin', 'comment_ct_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/comments',
            'settings' => [],
            'elements' => [],
        ];
    }
}
