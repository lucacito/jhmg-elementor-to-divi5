<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--floating-elements" widget (Animation Addons for
 * Elementor, floating-elements.php) to a divi/group of divi/image children.
 *
 * The widget's 'wcf_floating_elements' repeater places each image at an
 * absolute offset with a parallax/float animation. Divi has no free-floating
 * absolute-position image module, so the positioning and animation are
 * logged as not carried over; each image itself still converts as an
 * ordinary divi/image, rather than vanishing along with the positioning.
 */
class WcfFloatingElementsConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_floating_' );
        $settings = $element['settings'] ?? [];

        $items    = is_array( $settings['wcf_floating_elements'] ?? null ) ? $settings['wcf_floating_elements'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $image_raw = is_array( $item['floating_image'] ?? null ) ? $item['floating_image'] : [];
            $img_url   = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
            if ( $img_url === '' ) {
                continue;
            }

            $image_value = [ 'src' => $img_url ];
            $img_alt     = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';
            if ( $img_alt !== '' ) {
                $image_value['alt'] = $img_alt;
            }

            $children[] = [
                'id'       => $id . '-item-' . ( $idx + 1 ),
                'name'     => 'divi/image',
                'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                'elements' => [],
            ];
        }

        if ( $children !== [] ) {
            $this->engine->logNotCarriedOver( 'floating_position', (string) $id, "each element's absolute float position and parallax animation have no Divi equivalent; images were converted in normal document flow instead" );
        }

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'wcf_floating_elements', 'floating_image', 'floating_el_size',
            // wcf--floating-elements' own offset/animation controls (floating-elements.php).
            'floating_el-translate_popover_toggle', 'floating_el_offset_o_h', 'floating_el_offset_o_v',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
