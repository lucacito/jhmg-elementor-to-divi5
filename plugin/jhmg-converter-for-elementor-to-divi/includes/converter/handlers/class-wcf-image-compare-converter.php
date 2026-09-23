<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--image-compare" widget (Animation Addons for Elementor,
 * image-compare.php) — a JS drag-to-reveal before/after image slider — to a
 * divi/group of the two images (and their captions, if shown).
 *
 * Divi has no interactive compare-slider module, so the drag behaviour is
 * logged as not carried over rather than approximated; the two images
 * themselves still carry over as ordinary divi/image children (with a
 * divi/text caption each, if 'show_caption' is on) instead of dropping both
 * images from the page via an empty placeholder.
 */
class WcfImageCompareConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_image_compare_' );
        $settings = $element['settings'] ?? [];

        $show_caption = ( $settings['show_caption'] ?? '' ) === 'yes';

        $children = [];
        foreach ( [
            'before_image' => 'before_caption',
            'after_image'  => 'after_caption',
        ] as $image_key => $caption_key ) {
            $image_raw = is_array( $settings[ $image_key ] ?? null ) ? $settings[ $image_key ] : [];
            $img_url   = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
            if ( $img_url === '' ) {
                continue;
            }

            $image_value = [ 'src' => $img_url ];
            $img_alt     = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';
            if ( $img_alt !== '' ) {
                $image_value['alt'] = $img_alt;
            } else {
                $this->engine->logWarning( "Image missing alt text: {$id}-{$image_key}" );
            }

            $children[] = [
                'id'       => $id . '-' . $image_key,
                'name'     => 'divi/image',
                'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                'elements' => [],
            ];

            if ( $show_caption ) {
                $caption = is_string( $settings[ $caption_key ] ?? null ) ? ( $settings[ $caption_key ] ?? '' ) : '';
                if ( $caption !== '' ) {
                    $children[] = [
                        'id'       => $id . '-' . $caption_key,
                        'name'     => 'divi/text',
                        'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $caption ] ] ] ],
                        'elements' => [],
                    ];
                }
            }
        }

        $this->engine->logNotCarriedOver( 'compare_slider', (string) $id, "the drag-to-reveal before/after comparison has no Divi equivalent; both images were converted side by side instead" );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'before_image', 'after_image', 'show_caption', 'before_caption', 'after_caption',
            // wcf--image-compare's own drag-handle/orientation/button controls (image-compare.php).
            'show_btn', 'compare_orientation', 'handle_type', 'handle_color', 'divider_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
