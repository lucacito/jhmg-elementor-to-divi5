<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EaelFeatureListConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_icon_list_' );
        $settings = $element['settings'] ?? [];

        $items    = $settings['eael_feature_list'] ?? [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            // EAEL 6.6.7 Feature_List.php: eael_feature_list_title, _content, _icon_new, _link.
            $title   = is_string( $item['eael_feature_list_title'] ?? '' ) ? ( $item['eael_feature_list_title'] ?? '' ) : '';
            $content = is_string( $item['eael_feature_list_content'] ?? '' ) ? trim( $item['eael_feature_list_content'] ?? '' ) : '';
            $link    = is_array( $item['eael_feature_list_link'] ?? null ) && is_string( $item['eael_feature_list_link']['url'] ?? null ) ? $item['eael_feature_list_link']['url'] : '';

            // divi/icon-list-item (IconListItemModule.php): the text is content.innerContent
            // (line 310), the icon an object in icon.innerContent (line 77), the link
            // module.advanced.link (line 178). module.advanced.text and a `link`
            // attribute were never read.
            $text = $content !== '' && $title !== '' ? '<strong>' . $title . '</strong> ' . $content : ( $title !== '' ? $title : $content );

            $child_attrs = [];
            if ( $text !== '' ) {
                $child_attrs['content']['innerContent']['desktop']['value'] = $text;
            }
            $icon_raw = $item['eael_feature_list_icon_new'] ?? null;
            if ( is_array( $icon_raw ) && ( $icon_raw['value'] ?? '' ) !== '' ) {
                $divi_icon = FontAwesomeIcons::fromControl( $icon_raw );
                if ( $divi_icon === null ) {
                    $label = is_string( $icon_raw['value'] ) ? $icon_raw['value'] : 'svg';
                    $this->engine->logWarning( "Feature list {$id}: icon '{$label}' has no FontAwesome equivalent in Divi; a star was used." );
                    $divi_icon = FontAwesomeIcons::STAR;
                }
                $child_attrs['icon']['innerContent']['desktop']['value'] = $divi_icon;
            }
            if ( $link !== '' ) {
                $child_attrs['module']['advanced']['link']['desktop']['value'] = [ 'url' => $link ];
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/icon-list-item',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'icon-list' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_feature_list',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/icon-list',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
