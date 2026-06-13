<?php

namespace ElementorDivi5Converter\Exporters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DiviExporter {
    private DiviBlockSerializer $serializer;

    public function __construct( ?DiviBlockSerializer $serializer = null ) {
        $this->serializer = $serializer ?? new DiviBlockSerializer();
    }

    /**
     * Transform converted Divi structure into Divi 5 post meta shape.
     *
     * @param array $divi_data Converted Divi structure from ConverterEngine.
     * @return array Associative array of post meta keys => values to save.
     */
    public function export( array $divi_data ): array {
        $meta = [];

        // Mark the post as using Divi builder.
        $meta['_et_pb_use_builder'] = 'on';

        // Record builder version to indicate Divi 5 usage.
        $meta['_et_builder_version'] = defined( 'ET_BUILDER_VERSION' ) ? sprintf( 'VB|Divi|%s', ET_BUILDER_VERSION ) : 'VB|Divi|5.0.0';

        // Preserve the converted structure for debugging and later export.
        $meta['_edc_divi_data'] = json_encode( $divi_data );

        // Store the conversion report (counts, warnings, skipped settings, unsupported).
        if ( isset( $divi_data['report'] ) ) {
            $report = array_merge( $divi_data['report'], [
                'unsupported' => $divi_data['unsupported'] ?? [],
            ] );
            $meta['_edc_conversion_report'] = json_encode( $report );
        }

        // Must be 'on' (string) — Divi checks === 'on' to recognize a D5 post.
        $meta['_et_pb_use_divi_5'] = 'on';

        return $meta;
    }

    /**
     * Save the exported Divi meta to a post.
     *
     * @param int $post_id
     * @param array $divi_data
     * @param bool $dry_run If true, do not persist, just return meta.
     * @return array|bool If $dry_run returns meta array, otherwise boolean success.
     */
    public function save( int $post_id, array $divi_data, bool $dry_run = false ) {
        $meta         = $this->export( $divi_data );
        $post_content = $this->serializer->serialize( $divi_data );

        if ( $dry_run ) {
            return $meta;
        }

        // wp_update_post expects slashed data; wp_slash prevents double-unslash.
        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_slash( $post_content ),
        ] );

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // Clear stale Divi CSS cache so the next page load regenerates assets.
        if ( class_exists( 'ET_Core_PageResource' ) ) {
            ET_Core_PageResource::remove_static_resources( $post_id, 'all' );
        }
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_modules' );
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_feature_used' );

        return true;
    }
}
