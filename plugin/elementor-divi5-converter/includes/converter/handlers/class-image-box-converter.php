<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageBoxConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_blurb_' );
        $settings = $element['settings'] ?? [];

        $title       = is_string( $settings['title_text'] ?? '' ) ? ( $settings['title_text'] ?? '' ) : '';
        $description = is_string( $settings['description_text'] ?? '' ) ? ( $settings['description_text'] ?? '' ) : '';

        $image = $settings['image'] ?? [];
        $src   = is_array( $image ) ? ( is_string( $image['url'] ?? '' ) ? ( $image['url'] ?? '' ) : '' ) : ( is_string( $image ) ? $image : '' );
        $alt   = is_array( $image ) ? ( is_string( $image['alt'] ?? '' ) ? ( $image['alt'] ?? '' ) : '' ) : '';

        $style_result = ( new StyleMapper() )->map( 'blurb', $settings );
        $attrs        = $style_result['divi_attrs'];

        if ( $src !== '' ) {
            $image_value = [ 'src' => $src ];
            if ( $alt !== '' ) {
                $image_value['alt'] = $alt;
            }
            $attrs['imageIcon'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ];
        }

        if ( $title !== '' ) {
            // Divi blurb has_header_text resolver checks $value['text'], not a plain string.
            $attrs['title']['innerContent']['desktop']['value'] = [ 'text' => $title ];
        }

        if ( $description !== '' ) {
            $attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $description ] ] ];
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'title_text', 'description_text', 'image', 'image_size',
                'title_size', 'link_to', 'link', 'selected_icon', 'icon',
                'icon_color', 'position', 'image_border_radius',
            ],
            $style_result['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/blurb',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
