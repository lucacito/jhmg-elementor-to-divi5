<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runs the converter + exporter for a list of import items and returns results.
 *
 * Each input item must have shape:
 *   ['title' => string, 'post_type' => string, 'post_name' => string, 'elements' => array]
 *
 * Each returned result has shape:
 *   ['title' => string, 'post_id' => int, 'success' => bool, 'error' => string, 'report' => array]
 */
class BatchImporter {
    private ConverterEngine $engine;
    private DiviExporter $exporter;

    public function __construct( ?ConverterEngine $engine = null, ?DiviExporter $exporter = null ) {
        $this->engine   = $engine   ?? new ConverterEngine();
        $this->exporter = $exporter ?? new DiviExporter();

        $global_colors = $this->extractElementorGlobalColors();
        if ( ! empty( $global_colors ) ) {
            $this->engine->setGlobalColors( $global_colors );
        }
    }

    /**
     * Reads Elementor global colors from the active Kit post so they can be
     * resolved during conversion.
     *
     * Elementor stores the active Kit post ID in the `elementor_active_kit`
     * option. The Kit's `_elementor_page_settings` meta holds `system_colors`
     * and `custom_colors` arrays, each containing `{_id, color}` objects.
     *
     * @return array<string,string> Map of color id → hex value.
     */
    private function extractElementorGlobalColors(): array {
        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }

        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        if ( $kit_id <= 0 ) {
            return [];
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            return [];
        }

        $colors = [];
        foreach ( [ 'system_colors', 'custom_colors' ] as $group_key ) {
            $group = $kit_settings[ $group_key ] ?? [];
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $color_item ) {
                $id  = $color_item['_id']   ?? '';
                $hex = $color_item['color'] ?? '';
                if ( $id !== '' && $hex !== '' ) {
                    $colors[ $id ] = $hex;
                }
            }
        }

        return $colors;
    }

    /**
     * @param  array[] $items   Import items from ElementorImportParser::parse().
     * @param  array   $options Accepts: post_status ('draft'|'publish'), post_type override.
     * @return array[] Per-item results.
     */
    public function import( array $items, array $options = [] ): array {
        $default_post_type   = $options['post_type']   ?? 'page';
        $default_post_status = $options['post_status'] ?? 'draft';

        $results = [];

        foreach ( $items as $item ) {
            $title     = (string) ( $item['title']     ?? 'Imported Page' );
            $post_type = $default_post_type;
            $post_name = (string) ( $item['post_name'] ?? '' );
            $elements  = $item['elements'] ?? [];

            try {
                $post_args = [
                    'post_type'    => $post_type,
                    'post_title'   => $title ?: 'Imported Page',
                    'post_status'  => $default_post_status,
                    'post_content' => '',
                ];

                if ( $post_name !== '' ) {
                    $post_args['post_name'] = $post_name;
                }

                $post_id = wp_insert_post( $post_args );

                if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
                    $error = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
                    $results[] = $this->failResult( $title, $error );
                    continue;
                }

                $post_id  = (int) $post_id;
                $converted = $this->engine->convert( $elements );
                $this->exporter->save( $post_id, $converted );

                update_post_meta( $post_id, '_edc_import_source', 'file_upload' );

                $results[] = [
                    'title'   => $title,
                    'post_id' => $post_id,
                    'success' => true,
                    'error'   => '',
                    'report'  => $converted['report']  ?? [],
                    'unsupported' => $converted['unsupported'] ?? [],
                ];
            } catch ( \Throwable $e ) {
                $results[] = $this->failResult( $title, $e->getMessage() );
            }
        }

        return $results;
    }

    private function failResult( string $title, string $error ): array {
        return [
            'title'      => $title,
            'post_id'    => 0,
            'success'    => false,
            'error'      => $error,
            'report'     => [],
            'unsupported' => [],
        ];
    }
}
