<?php

namespace ElementorDivi5Converter\Exporters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles saving a converted Divi header into the Divi Theme Builder structure.
 *
 * Divi Theme Builder stores headers across three post types:
 *
 *   et_theme_builder  — Container post that lists template IDs via _et_template meta.
 *   et_template       — One entry per "rule set"; links header/body/footer layout IDs
 *                       to display conditions (use_on / exclude_from).
 *   et_header_layout  — The actual header content post (Divi 5 block content).
 *
 * To install a global header:
 *   1. Create (or update) an et_header_layout post with the converted content.
 *   2. Create an et_template post marked as default (no condition needed).
 *   3. Get or create the live et_theme_builder post and append the template ID.
 */
class DiviThemeBuilderExporter {
    private DiviExporter $exporter;

    public function __construct( ?DiviExporter $exporter = null ) {
        $this->exporter = $exporter ?? new DiviExporter();
    }

    /**
     * Create an et_header_layout post, save the converted Divi content to it,
     * then wire it up as the global site header in the Divi Theme Builder.
     *
     * Both the layout post and the template post are always created as 'publish'.
     * Divi's own et_theme_builder_insert_layout() hardcodes this — several internal
     * checks gate on publish status, so a draft layout post will not appear.
     *
     * @param  string $title      Header title shown in Theme Builder admin.
     * @param  array  $divi_data  Result from ConverterEngine::convert().
     * @return array{post_id: int, template_id: int, theme_builder_id: int, success: bool, error: string}
     */
    public function saveHeader( string $title, array $divi_data ): array {
        try {
            $header_post_id = $this->createHeaderLayoutPost( $title );

            if ( $header_post_id === 0 ) {
                return $this->errorResult( 'Could not create et_header_layout post.' );
            }

            $this->exporter->save( $header_post_id, $divi_data );

            [ $template_id, $theme_builder_id ] = $this->wireGlobalHeader( $title, $header_post_id );

            return [
                'post_id'          => $header_post_id,
                'template_id'      => $template_id,
                'theme_builder_id' => $theme_builder_id,
                'success'          => true,
                'error'            => '',
            ];
        } catch ( \Throwable $e ) {
            return $this->errorResult( $e->getMessage() );
        }
    }

