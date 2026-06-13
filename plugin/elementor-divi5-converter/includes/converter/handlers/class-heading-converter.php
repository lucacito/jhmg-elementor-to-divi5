<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeadingConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_text_' );
        $settings = $element['settings'] ?? [];
        $title    = $this->getSettingValue( $settings, 'title' );
        // Real Elementor exports use 'header_size'; our fixtures use 'tag'.
        $tag_raw  = $this->getSettingValue( $settings, 'tag', '' );
        $tag      = $tag_raw !== '' ? $tag_raw : $this->getSettingValue( $settings, 'header_size', 'h2' );

        $html = ( $tag !== '' ) ? "<{$tag}>{$title}</{$tag}>" : (string) $title;

        $style  = ( new StyleMapper() )->map( 'heading', $settings );
        $attrs  = array_merge(
            [
                'content' => [
                    'innerContent' => [
                        'desktop' => [ 'value' => $html ],
                    ],
                ],
            ],
            $style['divi_attrs']
        );

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'title', 'tag', 'header_size' ],
            $style['handled_keys']
        ) );

        return [
            'id'       => $id,
            'name'     => 'divi/text',
            'settings' => $attrs,
            'elements' => [],
        ];
    }
}
