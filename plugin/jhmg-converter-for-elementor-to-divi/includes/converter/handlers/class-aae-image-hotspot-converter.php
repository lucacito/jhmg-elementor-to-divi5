<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "aae--image-hotspot" widget (Animation Addons for Elementor,
 * image-hotspot.php — get_name() returns 'aae--image-hotspot', not
 * 'wcf--image-hotspot') to a divi/group of the base image plus one divi/text
 * per hotspot with something to show.
 *
 * The widget places absolutely-positioned hover-tooltip pins ('hsp_list')
 * over the base image; Divi has no absolute-hotspot-with-tooltip module, so
 * the pin positions and hover interactivity are logged as not carried over.
 * Each hotspot's own visible text ('hsp_text', shown on the pin itself for
 * 'text'/'icon-text' layouts) and its tooltip content ('tlp_content', for
 * 'tooltip_type' => 'tooltip' hotspots) still carry over as plain text,
 * rather than vanishing along with the positioning.
 */
class AaeImageHotspotConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_hotspot_' );
        $settings = $element['settings'] ?? [];

        $children = [];

        $image_raw = is_array( $settings['hsp_image'] ?? null ) ? $settings['hsp_image'] : [];
        $img_url   = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
        if ( $img_url !== '' ) {
            $image_value = [ 'src' => $img_url ];
            $img_alt     = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';
            if ( $img_alt !== '' ) {
                $image_value['alt'] = $img_alt;
            } else {
                $this->engine->logWarning( "Image missing alt text: {$id}-image" );
            }
            $children[] = [
                'id'       => $id . '-image',
                'name'     => 'divi/image',
                'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                'elements' => [],
            ];
        }

        $hotspots = is_array( $settings['hsp_list'] ?? null ) ? $settings['hsp_list'] : [];
        $texts    = [];
        foreach ( $hotspots as $hotspot ) {
            if ( ! is_array( $hotspot ) ) {
                continue;
            }
            $label = is_string( $hotspot['hsp_text'] ?? null ) ? trim( $hotspot['hsp_text'] ?? '' ) : '';
            $tooltip = ( $hotspot['tooltip_type'] ?? 'tooltip' ) === 'tooltip'
                && is_string( $hotspot['tlp_content'] ?? null )
                ? trim( $hotspot['tlp_content'] ?? '' )
                : '';

            if ( $label !== '' && $tooltip !== '' ) {
                $texts[] = "<p><strong>{$label}</strong>: {$tooltip}</p>";
            } elseif ( $label !== '' ) {
                $texts[] = "<p>{$label}</p>";
            } elseif ( $tooltip !== '' ) {
                $texts[] = "<p>{$tooltip}</p>";
            }
        }

        if ( $texts !== [] ) {
            $children[] = [
                'id'       => $id . '-hotspots',
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", $texts ) ] ] ] ],
                'elements' => [],
            ];
        }

        $this->engine->logNotCarriedOver( 'image_hotspots', (string) $id, "the hotspot pin positions and hover tooltips have no Divi equivalent; each hotspot's own text was converted as plain content instead" );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'hsp_image', 'hsp_img_size', 'hsp_img_size_size', 'hsp_list',
            'hsp_layout', 'hsp_icon', 'hsp_text', 'tooltip_type', 'tlp_content', 'tlp_link',
            // wcf--image-hotspot's own pin position/style controls (image-hotspot.php).
            'pin_color', 'pin_size', 'tooltip_bg_color', 'tooltip_text_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