    /**
     * Create an et_footer_layout post, save the converted Divi content to it,
     * then wire it up as the global site footer in the Divi Theme Builder.
     *
     * @param  string $title      Footer title shown in Theme Builder admin.
     * @param  array  $divi_data  Result from ConverterEngine::convert().
     * @return array{post_id: int, template_id: int, theme_builder_id: int, success: bool, error: string}
     */
    public function saveFooter( string $title, array $divi_data ): array {
        try {
            $footer_post_id = $this->createFooterLayoutPost( $title );

            if ( $footer_post_id === 0 ) {
                return $this->errorResult( 'Could not create et_footer_layout post.' );
            }

            $this->exporter->save( $footer_post_id, $divi_data );

            [ $template_id, $theme_builder_id ] = $this->wireGlobalFooter( $title, $footer_post_id );

            return [
                'post_id'          => $footer_post_id,
                'template_id'      => $template_id,
                'theme_builder_id' => $theme_builder_id,
                'success'          => true,
                'error'            => '',
            ];
        } catch ( \Throwable $e ) {
            return $this->errorResult( $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function createHeaderLayoutPost( string $title ): int {
        $post_id = wp_insert_post( [
            'post_type'    => 'et_header_layout',
            'post_title'   => $title ?: 'Imported Header',
            'post_status'  => 'publish',
            'post_content' => '',
        ] );

        if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
            return 0;
        }

        $post_id = (int) $post_id;

        // Divi's et_theme_builder_insert_layout() does both of these after wp_insert_post:
        // - wp_set_object_terms assigns the 'layout' term on the layout_type taxonomy so
        //   the Theme Builder UI recognises this post as a proper layout entry.
        // - _et_pb_show_page_creation mirrors what et_builder_enable_for_post() sets.
        if ( function_exists( 'wp_set_object_terms' ) ) {
            wp_set_object_terms( $post_id, 'layout', 'layout_type', true );
        }
        update_post_meta( $post_id, '_et_pb_show_page_creation', 'on' );

        return $post_id;
    }

    /**
     * Create an et_template post that marks this header as the global default,
     * then attach it to the live et_theme_builder container post.
     *
     * @return int[] [ $template_id, $theme_builder_id ]
     */
    private function wireGlobalHeader( string $title, int $header_layout_id ): array {
        $template_id = $this->createTemplatePost( $title, $header_layout_id );

        $theme_builder_id = $this->getOrCreateThemeBuilderPost();

        if ( $theme_builder_id > 0 && $template_id > 0 ) {
            add_post_meta( $theme_builder_id, '_et_template', $template_id );
        }

        return [ $template_id, $theme_builder_id ];
    }

    private function createTemplatePost( string $title, int $header_layout_id ): int {
        $template_id = wp_insert_post( [
            'post_type'   => 'et_template',
            'post_title'  => $title ?: 'Global Header',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $template_id ) || (int) $template_id === 0 ) {
            return 0;
        }

        $template_id = (int) $template_id;

        // Mark as the default (global) template — no use_on condition required.
        update_post_meta( $template_id, '_et_default',               '1' );
        update_post_meta( $template_id, '_et_enabled',               '1' );

        // Link the header layout.
        update_post_meta( $template_id, '_et_header_layout_id',      $header_layout_id );
        update_post_meta( $template_id, '_et_header_layout_enabled', '1' );

        return $template_id;
    }

    /**
     * Returns the ID of the live (published) et_theme_builder post.
     * Creates one if none exists yet.
     */
    private function getOrCreateThemeBuilderPost(): int {
        // Use Divi's own function when available to avoid conflicts with its internal state.
        if ( function_exists( 'et_theme_builder_get_theme_builder_post_id' ) ) {
            $id = (int) \et_theme_builder_get_theme_builder_post_id( true, true );
            if ( $id > 0 ) {
                return $id;
            }
        }

        // Manual fallback: query for an existing published et_theme_builder post.
        $query = new \WP_Query( [
            'post_type'              => 'et_theme_builder',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'key'     => '_et_library_theme_builder',
                'compare' => 'NOT EXISTS',
            ] ],
        ] );

        if ( ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        // Create a fresh one.
        $new_id = wp_insert_post( [
            'post_type'   => 'et_theme_builder',
            'post_title'  => 'Theme Builder',
            'post_status' => 'publish',
        ] );

        return ( is_wp_error( $new_id ) || (int) $new_id === 0 ) ? 0 : (int) $new_id;
    }

    private function createFooterLayoutPost( string $title ): int {
        $post_id = wp_insert_post( [
            'post_type'    => 'et_footer_layout',
            'post_title'   => $title ?: 'Imported Footer',
            'post_status'  => 'publish',
            'post_content' => '',
        ] );

        if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
            return 0;
        }

        $post_id = (int) $post_id;

        if ( function_exists( 'wp_set_object_terms' ) ) {
            wp_set_object_terms( $post_id, 'layout', 'layout_type', true );
        }
        update_post_meta( $post_id, '_et_pb_show_page_creation', 'on' );

        return $post_id;
    }

    /**
     * @return int[] [ $template_id, $theme_builder_id ]
     */
    private function wireGlobalFooter( string $title, int $footer_layout_id ): array {
        $template_id = $this->createFooterTemplatePost( $title, $footer_layout_id );

        $theme_builder_id = $this->getOrCreateThemeBuilderPost();

        if ( $theme_builder_id > 0 && $template_id > 0 ) {
            add_post_meta( $theme_builder_id, '_et_template', $template_id );
        }

        return [ $template_id, $theme_builder_id ];
    }

    private function createFooterTemplatePost( string $title, int $footer_layout_id ): int {
        $template_id = wp_insert_post( [
            'post_type'   => 'et_template',
            'post_title'  => $title ?: 'Global Footer',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $template_id ) || (int) $template_id === 0 ) {
            return 0;
        }

        $template_id = (int) $template_id;

        update_post_meta( $template_id, '_et_default',               '1' );
        update_post_meta( $template_id, '_et_enabled',               '1' );

        update_post_meta( $template_id, '_et_footer_layout_id',      $footer_layout_id );
        update_post_meta( $template_id, '_et_footer_layout_enabled', '1' );

        return $template_id;
    }

    private function errorResult( string $error ): array {
        return [
            'post_id'          => 0,
            'template_id'      => 0,
            'theme_builder_id' => 0,
            'success'          => false,
            'error'            => $error,
        ];
    }
}
