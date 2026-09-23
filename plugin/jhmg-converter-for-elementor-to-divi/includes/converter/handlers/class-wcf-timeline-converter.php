<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts the "wcf--timeline" widget (Animation Addons for Elementor,
 * timeline.php — not related to wcf--posts-timeline, which reads live posts)
 * to a divi/group of divi/group step children.
 *
 * Fully static: each 'timelines' repeater item (image/date/title/subtitle/
 * description) is real content, not live data, so it converts the same
 * free-form-container way WcfImageAccordionConverter does — one divi/group
 * per step, image + heading + text as ordinary child blocks. 'step_icon'
 * (an icon marker on the timeline rail itself, not a content field) has no
 * per-step child slot to carry over and is left unmapped, matching how
 * WcfImageBoxSliderConverter treats other rail/marker-only controls.
 */
class WcfTimelineConverter extends BaseElementorConverter {
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_timeline_' );
        $settings = $element['settings'] ?? [];

        $items    = is_array( $settings['timelines'] ?? null ) ? $settings['timelines'] : [];
        $children = [];

        foreach ( $items as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $date    = is_string( $item['timeline_date'] ?? null ) ? ( $item['timeline_date'] ?? '' ) : '';
            $title   = is_string( $item['timeline_title'] ?? null ) ? ( $item['timeline_title'] ?? '' ) : '';
            $sub     = is_string( $item['timeline_sub'] ?? null ) ? ( $item['timeline_sub'] ?? '' ) : '';
            $desc    = is_string( $item['timeline_desc'] ?? null ) ? ( $item['timeline_desc'] ?? '' ) : '';

            $image_raw = is_array( $item['timeline_image'] ?? null ) ? $item['timeline_image'] : [];
            $img_url   = is_string( $image_raw['url'] ?? '' ) ? ( $image_raw['url'] ?? '' ) : '';
            $img_alt   = is_string( $image_raw['alt'] ?? '' ) ? ( $image_raw['alt'] ?? '' ) : '';

            $step_children = [];
            $step_id       = $id . '-step-' . ( $idx + 1 );

            if ( $img_url !== '' ) {
                $image_value = [ 'src' => $img_url ];
                if ( $img_alt !== '' ) {
                    $image_value['alt'] = $img_alt;
                } else {
                    $this->engine->logWarning( "Image missing alt text: {$step_id}-image" );
                }
                $step_children[] = [
                    'id'       => $step_id . '-image',
                    'name'     => 'divi/image',
                    'settings' => [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image_value ] ] ] ],
                    'elements' => [],
                ];
            }

            if ( $title !== '' ) {
                $step_children[] = [
                    'id'       => $step_id . '-title',
                    'name'     => 'divi/heading',
                    'settings' => [
                        'title' => [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ],
                    ],
                    'elements' => [],
                ];
            }

            $content_parts = array_filter( [ $date, $sub, $desc ] );
            if ( $content_parts !== [] ) {
                $step_children[] = [
                    'id'       => $step_id . '-text',
                    'name'     => 'divi/text',
                    'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => implode( "\n", array_map( static fn( $p ) => "<p>{$p}</p>", $content_parts ) ) ] ] ] ],
                    'elements' => [],
                ];
            }

            $children[] = [
                'id'       => $step_id,
                'name'     => 'divi/group',
                'settings' => [],
                'elements' => $step_children,
            ];
        }

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, [
            'timelines', 'step_type', 'step_icon', 'timeline_image', 'timeline_date',
            'timeline_title', 'timeline_sub', 'timeline_desc', 'link',
            // wcf--timeline's own layout/rail/icon style controls (timeline.php).
            'element_list', 'timeline_direction', 'step_icon_size', 'step_bg_color',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/group',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
